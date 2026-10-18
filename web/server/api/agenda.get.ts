/**
 * Agenda del dia (o del rango pedido).
 */
export default defineEventHandler(async (evento) => {
    const consulta = getQuery(evento)

    return await llamarApi(evento, '/agenda', {
        consulta: {
            desde: typeof consulta.desde === 'string' ? consulta.desde : undefined,
            hasta: typeof consulta.hasta === 'string' ? consulta.hasta : undefined,
            profesional_id:
                typeof consulta.profesional_id === 'string' ? consulta.profesional_id : undefined,
            sala_id: typeof consulta.sala_id === 'string' ? consulta.sala_id : undefined,
        },
    })
})