<script setup lang="ts">
/**
 * Ficha clinica de un paciente.
 *
 * Esta pantalla solo la ven profesionales tratantes: el servidor responde 404
 * para todos los demas, y la interfaz muestra exactamente eso. No hay ninguna
 * comprobacion de rol en el navegador que valga como defensa.
 *
 * Si se entra aca, cada recarga deja un registro en la bitacora. Es
 * intencional: es el costo de abrir una historia clinica.
 */

interface Evolu {
    id: string
    fecha: string
    motivo_consulta: string
    codigo_cie10: string | null
    diagnostico: string
    tratamiento: string
    indicaciones: string | null
    profesional: { id: string; nombre: string; profesion: string } | null
}

interface Recet {
    id: string
    fecha: string
    contenido: string
    estado: string
    estado_etiqueta: string
    firmada: boolean
    documento_referencia_externo: string | null
    profesional: { id: string; nombre: string } | null
}

interface Historial {
    paciente: {
        id: string
        nombre: string
        rut: string
        fecha_nacimiento: string | null
        edad: number | null
        cobertura: string | null
    }
    consentimientos: { id: string; tipo: string; otorgado: boolean; fecha: string }[]
    equipo_de_cuidado: { id: string; profesional_id: string }[]
    evoluciones: Evolu[]
    recetas: Recet[]
}

const ruta = useRoute()

const { data: ficha, error } = await useAsyncData(`ficha-${ruta.params.id}`, () =>
    $fetch<Historial>(`/api/fichas/${ruta.params.id}`),
)

const evolucion = reactive({
    motivo_consulta: '',
    codigo_cie10: '',
    diagnostico: '',
    tratamiento: '',
    indicaciones: '',
})

const receta = reactive({
    contenido: '',
    documento_referencia_externo: '',
})

const aviso = ref<string | null>(null)

async function registrarEvolucion() {
    aviso.value = null

    try {
        await $fetch(`/api/fichas/${ruta.params.id}/evoluciones`, {
            method: 'POST',
            body: evolucion,
        })

        aviso.value = 'Evolucion registrada.'
        Object.assign(evolucion, {
            motivo_consulta: '',
            codigo_cie10: '',
            diagnostico: '',
            tratamiento: '',
            indicaciones: '',
        })

        await refreshNuxtData(`ficha-${ruta.params.id}`)
    } catch (fallo) {
        aviso.value = (fallo as { statusMessage?: string }).statusMessage ?? 'No se pudo registrar.'
    }
}

async function emitirReceta() {
    aviso.value = null

    try {
        await $fetch(`/api/fichas/${ruta.params.id}/recetas`, {
            method: 'POST',
            body: {
                contenido: receta.contenido,
                documento_referencia_externo: receta.documento_referencia_externo || null,
            },
        })

        aviso.value = 'Receta emitida.'
        receta.contenido = ''
        receta.documento_referencia_externo = ''

        await refreshNuxtData(`ficha-${ruta.params.id}`)
    } catch (fallo) {
        aviso.value = (fallo as { statusMessage?: string }).statusMessage ?? 'No se pudo emitir.'
    }
}

function fechaCorta(iso: string): string {
    return new Date(iso).toLocaleDateString('es-CL')
}
</script>

<template>
    <section>
        <div v-if="error" class="error">
            No se encontro la ficha. Si cree que deberia verla, hable con el administrador de la
            clinica.
        </div>

        <template v-else-if="ficha">
            <h1>{{ ficha.paciente.nombre }}</h1>
            <p class="sin-permiso">
                {{ ficha.paciente.rut }} · {{ ficha.paciente.edad ?? '—' }} anos ·
                {{ ficha.paciente.cobertura ?? 'sin cobertura' }}
            </p>

            <div v-if="aviso" class="aviso">{{ aviso }}</div>

            <div class="aviso">
                <strong>Registro de acceso.</strong> La apertura de esta ficha quedo anotada con su
                nombre, la fecha y la hora. Lo mismo pasa al imprimir una receta.
            </div>

            <div class="panel">
                <h2>Evoluciones</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th>Diagnostico</th>
                            <th>Tratamiento</th>
                            <th>Profesional</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in ficha.evoluciones" :key="item.id">
                            <td>{{ fechaCorta(item.fecha) }}</td>
                            <td>{{ item.motivo_consulta }}</td>
                            <td>
                                {{ item.diagnostico }}
                                <span v-if="item.codigo_cie10" class="sin-permiso">
                                    ({{ item.codigo_cie10 }})
                                </span>
                            </td>
                            <td>{{ item.tratamiento }}</td>
                            <td>{{ item.profesional?.nombre ?? '—' }}</td>
                        </tr>
                        <tr v-if="ficha.evoluciones.length === 0">
                            <td colspan="5">Este paciente no tiene evoluciones registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <h2>Registrar evolucion</h2>

                <form @submit.prevent="registrarEvolucion">
                    <div class="campo">
                        <label for="motivo">Motivo de la consulta</label>
                        <input id="motivo" v-model="evolucion.motivo_consulta" required />
                    </div>
                    <div class="campo" style="max-width: 200px;">
                        <label for="cie10">Codigo CIE-10 (opcional)</label>
                        <input id="cie10" v-model="evolucion.codigo_cie10" />
                    </div>
                    <div class="campo">
                        <label for="diagnostico">Diagnostico</label>
                        <textarea id="diagnostico" v-model="evolucion.diagnostico" rows="2" required />
                    </div>
                    <div class="campo">
                        <label for="tratamiento">Tratamiento</label>
                        <textarea id="tratamiento" v-model="evolucion.tratamiento" rows="3" required />
                    </div>
                    <div class="campo">
                        <label for="indicaciones">Indicaciones para el paciente</label>
                        <textarea id="indicaciones" v-model="evolucion.indicaciones" rows="2" />
                    </div>
                    <button type="submit" class="boton">Registrar</button>
                </form>
            </div>

            <div class="panel">
                <h2>Recetas</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Firma</th>
                            <th>Profesional</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in ficha.recetas" :key="item.id">
                            <td>{{ fechaCorta(item.fecha) }}</td>
                            <td>{{ item.estado_etiqueta }}</td>
                            <td>
                                <span v-if="item.firmada">Firmada en DocuSeal</span>
                                <span v-else class="sin-permiso">Sin firma digital</span>
                            </td>
                            <td>{{ item.profesional?.nombre ?? '—' }}</td>
                        </tr>
                        <tr v-if="ficha.recetas.length === 0">
                            <td colspan="4">No hay recetas registradas.</td>
                        </tr>
                    </tbody>
                </table>

                <h2>Emitir receta</h2>

                <form @submit.prevent="emitirReceta">
                    <div class="campo">
                        <label for="contenido">Contenido de la receta</label>
                        <textarea id="contenido" v-model="receta.contenido" rows="4" required />
                    </div>
                    <div class="campo" style="max-width: 420px;">
                        <label for="referencia">Referencia del documento firmado en DocuSeal</label>
                        <input
                            id="referencia"
                            v-model="receta.documento_referencia_externo"
                            placeholder="docuseal:plantilla-receta-0004821"
                        />
                    </div>
                    <p class="sin-permiso">
                        ClinicaSalud arma el texto y el formato imprimible, pero no firma. La firma la
                        hace DocuSeal; si la referencia queda vacia, la receta se emite sin firma.
                    </p>
                    <button type="submit" class="boton">Emitir</button>
                </form>
            </div>
        </template>
    </section>
</template>