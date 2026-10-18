# 01 — Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

## Que es ClinicaSalud

Aplicación propia de Hefesto Studio para clinicas y centros de salud de tamano
pequeno o mediano en Chile. Reemplaza el sistema de reservas, las fichas en
papel y las recetas perdidas, y pone orden en el acceso a la información
clinica.

Este documento describe como esta Armada la aplicación. Las decisiones que
explican el resto estan en el [03](03-MULTITENANCY-Y-ROLES.md) y en el
[07](07-SEGURIDAD-Y-LEY-19628.md).

## Las dos fichas

La distincion que organiza todo el producto:

| | Ficha de identificacion | Ficha clinica |
|---|---|---|
| Que tiene | nombre, RUT, contacto, cobertura | evoluciones, diagnosticos, recetas |
| Donde vive | tabla `pacientes` | tablas `evoluciones`, `recetas`, `citas` |
| Quien la ve | los tres roles | solo el profesional tratante |
| Se audita | no | si, siempre |

Confundirlas es el error clasico de los sistemas de consulta medica, y por eso
estan en tablas distintas, con Policies distintas y con rutas distintas.

## Componentes

```
    Navegador
        |  (cookie de sesion, mismo origen)
        v
    Caddy  -------- terminates TLS, cabeceras, un solo dominio
        |
        +-- /api/* --------> API (Laravel 13)
        |                     nginx + php-fpm + queue worker + scheduler
        |                     |
        |                     +--> PostgreSQL 18 (RLS por clinica)
        |                     +--> Redis (cache, sesiones, colas)
        |
        +-- /* -----------> Interfaz (Nuxt 4, SSR)
                              |
                              +--> BFF: reenvia la cookie a la API
```

### API: Laravel 13

- Controladores en `app/Http/Controllers`,Policies en `app/Policies`,
  servicios en `app/Services`.
- Sesión propia de Laravel. La identidad llega de Authentik con OpenID
  Connect y se verifica una vez, al entrar (ver `AuthController`).
- Una sola versión de API: `/api/v1`. Toda la superficie cuelga de ahi, así
  que el alcance esta a la vista en `routes/web.php`.
- Middleware en tres capas: `ResolverTenant` (contexto de clinica),
  `auth` (sesión), `ficha.auditada` (bitacora de acceso clinico).

### Interfaz: Nuxt 4 con render en el servidor

- El navegador nunca habla con la API: habla con el servidor de Nuxt, que
  reenvia la cookie de sesión. No hay ningun token en el código del cliente.
- El render en el servidor es una decisión de privacidad: una pantalla que se
  dibuja en el servidor no depende de que el JavaScript haya cargado para no
  entregar información.
- Sin framework de estilos: `app/assets/css/clinica.css`.

### Datos: PostgreSQL 18

PostgreSQL no es reemplazable aca. El aislamiento entre clinicas lo impone su
Row Level Security. En otro motor, el mismo código no garantizaria nada
(motivo técnico en el [03](03-MULTITENANCY-Y-ROLES.md)).

### Redis 8

Cache (documento de descubrimiento OIDC y conjunto de claves), sesiones si se
cambia el controlador, y colas.

### Authentik

Identidad compartida del estudio, la misma de las otras aplicaciones de
Hefesto, con un cliente OIDC **exclusivo** de ClinicaSalud para poder revocar
el acceso a esta aplicación sin tocar las demas. Ver
[kits/hefesto-identidad-authentik](../../kits/hefesto-identidad-authentik).

### DocuSeal

La firma de recetas y consentimientos ocurre fuera, en DocuSeal, que ya esta en
el catalogo de Hefesto. ClinicaSalud guarda la referencia del documento
firmado. **No** implementa firma criptografica: duplicarla seria una garantia
mas baja, no mas alta. Ver el [03](03-MULTITENANCY-Y-ROLES.md).

## Recorrido de una peticion de ficha clinica

Este es el camino que mas importa, porque es el que maneja el dato mas sensible:

1. Llega `GET /api/v1/fichas/{paciente}` desde el BFF de Nuxt.
2. `ResolverTenant` lee la sesión, ve el `tenant_id` del usuario y abre una
   transacción con el contexto de clinica fijado.
3. `auth` confirma que hay sesión abierta.
4. `ficha.auditada` prepara el registro: accion, paciente, ruta, IP.
5. El controlador busca el paciente dentro de la clinica. La RLS ya filtro, así
   que un paciente de otra clinica **no aparece**.
6. `PacientePolicy::verFicha` pregunta si esa persona es profesional tratante
   de ese paciente.
7. Si algo falla, la excepcion sube hasta `ficha.auditada`, que anota el
   intento como **denegado** y lo reenvia. El registro va en una conexión
   aparte, para que sobreviva a la reversa de la transacción.
8. Si todo pasa, se entrega la respuesta y `ficha.auditada` anota el acceso
   como **permitido**.

En ninguno de los dos casos hay una ruta por la que se abra una ficha sin dejar
rastro.

## Estructura de carpetas

```
apps/clinica-salud/
├── README.md
├── .env.example              Variables de la composicion
├── api/                      Laravel
│   ├── app/
│   │   ├── Enums/            Rol, estados, acciones auditadas
│   │   ├── Models/           Clinica, Usuario, Paciente, Evolucion...
│   │   ├── Policies/         Permisos por modelo
│   │   ├── Http/             Controladores, middleware, peticiones, recursos
│   │   ├── Services/
│   │   │   ├── Tenancy/      ContextoTenant (contexto de clinica)
│   │   │   ├── Auth/         OIDC con Authentik
│   │   │   ├── Permisos/     Capacidades y equipo de cuidado
│   │   │   ├── Fichas/       Bitacora de accesos
│   │   │   └── Agenda/       Reglas de disponibilidad
│   │   └── Console/          Comandos de operacion
│   ├── database/
│   │   ├── migrations/       16 migraciones, la ultima activa la RLS
│   │   ├── seeders/          Clinica de ejemplo
│   │   └── factories/
│   ├── routes/web.php        Toda la superficie de la API
│   └── tests/                Aislamiento, permisos, unidades
├── web/                      Nuxt 4
│   ├── app/                  Paginas, layout, composable de sesion
│   └── server/api/           BFF que reenvia la cookie
├── deploy/                   Dockerfiles, compose, Caddy
├── docs/                     Este conjunto
└── herramientas/             Tres scripts de verificacion que no dependen de PHP ni Node
```

## Que NO esta

- No hay pagos ni facturacion. El tarifario existe como dato; la facturacion es
  la integración que ya existe en Hefesto.
- No hay recordatorios ni messaging a pacientes.
- No hay portal de autoservicio del paciente: ClinicaSalud es la aplicación de
  la clinica. El Portal de Autoservicio es otra aplicación, con su propia
  instalación.
- No hay firma electrónica. Ver arriba.
- No hay telemedicina.
- No hay expediente compartido con los estudios juridicos o de imagenologia: ClinicaSalud es el sistema de registro clinico de la institucion.

El detalle del alcance esta en el [09](09-API.md).

## Convenciones de escritura

Regla que se aplico en todo el código, y que conviene no romper:

- **La prosa lleva ortografia completa de Chile, con tildes**: comentarios,
  mensajes de error, textos de la interfaz, documentacion. Un profesional que
  lee un error tiene que entenderlo.
- **Los identificadores van en ASCII**: `Evolucion`, no `Evolución`. La razon
  es técnica y concreta: en PHP el nombre de la clase se convierte en ruta de
  archivo para el cargador automático, y en sistemas de archivos que
  normalizan Unicode (macOS) una tilde en el nombre se rompe en el camino mas
  incomodo, con un fallo fatal en producción. El nombre de la clase aparece en
  la URL y en el registro; la palabra con tilde aparece en la pantalla y en la
  documentacion, que es donde tiene que estar.
- **Los nombres de tabla y de columna van en `snake_case` en espanol sin
  tildes**: `evoluciones`, `codigo_cie10`, `motivo_consulta`.

Con esto, tres scripts de `herramientas/` vigilan lo que la revision humana
deja pasar. Los tres se ejecutan sin PHP ni Node:

| Script | Que vigila |
|---|---|
| `verificar_caracteres.py` | prosa sin CJK, cirilicos, hebreos, arabes, griegos ni emojis |
| `verificar_referencias.py` | que cada `use` interno apunte a una clase declarada, y que los controladores de `routes/web.php` existan |
| `verificar_php_basico.py` | llaves y parentesis balanceados en el PHP |

Los dos ultimos encontraron defectos reales durante la escritura: un `use` de
una clase acentuada mal escrito, y un metodo al que le faltaba la llave de
apertura. No reemplazan a `php -l` ni a PHPUnit, pero acortan la distancia
entre un error de escritura y su descubrimiento.
