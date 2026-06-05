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
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                        <i class="pi pi-file-edit text-blue-500" />
                                        {{ examLabel(examen.descripcion) }}
                                        <span class="text-xs text-gray-400 font-normal">({{ examen.archivos.length }} archivo(s))</span>
                                    </p>
                                    <button type="button"
                                        @click="eliminarExamen(examen.id)"
                                        class="flex items-center gap-1 px-2 py-1 text-xs text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Eliminar examen">
                                        <i class="pi pi-trash text-xs" /> Eliminar
                                    </button>
                                </div>

                                <!-- Análisis cefalométrico: selector editable -->
                                <div v-if="isCefaloExam(examen)" class="mb-3">
                                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Análisis solicitados</p>
                                    <div class="space-y-1">
                                        <div v-for="sub in CEFALO_SUBS" :key="sub" class="flex items-center gap-2">
                                            <Checkbox
                                                :inputId="`cefalo_${examen.id}_${sub}`"
                                                :value="sub"
                                                v-model="cefaloSeleccion[examen.id]"
                                            />
                                            <label :for="`cefalo_${examen.id}_${sub}`" class="text-sm cursor-pointer">{{ sub }}</label>
                                        </div>
                                        <div v-if="cefaloSeleccion[examen.id]?.includes('Otros')" class="mt-2">
                                            <InputText
                                                v-model="cefaloOtrosTexto[examen.id]"
                                                placeholder="Especifique el tipo de análisis..."
                                                class="w-full text-sm"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Archivos actuales -->
                                <div v-if="examen.archivos.length" class="flex flex-wrap gap-3 mb-3">
                                    <div v-for="f in examen.archivos" :key="f.id" class="relative group">
                                        <FileThumbnail
                                            :file="f"
                                            :showDicom="examen.grupo === 4"
                                            @lightbox="openLightbox" />
                                        <button type="button"
                                            @click="eliminarArchivo(f.id)"
                                            class="absolute top-1 right-1 hidden group-hover:flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white shadow"
                                            title="Eliminar archivo">
                                            <i class="pi pi-times text-xs" />
                                        </button>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-400 italic mb-3">Sin archivos adjuntos.</p>

                                <!-- Piezas dentales para Retroalveolar Unitaria -->
                                <div v-if="examen.descripcion?.toLowerCase().includes('unitaria')" class="mt-3 border-t border-gray-100 pt-3">
                                    <p class="text-xs font-medium text-gray-600 mb-2">Piezas dentales:</p>
                                    <div class="text-xs">
                                        <template v-if="!isNino(examen.descripcion)">
                                            <p class="font-semibold text-gray-700 mb-1">Dientes Permanentes</p>
                                            <p class="text-gray-500 mb-0.5">Maxilar</p>
                                            <div class="flex flex-wrap gap-x-1 gap-y-1 mb-1">
                                                <template v-for="n in [18,17,16,15,14,13,12,11]" :key="n">
                                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                        <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                            @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                        <span>{{ toDot(n) }}</span>
                                                    </label>
                                                </template>
                                                <span class="text-gray-400 mx-0.5">|</span>
                                                <template v-for="n in [21,22,23,24,25,26,27,28]" :key="n">
                                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                        <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                            @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                        <span>{{ toDot(n) }}</span>
                                                    </label>
                                                </template>
                                            </div>
                                            <p class="text-gray-500 mb-0.5">Mandíbula</p>
                                            <div class="flex flex-wrap gap-x-1 gap-y-1 mb-2">
                                                <template v-for="n in [48,47,46,45,44,43,42,41]" :key="n">
                                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                        <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                            @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                        <span>{{ toDot(n) }}</span>
                                                    </label>
                                                </template>
                                                <span class="text-gray-400 mx-0.5">|</span>
                                                <template v-for="n in [31,32,33,34,35,36,37,38]" :key="n">
                                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                        <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                            @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                        <span>{{ toDot(n) }}</span>
                                                    </label>
                                                </template>
                                            </div>
                                        </template>
                                        <p class="font-semibold text-gray-700 mb-1">Dientes Temporales</p>
                                        <p class="text-gray-500 mb-0.5">Maxilar</p>
                                        <div class="flex flex-wrap gap-x-1 gap-y-1 mb-1">
                                            <template v-for="n in [55,54,53,52,51]" :key="n">
                                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                    <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                        @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                    <span>{{ toDot(n) }}</span>
                                                </label>
                                            </template>
                                            <span class="text-gray-400 mx-0.5">|</span>
                                            <template v-for="n in [61,62,63,64,65]" :key="n">
                                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                    <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                        @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                    <span>{{ toDot(n) }}</span>
                                                </label>
                                            </template>
                                        </div>
                                        <p class="text-gray-500 mb-0.5">Mandíbula</p>
                                        <div class="flex flex-wrap gap-x-1 gap-y-1 mb-1">
                                            <template v-for="n in [85,84,83,82,81]" :key="n">
                                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                    <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                        @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                    <span>{{ toDot(n) }}</span>
                                                </label>
                                            </template>
                                            <span class="text-gray-400 mx-0.5">|</span>
                                            <template v-for="n in [71,72,73,74,75]" :key="n">
                                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                                    <input type="checkbox" :checked="isPiezaSelectedExisting(examen.id, n)"
                                                        @change="togglePiezaExisting(examen.id, n)" class="accent-blue-600 cursor-pointer" />
                                                    <span>{{ toDot(n) }}</span>
                                                </label>
                                            </template>
                                        </div>
                                        <p v-if="existingExamPiezas[examen.id]?.length" class="text-blue-600 mt-1">
                                            Seleccionadas: {{ existingExamPiezas[examen.id].map(toDot).join(', ') }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Subir más archivos a este examen -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Agregar más imágenes:</label>
                                    <FileUpload
                                        :name="`archivos_${examen.id}`"
                                        mode="basic"
                                        accept="image/*,application/pdf,.zip,.rar,.dcm,.dicom,.cbct,.7z,.tar,.gz,.doc,.docx,.xls,.xlsx"
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
                                        @toggle="nuevosExamenes = $event" @files="onNewFilesSelect"
                                        @piezas="onNewPiezasSelect" @urltext="onNewUrlTextChange" />
                                </div>
                            </div>
                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="col in editExtraoralesCols" :key="col.group_id">
                                    <EditExamCol :col="col" :nuevosExamenes="nuevosExamenes" :newExamFiles="newExamFiles"
                                        :yaExiste="yaExiste" :examLabel="examLabel" :stripSuffix="(l) => l"
                                        @toggle="nuevosExamenes = $event" @files="onNewFilesSelect"
                                        @piezas="onNewPiezasSelect" @urltext="onNewUrlTextChange" />
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
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';
import InputText from 'primevue/inputtext';
import { useTerms } from '@/composables/useTerms.js';
import EditExamCol from '@/Components/EditExamCol.vue';
import FileThumbnail from '@/Components/FileThumbnail.vue';
import ImageViewer   from '@/Components/ImageViewer.vue';

const { examLabel } = useTerms();

const CEFALO_SUBS = ['Análisis Rickets','Análisis Roth','Análisis Jaraback','Análisis Steiner','Análisis Mcnamara','Otros'];

function isCefaloExam(ex) { return /cefalom/i.test(ex?.descripcion ?? ''); }

// Inicializar selección de análisis desde url_texto existente
const cefaloSeleccion  = reactive({});
const cefaloOtrosTexto = reactive({});

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
const newFiles            = reactive({});   // archivos nuevos para exámenes existentes
const newExamFiles        = reactive({});   // archivos para nuevos exámenes
const newExamPiezas       = reactive({});   // piezas para nuevos exámenes
const newExamUrlTexts     = reactive({});   // urltexto para nuevos exámenes
const existingExamPiezas  = reactive({});   // piezas editadas en exámenes existentes unitaria
const submitting         = ref(false);
const currentAction      = ref('');

// Kind IDs already in this order — computed so it updates reactively when props change
const existingKindIds = computed(() => props.examenes.map(e => e.kind_id));
const yaExiste = (kindId) => existingKindIds.value.includes(kindId);
const isSelected = (id) => nuevosExamenes.value.includes(id);

// ── Tabs de examen ────────────────────────────────────────────────────────
const activeExamTab = ref('intraorales');

// examTypes = { intraorales: [{group_id, nombre, items}], extraorales: [...] }
const editIntraoralesCols = computed(() => props.examTypes?.intraorales ?? []);
const editExtraoralesCols = computed(() => props.examTypes?.extraorales ?? []);

const stripSuffix = (label) =>
    label.replace(/ Adulto$/i, '').replace(/ Niño$/i, '');

function isUnitaria(desc) { return desc?.toLowerCase().includes('unitaria'); }
function isNino(desc)     { return /niño|nino/i.test(desc); }
function toDot(n)         { const s = String(n); return s[0] + '.' + s[1]; }

function isPiezaSelectedExisting(examId, n) {
    return existingExamPiezas[examId]?.includes(n) ?? false;
}

function togglePiezaExisting(examId, n) {
    if (!existingExamPiezas[examId]) existingExamPiezas[examId] = [];
    const idx = existingExamPiezas[examId].indexOf(n);
    if (idx >= 0) existingExamPiezas[examId].splice(idx, 1);
    else existingExamPiezas[examId].push(n);
}

const onFilesSelect        = (examId, e) => { newFiles[examId] = e.files; };
const onNewFilesSelect     = (kindId, e) => { newExamFiles[kindId] = e.files; };
const onNewPiezasSelect    = (kindId, p) => { newExamPiezas[kindId] = p; };
const onNewUrlTextChange   = (kindId, v) => { newExamUrlTexts[kindId] = v; };

// Inicializar piezas de exámenes Unitaria existentes
props.examenes.forEach(e => {
    if (!isUnitaria(e.descripcion)) return;
    existingExamPiezas[e.id] = e.piezas ? e.piezas.split(',').map(Number) : [];
});

// Inicializar selección cefalométrico desde url_texto
props.examenes.forEach(ex => {
    if (!isCefaloExam(ex)) return;
    if (!ex.url_texto) { cefaloSeleccion[ex.id] = []; return; }
    const parts = ex.url_texto.split(',').map(s => s.trim());
    cefaloSeleccion[ex.id] = parts
        .map(p => p.startsWith('Otros:') ? 'Otros' : p)
        .filter(p => CEFALO_SUBS.includes(p));
    const otrosPart = parts.find(p => p.startsWith('Otros:'));
    if (otrosPart) cefaloOtrosTexto[ex.id] = otrosPart.replace('Otros:', '').trim();
});

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

    // Piezas de exámenes Unitaria existentes
    Object.entries(existingExamPiezas).forEach(([examId, piezas]) => {
        data.append(`piezas_existente_${examId}_update`, '1');
        if (piezas?.length) piezas.forEach(p => data.append(`piezas_existente_${examId}[]`, p));
    });

    // url_texto actualizado para exámenes cefalométricos existentes
    Object.entries(cefaloSeleccion).forEach(([exId, subs]) => {
        const parts = subs.map(s => {
            if (s === 'Otros' && cefaloOtrosTexto[exId]) return `Otros: ${cefaloOtrosTexto[exId]}`;
            return s;
        });
        data.append(`url_texto_existente[${exId}]`, parts.join(','));
    });

    // Nuevos exámenes
    nuevosExamenes.value.forEach(id => data.append('nuevos_examenes[]', id));
    Object.entries(newExamFiles).forEach(([kindId, files]) => {
        files.forEach(file => data.append(`archivos_nuevo_${kindId}[]`, file));
    });
    Object.entries(newExamPiezas).forEach(([kindId, piezas]) => {
        if (piezas?.length) piezas.forEach(p => data.append(`piezas_nuevo_${kindId}[]`, p));
    });
    Object.entries(newExamUrlTexts).forEach(([kindId, val]) => {
        if (val) data.append(`url_nuevo_${kindId}`, val);
    });

    router.post(route('ordenes.update', props.order.id), data, {
        forceFormData: true,
        onFinish: () => { submitting.value = false; },
    });
};

function eliminarArchivo(id) {
    if (!confirm('¿Eliminar este archivo?')) return;
    router.delete(route('archivos.destroy', id), { preserveScroll: true });
}

function eliminarExamen(examenId) {
    if (!confirm('¿Eliminar este examen y todos sus archivos? Esta acción no se puede deshacer.')) return;
    router.delete(route('ordenes.examenes.destroy', { order: props.order.id, examination: examenId }), {
        preserveScroll: true,
    });
}
</script>
