<template>
    <div>
        <p class="text-sm font-semibold text-gray-600 mb-2 flex items-center gap-1.5 pb-2 border-b border-gray-200">
            <i class="pi pi-info-circle text-xs text-gray-400" /> {{ col.nombre }}
        </p>
        <div class="border border-gray-200 rounded-lg p-3 space-y-1 min-h-24">
            <template v-for="exam in col.items" :key="exam.id">
                <template v-if="!yaExiste(exam.id)">
                    <div class="flex items-center gap-2 py-1 px-1 rounded hover:bg-gray-50">
                        <Checkbox :inputId="`new_${exam.id}`" :value="exam.id" v-model="localSelected" />
                        <label :for="`new_${exam.id}`" class="text-sm cursor-pointer text-gray-700 select-none">
                            {{ examLabel(stripSuffix(exam.label)) }}
                        </label>
                    </div>
                    <div v-if="localSelected.includes(exam.id)" class="ml-6 mt-1 mb-2 space-y-2">

                        <!-- Tooth chart para exámenes unitarios -->
                        <div v-if="isUnitaria(exam.label)" class="text-xs">
                            <template v-if="!isNino(exam.label)">
                                <p class="font-semibold text-gray-700 mb-1">Dientes Permanentes</p>
                                <p class="text-gray-500 mb-0.5">Maxilar</p>
                                <div class="flex flex-wrap gap-x-1 gap-y-1 mb-1">
                                    <template v-for="n in [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28]" :key="n">
                                        <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                            <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                                @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                            <span>{{ toDot(n) }}</span>
                                        </label>
                                        <span v-if="n===11" class="text-gray-400 mx-0.5">|</span>
                                    </template>
                                </div>
                                <p class="text-gray-500 mb-0.5">Mandíbula</p>
                                <div class="flex flex-wrap gap-x-1 gap-y-1 mb-2">
                                    <template v-for="n in [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38]" :key="n">
                                        <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                            <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                                @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                            <span>{{ toDot(n) }}</span>
                                        </label>
                                        <span v-if="n===41" class="text-gray-400 mx-0.5">|</span>
                                    </template>
                                </div>
                            </template>
                            <p class="font-semibold text-gray-700 mb-1">Dientes Temporales</p>
                            <p class="text-gray-500 mb-0.5">Maxilar</p>
                            <div class="flex flex-wrap gap-x-1 gap-y-1 mb-1">
                                <template v-for="n in [55,54,53,52,51,61,62,63,64,65]" :key="n">
                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                        <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                            @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                        <span>{{ toDot(n) }}</span>
                                    </label>
                                    <span v-if="n===51" class="text-gray-400 mx-0.5">|</span>
                                </template>
                            </div>
                            <p class="text-gray-500 mb-0.5">Mandíbula</p>
                            <div class="flex flex-wrap gap-x-1 gap-y-1 mb-1">
                                <template v-for="n in [85,84,83,82,81,71,72,73,74,75]" :key="n">
                                    <label class="flex items-center gap-0.5 cursor-pointer select-none">
                                        <input type="checkbox" :checked="isPiezaSelected(exam.id, n)"
                                            @change="togglePieza(exam.id, n)" class="accent-blue-600 cursor-pointer" />
                                        <span>{{ toDot(n) }}</span>
                                    </label>
                                    <span v-if="n===81" class="text-gray-400 mx-0.5">|</span>
                                </template>
                            </div>
                            <p v-if="selectedPiezas[exam.id]?.length" class="text-blue-600 mt-1">
                                Seleccionadas: {{ selectedPiezas[exam.id].map(toDot).join(', ') }}
                            </p>
                        </div>

                        <FileUpload :name="`archivos_nuevo_${exam.id}`" mode="basic"
                            accept="image/*,.dcm,.dicom,.pdf,.zip,.rar" :multiple="true"
                            chooseLabel="Adjuntar archivos" class="w-full text-xs"
                            @select="(e) => $emit('files', exam.id, e)" />
                        <div v-if="newExamFiles[exam.id]?.length" class="mt-1 text-xs text-green-600">
                            {{ newExamFiles[exam.id].length }} archivo(s)
                        </div>
                    </div>
                </template>
            </template>
            <p v-if="!col.items?.length" class="text-xs text-gray-400 italic py-2">Sin exámenes en esta categoría.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';

const props = defineProps({
    col:           Object,
    nuevosExamenes: Array,
    newExamFiles:  Object,
    yaExiste:      Function,
    examLabel:     Function,
    stripSuffix:   Function,
});

const emit = defineEmits(['toggle', 'files', 'piezas']);

const localSelected = computed({
    get: () => props.nuevosExamenes,
    set: (val) => emit('toggle', val),
});

const selectedPiezas = reactive({});

function isUnitaria(label) { return label?.toLowerCase().includes('unitaria'); }
function isNino(label)     { return /niño|nino/i.test(label); }
function toDot(n)          { const s = String(n); return s[0] + '.' + s[1]; }

function isPiezaSelected(examId, n) { return selectedPiezas[examId]?.includes(n) ?? false; }

function togglePieza(examId, n) {
    if (!selectedPiezas[examId]) selectedPiezas[examId] = [];
    const idx = selectedPiezas[examId].indexOf(n);
    if (idx >= 0) selectedPiezas[examId].splice(idx, 1);
    else selectedPiezas[examId].push(n);
    emit('piezas', examId, [...selectedPiezas[examId]]);
}
</script>
