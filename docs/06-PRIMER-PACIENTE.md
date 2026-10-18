# 06 — Primer paciente

Guia para dejar la clinica funcionando desde cero: alta de profesionales,
primer paciente, primera cita y primera evolucion.

## Orden recomendado

1. Clinica y administrador (paso 5 del [04](04-INSTALACION.md)).
2. Equipos en Authentik (paso 6 del [04](04-INSTALACION.md)).
3. Verificación del aislamiento (paso 7 del [04](04-INSTALACION.md)).
4. Salas, tipos de atención y tarifas.
5. Profesionales y sus cuentas.
6. Primer paciente, su consentimiento y su cita.

No de el paso 4 al 6 sin el 3: el aislamiento se verifica una vez y es la única
red de seguridad que hay debajo de los permisos.

## 4. Salas, tipos de atención y tarifas

Las necesita un administrador de la clinica (`/api/v1/admin/...` o la pantalla
de administración).

**Salas.** "Consulta 1", "Sala de procedimientos". La sala es un recurso
agendable: dos citas no pueden ocupar la misma sala al mismo tiempo.

**Tipos de atención.** Aquí va la duracion, y es lo que hace util la agenda:

| Nombre | Duracion |
|---|---|
| Consulta medica general | 30 min |
| Control de enfermedad cronica | 30 min |
| Procedimiento menor | 45 min |

**Tarifario.** Concepto y valor en pesos, sin decimales. La tarifa esta
desligada del tipo de atención: sirve para el tarifario de la clinica, no para
el calculo de la cita.

## 5. Profesionales y sus cuentas

**Primero la ficha de profesional** (`/api/v1/admin/profesionales`):

```json
{
    "nombre": "Dra. Valentina Gutierrez Rojas",
    "profesion": "Medica general",
    "registro_profesional": "MP-12.345",
    "correo": "dra.gutierrez@clinicasalud.cl"
}
```

**Despues la cuenta** (`/api/v1/admin/usuarios`):

```json
{
    "nombre": "Dra. Valentina Gutierrez Rojas",
    "correo": "dra.gutierrez@clinicasalud.cl",
    "rol": "profesional",
    "profesional_id": "<id de la ficha de profesional>"
}
```

Dos detalles que importan:

1. **La cuenta nace desactivada.** La activacion depende de que esa persona
   pueda autenticarse en Authentik con ese correo. Sin ese paso, un
   administrador con acceso al panel podria dar de alta a cualquiera con
   cualquier correo y empezar a operar en nombre de esa persona.
2. **Un rol `profesional` exige `profesional_id`.** Sin ficha de profesional no
   hay acceso a ninguna ficha clinica: la aplicación no deja crear una cuenta
   que parece clinica y no lo es.

La cuenta que se cree aca **existe en la base**, pero en cada ingreso el rol se
vuelve a leer de Authentik. Si en Authentik le quitan el equipo, en la sesión
siguiente pierde el acceso.

## 6. Primer paciente

### Consentimiento primero

En una clinica, la ficha de identificacion y el consentimiento son la puerta de
entrada. Se registran juntos:

```json
POST /api/v1/pacientes
{
    "rut": "15.555.666-7",
    "nombres": "Maria",
    "apellidos": "Fernandez Lopez",
    "fecha_nacimiento": "1984-03-12",
    "sexo": "F",
    "telefono": "+56 9 8877 6655",
    "correo": "maria.fernandez@example.cl",
    "direccion": "Las Camelias 1148, Conchalaba",
    "cobertura": "Fonasa B"
}
```

```json
POST /api/v1/pacientes/{id}/consentimientos
{
    "tipo": "tratamiento",
    "otorgado": true,
    "fecha": "2026-02-10",
    "documento_referencia_externo": "docuseal:plantilla-consentimiento-0004821"
}
```

`documento_referencia_externo` es la referencia del documento firmado en
DocuSeal. Si el paciente no lo firma todavia, el registro se puede hacer con el
campo en `null` y `otorgado = false`, y se actualiza cuando exista.

**Recepción puede hacer los dos pasos.** El consentimiento es información de
identificacion, no clinica.

### Primera cita

```json
POST /api/v1/citas
{
    "paciente_id": "<id>",
    "profesional_id": "<id>",
    "sala_id": "<id>",
    "tipo_atencion_id": "<id>",
    "inicio": "2026-02-11T10:00:00-03:00"
}
```

Notese lo que **no** se manda: la duracion. La API la saca del tipo de
atención. Si el cliente pudiera mandar el `fin`, la agenda quedaria mal en el
primer choque.

Si el profesional no esta libre, la API responde 422 con un mensaje claro, y
el indice único de la base lo haria fallar igual aunque dos peticiones lleguen
a la vez.

## 7. Primera evolucion

**Solo el profesional tratante** puede. Y lo es por tener la cita: en el
momento en que la cita existe, ese profesional ya es tratante de ese paciente
(segunda forma de la regla, ver el [03](03-MULTITENANCY-Y-ROLES.md)).

```json
POST /api/v1/fichas/{id}/evoluciones
{
    "motivo_consulta": "Control de hipertension",
    "codigo_cie10": "I10",
    "diagnostico": "Hipertension arterial esencial",
    "tratamiento": "Se mantiene el tratamiento habitual.",
    "indicaciones": "Registrar la presion arterial tres veces por semana."
}
```

`codigo_cie10` es texto libre. La aplicación **no** trae el catalogo CIE-10 del
Ministerio de Salud, así que no comprueba que el código exista (pendiente
declarado en el [10](10-PRUEBAS-Y-ESTADO.md)).

## 8. Primera receta

```json
POST /api/v1/fichas/{id}/recetas
{
    "contenido": "1. Losartan 50 mg, un comprimido al dia, en la manana.",
    "documento_referencia_externo": "docuseal:plantilla-receta-0004821"
}
```

La referencia de DocuSeal es **opcional**: si no viene, la receta se emite sin
firma y la interfaz lo dice en pantalla, con una linea que pide no entregarla
al paciente como si estuviera firmada.

Para imprimirla:

```
GET /api/v1/fichas/{id}/recetas/{id_receta}/imprimir
```

Devuelve HTML listo para imprimir, con `Cache-Control: no-store`. **Esa
impresion también queda registrada** en la bitacora: el papel se va de la
institucion.

## 9. Comprobar que la separacion funciona

Antes de seguir, hagalos a propósito:

1. Entre como **recepción** y busque un paciente: lo ve.
2. Entre como **recepción** y abra la ficha clinica de ese paciente: **404**, y
   el intento queda en la bitacora como denegado.
3. Entre como **profesional tratante** y abra la misma ficha: la ve.
4. Entre como **administración** y abra la misma ficha: **404**. Es lo correcto.
5. Entre como **otro profesional** que no atiende a ese paciente: **404**.

Revise la pantalla de administración (bitacora) con los 5 accesos anotados.

Si algo de esto no se cumple, **no siga**: pare y arreglelo. La alternativa es
entregar la aplicación con el permiso roto.