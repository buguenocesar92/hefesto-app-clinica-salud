/**
 * Recursos de apoyo para agendar: profesionales, salas y tipos de atencion.
 *
 * Los tres los necesita la agenda para todos los roles, no solo la
 * administracion. Por eso viven bajo `admin/` en la API pero se piden desde
 * una ruta propia aca: la interfaz no debe conocer la organizacion interna de
 * las rutas del backend.
 */
export default defineEventHandler(async (evento) => {
    const recurso = getRouterParam(evento, 'recurso')

    const permitidos: Record<string, string> = {
        profesionales: '/admin/profesionales',
        salas: '/admin/salas',
        'tipos-atencion': '/admin/tipos-atencion',
    }

    const ruta = permitidos[recurso ?? '']

    if (ruta === undefined) {
        throw createError({ statusCode: 404, statusMessage: 'Recurso desconocido.' })
    }

    try {
        return await llamarApi(evento, ruta)
    } catch (error) {
        if (error instanceof ErrorDeApi) {
            throw createError({ statusCode: error.codigo, statusMessage: error.message })
        }

        throw error
    }
})