<script setup lang="ts">
/**
 * Ingreso.
 *
 * El formulario no pide usuario ni contrasena: la identidad la emite Authentik.
 * El boton lleva a la API, que arma la URL de autorizacion con PKCE.
 */
definePageMeta({
    layout: 'default',
})

const cargando = ref(false)
const mensaje = ref<string | null>(null)

async function entrar() {
    cargando.value = true
    mensaje.value = null

    try {
        await navigateTo('/api/entrar', { external: true })
    } catch (error) {
        mensaje.value = 'No se pudo iniciar el ingreso. Intente otra vez.'
        cargando.value = false
    }
}
</script>

<template>
    <section class="panel">
        <h1>Ingresar a ClinicaSalud</h1>

        <div v-if="mensaje" class="error">{{ mensaje }}</div>

        <p>
            El ingreso se hace con la cuenta de la institucion, administrada por Authentik. No hay
            contrasenas en esta aplicacion.
        </p>

        <button type="button" class="boton" :disabled="cargando" @click="entrar">
            {{ cargando ? 'Redirigiendo…' : 'Ingresar con mi cuenta' }}
        </button>

        <div class="aviso" style="margin-top: 24px;">
            <strong>Aviso.</strong> Esta aplicacion maneja datos personales sensibles de salud.
            No la use hasta que la institucion haya completado las validaciones, auditorias y
            controles que exige la normativa. Ver <code>docs/07</code> del repositorio.
        </div>
    </section>
</template>