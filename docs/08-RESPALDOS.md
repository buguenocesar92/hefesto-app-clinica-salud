# 08 — Respaldos, restauración y operación

## Que hay que respaldar

| Volumen | Que contiene | Criticidad |
|---|---|---|
| `clinica_salud_postgres` | todo: fichas, agenda, historia clinica, bitacora | **maxima** |
| `clinica_salud_storage` | registros y caches de Laravel | baja (se regeneran) |
| `clinica_salud_redis` | cache y colas | nula (se regenera) |
| `clinica_salud_caddy_datos` | certificados TLS | media (se reemiten) |
| `api/.env` | **secretos** | **maxima**, y con otro cuidado |

## Advertencia que no es técnica

Un respaldo de historia clinica es **una copia de los datos mas sensibles de la
clinica, fuera de sus controles**. Si un respaldo queda en un disco externo sin
cifrar, existe una segunda copia del dato desprotegida, y suele quedar en un
lugar con menos vigilancia que el servidor.

Por eso:

- El respaldo tiene que estar **cifrado**, no solo comprimido.
- La clave de cifrado **no** va junto al respaldo, y **no** va en el mismo
  disco.
- El acceso al respaldo tiene que estar registrado y limitado a quien
  corresponde.
- Si la clinica no puede sostener estas reglas, la instalación no debe recibir
  datos reales. Ver el [07](07-SEGURIDAD-Y-LEY-19628.md).

El estudio tiene el kit `kits/hefesto-respaldo-kopia` (Kopia), que es lo que se
usa en las demas aplicaciones.

## Respaldo

```bash
# Volcado comprimido, en caliente, sin detener la aplicacion.
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  pg_dump --format=custom --compress=9 --no-owner \
  --username=clinica_salud clinica_salud > clinica_salud-$(date +%F).dump
```

Con `pg_dump` no se necesita detener el servicio: PostgreSQL hace el volcado en
una transacción consistente.

Frecuencia recomendada: **diario**, mas un volcado completo antes de cualquier
cambio de versión.

Rotacion: conservar el **diario** de 15 dias, el **mensual** de 12 meses, y
todo lo anterior a la fecha de corte acordada con la institucion.

### Automatizacion

```cron
30 3 * * * /ruta/respaldar-clinica.sh >> /var/log/clinica-respaldo.log 2>&1
```

El script tiene que hacer tres cosas, y fallar si alguna falla:

1. Volcar la base.
2. **Cifrar** el resultado.
3. Verificar que el archivo final se puede leer.

Un script que copia y no verifica esta configurado en falso.

## Restauración

Este es el paso que casi nunca se ensaya, y el que importa:

```bash
# 1. Detener la API y la interfaz (la base sigue arriba).
docker compose -f deploy/docker-compose.prod.yml stop api web

# 2. Crear una base vacia con otro nombre.
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  psql -U clinica_salud -d postgres -c "DROP DATABASE IF EXISTS clinica_salud_restore;"
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  psql -U clinica_salud -d postgres -c "CREATE DATABASE clinica_salud_restore OWNER clinica_salud;"

# 3. Restaurar EN UNA BASE NUEVA. Nunca encima de la que tiene datos.
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  pg_restore --dbname=clinica_salud_restore --no-owner \
  --username=clinica_salud < clinica_salud-2026-02-10.dump

# 4. Revisar antes de cambiar nada.
docker compose -f deploy/docker-compose.prod.yml exec -T postgres \
  psql -U clinica_salud -d clinica_salud_restore \
  -c "SELECT COUNT(*) FROM pacientes; SELECT COUNT(*) FROM evoluciones;"
```

**Restaurar encima de la base viva** es como se pierde la información el dia que
mas falta. Por eso el paso 4 existe.

### Con que seguido ensayar

Un ensayo de restauración completo, **al menos trimestral**, en un servidor
aparte. Un respaldo que nunca se restauro es una hipotesis.

## Rotacion de la bitacora

`accesos_fichas` tiene un plazo de conservacion declarado
(`CLINICA_RETENCION_BITACORA_DIAS`, 10 anos por omision), pero **no hay purga
automática**: los registros se conservan.

Que se purguen o no es una decisión de la institucion, y conviene que quede
escrita. Una bitacora de accesos suele conservarse mas que los datos clinicos,
porque sirve para demostrar accesos a ellos.

## Mantencion periodica

| Cada | Que |
|---|---|
| Dia | respaldo automático (verificar que el archivo existe y tiene el tamano esperado) |
| Semana | revisar la bitacora de intentos denegados |
| Mes | revisar logs; actualizar imagenes si hay versiones nuevas |
| Trimestre | ensayo de restauración; revisar accesos a una muestra de fichas |
| Semestre | rotacion de secretos (`APP_KEY` con aviso, `DB_PASSWORD`, secreto de Authentik) y revision de permisos: quien sigue necesitando que |

## Logs

| Archivo | Que |
|---|---|
| `clinica.log` | registro de la aplicación |
| `accesos-rechazados.log` | intentos de acceso a ficha que no pudieron anotarse en la base |

El segundo es el mas importante de mirar: si aparece, **algo esta fallando en
la auditoria**. La aplicación corta la peticion cuando la bitacora no se puede
escribir (una ficha que se puede leer sin dejar rastro es peor que una peticion
que falla), pero hay que saber por que falla.

Nunca suban `LOG_LEVEL=debug` ni `APP_DEBUG=true` con datos reales: el cuerpo
de una peticion puede ser una evolucion.

## Disco

`storage/logs` y `storage/framework` crecen sin control si no se limpian. Un
cron diario que borre los registros con mas de 14 dias resuelve; los registros
de Laravel no traen rotacion propia.

## Actualizar

1. Respaldo completo verificado.
2. Construir la imagen nueva en un entorno de prueba.
3. Correr `php artisan clinica:verificar-aislamiento` en ese entorno.
4. Correr las pruebas:
   ```bash
   docker compose -f deploy/docker-compose.prod.yml run --rm api \
     php artisan test --testsuite=Aislamiento
   docker compose -f deploy/docker-compose.prod.yml run --rm api \
     php artisan test --testsuite=Permisos
   ```
5. Levantar.
6. Volver a correr `clinica:verificar-aislamiento`.
7. Revisar la bitacora: accesos denegados nuevos pueden indicar que algo cambio
   de comportamiento.

La base aplica las migraciones al arrancar. Si una migración falla, el
contenedor no levanta: es preferible a una versión a medias con fichas de
pacientes.