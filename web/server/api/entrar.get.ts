/**
 * Redirige a Authentik.
 *
 * La API genera la URL de autorizacion (con PKCE y `nonce`) y se la entrega al
 * navegador para que vaya ahi. El estado se guarda en la sesion de Laravel.
 */
export default defineEventHandler(async (evento) => {
    try {
        const respuesta = await llamarApi<{ mensaje?: string; url?: string }>(evento, '/auth/login', {
            metodo: 'GET',
        })

        return sendRedirect(evento, respuesta.url ?? '/login')
    } catch (error) {
        if (error instanceof ErrorDeApi) {
            throw createError({ statusCode: error.codigo, statusMessage: error.message })
        }

        throw error
    }
})