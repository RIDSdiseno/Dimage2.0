<template>
    <AppLayout title="Nueva Orden">
        <div class="p-6 max-w-4xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('ordenes.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <h1 class="text-2xl font-bold text-gray-800">Nueva Orden Radiográfica</h1>
            </div>

            <form @submit.prevent="submit">
                <div class="space-y-5">

                    <!-- Sección 1: Datos básicos -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-info-circle mr-2" />Datos de la Orden
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <!-- Clínica -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Clínica *</label>
                                <Select
                                    v-model="form.clinic_id"
                                    :options="clinics"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Selecciona clínica"
                                    class="w-full"
                                    :class="{'p-invalid': form.errors.clinic_id}"
                                    @change="onClinicChange"
                                />
                                <small class="text-red-500">{{ form.errors.clinic_id }}</small>
                            </div>

                            <!-- Odontólogo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Odontólogo *</label>
                                <Select
                                    v-model="form.odontologo_id"
                                    :options="odontologos"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Selecciona odontólogo"
                                    class="w-full"
                                    :loading="loadingOdontologos"
                                    :disabled="!form.clinic_id"
                                    :class="{'p-invalid': form.errors.odontologo_id}"
                                />
                                <small class="text-red-500">{{ form.errors.odontologo_id }}</small>
                            </div>

                            <!-- Paciente -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Paciente *</label>
                                <AutoComplete
                                    v-model="patientSearch"
                                    :suggestions="patientSuggestions"
                                    optionLabel="name"
                                    :placeholder="`Buscar por nombre o ${terms.id_label}...`"
                                    class="w-full"
                                    :class="{'p-invalid': form.errors.patient_id}"
                                    :disabled="!form.clinic_id"
                                    @complete="searchPatients"
                                    @option-select="onPatientSelect"
                                >
                                    <template #option="{ option }">
                                        <div>
                                            <span class="font-medium">{{ option.name }}</span>
                                            <span class="text-gray-400 text-xs ml-2">{{ terms.id_label }}: {{ option.rut }}</span>
                                        </div>
                                    </template>
                                </AutoComplete>
                                <small class="text-red-500">{{ form.errors.patient_id }}</small>
                            </div>

                            <!-- Prioridad -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad *</label>
                                <Select
                                    v-model="form.prioridad"
                                    :options="['1 día', '2 días', '3 días']"
                                    class="w-full"
                                    :class="{'p-invalid': form.errors.prioridad}"
                                />
                            </div>

                            <!-- Radiólogo (solo si tiene permiso) -->
                            <div v-if="canSelectRadiologo">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Radiólogo</label>
                                <Select
                                    v-model="form.radiologo_id"
                                    :options="radiologos"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Selecciona radiólogo"
                                    class="w-full"
                                    :loading="loadingRadiologos"
                                    :disabled="!form.clinic_id"
                                    showClear
                                />
                            </div>

                        </div>
                    </div>

                    <!-- Sección 2: Diagnóstico -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-file-edit mr-2" />Diagnóstico Clínico
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico *</label>
                                <Textarea
                                    v-model="form.diagnostico"
                                    rows="3"
                                    class="w-full"
                                    :class="{'p-invalid': form.errors.diagnostico}"
                                    placeholder="Describe el diagnóstico clínico..."
                                />
                                <small class="text-red-500">{{ form.errors.diagnostico }}</small>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                                <Textarea v-model="form.observaciones" rows="2" class="w-full" placeholder="Observaciones adicionales..." />
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Tipos de examen -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                            <h2 class="text-sm font-semibold uppercase tracking-wide" style="color:#3452ff">
                                <i class="pi pi-list mr-2" />Tipos de Examen *
                            </h2>
                        </div>
                        <small class="text-red-500 block px-5 pt-2" v-if="form.errors.examenes">{{ form.errors.examenes }}</small>

                        <!-- Tab bar -->
                        <div class="flex border-b border-gray-200 bg-gray-50">
                            <button type="button" @click="activeTab = 'intraorales'"
                                class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors focus:outline-none"
                                :class="activeTab === 'intraorales'
                                    ? 'border-blue-600 text-white bg-blue-600'
                                    : 'border-transparent text-gray-600 hover:text-gray-800 bg-gray-50'">
                                Radiografías Intraorales
                            </button>
                            <button type="button" @click="activeTab = 'extraorales'"
                                class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors focus:outline-none"
                                :class="activeTab === 'extraorales'
                                    ? 'border-blue-600 text-white bg-blue-600'
                                    : 'border-transparent text-gray-600 hover:text-gray-800 bg-gray-50'">
                                Radiografías Extraorales
                            </button>
                        </div>

                        <!-- Tab content -->
                        <div class="p-5">
                            <div v-if="activeTab === 'intraorales'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in intraoralesCols" :key="col.group_id">
                                    <ExamCol :col="col" :selected="form.examenes" :examFiles="examFiles"
                                        :examLabel="examLabel" :stripSuffix="stripGroupSuffix"
                                        @toggle="toggleExam" @files="onFilesSelect" @piezas="onPiezasSelect" />
                                </div>
                            </div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in extraoralesCols" :key="col.group_id">
                                    <ExamCol :col="col" :selected="form.examenes" :examFiles="examFiles"
                                        :examLabel="examLabel" :stripSuffix="(l) => l"
                                        @toggle="toggleExam" @files="onFilesSelect" @piezas="onPiezasSelect" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end gap-3">
                        <Link :href="route('ordenes.index')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            label="Guardar Borrador"
                            icon="pi pi-save"
                            severity="secondary"
                            type="button"
                            :loading="form.processing && form.action === 'guardar'"
                            @click="submitAction('guardar')"
                        />
                        <Button
                            label="Enviar a Radiólogo"
                            icon="pi pi-send"
                            type="button"
                            :loading="form.processing && form.action === 'enviar'"
                            :disabled="canSelectRadiologo && !form.radiologo_id"
                            @click="submitAction('enviar')"
                            style="background-color: #3452ff; border-color: #3452ff;"
                        />
                    </div>

                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTerms } from '@/composables/useTerms.js';

const { terms, examLabel } = useTerms();
import Button from 'primevue/button';
import Select from 'primevue/select';
import AutoComplete from 'primevue/autocomplete';
import Textarea from 'primevue/textarea';
import ExamCol from '@/Components/ExamCol.vue';

const props = defineProps({
    clinics:             Array,
    examTypes:           Array,
    canSelectRadiologo:  { type: Boolean, default: true },
});

const form = useForm({
    clinic_id:     null,
    odontologo_id: null,
    patient_id:    null,
    radiologo_id:  null,
    prioridad:     '2 días',
    diagnostico:   '',
    observaciones: '',
    examenes:      [],
    action:        'guardar',
});

// Estado auxiliar
const odontologos         = ref([]);
const radiologos          = ref([]);
const patientSearch       = ref('');
const patientSuggestions  = ref([]);
const examFiles           = reactive({});
const examPiezas          = reactive({});
const loadingOdontologos  = ref(false);
const loadingRadiologos   = ref(false);

// ── Tabs de examen ─────────────────────────────────────────────────────────
const activeTab = ref('intraorales');

// examTypes = { intraorales: [{group_id, nombre, items}], extraorales: [...] }
const intraoralesCols = computed(() => props.examTypes?.intraorales ?? []);
const extraoralesCols = computed(() => props.examTypes?.extraorales ?? []);

const stripGroupSuffix = (label) =>
    label.replace(/ Adulto$/i, '').replace(/ Niño$/i, '');

const isSelected = (id) => form.examenes.includes(id);
const toggleExam = (val) => { form.examenes = val; };

// Al cambiar clínica, recarga odontólogos y radiólogos
const onClinicChange = async () => {
    form.odontologo_id = null;
    form.radiologo_id  = null;
    form.patient_id    = null;
    patientSearch.value = '';
    odontologos.value  = [];
    radiologos.value   = [];

    if (!form.clinic_id) return;

    loadingOdontologos.value = true;
    loadingRadiologos.value  = true;

    const [od, rad] = await Promise.all([
        fetch(route('ordenes.odontologos') + `?clinic_id=${form.clinic_id}`).then(r => r.json()),
        fetch(route('ordenes.radiologos')  + `?clinic_id=${form.clinic_id}`).then(r => r.json()),
    ]);

    odontologos.value = od;
    radiologos.value  = rad;
    loadingOdontologos.value = false;
    loadingRadiologos.value  = false;
};

// Autocompletar pacientes
const searchPatients = async (event) => {
    const res = await fetch(
        route('ordenes.patients') + `?q=${encodeURIComponent(event.query)}&clinic_id=${form.clinic_id}`
    );
    patientSuggestions.value = await res.json();
};

const onPatientSelect = (event) => {
    form.patient_id = event.value.id;
};

// Guardar archivos y piezas por tipo de examen
const onFilesSelect  = (examId, event) => { examFiles[examId]  = event.files; };
const onPiezasSelect = (examId, piezas) => { examPiezas[examId] = piezas; };

// Submit
const submitAction = (action) => {
    form.action = action;

    const data = new FormData();
    data.append('clinic_id',      form.clinic_id);
    data.append('odontologo_id',  form.odontologo_id);
    data.append('patient_id',     form.patient_id);
    data.append('prioridad',      form.prioridad);
    data.append('diagnostico',    form.diagnostico);
    data.append('observaciones',  form.observaciones);
    data.append('action',         action);
    if (form.radiologo_id) data.append('radiologo_id', form.radiologo_id);

    form.examenes.forEach(id => data.append('examenes[]', id));

    // Adjuntar archivos y piezas por examen
    Object.entries(examFiles).forEach(([examId, files]) => {
        files.forEach(file => data.append(`files_${examId}[]`, file));
    });
    Object.entries(examPiezas).forEach(([examId, piezas]) => {
        piezas.forEach(p => data.append(`piezas_${examId}[]`, p));
    });

    router.post(route('ordenes.store'), data, {
        forceFormData: true,
        onError: (errors) => { form.errors = errors; },
    });
};
</script>
