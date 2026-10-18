# 02 — Modelo de datos

PostgreSQL 18. Todas las tablas de negocio llevan `tenant_id`, y todas tienen
Row Level Security (ver [03](03-MULTITENANCY-Y-ROLES.md)).

## Diagrama

```
clinicas
   │  (tenant: no lleva tenant_id)
   │
   ├── profesionales ─────────────┐
   │        │                     │
   │        ├── usuarios          ├── asignaciones_cuidado ──── pacientes
   │        │                     │         (quien trata a quien)
   │        │                     │
   │        ├── citas ────────────┼─── salas
   │        │      │              │
   │        │      └── tipos_atencion
   │        │
   │        ├── evoluciones ──────┼─── pacientes
   │        └── recetas ──────────┼─── pacientes
   │
   ├── salas
   ├── tipos_atencion ── tarifas
   ├── usuarios
   ├── pacientes ──┬── consentimientos
   │               └── (historico clinico en evoluciones y recetas)
   └── accesos_fichas   (bitacora: sin clave foranea, ver abajo)
```

## Tablas

### `clinicas`

El tenant. **Única tabla sin `tenant_id` y única sin RLS**: cada fila es una
clinica completa y hay que poder leerla antes de que exista un contexto que
filtrar.

| Columna | Tipo | Nota |
|---|---|---|
| `id` | uuid | clave primaria |
| `slug` | varchar(80) | único, para URL |
| `correlacion_identidad` | varchar(60) | **único**: liga con el equipo de Authentik `ClinicaSalud:<correlacion>` |
| `nombre`, `razon_social`, `rut` | | datos de la institucion |
| `direccion`, `telefono`, `correo` | | |
| `zona_horaria` | varchar(60) | por omision `America/Santiago` |
| `activo` | boolean | una clinica desactivada no entra |

### `profesionales`

Medicos, odontologos, enfermeros, matronas. `registro_profesional` se guarda y
se muestra; **no se valida** contra ningun registro nacional, porque no hay
conexión a esa fuente (declarado en el [10](10-PRUEBAS-Y-ESTADO.md)).

### `salas`

Recurso agendable. Dos citas no pueden ocupar la misma sala al mismo tiempo.

### `tipos_atencion`

`duracion_minutos` es lo que hace util la agenda: la cita toma su duracion de
aca, no de lo que escriba el cliente.

### `tarifas`

Tarifario. `valor` es un entero en pesos chilenos, sin decimales: con float,
un cobro redondea distinto al esperado. Ver el [09](09-API.md) sobre por que
la facturacion real no sale de esta tabla.

### `usuarios`

Cuentas de la clinica. Sin contrasena: la identidad la emite Authentik.

| Columna | Nota |
|---|---|
| `tenant_id` | clinica |
| `correo` | llave de enlace con Authentik; único por clinica |
| `rol` | `recepcion`, `profesional` o `administrador`, con restriccion en la base |
| `profesional_id` | a quien ficha clinica representa |
| `activo` | una cuenta desactivada no opera, ni con sesión abierta |
| `ultimo_ingreso_en` | |

`rol` y `profesional_id` juntos deciden casi todo: un profesional sin
`profesional_id` no puede abrir ninguna ficha, y por eso `UsuarioController`
no deja crear un rol `profesional` sin ficha asociada.

### `pacientes`

Ficha de **identificacion**, no clinica. Es lo que recepción ve.

| Columna | Nota |
|---|---|
| `rut` | único **dentro de la clinica**: dos clinicas pueden tener al mismo paciente |
| `nombres`, `apellidos`, `fecha_nacimiento`, `sexo` | `sexo` admite `F`, `M`, `O`, `N` (no informado) |
| `telefono`, `correo`, `direccion`, `cobertura` | |
| `activo` | |

### `consentimientos`

`documento_referencia_externo` guarda la referencia del documento firmado en
DocuSeal. Un consentimiento rechazado queda con `otorgado = false` y fecha:
borrarlo perdería la evidencia de que se le informo.

### `citas`

| Columna | Nota |
|---|---|
| `inicio`, `fin` | `timestamp with time zone` |
| `estado` | `agendada`, `confirmada`, `atendida`, `cancelada`, `no_asistio` |
| `motivo_consulta` | nota de agenda, visible para recepción |
| `motivo_cancelacion` | |
| `creado_por_usuario_id` | |

`fin` se calcula con la duracion del tipo de atención. La API no acepta `fin`
en la peticion.

**Indices unicos parciales:** no se puede agendar dos veces al mismo
profesional (o en la misma sala) en el mismo minuto mientras la cita este
`agendada` o `confirmada`. Son la segunda capa de defensa, despues de la
comprobacion en la aplicación. Los indices son parciales a propósito: una cita
atendida queda en el historico y no debe bloquear ese minuto para siempre.

### `bloqueos_agenda`

Feriado, almuerzo, vacaciones. Con `profesional_id` y `sala_id` nulos, el
bloqueo es de toda la clinica.

### `asignaciones_cuidado`

"Este profesional atiende a este paciente", registrado por la clinica para los
casos con equipo de salud definido. Es una de las tres formas de ser tratante
(ver [03](03-MULTITENANCY-Y-ROLES.md)). Al terminar un episodio se desactiva
la fila, no se borra.

### evoluciones (modelo Evolucion)

Historia clinica escrita. `codigo_cie10` es texto libre: la aplicación **no**
trae el catalogo CIE-10 del Ministerio de Salud y no puede validar el código
(pendiente declarado en el [10](10-PRUEBAS-Y-ESTADO.md)).

### `recetas`

`contenido` es el texto que se imprime. `estado`: `borrador`, `emitida`,
`anulada`. La receta **no se borra**: se anula.

`documento_referencia_externo` es la referencia del documento firmado en
DocuSeal; puede ir nula, y en ese caso la receta esta emitida **sin firma** y
la interfaz lo avisa.

### `accesos_fichas`

La bitacora. Solo se anade: el modelo rechaza actualizaciones y borrados con
`LogicException`.

| Columna | Nota |
|---|---|
| `usuario_id`, `paciente_id` | **sin clave foranea**, ver abajo |
| `accion` | texto (`ver_historial`, `crear_evolucion`...). El catalogo esta en `App\Enums\AccionFicha` |
| `resultado` | `permitido` o `denegado` |
| `metodo_http`, `ruta`, `nombre_ruta` | donde se intento |
| `direccion_ip`, `agente_usuario` | desde donde |
| `codigo_respuesta` | cuando la peticion llego a responder |
| `registrado_en` | con valor por omision en la base |

**Por que no hay claves foraneas.** Si las hubiera, borrar (o volver
invisible) una ficha abriria un hueco en el registro que explica ese borrado. La
bitacora tiene que sobrevivir a que la fila que describe ya no exista.

**Por que se escribe en otra conexión.** La peticion corre en una transacción;
si el acceso es denegado, la excepcion la revierte y una bitacora escrita en
esa conexión se perderia justo en el caso que mas importa. Ver
`App\Services\Fichas\BitacoraFichas`.

### Tablas técnicas

`cache`, `cache_locks`, `sessions` y `password_reset_tokens`. **Sin `tenant_id`
y sin RLS**: la sesión se consulta antes de saber de que clinica se trata, y
una sesión protegida por RLS impediria iniciar sesión.

## Reglas transversales

1. **Ningun borrado de información clinica.** Se anula o se marca. Las unicas
   filas que se borran son las de cache.
2. **Las horas son instantes.** `timestamptz` en la base, presentacion en la
   zona horaria de la clinica.
3. **Los identificadores son uuid** generados en la aplicación.
4. **Las marcas de tiempo son `timestamptz`** en todo el esquema.
5. **El dominio de los estados esta en la base**: `CHECK` en `usuarios.rol`,
   `pacientes.sexo`, `citas.estado`, `recetas.estado`,
   `consentimientos.tipo`. Un rol mal escrito es imposible de guardar.
6. **`accion` es texto a propósito**: anadir una accion nueva no puede exigir
   una migración en una base con millones de registros.

## Migraciones

| Archivo | Que hace |
|---|---|
| `2026_01_20_000001_crear_tabla_clinicas` | tenant |
| `..._000002_crear_tabla_profesionales` | |
| `..._000003_crear_tabla_salas` | |
| `..._000004_crear_tabla_tipos_atencion` | |
| `..._000005_crear_tabla_tarifas` | |
| `..._000006_crear_tabla_usuarios` | rol con `CHECK` |
| `..._000007_crear_tabla_pacientes` | sexo con `CHECK`, RUT único por clinica |
| `..._000008_crear_tabla_consentimientos` | tipo con `CHECK` |
| `..._000009_crear_tabla_citas` | estado con `CHECK`, indices unicos parciales |
| `..._000010_crear_tabla_bloqueos_agenda` | |
| `..._000011_crear_tabla_asignaciones_cuidado` | |
| `..._000012_crear_tabla_evoluciones` | |
| `..._000013_crear_tabla_recetas` | estado con `CHECK` |
| `..._000014_crear_tabla_accesos_fichas` | bitacora, sin FK |
| `..._000015_crear_tabla_sesiones` | sesiones, cache, sin RLS |
| `..._000016_activar_row_level_security` | **la importante** |