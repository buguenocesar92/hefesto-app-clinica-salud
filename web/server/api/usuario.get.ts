/**
 * Quien soy: la API decide, la interfaz solo esconde botones.
 *
 * Este endpoint no inventa permisos. Devuelve lo que la API respondio en
 * `/auth/usuario`, que a su vez sale de `Usuario::capacidades()`.
 *
 * Si `puede_leer_fichas` es falso, la interfaz no muestra el boton de ficha
 * clinica. Eso NO es una defensa: si alguien llama directo a la API, es el
 * servidor el que responde 404. Ver docs/09.
 */
export default defineEventHandler(async (evento) => {
    return await llamarApi(evento, '/auth/usuario')
})