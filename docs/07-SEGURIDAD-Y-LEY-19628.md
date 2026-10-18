# 07 — Seguridad y Ley 19.628

## Aviso, primero y en serio

> **Esta aplicación NO esta lista para atender pacientes reales.**
>
> Manejar datos clinicos exige un conjunto de controles que no se puede
> implementar solo con software: acuerdos de tratamiento de datos con los
> pacientes, designacion de un responsable, contratos con los profesionales,
> protocolos de respuesta ante incidentes, capacitacion, control fisico del
> acceso a los equipos, y la cooperacion del registro de salud que le
> corresponde a la institucion.
>
> Poner esto en producción sin esas validaciones es un riesgo para el paciente
> y para la clinica cliente, y también para Hefesto Studio.
>
> Este documento explica que hace el software. No reemplaza el trabajo
> juridico ni las garantias técnicas que la normativa exige.

## Ley 19.628

Los datos de salud son **datos personales sensibles** bajo la Ley 19.628 sobre
Protección de la Vida Privada. Eso cambia el tratamiento que corresponde:

- El **titular** tiene derecho a saber, a acceder y a pedir rectificacion o
  supresión de sus datos.
- La **finalidad** tiene que ser determinada y declarada. La ficha clinica
  existe para atender pacientes; no para otros usos.
- El acceso indebido a datos de salud no es una falla técnica: es un delito.

> **Pendiente de verificación juridica.** Esta seccion fue escrita el 2026-10-17
> a partir de la Ley 19.628. **No se pudo verificar en este entorno** el estado
> de las reformas posteriores a la distancia (en particular la creacion de una
> Agencia de Protección de Datos Personales y los cambios asociados). Antes de
> cualquier despliegue real, un abogado debe revisar esta seccion completa y
> redactar los textos de consentimiento y aviso de privacidad que usara la
> clinica. Queda como trabajo pendiente, no como algo resuelto.

## Que hace el software

### Aislamiento entre clinicas

Row Level Security de PostgreSQL, con política por `tenant_id` y RLS forzado.
Una consulta sin contexto de clinica **falla**, no devuelve filas. Ver el
[03](03-MULTITENANCY-Y-ROLES.md).

### Separacion de roles

Recepción agenda y ve datos de identificacion; la historia clinica la ve solo
el profesional tratante. El administrador de la clinica tampoco, salvo que
ademas sea profesional.

### Auditoria de accesos a ficha clinica

**Todo** acceso a información clinica queda registrado: permitido o denegado,
con quien, cuando, desde que IP, por que ruta y con que respuesta.

Detalles que importan:

| Decisión | Por que |
|---|---|
| Los intentos **denegados** también se anotan | son la única forma de detectar que alguien prueba el sistema con el rol equivocado |
| Se anota en una **conexión aparte** | la peticion denegada revierte la transacción; en la misma conexión el registro se perderia |
| El modelo **rechaza modificar y borrar** la bitacora | si se pudiera, dejaria de ser evidencia en el momento que mas importa |
| `paciente_id` **sin clave foranea** | el registro tiene que sobrevivir a que la ficha se borre |
| La ruta de **impresion** también se audita | el papel se va de la institucion |
| `APP_DEBUG` en `false` y `LOG_LEVEL=info` | con `debug`, el cuerpo de una peticion puede quedar en el registro, y el cuerpo puede ser una evolucion |

### Sesión

- Cookie `HttpOnly` y `SameSite=Lax`.
- `Secure` cuando el despliegue es por HTTPS.
- Se **renueva la sesión al entrar** (`invalidate()` + `regenerateToken()`),
  para evitar el fijamiento de sesión (CWE-384).
- `SESSION_EXPIRACION_MINUTOS` corto: una sesión abierta mucho rato es una
  sesión abierta a la ficha clinica.
- Una cuenta desactivada deja de operar en su sesión siguiente.

### Autenticación

- Authorization code **con PKCE** contra Authentik. Nada de flujo implicito.
- El token de identificacion **se verifica aca**: algoritmo, firma (JWKS),
  emisor, audiencia, `azp`, `nonce` de sesión y expiracion. Socialite canjea el
  código pero **no** valida el token, y sin esta verificación la aplicación
  aceptaria como valido un token emitido por otro proveedor.
- El rol se resuelve de los equipos de Authentik **en cada ingreso**.
- Sin equipo de clinica, no hay ingreso.

### Transporte y cabeceras

- Caddy termina TLS y obliga a HTTPS.
- `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`,
  `Content-Security-Policy` sin `frame-ancestors`.
- `Cache-Control: no-store` en toda respuesta: ni una agenda ni una ficha
  clinica pueden quedar en el historial del navegador o en un proxy.
- Registro de acceso de Caddy en JSON, sin cuerpos ni cookies.

### Superficie expuesta

En producción solo Caddy pública puertos. PostgreSQL, Redis, la API y la
interfaz no se alcanzan desde afuera.

### Lo que NO esta hecho

Con esto a propósito, y dicho con todas las letras:

| Falta | Por que importa |
|---|---|
| Doble factor de autenticación | el acceso a una ficha clinica con solo una contrasena robada es un incidente |
| Cifrado a nivel de columna o de disco para el historico clinico | el respaldo es la copia mas expuesta del dato (ver [08](08-RESPALDOS.md)) |
| Política de respaldo probada y restaurada periodicamente | un respaldo no probado no es un respaldo |
| Certificados validados del registro profesional | `registro_profesional` es texto libre, no un dato verificado |
| Catalogo CIE-10 oficial | `codigo_cie10` no se valida |
| Firma electrónica propia | por decisión: la firma es DocuSeal |
| Control de concurrencia optimista en evoluciones | dos pestanas podrian pisarse una evolucion; hoy la última gana |
| Alertas automaticas por volumen de denegados | las variables de umbral existen, el aviso automático no |
| Bitacora firmada criptograficamente o escrita a append-only en almacenamiento externo | hoy la inmutabilidad la impone el modelo, no el almacenamiento |
| Política formal de datos de la clinica | es un documento legal, no una linea de código |

## Que hacer antes de producción

1. Revision juridica de los textos de consentimiento y aviso de privacidad.
2. Designar responsable y cumplir los derechos del titular (acceso, rectificacion,
   supresión).
3. Acuerdos documentados con cada profesional que use el sistema, sobre
   confidencialidad.
4. Activar doble factor en Authentik.
5. Resolver la exposicion de los respaldos (cifrado).
6. Ensayar una restauración de respaldo de verdad, no solo una copia.
7. Prueba de penetracion por terceros, con foco en el aislamiento entre
   clinicas y en el bypass de permisos.
8. Plan de respuesta ante incidentes, escrito y ensayado.
9. Registro de los profesionales contra una fuente oficial.
10. `php artisan clinica:verificar-aislamiento` en cada despliegue, y
    `tests/Feature/Permisos/` en cada entrega.

## Revisiones de la bitacora

La pantalla de administración permite filtrar por resultado. Dos revisiones que
conviene hacer:

- **Mensual:** intentos denegados por persona. Si aparecen muchos de la misma
  persona, hay que conversar con ella o corregir sus permisos.
- **Bajo demanda:** accesos de un paciente concreto, por ejemplo cuando el
  paciente o su familia pregunta quien miro su ficha. La respuesta tiene que
  salir de aca, no de la memoria.

Los umbrales `CLINICA_ALERTA_DENEGADOS_POR_HORA` y
`CLINICA_ALERTA_DENEGADOS_POR_USUARIO` estan en la configuración, pero el
aviso automático **no esta implementado**: hoy la revision es manual.