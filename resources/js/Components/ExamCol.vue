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
                <div v-if="localSelected.includes(exam.id)" class="ml-6 mt-1 mb-2">
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
import { computed } from 'vue';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';

const props = defineProps({
    col:       Object,
    selected:  Array,
    examFiles: Object,
    examLabel: Function,
    stripSuffix: Function,
});

const emit = defineEmits(['toggle', 'files']);

// Two-way binding shim — reads from props.selected, writes via parent's v-model
const localSelected = computed({
    get: () => props.selected,
    set: (val) => emit('toggle', val),
});
</script>
