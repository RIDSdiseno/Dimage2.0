<template>
    <div>
        <p class="text-sm font-semibold text-gray-600 mb-2 flex items-center gap-1.5 pb-2 border-b border-gray-200">
            <i class="pi pi-info-circle text-xs text-gray-400" /> {{ col.nombre }}
        </p>
        <div class="border border-gray-200 rounded-lg p-3 space-y-1 min-h-24">
            <template v-for="exam in col.items" :key="exam.id">
                <div class="flex items-center gap-2 py-1 px-1 rounded hover:bg-gray-50">
                    <Checkbox :inputId="`exam_${exam.id}`" :value="exam.id" v-model="localSelected" />
                    <label :for="`exam_${exam.id}`" class="text-sm cursor-pointer text-gray-700 select-none">
                        {{ examLabel(stripSuffix(exam.label)) }}
                    </label>
                </div>

                <div v-if="localSelected.includes(exam.id)" class="ml-6 mt-1 mb-2 space-y-2">

                    <!-- Tooth chart: unitaria exams -->
                    <div v-if="isUnitaria(exam.label)" class="text-xs">

                        <!-- Dientes Permanentes -->
                        <template v-if="true">
                            <p class="font-semibold text-gray-700 mb-1">Dientes Permanentes</p>

                            <p class="text-gray-500 mb-0.5">Maxilar</p>
                            <div class="flex items-center flex-wrap gap-x-1 gap-y-1 mb-1">
                                <template v-for="n in [18,17,16,15,14,13,12,11]" :key="n">
                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                        <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                            @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                        <span>{{ toDot(n) }}</span>
                                    </label>
                                </template>
                                <span class="text-gray-400 mx-0.5">|</span>
                                <template v-for="n in [21,22,23,24,25,26,27,28]" :key="n">
                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                        <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                            @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                        <span>{{ toDot(n) }}</span>
                                    </label>
                                </template>
                            </div>

                            <p class="text-gray-500 mb-0.5">Mandíbula</p>
                            <div class="flex items-center flex-wrap gap-x-1 gap-y-1 mb-2">
                                <template v-for="n in [48,47,46,45,44,43,42,41]" :key="n">
                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                        <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                            @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                        <span>{{ toDot(n) }}</span>
                                    </label>
                                </template>
                                <span class="text-gray-400 mx-0.5">|</span>
                                <template v-for="n in [31,32,33,34,35,36,37,38]" :key="n">
                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                        <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                            @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                        <span>{{ toDot(n) }}</span>
                                    </label>
                                </template>
                            </div>
                        </template>

                        <!-- Dientes Temporales (siempre visibles en unitaria) -->
                        <p class="font-semibold text-gray-700 mb-1">Dientes Temporales</p>

                        <p class="text-gray-500 mb-0.5">Maxilar</p>
                        <div class="flex items-center flex-wrap gap-x-1 gap-y-1 mb-1">
                            <template v-for="n in [55,54,53,52,51]" :key="n">
                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                    <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                        @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                    <span>{{ toDot(n) }}</span>
                                </label>
                            </template>
                            <span class="text-gray-400 mx-0.5">|</span>
                            <template v-for="n in [61,62,63,64,65]" :key="n">
                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                    <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                        @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                    <span>{{ toDot(n) }}</span>
                                </label>
                            </template>
                        </div>

                        <p class="text-gray-500 mb-0.5">Mandíbula</p>
                        <div class="flex items-center flex-wrap gap-x-1 gap-y-1 mb-1">
                            <template v-for="n in [85,84,83,82,81]" :key="n">
                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                    <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                        @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                    <span>{{ toDot(n) }}</span>
                                </label>
                            </template>
                            <span class="text-gray-400 mx-0.5">|</span>
                            <template v-for="n in [71,72,73,74,75]" :key="n">
                                <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                    <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                        @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                    <span>{{ toDot(n) }}</span>
                                </label>
                            </template>
                        </div>

                        <p v-if="selectedPiezas[exam.id]?.length" class="text-blue-600 mt-1">
                            Seleccionadas: {{ selectedPiezas[exam.id].map(toDot).join(', ') }}
                        </p>
                    </div>

                    <!-- Sub-option: Estudio para implantes (panorámica) -->
                    <div v-if="isPanoramica(exam.label)" class="flex items-center gap-2">
                        <Checkbox
                            :modelValue="localImplantes[exam.id] || false"
                            :binary="true"
                            :inputId="`impl_${exam.id}`"
                            @update:modelValue="(v) => { localImplantes[exam.id] = v; emitImplantes(exam.id); }"
                        />
                        <label :for="`impl_${exam.id}`" class="text-sm font-medium cursor-pointer">
                            Estudio para implantes
                        </label>
                    </div>

                    <!-- Sub-options: Análisis cefalométrico -->
                    <div v-if="isCefalometrico(exam.label)">
                        <div v-for="sub in CEFALO_SUBS" :key="sub"
                            class="flex items-center gap-2 py-1 border-b border-gray-100 last:border-0">
                            <Checkbox
                                :modelValue="localSubopts[exam.id] || []"
                                :value="sub"
                                :inputId="`sub_${exam.id}_${sub}`"
                                @update:modelValue="(v) => { localSubopts[exam.id] = v; emitSubopts(exam.id); }"
                            />
                            <label :for="`sub_${exam.id}_${sub}`" class="text-sm font-medium cursor-pointer">
                                {{ sub }}
                            </label>
                        </div>
                        <!-- Campo libre cuando se selecciona "Otros" -->
                        <div v-if="(localSubopts[exam.id] || []).includes('Otros')" class="mt-2">
                            <InputText
                                :modelValue="localOtrosText[exam.id] || ''"
                                placeholder="Especifique el tipo de análisis..."
                                class="w-full text-sm"
                                @input="(e) => { localOtrosText[exam.id] = e.target.value; emitSubopts(exam.id); }"
                            />
                        </div>
                    </div>

                    <!-- Imágenes Asociadas separator -->
                    <div class="flex items-center gap-2 text-xs text-gray-400 pt-1">
                        <hr class="flex-1 border-gray-200" />
                        <span><i class="pi pi-images text-xs mr-1" />Imágenes Asociadas</span>
                        <hr class="flex-1 border-gray-200" />
                    </div>

                    <!-- File upload -->
                    <FileUpload :name="`files_${exam.id}`" mode="basic" accept="image/*,application/pdf,.zip,.rar,.dcm,.dicom,.cbct,.7z,.tar,.gz,.doc,.docx,.xls,.xlsx"
                        :multiple="true" chooseLabel="Buscar Imagen o Archivo" class="w-full text-xs"
                        @select="(e) => addFiles(exam.id, e)" />

                    <!-- Selected files list with delete buttons -->
                    <div v-if="localFiles[exam.id]?.length" class="mt-2 space-y-1">
                        <div v-for="(f, idx) in localFiles[exam.id]" :key="idx"
                            class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded px-2 py-1 text-xs">
                            <span class="truncate max-w-[80%] text-gray-700">
                                <i class="pi pi-file text-gray-400 mr-1" />{{ f.name }}
                            </span>
                            <button type="button" @click="removeFile(exam.id, idx)"
                                class="text-red-400 hover:text-red-600 transition ml-1 shrink-0">
                                <i class="pi pi-times text-xs" />
                            </button>
                        </div>
                    </div>

                    <!-- URL IMAGEN: cone beam only -->
                    <div v-if="isConeBeam(exam.label)">
                        <InputText
                            :modelValue="localUrlText[exam.id] || ''"
                            placeholder="URL IMAGEN, ej: http://192.168.0.1/imagen.jpg"
                            class="w-full text-sm"
                            @update:modelValue="(v) => { localUrlText[exam.id] = v; $emit('urltext', exam.id, v); }"
                        />
                    </div>

                </div>
            </template>
            <p v-if="!col.items?.length" class="text-xs text-gray-400 italic py-2">Sin exámenes en esta categoría.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';
import InputText from 'primevue/inputtext';

const props = defineProps({
    col:         Object,
    selected:    Array,
    examFiles:   Object,
    examLabel:   Function,
    stripSuffix: Function,
});

const emit = defineEmits(['toggle', 'files', 'piezas', 'urltext']);

const localSelected  = computed({
    get: () => props.selected,
    set: (val) => emit('toggle', val),
});

const selectedPiezas  = reactive({});
const localImplantes  = reactive({});
const localSubopts    = reactive({});
const localOtrosText  = reactive({});
const localUrlText    = reactive({});
const localFiles      = reactive({});

function addFiles(examId, e) {
    if (!localFiles[examId]) localFiles[examId] = [];
    for (const f of e.files) {
        localFiles[examId].push(f);
    }
    emit('files', examId, { files: [...localFiles[examId]] });
}

function removeFile(examId, idx) {
    localFiles[examId].splice(idx, 1);
    emit('files', examId, { files: [...localFiles[examId]] });
}

const CEFALO_SUBS = [
    'Análisis Rickets',
    'Análisis Roth',
    'Análisis Jaraback',
    'Análisis Steiner',
    'Análisis Mcnamara',
    'Otros',
];

function isUnitaria(label)     { return label.toLowerCase().includes('unitaria'); }
function isPanoramica(label)   { return /panorám|panoram/i.test(label); }
function isCefalometrico(label){ return /cefalom/i.test(label); }
function isConeBeam(label)     { return label.toLowerCase().includes('cone beam'); }
function isNino(label)         { return /niño|nino/i.test(label); }

function toDot(n) {
    const s = String(n);
    return s[0] + '.' + s[1];
}

function isPiezaSelected(examId, n) { return selectedPiezas[examId]?.includes(n) ?? false; }

function togglePieza(examId, n) {
    if (!selectedPiezas[examId]) selectedPiezas[examId] = [];
    const idx = selectedPiezas[examId].indexOf(n);
    if (idx >= 0) selectedPiezas[examId].splice(idx, 1);
    else selectedPiezas[examId].push(n);
    emit('piezas', examId, [...selectedPiezas[examId]]);
}

function emitImplantes(examId) {
    const val = localImplantes[examId] ? 'implantes' : '';
    emit('urltext', examId, val);
}

function emitSubopts(examId) {
    const parts = (localSubopts[examId] || []).map(s => {
        if (s === 'Otros' && localOtrosText[examId]) return `Otros: ${localOtrosText[examId]}`;
        return s;
    });
    emit('urltext', examId, parts.join(','));
}
</script>
