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
                                    :options="['Normal', 'Urgente']"
                                    class="w-full"
                                    :class="{'p-invalid': form.errors.prioridad}"
                                />
                            </div>

                            <!-- Radiólogo -->
                            <div>
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
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-list mr-2" />Tipos de Examen *
                        </h2>
                        <small class="text-red-500 block mb-3" v-if="form.errors.examenes">{{ form.errors.examenes }}</small>

                        <div v-for="group in examTypes" :key="group.label" class="mb-5">
                            <p class="text-xs font-bold uppercase text-gray-400 tracking-widest mb-2">{{ group.label }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div v-for="exam in group.items" :key="exam.id"
                                    class="border rounded-lg p-3 transition"
                                    :class="isSelected(exam.id) ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                                    <div class="flex items-center gap-2 mb-2">
                                        <Checkbox
                                            :inputId="`exam_${exam.id}`"
                                            :value="exam.id"
                                            v-model="form.examenes"
                                        />
                                        <label :for="`exam_${exam.id}`" class="text-sm cursor-pointer">{{ examLabel(exam.label) }}</label>
                                    </div>
                                    <!-- File upload cuando está seleccionado -->
                                    <div v-if="isSelected(exam.id)" class="mt-2">
                                        <FileUpload
                                            :name="`files_${exam.id}`"
                                            mode="basic"
                                            accept="image/*,.dcm"
                                            :multiple="true"
                                            chooseLabel="Adjuntar imágenes"
                                            class="w-full text-xs"
                                            @select="(e) => onFilesSelect(exam.id, e)"
                                        />
                                        <div v-if="examFiles[exam.id]?.length" class="mt-1 text-xs text-gray-500">
                                            {{ examFiles[exam.id].length }} archivo(s) seleccionado(s)
                                        </div>
                                    </div>
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
                            :disabled="!form.radiologo_id"
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
import { ref, reactive } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTerms } from '@/composables/useTerms.js';

const { terms, examLabel } = useTerms();
import Button from 'primevue/button';
import Select from 'primevue/select';
import AutoComplete from 'primevue/autocomplete';
import Textarea from 'primevue/textarea';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';

const props = defineProps({
    clinics:   Array,
    examTypes: Array,
});

const form = useForm({
    clinic_id:     null,
    odontologo_id: null,
    patient_id:    null,
    radiologo_id:  null,
    prioridad:     'Normal',
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
const loadingOdontologos  = ref(false);
const loadingRadiologos   = ref(false);

const isSelected = (id) => form.examenes.includes(id);

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

// Guardar archivos por tipo de examen
const onFilesSelect = (examId, event) => {
    examFiles[examId] = event.files;
};

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

    // Adjuntar archivos
    Object.entries(examFiles).forEach(([examId, files]) => {
        files.forEach(file => data.append(`files_${examId}[]`, file));
    });

    router.post(route('ordenes.store'), data, {
        forceFormData: true,
        onError: (errors) => { form.errors = errors; },
    });
};
</script>
