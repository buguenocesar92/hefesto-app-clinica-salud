/**
 * Agendar una cita.
 *
 * La duracion no se manda: la API la saca del tipo de atencion. Ver
 * AgendarCitaRequest en el backend.
 */
export default defineEventHandler(async (evento) => {
    const cuerpo = await readBody(evento)

    try {
        return await llamarApi(evento, '/citas', {
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