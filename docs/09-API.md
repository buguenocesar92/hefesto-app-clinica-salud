# 09 — API

Toda la superficie cuelga de `/api/v1` y esta declarada en un solo archivo:
`api/routes/web.php`. Esa es la lista completa y vigente.

## Convenciones

- Todo es JSON, excepto la ruta de impresion, que devuelve HTML.
- Las fechas viajan en ISO 8601 con zona (`2026-02-11T10:00:00-03:00`).
- Los identificadores son UUID.
- Los errores de validación vienen con la forma de Laravel (campo -> mensajes).
- Los errores de regla propia usan `{"mensaje": "..."}`.

## El navegador no habla con la API

Toda peticion pasa por el BFF de Nuxt (`web/server/api`), que reenvia la cookie
de sesión. La API no esta expuesta al navegador: en producción solo Caddy
pública puertos.

Por que: la cookie de sesión viaja en el mismo origen y el token CSRF se
reenvia como `X-XSRF-TOKEN`. Es el mismo mecanismo que usan las demas
aplicaciones de Hefesto.

## Rutas

### Autenticación (sin sesión)

| Metodo | Ruta | Que hace |
|---|---|---|
| GET | `/api/v1/auth/login` | genera la URL de Authentik con PKCE y `nonce`, y redirige |
| GET | `/api/v1/auth/callback` | canjea el código, verifica el token, crea la sesión |
| POST | `/api/v1/auth/logout` | cierra la sesión; devuelve la URL de cierre de Authentik |
| GET | `/api/v1/auth/usuario` | quien soy, rol y capacidades |
| GET | `/api/v1/salud` | salud; no necesita sesión ni clinica |

### Agenda (los tres roles)

| Metodo | Ruta | Que hace |
|---|---|---|
| GET | `/api/v1/agenda` | citas del rango pedido (`desde`, `hasta`, `profesional_id`, `sala_id`, `estado`) |
| GET | `/api/v1/agenda/disponibilidad` | huecos libres de un profesional (`profesional_id`, `fecha`, `duracion_minutos`) |
| GET | `/api/v1/citas` | listado, filtrable por `paciente_id` y `estado`, tope 200 |
| POST | `/api/v1/citas` | agenda |
| GET | `/api/v1/citas/{id}` | detalle |
| PATCH | `/api/v1/citas/{id}` | mueve o cambia el estado |
| DELETE | `/api/v1/citas/{id}` | **cancela**, no borra |

`DELETE /citas` cancela la cita y guarda `motivo_cancelacion`. Una cita atendida,
cancelada o con `no_asistio` no se modifica ni se cancela: `CitaPolicy`
devuelve `false`.

### Pacientes (los tres roles)

| Metodo | Ruta |
|---|---|
| GET | `/api/v1/pacientes` (`buscar`, `activo`, `pagina`, `por_pagina`) |
| POST | `/api/v1/pacientes` |
| GET | `/api/v1/pacientes/{id}` |
| PATCH | `/api/v1/pacientes/{id}` |
| GET | `/api/v1/pacientes/{id}/consentimientos` |
| POST | `/api/v1/pacientes/{id}/consentimientos` |

El RUT es único **dentro de la clinica**.

### Ficha clinica (solo profesional tratante, todo queda auditado)

| Metodo | Ruta |
|---|---|
| GET | `/api/v1/fichas/{paciente}` — historial completo: evoluciones y recetas |
| GET | `/api/v1/fichas/{paciente}/evoluciones` |
| GET | `/api/v1/fichas/{paciente}/evoluciones/{evolucion}` |
| POST | `/api/v1/fichas/{paciente}/evoluciones` |
| GET | `/api/v1/fichas/{paciente}/recetas` |
| GET | `/api/v1/fichas/{paciente}/recetas/{receta}` |
| POST | `/api/v1/fichas/{paciente}/recetas` |
| POST | `/api/v1/fichas/{paciente}/recetas/{receta}/anular` |
| GET | `/api/v1/fichas/{paciente}/recetas/{receta}/imprimir` — HTML imprimible |

Todas van bajo el middleware `ficha.auditada`. Los 404 son intencionales.

### Administración (solo administrador de la clinica)

| Metodo | Ruta |
|---|---|
| GET/POST | `/api/v1/admin/profesionales` |
| GET/PATCH | `/api/v1/admin/profesionales/{id}` |
| GET/POST | `/api/v1/admin/salas` |
| GET/PATCH | `/api/v1/admin/salas/{id}` |
| GET/POST | `/api/v1/admin/tipos-atencion` |
| GET/PATCH | `/api/v1/admin/tipos-atencion/{id}` |
| GET/POST | `/api/v1/admin/tarifas` |
| GET/PATCH | `/api/v1/admin/tarifas/{id}` |
| GET/POST | `/api/v1/admin/usuarios` |
| GET/PATCH | `/api/v1/admin/usuarios/{id}` |
| GET | `/api/v1/admin/auditoria` |

El `index` de profesionales, salas y tipos de atención también lo puede usar
quien agenda (`ProfesionalPolicy::verParaAgenda`): al agendar hay que elegir a
quien y en que sala. Lo que no se abre es crear o modificar.

## Por que 404 y no 403

Dentro de `/api/v1`, un rechazo de autorización se traduce a **404**, no a 403
(ver `bootstrap/app.php`).

En una ficha clinica, responder "existe pero no puedes" ya es filtrar
información: confirma que el paciente existe. El 404 no dice nada.

El 404 también cubre el caso de otro tenant: la RLS filtra antes de que la
Policy pregunte nada, así que un paciente de otra clinica simplemente no
aparece.

La bitacora registra el intento en los dos casos.

## Ejemplos

### Agendar

```http
POST /api/v1/citas
Content-Type: application/json
X-XSRF-TOKEN: <de la cookie XSRF-TOKEN>

{
    "paciente_id": "3f2c...-...",
    "profesional_id": "9ab1...-...",
    "sala_id": "77de...-...",
    "tipo_atencion_id": "c014...-...",
    "inicio": "2026-02-11T10:00:00-03:00",
    "motivo_consulta": "Control de hipertension"
}
```

No se manda `fin`: la duracion sale del tipo de atención. Respuestas:

| Código | Cuando |
|---|---|
| 201 | agendada |
| 422 | profesional ocupado, sala ocupada, paciente o profesional inexistente |

### Registrar una evolucion

```http
POST /api/v1/fichas/{paciente}/evoluciones

{
    "motivo_consulta": "Control de hipertension",
    "codigo_cie10": "I10",
    "diagnostico": "Hipertension arterial esencial",
    "tratamiento": "Se mantiene el tratamiento habitual.",
    "indicaciones": "Registrar la presion arterial tres veces por semana."
}
```

| Código | Cuando |
|---|---|
| 201 | registrada |
| 404 | no es profesional tratante, o el paciente no existe para quien pregunta |
| 422 | faltan campos |

### Emitir una receta

```http
POST /api/v1/fichas/{paciente}/recetas

{
    "contenido": "1. Losartan 50 mg, un comprimido al dia.",
    "documento_referencia_externo": "docuseal:plantilla-receta-0004821",
    "emitir": true
}
```

Sin `documento_referencia_externo`, la receta sale **sin firma** y la
respuesta lo dice (`"firmada": false`).

### Consultar la bitacora

```http
GET /api/v1/admin/auditoria?resultado=denegado&pagina=1
```

```json
{
    "datos": [
        {
            "usuario": { "id": "...", "nombre": "Recepcion turno manana" },
            "accion": "ver_historial",
            "resultado": "denegado",
            "metodo_http": "GET",
            "ruta": "api/v1/fichas/6b1e...",
            "direccion_ip": "192.0.2.44",
            "codigo_respuesta": 404,
            "registrado_en": "2026-02-11T13:04:11+00:00"
        }
    ],
    "paginacion": { "pagina": 1, "por_pagina": 50, "total": 3, "paginas": 1 }
}
```

## Sobre DocuSeal

La API **no firma**. Acepta `documento_referencia_externo` y lo guarda.

La integración con la API de DocuSeal (enviar la plantilla, recibir el estado
de la firma) **no esta implementada**: no se verifico su contrato contra la
documentacion oficial en este entorno, y una integración a ojo es peor que
ninguna. Queda declarado como pendiente en el [10](10-PRUEBAS-Y-ESTADO.md).

## Limites conocidos

| Limite | Valor | Nota |
|---|---|---|
| Citas por listado | 200 | tope fijo en `CitaController::index` |
| Pagina de pacientes | 25 por omision, hasta 100 | |
| Pagina de evoluciones y recetas | 25 | |
| Pagina de bitacora | 50 por omision, hasta 200 | |
| Horario de disponibilidad | 08:00 a 18:00 | fijo en `DisponibilidadController`; deberia venir de la configuración de la clinica |

El último es el mas visible: una clinica con atención vespertina no vera huecos
despues de las 18:00 en la pantalla de disponibilidad. La agenda en si acepta
cualquier hora; lo que no las calcula es el listado de huecos.

## Lo que la API no hace

- **No hay endpoint público.** Todo requiere sesión.
- **No hay emision de tokens.** No hay OAuth para terceros.
- **No hay exportación masiva de fichas clinicas.** A propósito: una
  exportación de historia clinica es el caso de uso mas comun de una
  filtracion y el que peor conviene dejar a medio hacer.
- **No hay integración con sistemas de laboratorio o farmacia.**
- **No hay facturacion.** El tarifario es un dato.