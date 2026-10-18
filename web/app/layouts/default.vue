<script setup lang="ts">
/**
 * Estructura de la aplicacion: cabecera con la clinica y el rol, y contenido.
 *
 * El nombre de la persona y su rol se piden al servidor al cargar. Si la API
 * responde 401, la pagina va al ingreso: una pantalla vacia con el menu es
 * peor que una pantalla de ingreso clara.
 */
const { usuario, cargar, cerrarSesion } = useSesion()

const ruta = useRoute()

const { data, error } = await useAsyncData('sesion', async () => {
    await cargar()
    return usuario.value
})

watchEffect(() => {
    if (error.value) {
        navigateTo('/login')
    }
})

const rutaInicio = computed(() => {
    if (!usuario.value) {
        return '/login'
    }

    return usuario.value.capacidades.puede_administrar ? '/admin/auditoria' : '/agenda'
})

function salir() {
    $fetch('/api/salir', { method: 'POST' }).finally(() => cerrarSesion())
}
</script>

<template>
    <div class="aplicacion">
        <header v-if="usuario" class="cabecera">
            <div class="cabecera__marca">
                <NuxtLink :to="rutaInicio" class="cabecera__nombre">
                    {{ usuario.clinica?.nombre ?? 'ClinicaSalud' }}
                </NuxtLink>
                <span class="cabecera__subtitulo">ClinicaSalud</span>
            </div>

            <nav class="cabecera__menu">
                <NuxtLink to="/agenda" :class="{ activo: ruta.path.startsWith('/agenda') }">Agenda</NuxtLink>
                <NuxtLink to="/pacientes" :class="{ activo: ruta.path.startsWith('/pacientes') }">Pacientes</NuxtLink>
                <NuxtLink
                    v-if="usuario.capacidades.puede_administrar"
                    to="/admin/auditoria"
                    :class="{ activo: ruta.path.startsWith('/admin') }"
                >
                    Administracion
                </NuxtLink>
            </nav>

            <div class="cabecera__persona">
                <span class="cabecera__rol">{{ usuario.rol_etiqueta }}</span>
                <span>{{ usuario.nombre }}</span>
                <button type="button" class="boton boton--chico" @click="salir">Salir</button>
            </div>
        </header>

        <main class="contenido">
            <slot />
        </main>

        <footer v-if="usuario" class="pie">
            <span>
                {{ usuario.clinica?.nombre }} · zona horaria
                {{ usuario.clinica?.zona_horaria ?? 'America/Santiago' }}
            </span>
            <span class="pie__aviso">
                Todo acceso a una ficha clinica queda registrado.
            </span>
        </footer>
    </div>
</template>