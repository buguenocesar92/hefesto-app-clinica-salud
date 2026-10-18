# ClinicaSalud

Aplicación propia de **Hefesto Studio** para clinicas y centros de salud de
tamano pequeno o mediano en Chile. Agenda medica, pacientes, ficha clinica,
recetas y administración de la clinica.

> **Este proyecto es software original de Hefesto.** No es un despliegue de una
> herramienta de terceros ni una reubicacion de código ajeno. Se uso Laravel,
> Nuxt, Socialite, `firebase/php-jwt`, PostgreSQL y Authentik como librerias,
> que es lo normal: lo que se escribio aquí es la aplicación.

---

## Aviso que hay que leer antes que nada

> **Esto NO esta listo para atender pacientes reales.**
>
> Manejar datos clinicos exige validaciones, auditorias y controles que no se
> pueden implementar solo con software: acuerdos de tratamiento de datos,
> designacion de responsable, protocolos de incidentes, capacitacion, control
> fisico del acceso, y la cooperacion del registro de salud que le
> corresponde a la institucion.
>
> Poner esto en producción sin esas garantias es un riesgo para el paciente,
> para la clinica cliente y para Hefesto Studio. Lo que si y lo que no esta
> hecho esta separado, sin adornos, en
> **[docs/07-SEGURIDAD-Y-LEY-19628.md](docs/07-SEGURIDAD-Y-LEY-19628.md)** y en
> **[docs/10-PRUEBAS-Y-ESTADO.md](docs/10-PRUEBAS-Y-ESTADO.md)**.

## Estado

**Primer corte. Código escrito, nada ejecutado.**

En el entorno donde se escribio esto no hay PHP, ni Composer, ni Docker. **No
se corrieron las pruebas, ni las migraciones, ni una construccion del
frontend.** Nada de lo que hay aquí esta probado por ejecución, salvo lo que
[docs/10](docs/10-PRUEBAS-Y-ESTADO.md) dice textualmente que si.

## Que problema resuelve

Una clinica pequena o mediana en Chile gestiona hoy las reservas, las fichas y
las recetas en sistemas distintos o en papel. El resultado son fichas
perdidas, horas de recepción ocupadas en llamadas, y lo mas grave:
**información clinica repartida en lugares donde cualquiera la ve**.

Un dato de salud es un dato personal sensible bajo la **Ley 19.628**, y el
acceso indebido a el es un delito. Este diseno los trata como lo que son.

## La decisión que explica el resto

**El aislamiento entre clinicas no lo implementa solo la aplicación: lo impone
PostgreSQL.** Y dentro de la clinica, **poder agendar no es lo mismo que poder
leer una historia clinica**.

Las dos fichas van separadas:

| | Ficha de identificacion | Ficha clinica |
|---|---|---|
| Que tiene | nombre, RUT, contacto, cobertura | evoluciones, diagnosticos, recetas |
| Donde vive | `pacientes` | `evoluciones`, `recetas`, `citas` |
| Quien la ve | los tres roles | **solo el profesional tratante** |
| Se audita | no | **siempre, permitido o denegado** |

Recepción agenda, ve los datos de identificacion y registra consentimientos. No
puede abrir una ficha clinica, ni la de un paciente que tiene cita con ella, y
**no hay ninguna bandera que se le pueda poner** para darsela. El administrador
de la clinica tampoco, salvo que ademas sea profesional: la direccion no es un
cargo clinico.

Las dos decisiones estan explicadas en
**[docs/03-MULTITENANCY-Y-ROLES.md](docs/03-MULTITENANCY-Y-ROLES.md)**.

## Documentos

| | |
|---|---|
| [01 Arquitectura](docs/01-ARQUITECTURA.md) | componentes y recorrido de una peticion |
| [02 Modelo de datos](docs/02-MODELO-DE-DATOS.md) | tablas, indices y reglas transversales |
| [03 Multi-tenancy y roles](docs/03-MULTITENANCY-Y-ROLES.md) | **el documento que explica todo lo demas** |
| [04 Instalación](docs/04-INSTALACION.md) | de cero a la primera persona dentro |
| [05 Configuración](docs/05-CONFIGURACION.md) | cada variable y por que |
| [06 Primer paciente](docs/06-PRIMER-PACIENTE.md) | la sesión completa, paso a paso |
| [07 Seguridad y Ley 19.628](docs/07-SEGURIDAD-Y-LEY-19628.md) | controles, lo que falta y **el aviso** |
| [08 Respaldos](docs/08-RESPALDOS.md) | respaldo, restauración, operación |
| [09 API](docs/09-API.md) | rutas, convenciones y limites |
| [10 Pruebas y estado](docs/10-PRUEBAS-Y-ESTADO.md) | **que se probo y que no** |

## Carpeta

```
apps/clinica-salud/
├── api/        Laravel 13: Policies, migraciones, RLS, auditoria, pruebas
├── web/        Nuxt 4 con render en el servidor, y el BFF que reenvia la cookie
├── deploy/     Dockerfiles, compose de desarrollo y produccion, Caddy
├── docs/       los diez documentos de arriba
└── herramientas/ scripts que no dependen de PHP ni de Node
```

## Modulos

1. **Agenda medica** por profesional y por sala, con duracion por tipo de
   atención, indice único que impide el doble booked y bloqueos de agenda.
2. **Ficha clinica**: historial, evoluciones, diagnosticos y recetas. **Cada
   acceso queda registrado** con quien, cuando y desde donde. Los intentos
   denegados también.
3. **Pacientes**: identificacion, cobertura y consentimientos.
4. **Recetas y certificados**: emision y formato imprimible. **Sin firma
   electronia propia**: la firma es DocuSeal, que ya existe en el catalogo de
   Hefesto, y aca queda solo la referencia.
5. **Administración**: profesionales, salas, tarifas, tipos de atención y
   usuarios.

## Estado de las integraciones

| Integración | Estado |
|---|---|
| Authentik | Implementada y verificada contra la documentacion oficial |
| DocuSeal | **Integración no implementada.** Se guarda la referencia del documento firmado. Ver [docs/09](docs/09-API.md) |
| Facturacion (Hefesto) | No implementada. El tarifario es un dato |
| CIE-10 (MINSAL) | No implementado. El código es texto libre |
| Registro profesional | No implementado |

## Como se levanta

```bash
cp api/.env.example api/.env
cp .env.example .env
# complete los valores (docs/04 y docs/05)

docker compose -f deploy/docker-compose.prod.yml up -d --build
docker compose -f deploy/docker-compose.prod.yml exec api php artisan key:generate
docker compose -f deploy/docker-compose.prod.yml exec api \
  php artisan clinica:verificar-aislamiento
```

En desarrollo: `docker compose -f deploy/docker-compose.dev.yml up --build`
(interfaz en <http://localhost:3000>, API en <http://localhost:8000>).

## Herramientas de verificación

Tres scripts que corren sin PHP ni Node, pensados para trabajar en un entorno
sin la pila completa:

```bash
python herramientas/verificar_caracteres.py    # prosa sin CJK, cirilicos, emojis
python herramientas/verificar_referencias.py   # cada `use` apunta a una clase real
python herramientas/verificar_php_basico.py    # llaves y parentesis balanceados
```

Los dos ultimos encontraron defectos reales durante la escritura (un `use` con
una clase acentuada mal escrita y una llave de apertura faltante). No
reemplazan a `php -l` ni a PHPUnit, pero acortan la distancia entre un error de
escritura y su descubrimiento.

## Lo que falta para producción

En el [07](docs/07-SEGURIDAD-Y-LEY-19628.md) hay una tabla completa. Lo mas
urgente:

1. Revision juridica de los textos de consentimiento y aviso de privacidad.
2. Doble factor de autenticación en Authentik.
3. Cifrado de los respaldos, y un ensayo de restauración de verdad.
4. Prueba de penetracion por terceros.
5. Validar el registro de profesionales contra una fuente oficial.
6. Correr las pruebas y el comando de verificación de aislamiento.