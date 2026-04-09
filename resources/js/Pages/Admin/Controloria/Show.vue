<template>
    <AppLayout title="Ver Contraloría">
        <div class="p-6 max-w-3xl mx-auto">

            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <Link :href="route('admin.controloria')">
                        <Button icon="pi pi-arrow-left" text />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2 mb-0.5">
                            <Link :href="route('admin.controloria')" class="text-gray-400 hover:text-blue-600 text-xs">
                                Contraloría
                            </Link>
                            <i class="pi pi-chevron-right text-gray-300 text-xs" />
                            <span class="text-xs text-gray-600">#{{ account.id }}</span>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-800">Contraloría #{{ account.id }}</h1>
                    </div>
                </div>
                <a :href="route('admin.controloria.pdf', account.id)" target="_blank">
                    <Button label="Descargar PDF" icon="pi pi-file-pdf" size="small" severity="secondary" />
                </a>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-5">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Paciente</p>
                    <p class="font-semibold text-gray-800">{{ account.paciente }}</p>
                    <p class="text-sm text-gray-500">{{ account.rut }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Clínica / Contralor</p>
                    <p class="font-semibold text-gray-800">{{ account.clinica }}</p>
                    <p class="text-sm text-gray-500">{{ account.contralor }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Estado</p>
                    <span :class="account.estado ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                        class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold">
                        {{ account.estado ? 'Respondida' : 'Pendiente' }}
                    </span>
                    <p class="text-xs text-gray-400 mt-1">{{ account.created_at }}</p>
                </div>
            </div>

            <!-- Diagnóstico -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-4">
                <h3 class="font-semibold text-gray-700 mb-2">Diagnóstico</h3>
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ account.diagnostico }}</p>
            </div>

            <!-- Observaciones -->
            <div v-if="account.observaciones" class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-4">
                <h3 class="font-semibold text-gray-700 mb-2">Observaciones</h3>
                <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ account.observaciones }}</p>
            </div>

            <!-- Respuesta existente -->
            <div v-if="account.respuesta" class="bg-green-50 rounded-xl border border-green-200 p-5 mb-5">
                <h3 class="font-semibold text-green-800 mb-2 flex items-center gap-2">
                    <i class="pi pi-check-circle" /> Respuesta del Contralor
                </h3>
                <p class="text-sm text-green-700 whitespace-pre-wrap">{{ account.respuesta }}</p>
            </div>

            <!-- Archivos adjuntos -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-4">
                <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="pi pi-paperclip text-blue-500" /> Archivos adjuntos
                </h3>
                <div v-if="account.archives && account.archives.length" class="flex flex-wrap gap-3 mb-4">
                    <div v-for="f in account.archives" :key="f.id"
                        class="relative group flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 hover:border-blue-400 hover:bg-blue-50 text-sm text-gray-700 transition-colors">
                        <a :href="f.url" target="_blank" class="flex items-center gap-2">
                            <i class="pi pi-file text-blue-400" />
                            <span class="max-w-[160px] truncate">{{ f.name }}</span>
                        </a>
                        <button @click="eliminarArchivo(f.id)"
                            class="ml-1 text-gray-300 hover:text-red-500 transition-colors"
                            title="Eliminar archivo">
                            <i class="pi pi-times text-xs" />
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 mb-4">Sin archivos adjuntos.</p>

                <!-- Subir más archivos -->
                <form @submit.prevent="subirArchivos" enctype="multipart/form-data">
                    <div class="flex items-center gap-3">
                        <input type="file" multiple ref="fileInput"
                            class="block text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
                        <Button label="Subir" icon="pi pi-upload" size="small" type="submit"
                            :loading="uploading" severity="secondary" />
                    </div>
                </form>
            </div>

            <!-- Formulario de respuesta -->
            <div v-if="!account.estado" class="bg-white rounded-xl border border-blue-100 shadow-sm p-5">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="pi pi-reply text-blue-500" /> Responder
                </h3>
                <form @submit.prevent="responder">
                    <Textarea v-model="form.respuesta" rows="4" class="w-full mb-3"
                        placeholder="Escriba la respuesta del contralor..." />
                    <Button label="Guardar Respuesta" icon="pi pi-save" type="submit"
                        :loading="form.processing" :disabled="!form.respuesta.trim()"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </form>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';

const props = defineProps({ account: Object });

const form = useForm({ respuesta: '' });
const responder = () => form.post(route('admin.controloria.respond', props.account.id));

const fileInput = ref(null);
const uploading = ref(false);

function eliminarArchivo(id) {
    if (!confirm('¿Eliminar este archivo?')) return;
    router.delete(route('admin.controloria.archivos.destroy', id), { preserveScroll: true });
}

function subirArchivos() {
    const files = fileInput.value?.files;
    if (!files || files.length === 0) return;

    const data = new FormData();
    for (const f of files) data.append('archivos[]', f);

    uploading.value = true;
    router.post(route('admin.controloria.archivos', props.account.id), data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploading.value = false;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>
