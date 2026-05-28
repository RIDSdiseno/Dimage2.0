<template>
    <AppLayout title="Editar Orden">

        <ImageViewer :src="lightbox.open ? lightbox.src : null" :name="lightbox.name" @close="lightbox.open = false" />

        <div class="p-6 max-w-4xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('ordenes.show', order.id)">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Editar Orden #{{ order.id }}</h1>
                    <span v-if="order.estadoradiologo == 4"
                        class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">Borrador</span>
                    <span v-else
                        class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-600 font-medium">Pendiente</span>
                </div>
            </div>

            <form @submit.prevent>
                <div class="space-y-5">

                    <!-- Datos básicos -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-info-circle mr-2" />Datos de la Orden
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <!-- Clínica (solo display) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Clínica</label>
                                <Select
                                    v-model="form.clinic_id"
                                    :options="clinics"
                                    optionLabel="name"
                                    optionValue="id"
                                    class="w-full"
                                    disabled
                                />
                            </div>

                            <!-- Odontólogo -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Odontólogo</label>
                                <Select
                                    v-model="form.odontologo_id"
                                    :options="odontologos"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Selecciona odontólogo"
                                    class="w-full"
                                    :loading="loadingOdontologos"
                                    showClear
                                />
                            </div>

                            <!-- Prioridad -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad *</label>
                                <Select
                                    v-model="form.prioridad"
                                    :options="['1 día', '2 días', '3 días', 'Normal', 'Urgente']"
                                    class="w-full"
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
                                    showClear
                                />
                            </div>

                        </div>
                    </div>

                    <!-- Diagnóstico -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-file-edit mr-2" />Diagnóstico Clínico
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 mb-1">
                                <Checkbox v-model="form.sin_diagnostico" inputId="sin_diag" :binary="true" />
                                <label for="sin_diag" class="text-sm text-gray-600">Sin diagnóstico</label>
                            </div>
                            <div v-if="!form.sin_diagnostico">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico *</label>
                                <Textarea v-model="form.diagnostico" rows="3" class="w-full"
                                    placeholder="Describe el diagnóstico clínico..." />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Observaciones</label>
                                <Textarea v-model="form.observaciones" rows="2" class="w-full"
                                    placeholder="Observaciones adicionales..." />
                            </div>
                        </div>
                    </div>

                    <!-- Exámenes existentes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-images mr-2" />Exámenes Actuales
                        </h2>
                        <div class="space-y-4">
                            <div v-for="examen in examenes" :key="examen.id"
                                class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                    <i class="pi pi-file-edit text-blue-500" />
                                    {{ examLabel(examen.descripcion) }}
                                    <span class="text-xs text-gray-400 font-normal">({{ examen.archivos.length }} archivo(s))</span>
                                </p>

                                <!-- Archivos actuales -->
                                <div v-if="examen.archivos.length" class="flex flex-wrap gap-3 mb-3">
                                    <FileThumbnail
                                        v-for="f in examen.archivos"
                                        :key="f.id"
                                        :file="f"
                                        :showDicom="examen.grupo === 4"
                                        @lightbox="openLightbox" />
                                </div>
                                <p v-else class="text-xs text-gray-400 italic mb-3">Sin archivos adjuntos.</p>

                                <!-- Subir más archivos a este examen -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Agregar más imágenes:</label>
                                    <FileUpload
                                        :name="`archivos_${examen.id}`"
                                        mode="basic"
                                        accept="image/*,.dcm,.pdf"
                                        :multiple="true"
                                        chooseLabel="Adjuntar archivos"
                                        class="text-xs"
                                        @select="(e) => onFilesSelect(examen.id, e)"
                                    />
                                    <div v-if="newFiles[examen.id]?.length" class="mt-1 text-xs text-green-600">
                                        {{ newFiles[examen.id].length }} archivo(s) nuevo(s) seleccionado(s)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Agregar nuevos exámenes -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                            <h2 class="text-sm font-semibold uppercase tracking-wide" style="color:#3452ff">
                                <i class="pi pi-plus-circle mr-2" />Agregar Nuevos Exámenes
                            </h2>
                        </div>

                        <!-- Tab bar -->
                        <div class="flex border-b border-gray-200 bg-gray-50">
                            <button type="button" @click="activeExamTab = 'intraorales'"
                                class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors focus:outline-none"
                                :class="activeExamTab === 'intraorales'
                                    ? 'border-blue-600 text-white bg-blue-600'
                                    : 'border-transparent text-gray-600 hover:text-gray-800 bg-gray-50'">
                                Radiografías Intraorales
                            </button>
                            <button type="button" @click="activeExamTab = 'extraorales'"
                                class="px-5 py-2.5 text-sm font-medium border-b-2 transition-colors focus:outline-none"
                                :class="activeExamTab === 'extraorales'
                                    ? 'border-blue-600 text-white bg-blue-600'
                                    : 'border-transparent text-gray-600 hover:text-gray-800 bg-gray-50'">
                                Radiografías Extraorales
                            </button>
                        </div>

                        <div class="p-5">
                            <div v-if="activeExamTab === 'intraorales'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in editIntraoralesCols" :key="col.group_id">
                                    <EditExamCol :col="col" :nuevosExamenes="nuevosExamenes" :newExamFiles="newExamFiles"
                                        :yaExiste="yaExiste" :examLabel="examLabel" :stripSuffix="stripSuffix"
                                        @toggle="nuevosExamenes = $event" @files="onNewFilesSelect" />
                                </div>
                            </div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in editExtraoralesCols" :key="col.group_id">
                                    <EditExamCol :col="col" :nuevosExamenes="nuevosExamenes" :newExamFiles="newExamFiles"
                                        :yaExiste="yaExiste" :examLabel="examLabel" :stripSuffix="(l) => l"
                                        @toggle="nuevosExamenes = $event" @files="onNewFilesSelect" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3">
                        <Link :href="route('ordenes.show', order.id)">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <a :href="route('ordenes.pdf', order.id)" target="_blank">
                            <Button label="Imprimir" icon="pi pi-print" severity="secondary" type="button" />
                        </a>
                        <Button
                            label="Guardar Orden"
                            icon="pi pi-save"
                            severity="secondary"
                            type="button"
                            :loading="submitting && currentAction === 'guardar'"
                            @click="submitAction('guardar')"
                        />
                        <Button
                            label="Enviar a Informar"
                            icon="pi pi-send"
                            type="button"
                            :loading="submitting && currentAction === 'enviar'"
                            :disabled="canSelectRadiologo && !form.radiologo_id"
                            @click="submitAction('enviar')"
                            style="background-color:#3452ff;border-color:#3452ff;"
                        />
                    </div>

                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useTerms } from '@/composables/useTerms.js';
import EditExamCol from '@/Components/EditExamCol.vue';
import FileThumbnail from '@/Components/FileThumbnail.vue';
import ImageViewer   from '@/Components/ImageViewer.vue';

const { examLabel } = useTerms();

const lightbox = reactive({ open: false, src: '', name: '' });

function openLightbox(file) {
    lightbox.src  = file.url;
    lightbox.name = file.name || 'Imagen';
    lightbox.open = true;
}

const props = defineProps({
    order:               Object,
    examenes:            Array,
    examTypes:           Array,
    clinics:             Array,
    canSelectRadiologo:  { type: Boolean, default: true },
});

// Form state
const form = reactive({
    clinic_id:       props.order.clinic_id,
    odontologo_id:   props.order.odontologo_id,
    radiologo_id:    props.order.radiologo_id,
    prioridad:       props.order.prioridad,
    diagnostico:     props.order.diagnostico ?? '',
    observaciones:   props.order.observaciones ?? '',
    sin_diagnostico: props.order.sin_diagnostico ?? false,
});

const odontologos        = ref([]);
const radiologos         = ref([]);
const loadingOdontologos = ref(false);
const loadingRadiologos  = ref(false);
const nuevosExamenes     = ref([]);
const newFiles           = reactive({});   // archivos nuevos para exámenes existentes
const newExamFiles       = reactive({});   // archivos para nuevos exámenes
const submitting         = ref(false);
const currentAction      = ref('');

// Kind IDs already in this order
const existingKindIds = props.examenes.map(e => e.kind_id);
const yaExiste = (kindId) => existingKindIds.includes(kindId);
const isSelected = (id) => nuevosExamenes.value.includes(id);

// ── Tabs de examen ────────────────────────────────────────────────────────
const activeExamTab = ref('intraorales');

// examTypes = { intraorales: [{group_id, nombre, items}], extraorales: [...] }
const editIntraoralesCols = computed(() => props.examTypes?.intraorales ?? []);
const editExtraoralesCols = computed(() => props.examTypes?.extraorales ?? []);

const stripSuffix = (label) =>
    label.replace(/ Adulto$/i, '').replace(/ Niño$/i, '');

const onFilesSelect    = (examId, e) => { newFiles[examId] = e.files; };
const onNewFilesSelect = (kindId, e) => { newExamFiles[kindId] = e.files; };

// Load odontólogos y radiólogos for current clinic
onMounted(async () => {
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
});

const submitAction = (action) => {
    submitting.value    = true;
    currentAction.value = action;

    const data = new FormData();
    data.append('_method',         'POST');
    data.append('prioridad',       form.prioridad);
    data.append('diagnostico',     form.sin_diagnostico ? '' : form.diagnostico);
    data.append('observaciones',   form.observaciones ?? '');
    data.append('sin_diagnostico', form.sin_diagnostico ? '1' : '0');
    data.append('action',          action);
    if (form.radiologo_id)  data.append('radiologo_id',  form.radiologo_id);
    if (form.odontologo_id) data.append('odontologo_id', form.odontologo_id);

    // Archivos nuevos para exámenes existentes
    Object.entries(newFiles).forEach(([examId, files]) => {
        files.forEach(file => data.append(`archivos_${examId}[]`, file));
    });

    // Nuevos exámenes
    nuevosExamenes.value.forEach(id => data.append('nuevos_examenes[]', id));
    Object.entries(newExamFiles).forEach(([kindId, files]) => {
        files.forEach(file => data.append(`archivos_nuevo_${kindId}[]`, file));
    });

    router.post(route('ordenes.update', props.order.id), data, {
        forceFormData: true,
        onFinish: () => { submitting.value = false; },
    });
};
</script>
