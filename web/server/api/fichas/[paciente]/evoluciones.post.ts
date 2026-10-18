/**
 * Registrar una evolucion.
 *
 * La respuesta 201 llega con el registro recien creado. Un 404 significa que
 * la API no autorizo el acceso (o que el paciente no existe para quien
 * pregunta): en los dos casos no se muestra mas detalle.
 */
export default defineEventHandler(async (evento) => {
    const paciente = getRouterParam(evento, 'paciente')
    const cuerpo = await readBody(evento)

    try {
        return await llamarApi(evento, `/fichas/${paciente}/evoluciones`, {
            metodo: 'POST',
            cuerpo,
        })
    } catch (error) {
        if (error instanceof ErrorDeApi) {
            throw createError({ statusCode: error.codigo, statusMessage: error.message })
        }

        throw error
    }
})