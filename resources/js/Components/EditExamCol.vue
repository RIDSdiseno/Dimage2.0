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
                    <div v-if="localSelected.includes(exam.id)" class="ml-6 mt-1 mb-2">
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
import { computed } from 'vue';
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

const emit = defineEmits(['toggle', 'files']);

const localSelected = computed({
    get: () => props.nuevosExamenes,
    set: (val) => emit('toggle', val),
});
</script>
