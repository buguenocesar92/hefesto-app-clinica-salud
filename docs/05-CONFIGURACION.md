# 05 — Configuración

Todas las variables estan comentadas en `api/.env.example`. Este documento
explica las que tienen una razon de ser.

## Aplicación

| Variable | Por omision | Nota |
|---|---|---|
| `APP_ENV` | `local` | `production` en un despliegue real |
| `APP_DEBUG` | `false` | **no lo active con datos de salud**: el registro con nivel `debug` puede incluir el cuerpo de una peticion, y el cuerpo de una peticion puede ser una evolucion |
| `APP_TIMEZONE` | `America/Santiago` | zona por omision de las clinicas de esta instalación |
| `LOG_LEVEL` | `info` | `debug` deja el cuerpo de las peticiones en el registro |
| `SESSION_EXPIRACION_MINUTOS` | `120` | una sesión abierta mucho rato es una sesión abierta a la ficha clinica |

## Base de datos

| Variable | Por omision | Nota |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | **obligatorio**. La RLS no existe en otro motor |
| `DB_SSLMODE` | `prefer` | sirve dentro de la red de Compose. Si la base queda afuera de la instalación, use `require` |
| `DB_AUDITORIA_*` | los valores de `DB_*` | conexión aparte para la bitacora |

La conexión `auditoria` existe porque la peticion puede terminar revertida
(acceso denegado) y el registro del intento tiene que sobrevivir a esa reversa.
Puede apuntar a otra base de datos o a otra maquina con las mismas variables.
Ver el [07](07-SEGURIDAD-Y-LEY-19628.md).

## Authentik

| Variable | Nota |
|---|---|
| `AUTHENTIK_ISSUER` | URL pública **sin barra final**. El descubrimiento se lee de `<issuer>/.well-known/openid-configuration` |
| `AUTHENTIK_CLIENT_ID` / `_SECRET` | cliente **exclusivo** de esta aplicación, para revocar el acceso sin tocar las demas de Hefesto |
| `AUTHENTIK_REDIRECT_URI` | tiene que coincidir exactamente con la configurada en Authentik |
| `AUTHENTIK_SCOPES` | `openid profile email`. `profile` es lo que trae el claim `groups` |
| `AUTHENTIK_ALGORITMO` | `RS256`. Se comprueba **antes** de verificar la firma, para cortar el ataque de confusion de algoritmo |
| `AUTHENTIK_CACHE_METADATA` | 1 hora |
| `AUTHENTIK_CACHE_JWKS` | 24 horas. Las claves rotan pocas veces |
| `AUTHENTIK_LEEWAY` | 60 s de margen de reloj |

### El mapa de equipos (esta en `config/authentik.php`, no en el `.env`)

```php
'grupos_por_rol' => [
    'Administradores de ClinicaSalud' => 'administrador',
    'Profesionales de ClinicaSalud' => 'profesional',
    'Recepcion de ClinicaSalud' => 'recepcion',
],
```

**Va comentado en el repositorio a propósito:** los nombres de los equipos los
decide cada instalación, según como este organizado Authentik. Descomente y
ajuste antes de dar de alta a la primera persona.

El orden importa: si alguien pertenece a dos equipos, gana el primero que
aparece. Si quiere que un administrador que también atiende pacientes tenga
acceso clinico, la respuesta es **dos cuentas**, no dos roles en una.

La precedencia de `rol` en el enum `RolClinico` es la que se usa para el
metodo `equipoAuthentik()`, que sirve de guia en el
[06](06-PRIMER-PACIENTE.md).

### La clinica de una persona

| Variable | Por omision |
|---|---|
| `AUTHENTIK_GRUPO_POR_CLINICA` | `ClinicaSalud` |

Es el **prefijo** de los equipos que declaran el tenant. El equipo se llama
`<prefijo>:<correlacion>`: por ejemplo `ClinicaSalud:centro-vida`. El texto
despues de los dos puntos se busca en `clinicas.correlacion_identidad`.

Sin un equipo que declare una correlacion no hay ingreso: se responde 403.

## Reglas de la clinica

| Variable | Por omision | Que controla |
|---|---|---|
| `CLINICA_DURACION_MAXIMA_MINUTOS` | 240 | tope de la duracion de un tipo de atención |
| `CLINICA_ANTICIPACION_MAXIMA_DIAS` | 180 | hasta cuando se puede agendar |
| `CLINICA_MARGEN_ENTRE_CITAS` | 0 | hueco entre una cita y otra |
| `CLINICA_RETENCION_BITACORA_DIAS` | 3650 | cuanto se conserva la bitacora de accesos |
| `CLINICA_ALERTA_DENEGADOS_POR_HORA` | 20 | umbral de revision de intentos denegados |
| `CLINICA_ALERTA_DENEGADOS_POR_USUARIO` | 5 | idem, por persona |

`CLINICA_RETENCION_BITACORA_DIAS` no es un tecnicismo: es el plazo que una
clinica podria tener que demostrar ante una denuncia o un reclamo. Ver el
[07](07-SEGURIDAD-Y-LEY-19628.md).

## Rotacion de claves de Authentik

Si Authentik rota su clave de firma y la API sigue rechazando tokens que si son
validos, la causa casi siempre es el JWKS cacheado:

```bash
php artisan clinica:olvidar-cache-oidc
```

## Interfaz (Nuxt)

| Variable | Donde vive | Que controla |
|---|---|---|
| `NUXT_API_BASE` | `web/.env` o el compose | URL **interna** de la API. Solo la usa el servidor de Nuxt |
| `NUXT_DIAS_AGENDA` | idem | dias que muestra la agenda por omision |
| `NITRO_PORT` | idem | puerto del servidor de Nuxt |

`NUXT_API_BASE` nunca sale hacia el navegador: vive en `runtimeConfig` sin el
prefijo `public`.

## Lo que NO se configura

- **Permisos.** No hay bandera que le de a recepción acceso a fichas clinicas.
  Viene del rol en Authentik.
- **Firmas.** La firma es DocuSeal. ClinicaSalud guarda la referencia.
- **Zonas horarias por clinica.** Cada fila de `clinicas` tiene la suya; la
  presentacion usa esa. `APP_TIMEZONE` es la por omision para clinicas nuevas.
- **JWT propio.** No hay emision de tokens: la sesión la maneja Laravel.

## Rotar la clave de la aplicación

```bash
php artisan key:generate
```

Esto **invalida todas las sesiones abiertas**: cada persona tendra que entrar
de nuevo. Es lo correcto desde el punto de vista de seguridad y lo molesto
desde el punto de vista operativo. Avisar antes si la clinica esta atendiendo.