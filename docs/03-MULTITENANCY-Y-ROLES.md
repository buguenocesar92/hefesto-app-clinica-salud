# 03 — Multi-tenancy y roles

Este es el documento que explica el resto. Si algo no se entiende, hay que
volver aca.

---

# Parte 1: Aislamiento entre clinicas

## El patron: columna `tenant_id` mas Row Level Security

**Mismo patron y misma justificacion que en el Portal de Autoservicio de
Hefesto** (`apps/portal-autoservicio/docs/03-MULTITENANCY.md`). No se
redecide aca: se reutiliza. La única diferencia de nombre es que aquí el tenant
es una clinica y la variable de sesión es `app.clinica_id`.

## Por que NO un esquema por clinica

La alternativa (un esquema de base por clinica) se descarto en el portal, y la
razon sigue valiendo:

| | Columna + RLS | Esquema por clinica |
|---|---|---|
| Consulta que olvida el filtro | PostgreSQL devuelve cero filas ajenas | Se filtran datos de otra clinica |
| Indices y planes | Se comparten | Se duplican por clinica; el planificador se sobredimensiona |
| Respaldos | Uno | Uno por clinica |
| Alta de una clinica | Una fila | Crear esquema, Dueños, permisos, extensiones |
| Conexiones | Una | Una por clinica |

El argumento que decide: **con RLS, un error de la aplicación no es una
filtracion de datos de salud; es un error.** El fallo deja de existir en la
capa donde puede corregirse.

## Como esta implementado

### 1. Una función que falla cerrado

```sql
CREATE OR REPLACE FUNCTION public.app_clinica_id() RETURNS uuid
LANGUAGE plpgsql STABLE AS $$
DECLARE valor text;
BEGIN
    valor := current_setting('app.clinica_id', true);

    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION 'Falta el contexto de clinica (app.clinica_id).';
    END IF;

    RETURN valor::uuid;
END;
$$;
```

Que lance excepcion es lo importante. Si devolviera `NULL`, una consulta sin
contexto devolveria "cero filas y ya", que parece un resultado valido. Con la
excepcion, **la ausencia de contexto es un error**, no una condicion.

### 2. Una política por tabla

```sql
CREATE POLICY aislamiento_tenant ON pacientes
    USING (tenant_id = public.app_clinica_id())
    WITH CHECK (tenant_id = public.app_clinica_id());
```

`USING` filtra la lectura, la modificacion y el borrado. `WITH CHECK` impide
**escribir** una fila declarando una clinica que no es la del contexto. Sin
`WITH CHECK`, se podrian crear filas ajenas.

La política es por `tenant_id`, no por id de paciente: la columna es comun a
todas las tablas y es la que existe en cada una.

### 3. RLS FORZADO en cada tabla

```sql
ALTER TABLE pacientes ENABLE ROW LEVEL SECURITY;
ALTER TABLE pacientes FORCE ROW LEVEL SECURITY;
```

El `FORCE` no es un detalle menor. **Sin el, las políticas no le aplican al
dueno de la tabla**, y en esta instalación el dueno es el mismo usuario con el
que se conecta Laravel (el que migra). El RLS de adorno no protege nada.

### 4. El contexto viaja con alcance de transacción

```php
DB::select('SELECT set_config(?, ?, true)', ['app.clinica_id', $clinicaId]);
```

El `true` final es lo critico: el valor desaparece al confirmar o revertir la
transacción. Con un `SET` normal (ambito de sesión) sobreviviria, y en el worker
de colas, que reutiliza la misma conexión para trabajos de clinicas distintas,
una tarea leeria datos de otra clinica. Ese es exactamente el fallo que este
diseno evita.

`ContextoTenant::establecer()` **exige** estar dentro de una transacción y
lanza excepcion si no lo esta: es la única via para fijar el contexto.

### 5. Un único punto de entrada

`App\Http\Middleware\ResolverTenant` envuelve toda peticion autenticada:

```
peticion
  -> ResolverTenant  (abre transaccion, fija clinica)
     -> auth         (confirma la sesion)
        -> ruta      (controlador -> Policy -> datos)
```

Las peticiones sin sesión (ingreso, callback, salud) pasan sin transacción. Si
esas rutas tocaran una tabla protegida, `app_clinica_id()` lanzaria excepcion
y la peticion fallaria. El aislamiento falla cerrado, nunca abierto.

### 6. Las tablas exentas

| Tabla | Por que no tiene RLS |
|---|---|
| `clinicas` | hay que leerla para saber cual es el tenant |
| `sessions` | se consulta antes de saber el tenant; con RLS, nadie podria iniciar sesión |
| `cache`, `cache_locks` | no son datos de la clinica |

## Verificación

```bash
php artisan clinica:verificar-aislamiento
```

Comprueba, sin pasar por la aplicación, que el rol conectado no sea
superusuario ni tenga `BYPASSRLS` (primero, antes de escribir nada), que las 13
tablas tienen RLS forzado, que la política existe, que una clínica no ve filas de
otra y que una consulta sin contexto falla. Escribe filas temporales y las borra
al final.

En las pruebas, `tests/Feature/Aislamiento/` hace lo mismo de forma automatizada.

---

# Parte 2: Roles dentro de la clinica

## Los tres roles

| | recepción | profesional | administrador |
|---|---|---|---|
| Ver y mover la agenda | si | si | si |
| Ver y registrar pacientes | si | si | si |
| Registrar consentimientos | si | si | si |
| **Leer historia clinica** | **no** | **si, si trata al paciente** | **no** |
| **Registrar evoluciones** | **no** | si, si lo trata | **no** |
| **Emitir recetas** | **no** | si, si lo trata | **no** |
| Administrar profesionales, salas, tarifas, usuarios | no | no | si |
| Ver la bitacora de accesos | no | no | si |

## La distincion que define el producto

**"Puede agendar" y "puede leer historia clinica" no son lo mismo.**

Recepción vive en el mostrador: ve nombres, telefonos, coberturas y la agenda.
No ve evoluciones, ni recetas, ni diagnosticos. Y no es unaolesale: que el
paciente tenga cita con el no cambia nada. No existe ninguna bandera en la
base ni en la configuración que le de acceso; el permiso depende del rol.

## El administrador no lee fichas clinicas

Es una decisión de producto, no un descuido:

> La direccion de la clinica no es un cargo clinico. El acceso a datos de
> salud se justifica por la atención al paciente, no por la jerarquia dentro
> de la institucion.

Un administrador que ademas atiende pacientes (cosa comun en una clinica
pequena) tiene un usuario con rol `profesional` y su ficha de profesional. Con
esa ficha entra; sin ella, no.

Esta regla esta fijada por una prueba
(`tests/Feature/Permisos/ProfesionalTratanteYAdminTest`) para que no se
desboque dentro de seis meses "porque era mas comodo".

## Las tres formas de ser tratante

Un profesional es tratante de un paciente si cumple **cualquiera** de estas
tres (`App\Services\Permisos\EquipoDeCuidado`):

1. **Tiene una asignacion de cuidado** registrada por la clinica
   (`asignaciones_cuidado`). Es la forma administrativa, para equipos de
   salud definidos.
2. **Tiene una cita** con ese paciente, en cualquier estado. Agendarla ya
   significa que el paciente entro en su agenda.
3. **Registro una evolucion** para ese paciente antes.

El motivo de las tres: en una clinica chica, "tratante" se decide de varias
formas y ninguna merita quedar afuera del sistema. Lo que no se acepta es que
cualquier profesional vea **todas** las fichas de la clinica.

## De donde sale el rol

De los **equipos de Authentik**, no de la base:

- `Administradores de ClinicaSalud` -> administrador
- `Profesionales de ClinicaSalud` -> profesional
- `Recepcion de ClinicaSalud` -> recepción
- `ClinicaSalud:<correlacion>` -> la clinica (el `tenant_id`)

El rol se resuelve **en cada ingreso**. Si en Authentik le quitan el equipo a
alguien, en ClinicaSalud pierde el acceso en la sesión siguiente. La
alternativa (guardar el rol para siempre en la base) deja ventanas de acceso
que nadie cerraba.

Ver el [05](05-CONFIGURACION.md) para el mapa completo.

## Una sesión sin clinica no entra

Si la persona no pertenece a ningun equipo `ClinicaSalud:<correlacion>` de una
clinica existente, `ResolutorDeSesion` falla y el ingreso se corta con un
mensaje generico. No hay cuenta sin clinica: sin tenant no hay nada que ver.

## La bitacora: sin excepciones

Todo acceso a ficha clinica queda anotado en `accesos_fichas`, **permitido o
denegado**, con quien, cuando, desde donde y por que ruta. Ver el
[07](07-SEGURIDAD-Y-LEY-19628.md).

Por que los denegados también: un volumen alto de intentos denegados es la
senal de que alguien esta probando el sistema con el rol equivocado, o de que
un profesional esta trabajando fuera de rol. Sin ese registro, el sistema no
puede ni notarlo.

## Donde se aplica cada permiso

| Capa | Que hace |
|---|---|
| `routes/web.php` | declara que recurso se toca y con que middleware |
| Middleware `admin` | corta las rutas de administración para quien no administra |
| `App\Policies\*` | **permiso fino**: quien puede ver, crear, editar, anular |
| `App\Services\Permisos\CapacidadesDeUsuario` | responde las preguntas de capacidad |
| PostgreSQL | limita que filas existen para quien consulta |
| Interfaz (Nuxt) | esconde botones según lo que dice la API |

El orden importa: **la base filtra filas, la Policy decide acciones, la
interfaz solo muestra**. Que un boton no aparezca no impide que alguien llame a
la API, y por eso cada ruta vuelve a preguntar al servidor. Ver el
[09](09-API.md).

---

## Los dos roles de PostgreSQL

La base de esta aplicación corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre clínicas funcione en vez de
quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `clinica_salud_admin` | `POSTGRES_USER` | Crear la base `clinica_salud` y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `clinica_salud` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

El rol de aplicación **no** lleva sufijo `_app`: se llama `clinica_salud`, igual
que la base.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: una
clínica habría visto las fichas, las evoluciones y las recetas de las demás, sin
error y sin aviso. Con datos de salud, eso es exactamente lo que no puede
pasar.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE clinica_salud OWNER TO clinica_salud;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan clinica:verificar-aislamiento
php artisan clinica:verificar-aislamiento --clinica
```

El prefijo del comando es `clinica:`, no `clinica-salud:`. La opción `--clinica`
recibe la correlación de una clínica existente para usar en la prueba; si no se
indica, el comando crea una clínica temporal.

**Lo que faltaba y se corrigió:** este comando **no consultaba `pg_roles`**.
Lo que hacía era comprobar que las tablas protegidas tengan RLS activada y
forzada, hacer una **lectura cruzada** (la clínica A no debe ver un paciente de
la clínica B) y comprobar que una consulta sin contexto falla cerrada.

Esa lectura cruzada detectaba un rol equivocado de forma indirecta: si la
conexión fuera superusuario, la clínica A vería al paciente de la B y el
comando fallaría. Es cierto que, a diferencia de otros comandos de Hefesto,
aquí la detección no depende de que la base tenga datos, porque el propio
comando crea las dos clínicas temporales. Aun así, la comprobación directa de
`pg_roles` **no estaba automatizada** y había que hacerla a mano.

**Ahora el comando consulta `pg_roles`**, directo por el nombre del rol, y es la
**primera** comprobación: antes de revisar las tablas y, sobre todo, antes de
escribir las filas temporales. Escribirlas con un rol que salta RLS sería
escribir en una base sin aislamiento. Falla si `rolsuper` o `rolbypassrls`
están activos, y el mensaje explica qué se detectó, por qué eso anula el
aislamiento, qué hacer (`POSTGRES_USER` debe ser el rol de arranque
`clinica_salud_admin`, que la aplicación nunca usa, y el rol de la aplicación se
crea `NOSUPERUSER NOBYPASSRLS` en `deploy/postgres/inicializar-rol.sh`) y dónde
está el patrón de referencia
(`apps/almacen/deploy/postgres/inicializar-rol.sh`).

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).