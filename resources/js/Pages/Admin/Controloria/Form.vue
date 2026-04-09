<template>
    <AppLayout title="Nueva Contraloría">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.controloria')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.controloria')" class="text-gray-400 hover:text-blue-600 text-xs">
                            Contraloría
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">Nueva</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Nueva Contraloría</h1>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="space-y-4">

                        <!-- Paciente (autocomplete) -->
                        <div>
                            <label class="block text-sm font-medium mb-1">Paciente *</label>
                            <div class="relative">
                                <InputText
                                    v-model="patientSearch"
                                    class="w-full"
                                    :class="{ 'p-invalid': form.errors.patient_id }"
                                    placeholder="Buscar por nombre o RUT..."
                                    autocomplete="off"
                                    @input="buscarPaciente"
                                    @blur="cerrarSugerencias" />
                                <!-- Sugerencias -->
                                <div v-if="sugerencias.length"
                                    class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                                    <button v-for="p in sugerencias" :key="p.id" type="button"
                                        @mousedown.prevent="seleccionarPaciente(p)"
                                        class="w-full text-left px-4 py-2.5 hover:bg-blue-50 transition-colors border-b border-gray-50 last:border-0">
                                        <span class="font-medium text-gray-800 text-sm">{{ p.name }}</span>
                                        <span class="text-xs text-gray-400 ml-2">{{ p.rut }}</span>
                                    </button>
                                </div>
                            </div>
                            <!-- Paciente seleccionado -->
                            <div v-if="pacienteSeleccionado"
                                class="mt-2 flex items-center gap-2 px-3 py-2 bg-blue-50 rounded-lg border border-blue-200">
                                <i class="pi pi-user text-blue-500 text-sm" />
                                <span class="text-sm font-medium text-blue-800">{{ pacienteSeleccionado.name }}</span>
                                <span class="text-xs text-blue-500">{{ pacienteSeleccionado.rut }}</span>
                                <button type="button" @click="limpiarPaciente"
                                    class="ml-auto text-blue-300 hover:text-red-400 transition-colors">
                                    <i class="pi pi-times text-xs" />
                                </button>
                            </div>
                            <small class="text-red-500">{{ form.errors.patient_id }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Clínica *</label>
                            <Select v-model="form.clinic_id" :options="clinicas"
                                optionLabel="label" optionValue="value"
                                placeholder="Seleccionar clínica" class="w-full"
                                :class="{ 'p-invalid': form.errors.clinic_id }" />
                            <small class="text-red-500">{{ form.errors.clinic_id }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Contralor *</label>
                            <Select v-model="form.staff_id" :options="contralores"
                                optionLabel="label" optionValue="value"
                                placeholder="Seleccionar contralor" class="w-full"
                                :class="{ 'p-invalid': form.errors.staff_id }" />
                            <small class="text-red-500">{{ form.errors.staff_id }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Diagnóstico *</label>
                            <Textarea v-model="form.diagnostico" rows="3" class="w-full"
                                placeholder="Diagnóstico o motivo de la contraloría..."
                                :class="{ 'p-invalid': form.errors.diagnostico }" />
                            <small class="text-red-500">{{ form.errors.diagnostico }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Observaciones</label>
                            <Textarea v-model="form.observaciones" rows="2" class="w-full"
                                placeholder="Observaciones adicionales..." />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Archivos adjuntos</label>
                            <input type="file" multiple ref="fileInput"
                                class="block text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
                            <p class="text-xs text-gray-400 mt-1">Puede adjuntar múltiples archivos (máx. 50 MB c/u).</p>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.controloria')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button label="Crear Contraloría" icon="pi pi-save" type="submit"
                            :loading="form.processing"
                            style="background-color:#3452ff;border-color:#3452ff;" />
                    </div>
                </form>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';

const props = defineProps({ account: Object, contralores: Array, clinicas: Array });

const fileInput        = ref(null);
const patientSearch    = ref('');
const sugerencias      = ref([]);
const pacienteSeleccionado = ref(null);
let   searchTimer      = null;

const form = useForm({
    patient_id:    '',
    clinic_id:     null,
    staff_id:      null,
    diagnostico:   '',
    observaciones: '',
});

function buscarPaciente() {
    const q = patientSearch.value.trim();
    if (!pacienteSeleccionado.value) form.patient_id = '';

    clearTimeout(searchTimer);
    if (q.length < 2) { sugerencias.value = []; return; }

    searchTimer = setTimeout(async () => {
        try {
            const res = await fetch(route('ordenes.patients') + '?search=' + encodeURIComponent(q));
            const data = await res.json();
            sugerencias.value = data.slice(0, 8);
        } catch { sugerencias.value = []; }
    }, 300);
}

function seleccionarPaciente(p) {
    pacienteSeleccionado.value = p;
    form.patient_id = p.id;
    patientSearch.value = p.name;
    sugerencias.value = [];
}

function limpiarPaciente() {
    pacienteSeleccionado.value = null;
    form.patient_id = '';
    patientSearch.value = '';
    sugerencias.value = [];
}

function cerrarSugerencias() {
    setTimeout(() => { sugerencias.value = []; }, 150);
}

function submit() {
    const data = new FormData();
    data.append('patient_id',    form.patient_id);
    data.append('clinic_id',     form.clinic_id ?? '');
    data.append('staff_id',      form.staff_id ?? '');
    data.append('diagnostico',   form.diagnostico);
    data.append('observaciones', form.observaciones);

    const files = fileInput.value?.files ?? [];
    for (const f of files) data.append('archivos[]', f);

    form.transform(() => data).post(route('admin.controloria.store'));
}
</script>
