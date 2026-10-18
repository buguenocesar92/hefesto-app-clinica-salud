# 10 — Pruebas y estado

Este es el primer documento que hay que leer antes de opinar sobre el código.

## Estado

**Código escrito, sin ejecutar.**

En el entorno donde se escribio esta aplicación **no hay PHP, ni Composer, ni
Docker**. Por lo tanto:

- **No se corrieron las pruebas de PHP.** No hay un solo "verde" de
  PHPUnit que pueda citarse.
- **No se corrieron las migraciones** contra una PostgreSQL real.
- **No se construyo la imagen** de la API ni de la interfaz.
- **No se instalaron ni ejecutaron las pruebas de la interfaz.**
- **No se hizo una prueba de penetracion.**

Lo que **si** se ejecutaron de verdad esta en la seccion siguiente. Nada mas.

## Lo que si se ejecuto

| Que | Resultado |
|---|---|
| Verificación de versiones en registries oficiales | Ver tabla abajo |
| `herramientas/verificar_caracteres.py` (dos pasadas) | Sin CJK, cirilicos, hebreos, arabes, griegos ni emojis |
| Prueba del verificador de caracteres (archivo sucio con cirilico y CJK) | Detectado correctamente, salida 1 |
| `herramientas/verificar_referencias.py` (tres corridas) | 86 clases declaradas, todas las referencias internas resuelven, salida 0. **En la primera corrida encontro un defecto real**: un `use` de `Evolución` sin tilde contra una clase accentuada, que habria sido un error fatal en ejecución. Se corrigio passandose el identificador a ASCII (`Evolucion`) |
| Lectura de JSON de `composer.json`, `package.json`, `tsconfig.json` | Sintaxis valida |
| `herramientas/verificar_php_basico.py` (cuatro corridas) | 110 archivos PHP revisados, llaves y parentesis balanceados, salida 0. **También encontro un defecto real**: a `render()` en `DocumentoController` le faltaba la llave de apertura, lo que habria sido un error fatal al cargar el archivo |
| Lectura de los archivos YAML de `deploy/` | Sintaxis valida |

Lo que **no** se ejecuto esta arriba. En ningun momento de este repositorio hay
una afirmacion de que algo paso si no esta en esta tabla.

## Versiones verificadas

Consultadas el 2026-10-17 contra el registro oficial de cada proyecto.

| Paquete | Versión | Fuente |
|---|---|---|
| `laravel/framework` | `v13.37.0` (última) | `repo.packagist.org/p2/laravel/framework.json` |
| `laravel/socialite` | `v5.31.0` | `packagist.org/packages/laravel/socialite.json` |
| `firebase/php-jwt` | `v7.2.1` | `packagist.org/packages/firebase/php-jwt.json` |
| `predis/predis` | `v3.7.0` | `packagist.org/packages/predis/predis.json` |
| `nuxt` | `4.6.2` | `registry.npmjs.org/nuxt/latest` |
| `vue` | `3.5.44` | `registry.npmjs.org/vue/latest` |
| `vue-router` | `5.4.0` | `registry.npmjs.org/vue-router/latest` |
| `postgres:18.6` | existe | Docker Hub |
| `redis:8.8.3-alpine` | existe | Docker Hub |
| `php:8.4-fpm-alpine3.24` | existe | Docker Hub |
| `node:24-alpine3.24` | existe | Docker Hub |
| `nginx:1.29-alpine` | existe | Docker Hub |
| `caddy:2.11.7-alpine` | existe | Docker Hub |
| `ghcr.io/goauthentik/server:2026.8.4` | etiqueta fijada en el kit de Hefesto | `kits/hefesto-identidad-authentik/VERSION.md` |
| Ley 19.628 | norma citada | **no verificada** en este entorno (el sitio del BCN carga con JavaScript) |

`fakerphp/faker ^1.24` y `phpunit/phpunit ^13.0.3` no se consultaron por
separado: son los rangos que declara el propio `laravel/framework` v13.37.0 en
su `require-dev`, o sea, las versiones con las que Laravel 13 se prueba.

## Pruebas escritas

### Suite `Aislamiento` (`tests/Feature/Aislamiento/`)

| Prueba | Que comprueba |
|---|---|
| `AislamientoEntreClinicasTest::una_clinica_no_ve_pacientes_de_otra` | con el contexto de A, el paciente de B no aparece ni por id |
| `::una_clinica_no_ve_historia_clinica_de_otra` | A no ve evoluciones de B, y B si ve las propias |
| `::sin_contexto_la_consulta_falla_y_no_devuelve_filas` | sin `app.clinica_id`, la consulta lanza excepcion |
| `::no_se_puede_escribir_una_fila_de_otra_clinica` | `WITH CHECK` impide escribir declarando otra clinica |
| `::por_la_api_un_paciente_de_otra_clinica_responde_404` | por la API, 404 y nada filtrado |
| `RowLevelSecurityTest::todas_las_tablas_de_negocio_tienen_rls_forzado` | las 13 tablas, `relforcerowsecurity` en true |
| `::cada_tabla_protegida_tiene_la_politica_de_aislamiento` | existe `aislamiento_tenant` en las 13 |
| `::la_funcion_de_contexto_falla_sin_variable` | `app_clinica_id()` lanza excepcion |
| `::la_funcion_de_contexto_devuelve_la_clinica_fijada` | devuelve el uuid correcto |
| `::el_contexto_desaparece_al_terminar_la_transaccion` | la variable no sobrevive a la transacción |

### Suite `Permisos` (`tests/Feature/Permisos/`)

| Prueba | Que comprueba |
|---|---|
| `RecepcionNoLeeFichaClinicaTest::recepcion_no_abre_la_historia_clinica` | 404 y el cuerpo no contiene el diagnóstico |
| `::recepcion_no_abre_las_evoluciones` | 404 |
| `::recepcion_no_abre_las_recetas` | 404 |
| `::recepcion_no_puede_registrar_una_evolucion` | 404 y **cero** evoluciones nuevas en la base |
| `::recepcion_si_puede_agendar_y_ver_pacientes` | la capacidad de mostrador sigue funcionando |
| `::el_intento_denegado_queda_en_la_bitacora` | existe el registro, con `denegado`, el usuario y la accion |
| `::el_acceso_permitido_tambien_queda_en_la_bitacora` | idem con `permitido` |
| `ProfesionalTratanteYAdminTest::el_profesional_tratante_abre_la_ficha` | 200 con la evolucion |
| `::un_profesional_que_no_trata_al_paciente_no_abre_la_ficha` | mismo rol, distinto paciente, 404 |
| `::el_administrador_no_abre_una_ficha_clinica` | el administrador recibe 404 |
| `::un_usuario_desactivado_no_agenda` | una cuenta desactivada pierde el acceso |
| `BitacoraInmutableTest::un_registro_no_se_puede_modificar` | `LogicException` |
| `BitacoraInmutableTest::un_registro_no_se_puede_borrar` | `LogicException` |

### Suite `Unit`

`EnumsTest`: los tres roles, sus equipos, que los estados finales de cita no
ocupan agenda, que existe el caso "no informado" de sexo.

## Como correrlas

```bash
cd api
composer install
cp .env.example .env
# La base de pruebas TIENE que ser PostgreSQL.
php artisan test --testsuite=Aislamiento
php artisan test --testsuite=Permisos
php artisan test --testsuite=Unit
php artisan test                       # todo
```

**No usar SQLite.** Las pruebas de aislamiento comprueban que PostgreSQL se
niega a devolver filas ajenas. En SQLite pasarían sin comprobar nada. Por eso
`phpunit.xml` fija `DB_CONNECTION=pgsql` y `DB_DATABASE=clinica_salud_test`.

Ademas hay una comprobacion desde la consola, para el servidor real:

```bash
php artisan clinica:verificar-aislamiento
```

## Pendiente, declarado

### Verificaciones técnicas

| Pendiente | Por que |
|---|---|
| Ejecutar las pruebas | no hay PHP ni Docker en este entorno |
| Ejecutar las migraciones | idem |
| Construir las imagenes | idem |
| `npm run build` de la interfaz | idem |
| Tipo de la interfaz (`nuxt typecheck`) | idem |
| Pruebas de la interfaz | no hay ninguna escrita |

### Integraciones sin verificar

| Pendiente | Estado |
|---|---|
| **DocuSeal**: envio de plantilla y recepción del estado de firma | **no implementada**. No se verifico el contrato de su API contra documentacion oficial. La aplicación solo guarda la referencia |
| **CIE-10**: validación de códigos contra el catalogo del Ministerio de Salud | **no implementada**. `codigo_cie10` es texto libre |
| **Registro profesional**: validación de `registro_profesional` | **no implementada**. Es texto |
| **Correo**: avisos al paciente (confirmacion de cita, receta lista) | **no implementado**. No hay correo saliente |
| **Facturacion**: cobro de la tarifa | **no implementado**. El tarifario es un dato |
| **Alertas**: aviso automático por volumen de intentos denegados | **no implementado**. Los umbrales existen en la configuración; el aviso no |
| **Rotacion de la bitacora**: purga según `CLINICA_RETENCION_BITACORA_DIAS` | **no implementada** |
| **Horario de la clinica**: 08:00 a 18:00 fijo en `DisponibilidadController` | deberia venir de la configuración |
| **Concurrencia en evoluciones**: dos pestanas pueden pisarse una evolucion | no hay control optimista |
| **Ley 19.628**: estado de las reformas posteriores | **no verificado**. Requiere revision juridica, ver el [07](07-SEGURIDAD-Y-LEY-19628.md) |
| **Horarios de atención por profesional** | no hay tabla de horarios; solo bloqueos globales o por recurso |

### Decisiones de producto pendientes de confirmar con una clinica real

- Si una recepción necesita ver "motivo de consulta" completo o solo la
  especialidad. Hoy ve el motivo que escribe recepción al agendar.
- Si el administrador de la clinica deberia ver la bitacora siempre, o solo
  cuando hay una solicitud formal. Hoy la ve siempre.
- Si el periodo de conservacion de la bitacora (10 anos por omision) es el
  correcto para la institucion.
- Cuanto tiempo se conserva una receta anulada antes de purgarla (hoy nunca).

## Como se sabe si esto sirve

Antes de ponerlo delante de un paciente:

1. `php artisan clinica:verificar-aislamiento` en el servidor.
2. Las tres suites en verde.
3. Los cinco pasos del final del [06](06-PRIMER-PACIENTE.md) a propósito, con
   los tres roles.
4. Un ensayo de restauración de respaldo.
5. Revision juridica de los textos de consentimiento.
6. Prueba de penetracion de terceros.

Sin los seis, no. Ver el [07](07-SEGURIDAD-Y-LEY-19628.md).

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`clinica_salud_admin`) y el rol de la
aplicación (`clinica_salud`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `clinica_salud` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS. Este paso **no está
   automatizado** en el comando verificador de esta aplicación, hay que hacerlo a
   mano.
3. Correr `php artisan clinica:verificar-aislamiento` y confirmar que sale con
   código 0.
4. Probar el escenario que motiva todo el cambio: una clínica intentando leer
   las evoluciones o recetas de un paciente de otra.

El comando verificador escribe filas temporales y las borra al final. No debe
correrse contra una base con pacientes reales.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.