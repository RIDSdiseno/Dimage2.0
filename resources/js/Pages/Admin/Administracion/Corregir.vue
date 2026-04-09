<template>
    <AppLayout title="Corregir Orden">
        <div class="p-6 max-w-5xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-gray-400 text-xs">Administración</span>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">Corregir Orden</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Corregir Orden</h1>
                </div>
            </div>

            <!-- Buscador -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
                <form @submit.prevent="buscar" class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">N° de Orden</label>
                        <InputText v-model="busqueda" placeholder="Ingrese ID de la orden..." class="w-full"
                            :class="{ 'p-invalid': error }" />
                        <small v-if="error" class="text-red-500">{{ error }}</small>
                    </div>
                    <Button label="Buscar" icon="pi pi-search" type="submit" :loading="buscando"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </form>
            </div>

            <!-- Flash -->
            <Message v-if="$page.props.flash?.success" severity="success" class="mb-5">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Orden encontrada -->
            <template v-if="orden">

                <!-- Info orden -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">Paciente</p>
                        <p class="font-semibold text-gray-800">{{ orden.paciente }}</p>
                        <p class="text-sm text-gray-500">{{ orden.rut }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">Clínica / Odontólogo</p>
                        <p class="font-semibold text-gray-800">{{ orden.clinica }}</p>
                        <p class="text-sm text-gray-500">{{ orden.odontologo }}</p>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-2">Estado</p>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                            :class="estadoClass">
                            {{ orden.estado_label }}
                        </span>
                        <p class="text-xs text-gray-400 mt-1">Enviada: {{ orden.enviada ?? '—' }}</p>
                    </div>
                </div>

                <!-- Examenes con archivos -->
                <div v-if="orden.examenes?.length" class="space-y-3 mb-5">
                    <div v-for="ex in orden.examenes" :key="ex.id"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-5 py-2.5 flex items-center justify-between" style="background-color:#0b2a4a;">
                            <span class="font-semibold text-white text-sm">{{ ex.descripcion }}</span>
                            <span class="text-xs text-blue-200">{{ ex.archivos?.length ?? 0 }} archivo(s)</span>
                        </div>
                        <div v-if="ex.archivos?.length" class="p-4 flex flex-wrap gap-2">
                            <a v-for="f in ex.archivos" :key="f.id"
                                :href="route('archivos.serve', f.id)" target="_blank"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-blue-600 hover:bg-blue-50 transition">
                                <i class="pi pi-file text-sm" /> {{ f.name || 'Archivo' }}
                            </a>
                        </div>
                        <p v-else class="px-5 py-3 text-xs text-gray-400 italic">Sin archivos adjuntos.</p>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Enviar a corrección -->
                    <div class="bg-white rounded-xl border border-orange-100 shadow-sm p-5">
                        <h3 class="font-semibold text-gray-800 mb-1 flex items-center gap-2">
                            <i class="pi pi-exclamation-triangle text-orange-500" /> Enviar a Corrección
                        </h3>
                        <p class="text-xs text-gray-400 mb-3">El radiólogo deberá corregir el informe.</p>
                        <form @submit.prevent="enviarCorreccion">
                            <Textarea v-model="mensajeCorreccion" rows="3" class="w-full mb-3"
                                placeholder="Detalle del motivo de corrección..." />
                            <Button label="Enviar a Corrección" icon="pi pi-send" type="submit"
                                :loading="enviandoCorreccion" severity="warning" class="w-full"
                                :disabled="!mensajeCorreccion.trim()" />
                        </form>
                    </div>

                    <!-- Cambiar a No Informada -->
                    <div class="bg-white rounded-xl border border-red-100 shadow-sm p-5">
                        <h3 class="font-semibold text-gray-800 mb-1 flex items-center gap-2">
                            <i class="pi pi-refresh text-red-500" /> Cambiar a No Informada
                        </h3>
                        <p class="text-xs text-gray-400 mb-3">Reinicia la orden al estado inicial pendiente.</p>
                        <Button label="Cambiar a No Informada" icon="pi pi-undo" type="button"
                            @click="cambiarNoInformada"
                            :loading="cambiandoEstado" severity="danger" class="w-full" />
                    </div>

                </div>
            </template>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';

const props = defineProps({ orden: Object });

const busqueda          = ref(props.orden?.id ?? '');
const error             = ref('');
const buscando          = ref(false);
const mensajeCorreccion = ref('');
const enviandoCorreccion = ref(false);
const cambiandoEstado   = ref(false);

const estadoClass = computed(() => {
    const e = props.orden?.estadoradiologo;
    if (e == 1) return 'bg-green-100 text-green-700';
    if (e == 2) return 'bg-orange-100 text-orange-700';
    return 'bg-amber-100 text-amber-700';
});

function buscar() {
    if (!busqueda.value) { error.value = 'Ingrese un número de orden.'; return; }
    error.value = '';
    buscando.value = true;
    router.get(route('admin.administracion.corregir'), { orden_id: busqueda.value.toString() }, {
        preserveState: false,
        onFinish: () => { buscando.value = false; },
    });
}

function enviarCorreccion() {
    enviandoCorreccion.value = true;
    router.post(route('admin.administracion.enviar-correccion'), {
        orden_id: props.orden.id,
        mensaje:  mensajeCorreccion.value,
    }, {
        onSuccess: () => { mensajeCorreccion.value = ''; },
        onFinish:  () => { enviandoCorreccion.value = false; },
    });
}

function cambiarNoInformada() {
    if (!confirm('¿Confirma cambiar la orden a estado "No Informada"?')) return;
    cambiandoEstado.value = true;
    router.post(route('admin.administracion.no-informada'), {
        orden_id: props.orden.id,
    }, {
        onFinish: () => { cambiandoEstado.value = false; },
    });
}
</script>
