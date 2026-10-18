/**
 * Composable de sesion.
 *
 * Solo lectura y navegacion: los permisos vienen de la API. Este archivo
 * esconde botones, no protege datos. Ver docs/09.
 */

export interface Capacidades {
    puede_agendar: boolean
    puede_administrar: boolean
    puede_leer_fichas: boolean
}

export interface UsuarioSesion {
    id: string
    nombre: string
    correo: string
    rol: 'recepcion' | 'profesional' | 'administrador'
    rol_etiqueta: string
    es_profesional: boolean
    clinica: {
        id: string | null
        nombre: string | null
        zona_horaria: string | null
    } | null
    capacidades: Capacidades
}

export function useSesion() {
    const usuario = useState<UsuarioSesion | null>('usuario', () => null)

    async function cargar(): Promise<UsuarioSesion | null> {
        try {
            usuario.value = await $fetch<UsuarioSesion>('/api/usuario')
        } catch {
            usuario.value = null
        }

        return usuario.value
    }

    async function cerrarSesion(): Promise<void> {
        usuario.value = null

        await navigateTo('/login')
    }

    const puedeLeerFichas = computed(() => usuario.value?.capacidades?.puede_leer_fichas === true)
    const puedeAdministrar = computed(() => usuario.value?.capacidades?.puede_administrar === true)

    return { usuario, cargar, cerrarSesion, puedeLeerFichas, puedeAdministrar }
}