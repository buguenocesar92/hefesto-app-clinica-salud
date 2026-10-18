/**
 * Bitacora de accesos a ficha clinica (solo administracion de la clinica).
 *
 * Reenvia los filtros tal cual. Si la API responde 403, se devuelve 403: no
 * se cae a una lista vacia, porque una lista vacia parece "no hay accesos" y
 * es un mensaje falso.
 */
export default defineEventHandler(async (evento) => {
    const consulta = getQuery(evento)

    try {
        return await llamarApi(evento, '/admin/auditoria', {
            consulta: {
                paciente_id:
                    typeof consulta.paciente_id === 'string' ? consulta.paciente_id : undefined,
                usuario_id:
                    typeof consulta.usuario_id === 'string' ? consulta.usuario_id : undefined,
                resultado: typeof consulta.resultado === 'string' ? consulta.resultado : undefined,
                pagina: typeof consulta.pagina === 'string' ? Number(consulta.pagina) : undefined,
            },
        })
    } catch (error) {
        if (error instanceof ErrorDeApi) {
            throw createError({ statusCode: error.codigo, statusMessage: error.message })
        }

        throw error
    }
})