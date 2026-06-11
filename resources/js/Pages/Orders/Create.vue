<template>
    <AppLayout title="Nueva Orden">
        <div class="p-6 max-w-4xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('ordenes.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <h1 class="text-2xl font-bold text-gray-800">Nueva Orden Radiográfica</h1>
            </div>

            <!-- Indicador de upload CBCT en progreso (mientras llena el formulario) -->
            <div v-if="cbctStillUploading && !submitting"
                class="fixed bottom-6 right-6 z-40 bg-[#0b2a4a] text-white rounded-xl shadow-xl px-4 py-3 flex items-center gap-3" style="min-width:260px;">
                <i class="pi pi-spin pi-spinner text-blue-400" style="font-size:1.1rem;" />
                <div class="flex-1">
                    <p class="text-xs font-semibold text-blue-200">Subiendo archivo 3D/CBCT...</p>
                    <div class="w-full bg-white/20 rounded-full h-1.5 mt-1 overflow-hidden">
                        <div class="h-1.5 rounded-full bg-blue-400 transition-all duration-300"
                            :style="`width:${Object.values(cbctUploads).find(u=>u.uploading)?.progress ?? 0}%`" />
                    </div>
                    <p class="text-[10px] text-blue-300 mt-1">
                        {{ Object.values(cbctUploads).find(u=>u.uploading)?.progress ?? 0 }}% — El formulario sigue disponible
                    </p>
                </div>
            </div>

            <!-- Overlay de carga -->
            <div v-if="submitting"
                class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 bg-white/80 backdrop-blur-sm">
                <div class="bg-white rounded-2xl shadow-xl p-8 w-80 text-center">
                    <i class="pi pi-cloud-upload text-4xl mb-3 block" style="color:#3452ff" />
                    <p class="text-sm font-semibold text-gray-800 mb-2">
                        {{ uploadProgress < 100 ? 'Subiendo archivos...' : (form.action === 'enviar' ? 'Enviando orden al radiólogo...' : 'Guardando orden...') }}
                    </p>
                    <!-- Barra de progreso real durante el upload -->
                    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                        <div v-if="uploadProgress < 100" class="h-2.5 rounded-full transition-all duration-300"
                            :style="`width:${uploadProgress}%; background:linear-gradient(90deg,#3452ff,#6366f1)`">
                        </div>
                        <!-- Indeterminada: el servidor está procesando -->
                        <div v-else class="h-2.5 rounded-full"
                            style="background:linear-gradient(90deg,#3452ff,#6366f1); animation: progress-indeterminate 1.4s ease-in-out infinite;">
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">
                        <template v-if="uploadProgress < 100">{{ uploadProgress }}% — Enviando datos...</template>
                        <template v-else-if="hasCbctFiles">Guardando orden con archivos 3D/CBCT...</template>
                        <template v-else>Procesando, por favor espere...</template>
                    </p>
                </div>
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

                            <!-- Radiólogo global (fallback cuando no hay asignación por examen) -->
                            <div v-if="canSelectRadiologo && form.examenes.length === 0">
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
                            <div v-if="canSinDiagnostico" class="flex items-center gap-2">
                                <Checkbox v-model="form.sin_diagnostico" inputId="sin_diag_create" :binary="true" />
                                <label for="sin_diag_create" class="text-sm text-gray-600 cursor-pointer select-none">
                                    Examen sin diagnóstico clínico
                                </label>
                            </div>
                            <div v-if="!form.sin_diagnostico">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Diagnóstico <span v-if="!form.sin_diagnostico" class="text-red-500">*</span>
                                </label>
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

                        <!-- Tab content — v-show keeps ExamCol mounted so selectedPiezas state is preserved across tab switches -->
                        <div class="p-5">
                            <div v-show="activeTab === 'intraorales'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in intraoralesCols" :key="col.group_id">
                                    <ExamCol :col="col" :selected="form.examenes" :examFiles="examFiles"
                                        :examLabel="examLabel" :stripSuffix="stripGroupSuffix"
                                        @toggle="toggleExam" @files="onFilesSelect" @piezas="onPiezasSelect"
                                        @urltext="onUrlTextChange" />
                                </div>
                            </div>
                            <div v-show="activeTab === 'extraorales'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in extraoralesCols" :key="col.group_id">
                                    <ExamCol :col="col" :selected="form.examenes" :examFiles="examFiles"
                                        :examLabel="examLabel" :stripSuffix="(l) => l"
                                        @toggle="toggleExam" @files="onFilesSelect" @piezas="onPiezasSelect"
                                        @urltext="onUrlTextChange" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Asignación de Radiólogos por Examen -->
                    <div v-if="canSelectRadiologo && form.examenes.length > 0"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-user-edit mr-2" />Asignación de Radiólogos por Examen
                        </h2>
                        <div class="space-y-2">
                            <div v-for="kindId in form.examenes" :key="kindId"
                                class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
                                <span class="text-sm text-gray-700 flex-1 min-w-0">
                                    {{ kindLabelMap[kindId] ?? `Examen #${kindId}` }}
                                </span>
                                <Select
                                    v-model="radiologoPorExamen[kindId]"
                                    :options="radiologos"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Auto-asignar"
                                    class="w-56"
                                    :loading="loadingRadiologos"
                                    showClear
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Error de archivos faltantes -->
                    <Message v-if="fileError" severity="error" class="mt-4">{{ fileError }}</Message>

                    <!-- Botones de acción -->
                    <div class="flex justify-end gap-3 mt-4">
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
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTerms } from '@/composables/useTerms.js';

const { terms, examLabel } = useTerms();
import Button from 'primevue/button';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import AutoComplete from 'primevue/autocomplete';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import ExamCol from '@/Components/ExamCol.vue';

const page = usePage();
const canSinDiagnostico = computed(() => !!page.props.auth?.user?.puede_sin_diagnostico);

const props = defineProps({
    clinics:                  Array,
    examTypes:                Array,
    canSelectRadiologo:       { type: Boolean, default: true },
    pacientePreseleccionado:  { type: Object, default: null },
});

const form = useForm({
    clinic_id:       null,
    odontologo_id:   null,
    patient_id:      props.pacientePreseleccionado?.id ?? null,
    radiologo_id:    null,
    prioridad:       '2 días',
    diagnostico:     '',
    observaciones:   '',
    sin_diagnostico: false,
    examenes:        [],
    action:          'guardar',
});

// Estado auxiliar
const odontologos         = ref([]);
const radiologos          = ref([]);
const patientSearch       = ref(
    props.pacientePreseleccionado
        ? `${props.pacientePreseleccionado.name} (${props.pacientePreseleccionado.rut})`
        : ''
);
const patientSuggestions  = ref([]);
const examFiles           = reactive({});
const examPiezas          = reactive({});
const examUrlTexts        = reactive({});
const radiologoPorExamen  = reactive({});

const kindLabelMap = computed(() => {
    const map = {};
    [...(props.examTypes?.intraorales ?? []), ...(props.examTypes?.extraorales ?? [])]
        .forEach(col => col.items?.forEach(item => { map[item.id] = item.label; }));
    return map;
});
const loadingOdontologos  = ref(false);
const loadingRadiologos   = ref(false);
const submitting          = ref(false);
const uploadProgress      = ref(0);
const fileError           = ref(null);

// Eager upload: ZIP CBCT sube a S3 en segundo plano al seleccionarlo
const cbctUploads = reactive({}); // { [examId]: {uploading, progress, s3_path, filename, file_size, error} }

const hasCbctFiles = computed(() =>
    Object.values(examFiles).some(files =>
        files?.some(f => f.name?.toLowerCase().endsWith('.zip'))
    )
);

const cbctStillUploading = computed(() =>
    Object.values(cbctUploads).some(u => u.uploading)
);

function startEagerUpload(examId, file) {
    cbctUploads[examId] = { uploading: true, progress: 0, s3_path: null, filename: file.name, file_size: file.size, error: null };

    const fd = new FormData();
    fd.append('file', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', route('archivos.cbct-temp'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) cbctUploads[examId].progress = Math.round((e.loaded / e.total) * 100);
    };
    xhr.onload = () => {
        if (xhr.status === 200) {
            const data = JSON.parse(xhr.responseText);
            cbctUploads[examId].s3_path  = data.s3_path;
            cbctUploads[examId].filename = data.filename;
            cbctUploads[examId].uploading = false;
        } else {
            cbctUploads[examId].error    = 'Error subiendo ZIP';
            cbctUploads[examId].uploading = false;
        }
    };
    xhr.onerror = () => {
        cbctUploads[examId].error    = 'Error de red';
        cbctUploads[examId].uploading = false;
    };
    xhr.send(fd);
}

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

// Guardar archivos, piezas y URL por tipo de examen
const onFilesSelect   = (examId, event) => {
    examFiles[examId] = event.files;
    // ZIP CBCT → subir inmediatamente en segundo plano
    const zip = event.files.find(f => f.name?.toLowerCase().endsWith('.zip'));
    if (zip) startEagerUpload(examId, zip);
};
const onPiezasSelect  = (examId, piezas) => { examPiezas[examId]  = piezas; };
const onUrlTextChange = (examId, val)   => { examUrlTexts[examId] = val; };

// Submit
const submitAction = (action) => {
    form.action = action;
    fileError.value = null;

    // Esperar a que terminen uploads CBCT en progreso
    if (cbctStillUploading.value) {
        fileError.value = 'Esperando que termine de subir el archivo 3D/CBCT...';
        return;
    }

    // Al enviar a informar, verificar que cada examen tenga al menos un archivo
    if (action === 'enviar') {
        const sinArchivo = form.examenes.filter(id => !examFiles[id]?.length);
        if (sinArchivo.length > 0) {
            fileError.value = 'Debes adjuntar al menos una imagen o archivo en cada examen seleccionado antes de enviar.';
            return;
        }
    }

    const data = new FormData();
    data.append('clinic_id',      form.clinic_id);
    data.append('odontologo_id',  form.odontologo_id);
    data.append('patient_id',     form.patient_id);
    data.append('prioridad',        form.prioridad);
    data.append('diagnostico',      form.sin_diagnostico ? '' : form.diagnostico);
    data.append('observaciones',    form.observaciones);
    data.append('sin_diagnostico',  form.sin_diagnostico ? '1' : '0');
    data.append('action',           action);
    // Construir asignaciones por examen
    const groupedByRad = {};
    form.examenes.forEach(kindId => {
        const radId = radiologoPorExamen[kindId];
        if (!radId) return;
        if (!groupedByRad[radId]) groupedByRad[radId] = [];
        groupedByRad[radId].push(kindId);
    });
    const assignments = Object.entries(groupedByRad);
    if (assignments.length > 0) {
        assignments.forEach(([radId, kindIds], i) => {
            data.append(`radiologo_assignments[${i}][radiologo_id]`, radId);
            kindIds.forEach(k => data.append(`radiologo_assignments[${i}][kind_ids][]`, k));
        });
    } else if (form.radiologo_id) {
        // Fallback: un radiólogo global para todos los exámenes
        data.append('radiologo_id', form.radiologo_id);
    }

    form.examenes.forEach(id => data.append('examenes[]', id));

    // Adjuntar archivos, piezas y URLs por examen
    Object.entries(examFiles).forEach(([examId, files]) => {
        files.forEach(file => {
            const isZip = file.name?.toLowerCase().endsWith('.zip');
            const upload = cbctUploads[examId];
            if (isZip && upload?.s3_path) {
                // Ya subido a S3 en segundo plano — solo enviar la ruta
                data.append(`cbct_s3_path_${examId}`, upload.s3_path);
                data.append(`cbct_s3_name_${examId}`, upload.filename || file.name);
                data.append(`cbct_s3_size_${examId}`, upload.file_size || file.size);
            } else {
                data.append(`files_${examId}[]`, file);
            }
        });
    });
    Object.entries(examPiezas).forEach(([examId, piezas]) => {
        piezas.forEach(p => data.append(`piezas_${examId}[]`, p));
    });
    Object.entries(examUrlTexts).forEach(([examId, url]) => {
        if (url) data.append(`url_${examId}`, url);
    });

    submitting.value    = true;
    uploadProgress.value = 0;
    router.post(route('ordenes.store'), data, {
        forceFormData: true,
        onProgress: (e) => { if (e.percentage) uploadProgress.value = Math.min(99, Math.round(e.percentage)); },
        onSuccess:  ()       => { uploadProgress.value = 100; },
        onError:    (errors) => { form.errors = errors; submitting.value = false; uploadProgress.value = 0; },
        onFinish:   ()       => { submitting.value = false; },
    });
};
</script>

<style>
@keyframes progress-indeterminate {
    0%   { margin-left: -40%; width: 40%; }
    50%  { margin-left: 60%; width: 40%; }
    100% { margin-left: 110%; width: 40%; }
}
</style>
