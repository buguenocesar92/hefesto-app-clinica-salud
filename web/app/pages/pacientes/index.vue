<script setup lang="ts">
/**
 * Pacientes: ficha de IDENTIFICACION.
 *
 * Visible para los tres roles. Aca no hay ni una evolucion ni una receta, y el
 * boton de ficha clinica solo aparece si la API dice que esa persona puede ver
 * fichas.
 */

interface Paciente {
    id: string
    rut: string
    nombres: string
    apellidos: string
    nombre_completo: string
    fecha_nacimiento: string | null
    edad: number | null
    sexo: string | null
    telefono: string | null
    correo: string | null
    cobertura: string | null
    activo: boolean
}

const busqueda = ref('')

const { data, pending } = await useAsyncData(
    'pacientes',
    () => $fetch<{ data: Paciente[] }>('/api/pacientes', { query: { buscar: busqueda.value } }),
    { watch: [busqueda] },
)

const { puedeLeerFichas } = useSesion()
</script>

<template>
    <section>
        <h1>Pacientes</h1>

        <p class="sin-permiso">
            Datos de identificacion y cobertura. La historia clinica se abre aparte y solo para el
            profesional tratante.
        </p>

        <div class="panel">
            <div class="campo" style="max-width: 340px;">
                <label for="buscar">Buscar por nombre o RUT</label>
                <input id="buscar" v-model="busqueda" type="search" placeholder="Fernandez o 15.555.666" />
            </div>

            <p v-if="pending">Buscando…</p>

            <table v-else>
                <thead>
                    <tr>
                        <th>RUT</th>
                        <th>Nombre</th>
                        <th>Edad</th>
                        <th>Telefono</th>
                        <th>Cobertura</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="paciente in data?.data ?? []" :key="paciente.id">
                        <td>{{ paciente.rut }}</td>
                        <td>{{ paciente.nombre_completo }}</td>
                        <td>{{ paciente.edad ?? '—' }}</td>
                        <td>{{ paciente.telefono ?? '—' }}</td>
                        <td>{{ paciente.cobertura ?? '—' }}</td>
                        <td>
                            <NuxtLink
                                v-if="puedeLeerFichas"
                                class="boton boton--chico boton--suave"
                                :to="`/fichas/${paciente.id}`"
                            >
                                Ficha
                            </NuxtLink>
                            <span v-else class="sin-permiso">—</span>
                        </td>
                    </tr>
                    <tr v-if="(data?.data ?? []).length === 0">
                        <td colspan="6">No hay pacientes que coincidan.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>