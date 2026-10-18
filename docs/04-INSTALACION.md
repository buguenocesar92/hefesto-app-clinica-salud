# 04 — Instalación

Instalación en un servidor Linux con Docker y Docker Compose. Todo lo que
aparece aca fue **escrito pero no ejecutado** en este repositorio (ver el
[10](10-PRUEBAS-Y-ESTADO.md)).

## Requisitos

| |.Versión | Verificación |
|---|---|---|
| Docker Engine | 24 o superior | — |
| Docker Compose | v2 (`docker compose`) | — |
| Puertos libres | 80, 443, 443/udp | Caddy necesita el 443 para TLS |
| Dominio con DNS apuntando al servidor | | |
| Correo para los certificados | | Caddy lo usa para avisar |

Las versiones de imagen **estan fijadas** en `deploy/`. Se consultaron el
2026-10-17 y son las que existen en Docker Hub:

| Imagen | Uso |
|---|---|
| `postgres:18.6` | base de datos |
| `redis:8.8.3-alpine` | cache y colas |
| `php:8.4-fpm-alpine3.24` | API |
| `nginx:1.29-alpine` | servidor web interno de la API |
| `node:24-alpine3.24` | interfaz |
| `caddy:2.11.7-alpine` | proxy y TLS |

## Paso 1 — Obtener el código

```bash
git clone <repositorio-hefesto> hefesto
cd hefesto/apps/clinica-salud
```

## Paso 2 — Configurar

```bash
cp api/.env.example api/.env
cp .env.example .env
```

Complete en `api/.env`:

```dotenv
APP_URL=https://clinica.ejemplo.cl
APP_KEY=                       # se genera en el paso 3

DB_PASSWORD=<clave larga>

AUTHENTIK_ISSUER=https://identidad.hefesto.cl
AUTHENTIK_CLIENT_ID=
AUTHENTIK_CLIENT_SECRET=
AUTHENTIK_REDIRECT_URI=https://clinica.ejemplo.cl/api/v1/auth/callback
```

Y en `.env` (de la raiz):

```dotenv
DOMINIO=clinica.ejemplo.cl
ACME_EMAIL=admin@ejemplo.cl
```

`SESSION_SECURE_COOKIE` debe quedar en `true` cuando el despliegue es por
HTTPS: si queda en `false` con HTTPS activo, la cookie viaja sin la marca de
segura y cualquiera en la red la puede leer. En desarrollo queda en `false`.

Ver el [05](05-CONFIGURACION.md) para el detalle de cada variable.

## Paso 3 — Generar la clave de la aplicación

```bash
docker compose -f deploy/docker-compose.prod.yml run --rm api php artisan key:generate
```

El resultado hay que guardarlo en `api/.env` como `APP_KEY=`. Sin esto el
contenedor se detiene a propósito: el `entrypoint` lo comprueba.

## Paso 4 — Levantar

```bash
docker compose -f deploy/docker-compose.prod.yml up -d --build
```

Las migraciones se ejecutan solas en el arranque de la API (ver
`deploy/api/docker/entrypoint.sh`). PostgreSQL bloquea correctamente para que
dos replicas arrancando a la vez no corran migraciones en paralelo.

Comprueba el estado:

```bash
docker compose -f deploy/docker-compose.prod.yml ps
curl -s https://clinica.ejemplo.cl/api/v1/salud
```

## Paso 5 — Crear la clinica y su primer administrador

La primera cuenta de administración **no se puede crear desde la interfaz**: si
pudiera, cualquiera con acceso al panel podria convertirse en dueno de la
institucion. La crea el despliegue.

Registra la clinica (sin RLS, se puede hacer sin contexto):

```bash
docker compose -f deploy/docker-compose.prod.yml exec api php artisan tinker --execute="
  App\Models\Clinica::create([
    'slug' => 'centro-vida-medica',
    'correlacion_identidad' => 'centro-vida',
    'nombre' => 'Centro de Vida Medica',
    'zona_horaria' => 'America/Santiago',
    'activo' => true,
  ]);
"
```

Luego crea la cuenta:

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
  php artisan clinica:crear-administrador clinica=centro-vida \
  correo=admin@centrovida.cl nombre="Direccion Medica"
```

## Paso 6 — Authentik

En Authentik hay que crear (ver el
[kits/hefesto-identidad-authentik](../../kits/hefesto-identidad-authentik)):

1. **Una aplicación OIDC** de tipo `OAuth2/OpenID Connect` con el cliente
   `AUTHENTIK_CLIENT_ID` de la configuración, y la URL de retorno
   `AUTHENTIK_REDIRECT_URI`.
2. **Equipos**:
   - `ClinicaSalud:centro-vida` (uno por clinica; el texto despues de los dos
     puntos es la `correlacion_identidad`).
   - `Administradores de ClinicaSalud`
   - `Profesionales de ClinicaSalud`
   - `Recepcion de ClinicaSalud`
3. **Cada persona** debe pertenecer a su equipo de rol y al equipo de su
   clinica. Sin el equipo de clinica, el ingreso se corta con 403.

Complete también `config/authentik.php` (`grupos_por_rol`), que viaja con los
valores comentados a propósito: los nombres de los equipos los decide cada
instalación.

## Paso 7 — Verificar el aislamiento

**Antes de cargar un solo dato real:**

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
  php artisan clinica:verificar-aislamiento
```

Si falla, la instalación no tiene el aislamiento activo. No siga hasta que
pase. El detalle de que comprueba esta en el [03](03-MULTITENANCY-Y-ROLES.md).

## Paso 8 — Datos de ejemplo (opcional)

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
  php artisan db:seed --class=ClinicaDeEjemploSeeder
```

Crea una clinica de ejemplo con dos profesionales, tres pacientes, agenda y
historia clinica. Sirve para probar la separacion de roles (ver el
[06](06-PRIMER-PACIENTE.md)). **No se debe usar en una instalación real con
datos reales.**

## Desarrollo

```bash
cp api/.env.example api/.env
docker compose -f deploy/docker-compose.dev.yml up --build
```

- API en <http://localhost:8000>
- Interfaz en <http://localhost:3000>

En desarrollo se montan el código y las caches (volumenes `storage` y
`bootstrap`), y el opcache valida sellos de tiempo para que los cambios se
vean sin reconstruir.

Para trabajar en la API sin Docker hace falta PHP 8.3 o superior, Composer y
una PostgreSQL con la extension `pdo_pgsql`. El aislamiento **no se puede
probar en SQLite**: las pruebas de `tests/Feature/Aislamiento/` estan escritas
para fallar en esa base, y estan configuradas con `pgsql` en `phpunit.xml`.

## Problemas frecuentes

**"Falta el contexto de clinica"**
Una consulta a una tabla protegida se hizo fuera de `ContextoTenant`. Casi
siempre es un comando de consola: debe correr dentro de
`ContextoTenant::ejecutarAislado()`.

**El ingreso se corta con 403**
La persona no pertenece a un equipo `ClinicaSalud:<correlacion>`, o la
correlacion no existe en `clinicas`. Ver el paso 6.

**401 despues de ingresar**
La URL de retorno de Authentik y `AUTHENTIK_REDIRECT_URI` no coinciden, o la
cookie se esta rechazando. Revise `SESSION_SECURE_COOKIE` y que este usando
HTTPS.

**El login devuelve 419**
Falta el token CSRF. El BFF tiene que reenviar `X-XSRF-TOKEN` leyendo la
cookie `XSRF-TOKEN`; ver `web/server/utils/api.ts`.

**La API rechaza tokens validos**
Authentik roto su clave de firma:

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
  php artisan clinica:olvidar-cache-oidc
```

## Desinstalar

```bash
docker compose -f deploy/docker-compose.prod.yml down
```

**Esto no borra los datos.** Los volumenes (`clinica_salud_postgres`) se
conservan. Para borrar también los datos, verifique que tiene el respaldo de
[08](08-RESPALDOS.md) y **despues** ejecute `docker volume rm`.

Un volumen de PostgreSQL borrado por accidente son fichas de pacientes
perdidas, y no hay forma de recuperarlas si no hay respaldo.