<template>
    <AppLayout title="Responder Orden">
        <div class="p-6 max-w-4xl mx-auto">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('ordenes.show', order.id)">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Responder Orden #{{ order.id }}</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Completa el informe para cada examen</p>
                </div>
            </div>

            <!-- Info cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 mb-1">Paciente</p>
                    <p class="font-semibold text-gray-800">{{ paciente?.name }}</p>
                    <p class="text-sm text-gray-500">{{ paciente?.rut }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 mb-1">Clínica</p>
                    <p class="font-semibold text-gray-800">{{ clinica }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 mb-1">Prioridad</p>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                        :class="order.prioridad === 'Urgente' ? 'bg-red-100 text-red-700' : 'bg-blue-50 text-blue-700'">
                        <i v-if="order.prioridad === 'Urgente'" class="pi pi-exclamation-circle text-xs" />
                        {{ order.prioridad }}
                    </span>
                    <p class="text-xs text-gray-400 mt-1">Enviada: {{ order.enviada }}</p>
                </div>
            </div>

            <!-- Diagnóstico -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6">
                <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-1">Diagnóstico clínico</p>
                <p class="text-sm text-gray-700">{{ order.diagnostico }}</p>
                <p v-if="order.observaciones" class="text-sm text-gray-500 mt-1">
                    <span class="font-medium">Obs:</span> {{ order.observaciones }}
                </p>
            </div>

            <!-- Error general -->
            <div v-if="form.errors?.general" class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-700">
                {{ form.errors.general }}
            </div>

            <!-- Examenes -->
            <form @submit.prevent="submit">
                <div class="space-y-5">

                    <div v-for="(ex, idx) in examenes" :key="ex.id"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

                        <!-- Exam header -->
                        <div class="flex items-center justify-between px-5 py-3"
                            style="background-color:#0b2a4a;">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-blue-500 text-white text-xs flex items-center justify-center font-bold">
                                    {{ idx + 1 }}
                                </span>
                                <span class="font-semibold text-white text-sm">{{ examLabel(ex.descripcion) }}</span>
                            </div>
                            <span class="text-xs text-blue-200">Grupo {{ ex.grupo }}</span>
                        </div>

                        <div class="p-5 space-y-4">
                            <!-- Archivos existentes -->
                            <div v-if="ex.archivos?.length">
                                <p class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">
                                    <i class="pi pi-paperclip mr-1" />Archivos de imagen ({{ ex.archivos.length }})
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <a v-for="f in ex.archivos" :key="f.id"
                                        :href="route('archivos.serve', f.id)" target="_blank"
                                        class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-blue-600 hover:bg-blue-50 transition-colors">
                                        <i :class="getFileIcon(f.extension)" class="text-sm" />
                                        {{ f.name || 'Archivo' }}
                                    </a>
                                </div>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic">Sin archivos de imagen adjuntos.</div>

                            <!-- Informe text -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Informe radiológico *
                                </label>
                                <Textarea
                                    v-model="respuestas[idx].texto"
                                    :placeholder="`Ingrese el informe para ${examLabel(ex.descripcion)}...`"
                                    rows="5"
                                    class="w-full"
                                    :class="{'p-invalid': !!respuestaErrors[idx]}"
                                    autoResize
                                />
                                <small v-if="respuestaErrors[idx]" class="text-red-500">{{ respuestaErrors[idx] }}</small>
                            </div>

                            <!-- Upload informe files -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    <i class="pi pi-upload mr-1 text-xs" />Adjuntar archivos del informe
                                    <span class="text-gray-400 font-normal ml-1">(opcional)</span>
                                </label>
                                <input
                                    type="file"
                                    :name="`archivos_${ex.id}[]`"
                                    multiple
                                    @change="onFileChange(idx, $event)"
                                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                                <div v-if="fileNames[idx]?.length" class="flex flex-wrap gap-1 mt-1.5">
                                    <span v-for="name in fileNames[idx]" :key="name"
                                        class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs">
                                        {{ name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 mt-6">
                    <Link :href="route('ordenes.show', order.id)">
                        <Button type="button" label="Cancelar" severity="secondary" />
                    </Link>
                    <Button type="submit" :loading="submitting"
                        label="Enviar Informe"
                        icon="pi pi-check"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </div>
            </form>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import { useTerms } from '@/composables/useTerms.js';

const { examLabel } = useTerms();

const props = defineProps({
    order:    Object,
    paciente: Object,
    clinica:  String,
    examenes: Array,
});

// Build respuestas array from existing answers
const respuestas = reactive(
    props.examenes.map(ex => ({
        id:    ex.id,
        texto: ex.respuesta?.texto ?? '',
    }))
);

const respuestaErrors = reactive(props.examenes.map(() => ''));
const fileNames = reactive(props.examenes.map(() => []));
const uploadedFiles = reactive(props.examenes.map(() => []));
const submitting = ref(false);

// Keep form errors reactive
const form = reactive({ errors: {} });

function onFileChange(idx, event) {
    const files = Array.from(event.target.files);
    uploadedFiles[idx] = files;
    fileNames[idx] = files.map(f => f.name);
}

function getFileIcon(ext) {
    if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) return 'pi pi-image';
    if (ext === 'pdf') return 'pi pi-file-pdf';
    if (['dcm', 'dicom'].includes(ext)) return 'pi pi-chart-bar';
    return 'pi pi-file';
}

function validate() {
    let ok = true;
    respuestas.forEach((r, i) => {
        if (!r.texto || r.texto.trim().length < 5) {
            respuestaErrors[i] = 'El informe debe tener al menos 5 caracteres.';
            ok = false;
        } else {
            respuestaErrors[i] = '';
        }
    });
    return ok;
}

function submit() {
    if (!validate()) return;

    submitting.value = true;

    const data = new FormData();
    respuestas.forEach((r, i) => {
        data.append(`respuestas[${i}][id]`,    r.id);
        data.append(`respuestas[${i}][texto]`, r.texto);

        uploadedFiles[i]?.forEach(file => {
            data.append(`archivos_${r.id}[]`, file);
        });
    });

    router.post(route('ordenes.doResponder', props.order.id), data, {
        forceFormData: true,
        onError:  (errors) => { form.errors = errors; submitting.value = false; },
        onFinish: () => { submitting.value = false; },
    });
}
</script>
