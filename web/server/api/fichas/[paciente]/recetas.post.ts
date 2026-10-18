/**
 * Emitir una receta.
 *
 * `documento_referencia_externo` es la referencia del documento firmado en
 * DocuSeal. Si no viene, la receta queda emitida SIN firma y la interfaz lo
 * avisa en pantalla: entregarla al paciente como si estuviera firmada seria un
 * problema para la clinica. Ver docs/03.
 */
export default defineEventHandler(async (evento) => {
    const paciente = getRouterParam(evento, 'paciente')
    const cuerpo = await readBody(evento)

    try {
        return await llamarApi(evento, `/fichas/${paciente}/recetas`, {
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