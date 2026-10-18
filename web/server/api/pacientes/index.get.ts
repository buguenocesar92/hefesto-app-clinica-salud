/**
 * Listado de pacientes (datos de identificacion, no ficha clinica).
 */
export default defineEventHandler(async (evento) => {
    const consulta = getQuery(evento)

    return await llamarApi(evento, '/pacientes', {
        consulta: {
            buscar: typeof consulta.buscar === 'string' ? consulta.buscar : undefined,
            pagina: typeof consulta.pagina === 'string' ? Number(consulta.pagina) : undefined,
            por_pagina:
                typeof consulta.por_pagina === 'string' ? Number(consulta.por_pagina) : undefined,
        },
    })
})