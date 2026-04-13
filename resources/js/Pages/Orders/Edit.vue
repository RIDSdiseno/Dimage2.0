<template>
    <AppLayout title="Editar Orden">
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
                                    :options="['Normal', 'Urgente']"
                                    class="w-full"
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
                                <div v-if="examen.archivos.length" class="flex flex-wrap gap-2 mb-3">
                                    <div v-for="f in examen.archivos" :key="f.id"
                                        class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 rounded px-2 py-1 text-xs text-gray-600">
                                        <i :class="f.extension === 'pdf' ? 'pi pi-file-pdf text-red-400' : 'pi pi-image text-blue-400'" />
                                        <span class="max-w-32 truncate">{{ f.name }}</span>
                                        <a v-if="f.url" :href="f.url" target="_blank"
                                            class="text-blue-500 hover:text-blue-700 ml-1">
                                            <i class="pi pi-external-link" style="font-size:10px" />
                                        </a>
                                    </div>
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
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide mb-4" style="color:#3452ff">
                            <i class="pi pi-plus-circle mr-2" />Agregar Nuevos Exámenes
                        </h2>
                        <div v-for="group in examTypes" :key="group.label" class="mb-5">
                            <p class="text-xs font-bold uppercase text-gray-400 tracking-widest mb-2">{{ group.label }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template v-for="exam in group.items" :key="exam.id">
                                <div v-if="!yaExiste(exam.id)"
                                    class="border rounded-lg p-3 transition"
                                    :class="isSelected(exam.id) ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                                    <div class="flex items-center gap-2 mb-2">
                                        <Checkbox :inputId="`new_${exam.id}`" :value="exam.id" v-model="nuevosExamenes" />
                                        <label :for="`new_${exam.id}`" class="text-sm cursor-pointer">{{ examLabel(exam.label) }}</label>
                                    </div>
                                    <div v-if="isSelected(exam.id)" class="mt-2">
                                        <FileUpload
                                            :name="`archivos_nuevo_${exam.id}`"
                                            mode="basic"
                                            accept="image/*,.dcm,.pdf"
                                            :multiple="true"
                                            chooseLabel="Adjuntar imágenes"
                                            class="w-full text-xs"
                                            @select="(e) => onNewFilesSelect(exam.id, e)"
                                        />
                                        <div v-if="newExamFiles[exam.id]?.length" class="mt-1 text-xs text-gray-500">
                                            {{ newExamFiles[exam.id].length }} archivo(s)
                                        </div>
                                    </div>
                                </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3">
                        <Link :href="route('ordenes.show', order.id)">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            label="Guardar Cambios"
                            icon="pi pi-save"
                            severity="secondary"
                            type="button"
                            :loading="submitting && currentAction === 'guardar'"
                            @click="submitAction('guardar')"
                        />
                        <Button
                            label="Guardar y Enviar"
                            icon="pi pi-send"
                            type="button"
                            :loading="submitting && currentAction === 'enviar'"
                            :disabled="!form.radiologo_id"
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
import { ref, reactive, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';
import { useTerms } from '@/composables/useTerms.js';

const { examLabel } = useTerms();

const props = defineProps({
    order:     Object,
    examenes:  Array,
    examTypes: Array,
    clinics:   Array,
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
