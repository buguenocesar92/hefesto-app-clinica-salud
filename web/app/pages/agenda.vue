<script setup lang="ts">
/**
 * Agenda del dia.
 *
 * Agendar es una capacidad de mostrador: los tres roles entran y ven lo mismo.
 * Lo que NO se ve aca es nada de la historia clinica: nombre, hora, profesional,
 * sala y estado.
 */

interface Cita {
    id: string
    inicio: string
    fin: string
    duracion_minutos: number
    estado: string
    estado_etiqueta: string
    motivo_consulta: string | null
    paciente: { id: string; nombre: string; telefono: string | null } | null
    profesional: { id: string; nombre: string } | null
    sala: { id: string; nombre: string } | null
    tipo_atencion: { id: string; nombre: string } | null
}

const fecha = ref(new Date().toISOString().slice(0, 10))
const guardando = ref(false)
const mensaje = ref<string | null>(null)
const error = ref<string | null>(null)

const { data: agenda, refresh } = await useAsyncData(
    'agenda',
    () => $fetch<{ data: Cita[] }>('/api/agenda', { query: { desde: fecha.value } }),
    { watch: [fecha] },
)

const { data: profesionales } = await useAsyncData('profesionales', () =>
    $fetch<{ datos: { id: string; nombre: string }[] }>('/api/recursos/profesionales'),
)

const { data: salas } = await useAsyncData('salas', () =>
    $fetch<{ datos: { id: string; nombre: string }[] }>('/api/recursos/salas'),
)

const { data: tipos } = await useAsyncData('tipos', () =>
    $fetch<{ datos: { id: string; nombre: string; duracion_minutos: number }[] }>(
        '/api/recursos/tipos-atencion',
    ),
)

const nueva = reactive({
    paciente_id: '',
    profesional_id: '',
    sala_id: '',
    tipo_atencion_id: '',
    inicio: '',
})

async function agendar() {
    guardando.value = true
    mensaje.value = null
    error.value = null

    try {
        await $fetch('/api/citas', {
            method: 'POST',
            body: {
                paciente_id: nueva.paciente_id,
                profesional_id: nueva.profesional_id,
                sala_id: nueva.sala_id || null,
                tipo_atencion_id: nueva.tipo_atencion_id || null,
                inicio: nueva.inicio,
            },
        })

        mensaje.value = 'Cita agendada.'
        nueva.inicio = ''
        await refresh()
    } catch (fallo) {
        error.value = (fallo as { statusMessage?: string }).statusMessage ?? 'No se pudo agendar.'
    } finally {
        guardando.value = false
    }
}

function hora(iso: string): string {
    return new Date(iso).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <section>
        <h1>Agenda medica</h1>

        <div v-if="mensaje" class="aviso">{{ mensaje }}</div>
        <div v-if="error" class="error">{{ error }}</div>

        <div class="panel">
            <div class="campo" style="max-width: 220px;">
                <label for="fecha">Dia</label>
                <input id="fecha" v-model="fecha" type="date" />
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Profesional</th>
                        <th>Sala</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="cita in agenda?.data ?? []" :key="cita.id">
                        <td>{{ hora(cita.inicio) }}–{{ hora(cita.fin) }}</td>
                        <td>{{ cita.paciente?.nombre }}</td>
                        <td>{{ cita.profesional?.nombre }}</td>
                        <td>{{ cita.sala?.nombre ?? '—' }}</td>
                        <td>{{ cita.motivo_consulta ?? '—' }}</td>
                        <td>{{ cita.estado_etiqueta }}</td>
                    </tr>
                    <tr v-if="(agenda?.data ?? []).length === 0">
                        <td colspan="6">No hay citas para este dia.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Agendar</h2>

            <form @submit.prevent="agendar">
                <div class="campo">
                    <label for="paciente">RUT o nombre del paciente</label>
                    <input id="paciente" v-model="nueva.paciente_id" required placeholder="Identificador del paciente" />
                </div>

                <div class="campo">
                    <label for="profesional">Profesional</label>
                    <select id="profesional" v-model="nueva.profesional_id" required>
                        <option value="">Elija un profesional</option>
                        <option v-for="p in profesionales?.datos ?? []" :key="p.id" :value="p.id">
                            {{ p.nombre }}
                        </option>
                    </select>
                </div>

                <div class="campo">
                    <label for="tipo">Tipo de atencion</label>
                    <select id="tipo" v-model="nueva.tipo_atencion_id">
                        <option value="">Sin tipo (30 minutos)</option>
                        <option v-for="t in tipos?.datos ?? []" :key="t.id" :value="t.id">
                            {{ t.nombre }} ({{ t.duracion_minutos }} min)
                        </option>
                    </select>
                </div>

                <div class="campo">
                    <label for="sala">Sala</label>
                    <select id="sala" v-model="nueva.sala_id">
                        <option value="">Sin sala</option>
                        <option v-for="s in salas?.datos ?? []" :key="s.id" :value="s.id">
                            {{ s.nombre }}
                        </option>
                    </select>
                </div>

                <div class="campo">
                    <label for="inicio">Inicio</label>
                    <input id="inicio" v-model="nueva.inicio" type="datetime-local" required />
                </div>

                <button type="submit" class="boton" :disabled="guardando">
                    {{ guardando ? 'Agendando…' : 'Agendar' }}
                </button>
            </form>
        </div>
    </section>
</template>