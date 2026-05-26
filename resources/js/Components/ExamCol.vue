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
                    <!-- Selector de piezas dentales para exámenes unitarios -->
                    <div v-if="isUnitaria(exam.label)">
                        <p class="text-xs font-medium text-gray-500 mb-1">Piezas dentales <span class="text-gray-400">(selecciona una o más)</span></p>
                        <div class="space-y-1">
                            <div class="flex gap-0.5 flex-wrap">
                                <template v-for="n in toothRow1(exam.label)" :key="n">
                                    <button type="button"
                                        class="w-6 h-6 text-xs rounded border font-mono transition-colors"
                                        :class="isPiezaSelected(exam.id, n)
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:bg-blue-50'"
                                        @click.stop="togglePieza(exam.id, n)">
                                        {{ n }}
                                    </button>
                                </template>
                            </div>
                            <div class="flex gap-0.5 flex-wrap">
                                <template v-for="n in toothRow2(exam.label)" :key="n">
                                    <button type="button"
                                        class="w-6 h-6 text-xs rounded border font-mono transition-colors"
                                        :class="isPiezaSelected(exam.id, n)
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400 hover:bg-blue-50'"
                                        @click.stop="togglePieza(exam.id, n)">
                                        {{ n }}
                                    </button>
                                </template>
                            </div>
                        </div>
                        <p v-if="selectedPiezas[exam.id]?.length" class="text-xs text-blue-600 mt-1">
                            Seleccionadas: {{ selectedPiezas[exam.id].join(', ') }}
                        </p>
                    </div>
                    <FileUpload :name="`files_${exam.id}`" mode="basic" accept="image/*,.dcm,.dicom,.pdf,.zip,.rar"
                        :multiple="true" chooseLabel="Adjuntar archivos" class="w-full text-xs"
                        @select="(e) => $emit('files', exam.id, e)" />
                    <div v-if="examFiles[exam.id]?.length" class="mt-1 text-xs text-green-600">
                        {{ examFiles[exam.id].length }} archivo(s) seleccionado(s)
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

const props = defineProps({
    col:       Object,
    selected:  Array,
    examFiles: Object,
    examLabel: Function,
    stripSuffix: Function,
});

const emit = defineEmits(['toggle', 'files', 'piezas']);

const localSelected = computed({
    get: () => props.selected,
    set: (val) => emit('toggle', val),
});

const selectedPiezas = reactive({});

function isUnitaria(label) {
    return label.toLowerCase().includes('unitaria');
}
function isNino(label) {
    return label.toLowerCase().includes('niño') || label.toLowerCase().includes('nino');
}

// FDI notation: upper row (right→left, then left→right)
function toothRow1(label) {
    return isNino(label)
        ? [55, 54, 53, 52, 51, 61, 62, 63, 64, 65]
        : [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
}
// FDI notation: lower row (right→left, then left→right)
function toothRow2(label) {
    return isNino(label)
        ? [85, 84, 83, 82, 81, 71, 72, 73, 74, 75]
        : [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
}

function isPiezaSelected(examId, n) {
    return selectedPiezas[examId]?.includes(n) ?? false;
}

function togglePieza(examId, n) {
    if (!selectedPiezas[examId]) selectedPiezas[examId] = [];
    const idx = selectedPiezas[examId].indexOf(n);
    if (idx >= 0) selectedPiezas[examId].splice(idx, 1);
    else selectedPiezas[examId].push(n);
    emit('piezas', examId, [...selectedPiezas[examId]]);
}
</script>
