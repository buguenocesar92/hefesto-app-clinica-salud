/**
 * Ficha clinica de un paciente.
 *
 * Esta ruta responde 404 para quien no sea profesional tratante, y eso se
 * traduce en un 404 tambien aca: la interfaz muestra "no encontrado", que es
 * exactamente lo que la API quiso decir.
 */
export default defineEventHandler(async (evento) => {
    const paciente = getRouterParam(evento, 'paciente')

    try {
        return await llamarApi(evento, `/fichas/${paciente}`)
    } catch (error) {
        if (error instanceof ErrorDeApi) {
            throw createError({ statusCode: error.codigo, statusMessage: error.message })
        }

        throw error
    }
})