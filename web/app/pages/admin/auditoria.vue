<script setup lang="ts">
/**
 * Bitacora de accesos a ficha clinica.
 *
 * Solo el administrador de la clinica. La pantalla es de lectura: sirve para
 * responder una pregunta ("¿quien vio la ficha de este paciente?") y para
 * detectar intentos denegados repetidos.
 *
 * Ver los intentos DENEGADOS es tan importante como ver los permitidos: son
 * la senal de que alguien esta probando el sistema con el rol equivocado, o de
 * que hay un problema de configuracion que conviene corregir antes de que
 * alguien mas lo note.
 */

interface Registro {
    id: string
    usuario_id: string | null
    paciente_id: string | null
    accion: string
    resultado: 'permitido' | 'denegado'
    metodo_http: string
    ruta: string
    direccion_ip: string | null
    codigo_respuesta: number | null
    registrado_en: string
    usuario: { id: string; nombre: string; correo: string } | null
}

const resultado = ref<string>('')
const pagina = ref(1)

const { data, pending } = await useAsyncData(
    'auditoria',
    () =>
        $fetch<{ datos: Registro[]; paginacion: { total: number; paginas: number } }>('/api/admin/auditoria', {
            query: { resultado: resultado.value || undefined, pagina: pagina.value },
        }),
    { watch: [resultado, pagina] },
)

function momento(iso: string): string {
    return new Date(iso).toLocaleString('es-CL')
}
</script>

<template>
    <section>
        <h1>Bitacora de accesos a ficha clinica</h1>

        <div class="aviso">
            Esta lista muestra quien abrio cada ficha y cuando. El filtro de <strong>denegados</strong>
            sirve para detectar intentos fuera de rol: si aparecen muchos de la misma persona,
            conviene revisar sus permisos y conversarla.
        </div>

        <div class="panel">
            <div class="campo" style="max-width: 240px;">
                <label for="resultado">Resultado</label>
                <select id="resultado" v-model="resultado">
                    <option value="">Todos</option>
                    <option value="permitido">Permitidos</option>
                    <option value="denegado">Denegados</option>
                </select>
            </div>

            <p v-if="pending">Cargando…</p>

            <table v-else>
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Persona</th>
                        <th>Accion</th>
                        <th>Resultado</th>
                        <th>Ruta</th>
                        <th>Desde</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="registro in data?.datos ?? []" :key="registro.id">
                        <td>{{ momento(registro.registrado_en) }}</td>
                        <td>{{ registro.usuario?.nombre ?? 'Sesion sin usuario' }}</td>
                        <td>{{ registro.accion }}</td>
                        <td>{{ registro.resultado }}</td>
                        <td>{{ registro.metodo_http }} {{ registro.ruta }}</td>
                        <td>{{ registro.direccion_ip ?? '—' }}</td>
                    </tr>
                    <tr v-if="(data?.datos ?? []).length === 0">
                        <td colspan="6">No hay registros para este filtro.</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="data">
                {{ data.paginacion.total }} registros · pagina {{ pagina }} de
                {{ data.paginacion.paginas }}
            </p>
        </div>
    </section>
</template>