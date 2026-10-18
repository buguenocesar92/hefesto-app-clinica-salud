/**
 * Cierre de sesion en la API.
 *
 * La cookie de sesion se borra acá; el cierre de la sesion de Authentik lo
 * hace la API (ver AuthController::logout), que devuelve la URL del
 * proveedor.
 */
export default defineEventHandler(async (evento) => {
    try {
        return await llamarApi(evento, '/auth/logout', { metodo: 'POST' })
    } catch (error) {
        // Cerrar la sesion no debe fallar nunca en pantalla: si la API ya no
        // reconoce la sesion, para el usuario el resultado es el mismo.
        return { mensaje: 'Sesion cerrada.' }
    }
})