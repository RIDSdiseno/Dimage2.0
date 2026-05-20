<template>
    <AppLayout title="Gestión de Estado">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-gray-400 text-xs">Administración</span>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">Gestión de Estado</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Gestión de Estado de Órdenes</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} órdenes encontradas</p>
                </div>
            </div>

            <!-- Flash -->
            <Message v-if="$page.props.flash?.success" severity="success" class="mb-5">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3 mb-5 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <IconField class="flex-1 min-w-60">
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters.q" placeholder="Buscar por paciente, RUT, clínica, radiólogo, N° orden..."
                        class="w-full" @input="onSearch" />
                </IconField>
                <Select v-model="filters.estado" :options="estadoOptions" optionLabel="label" optionValue="value"
                    placeholder="Todos los estados" class="w-48" showClear @change="applyFilters(1)" />
                <Select v-model="filters.radiologo_id" :options="radiologos" optionLabel="name" optionValue="id"
                    placeholder="Todos los radiólogos" class="w-56" showClear @change="applyFilters(1)" />
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead style="background-color:#f8fafc;">
                            <tr>
                                <th class="px-3 py-3 text-left">
                                    <input type="checkbox" :checked="allSelected" :indeterminate="someSelected"
                                        @change="toggleAll" class="rounded" />
                                </th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">N°</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Paciente</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Clínica</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Radiólogo</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Odontólogo</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Enviada</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Respondida</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="loading">
                                <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                    <td v-for="c in 10" :key="c" class="px-3 py-3">
                                        <div class="h-3 bg-gray-100 rounded w-16" />
                                    </td>
                                </tr>
                            </template>
                            <template v-else-if="orders.length">
                                <tr v-for="orden in orders" :key="orden.id"
                                    class="border-t border-gray-50 hover:bg-blue-50/30 transition"
                                    :class="{ 'bg-blue-50/50': selected.includes(orden.id) }">
                                    <td class="px-3 py-3">
                                        <input type="checkbox" :value="orden.id" v-model="selected" class="rounded" />
                                    </td>
                                    <td class="px-3 py-3 text-gray-400 font-mono text-xs">{{ orden.id }}</td>
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-gray-800 text-xs">{{ orden.paciente }}</p>
                                        <p class="text-gray-400 text-xs">{{ orden.rut }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-gray-600 text-xs">{{ orden.clinica }}</td>
                                    <td class="px-3 py-3 text-gray-500 text-xs">{{ orden.radiologo ?? '—' }}</td>
                                    <td class="px-3 py-3 text-gray-500 text-xs">{{ orden.odontologo ?? '—' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                            :class="estadoBadge(orden.estadoradiologo)">
                                            {{ estadoLabel(orden.estadoradiologo) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-xs text-blue-600">{{ formatDate(orden.enviada) }}</td>
                                    <td class="px-3 py-3 text-xs"
                                        :class="orden.respondida ? 'text-green-600 font-medium' : 'text-gray-300'">
                                        {{ formatDate(orden.respondida) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-1">
                                            <Button icon="pi pi-undo" size="small" severity="danger" text
                                                v-tooltip.top="'No Informada'"
                                                @click="accionIndividual(orden.id, 0)" />
                                            <Button icon="pi pi-exclamation-triangle" size="small" severity="warning" text
                                                v-tooltip.top="'Corrección'"
                                                @click="pedirCorreccion(orden.id)" />
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="10" class="px-4 py-16 text-center">
                                    <i class="pi pi-file-edit text-4xl text-gray-200 block mb-3" />
                                    <p class="text-gray-400 font-medium">No se encontraron órdenes.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Página {{ currentPage }} de {{ totalPages }}</span>
                    <div class="flex gap-2">
                        <Button label="Anterior" icon="pi pi-chevron-left" size="small" severity="secondary" text
                            :disabled="currentPage <= 1" @click="changePage(currentPage - 1)" />
                        <Button label="Siguiente" icon="pi pi-chevron-right" iconPos="right" size="small" severity="secondary" text
                            :disabled="currentPage >= totalPages" @click="changePage(currentPage + 1)" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel flotante de acciones masivas -->
        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-full opacity-0"
            leave-active-class="transition duration-150" leave-to-class="translate-y-full opacity-0">
            <div v-if="selected.length > 0"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-white border border-gray-200 rounded-2xl shadow-2xl p-4 flex flex-wrap items-center gap-4 min-w-96">

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-white text-sm font-bold"
                        style="background-color:#3452ff;">{{ selected.length }}</span>
                    <span class="text-sm font-semibold text-gray-700">orden(es) seleccionada(s)</span>
                </div>

                <div class="flex items-center gap-2">
                    <Button label="No Informada" icon="pi pi-undo" severity="danger"
                        :loading="enviando" @click="accionMasiva(0)" />
                    <Button label="Corrección" icon="pi pi-exclamation-triangle" severity="warning"
                        :loading="enviando" @click="mostrarModalCorreccion = true" />
                    <Button label="Cancelar" icon="pi pi-times" severity="secondary" text
                        @click="selected = []" />
                </div>
            </div>
        </Transition>

        <!-- Modal corrección -->
        <Dialog v-model:visible="mostrarModalCorreccion" header="Enviar a Corrección" :modal="true"
            :style="{ width: '480px' }" :closable="!enviando">
            <p class="text-sm text-gray-500 mb-3">
                Se enviará a corrección {{ modalOrdenIds.length || selected.length }} orden(es).
                Ingrese el motivo:
            </p>
            <Textarea v-model="mensajeCorreccion" rows="4" class="w-full mb-1"
                placeholder="Detalle el motivo de corrección..." />
            <small v-if="errorMensaje" class="text-red-500">{{ errorMensaje }}</small>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="cerrarModal" :disabled="enviando" />
                <Button label="Enviar a Corrección" icon="pi pi-send" severity="warning"
                    :loading="enviando" @click="confirmarCorreccion" />
            </template>
        </Dialog>

    </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Message from 'primevue/message';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

const props = defineProps({
    orders:      Array,
    total:       Number,
    currentPage: Number,
    perPage:     Number,
    filters:     Object,
    radiologos:  Array,
    estados:     Object,
});

// ── Filtros ──────────────────────────────────────────────────────────────

const filters  = reactive({
    q:           props.filters?.q ?? '',
    estado:      props.filters?.estado ?? null,
    radiologo_id: props.filters?.radiologo_id ? Number(props.filters.radiologo_id) : null,
});
const loading  = ref(false);
let   debounce = null;

const estadoOptions = computed(() => [
    { label: 'No Informada', value: 0 },
    { label: 'Informada',    value: 1 },
    { label: 'Corrección',   value: 2 },
    { label: 'Guardada',     value: 4 },
]);

const totalPages = computed(() => Math.ceil(props.total / props.perPage));

function applyFilters(page = 1) {
    loading.value = true;
    router.get(route('admin.administracion.gestion-estado'), {
        q:           filters.q || undefined,
        estado:      filters.estado !== null ? filters.estado : undefined,
        radiologo_id: filters.radiologo_id || undefined,
        page,
    }, {
        preserveState: true, replace: true,
        onFinish: () => { loading.value = false; },
    });
}

function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(1), 350);
}

function changePage(page) { applyFilters(page); }

// ── Selección ────────────────────────────────────────────────────────────

const selected = ref([]);

const allSelected  = computed(() => props.orders.length > 0 && props.orders.every(o => selected.value.includes(o.id)));
const someSelected = computed(() => selected.value.some(id => props.orders.some(o => o.id === id)) && !allSelected.value);

function toggleAll() {
    if (allSelected.value) {
        selected.value = selected.value.filter(id => !props.orders.some(o => o.id === id));
    } else {
        const pageIds = props.orders.map(o => o.id);
        selected.value = [...new Set([...selected.value, ...pageIds])];
    }
}

// ── Acciones ─────────────────────────────────────────────────────────────

const enviando             = ref(false);
const mostrarModalCorreccion = ref(false);
const mensajeCorreccion    = ref('');
const errorMensaje         = ref('');
const modalOrdenIds        = ref([]);

function estadoLabel(e) {
    const map = { 0: 'No Informada', 1: 'Informada', 2: 'Corrección', 4: 'Guardada' };
    return map[e] ?? 'Desconocido';
}

function estadoBadge(e) {
    if (e == 1) return 'bg-green-100 text-green-700';
    if (e == 2) return 'bg-orange-100 text-orange-700';
    if (e == 4) return 'bg-gray-100 text-gray-500';
    return 'bg-amber-100 text-amber-700';
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function accionIndividual(id, nuevoEstado) {
    if (!confirm(`¿Confirma cambiar la orden #${id} a "${estadoLabel(nuevoEstado)}"?`)) return;
    enviando.value = true;
    router.post(route('admin.administracion.cambiar-estado'), {
        orden_ids:    [id],
        nuevo_estado: nuevoEstado,
    }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
        onFinish:  () => { enviando.value = false; },
    });
}

function pedirCorreccion(id) {
    modalOrdenIds.value     = [id];
    mensajeCorreccion.value = '';
    errorMensaje.value      = '';
    mostrarModalCorreccion.value = true;
}

function accionMasiva(nuevoEstado) {
    const ids = selected.value;
    if (!ids.length) return;
    if (!confirm(`¿Confirma cambiar ${ids.length} orden(es) a "${estadoLabel(nuevoEstado)}"?`)) return;
    enviando.value = true;
    router.post(route('admin.administracion.cambiar-estado'), {
        orden_ids:    ids,
        nuevo_estado: nuevoEstado,
    }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
        onFinish:  () => { enviando.value = false; },
    });
}

function confirmarCorreccion() {
    if (!mensajeCorreccion.value.trim() || mensajeCorreccion.value.trim().length < 5) {
        errorMensaje.value = 'Ingrese al menos 5 caracteres.';
        return;
    }
    errorMensaje.value = '';
    enviando.value     = true;
    const ids = modalOrdenIds.value.length ? modalOrdenIds.value : selected.value;
    router.post(route('admin.administracion.cambiar-estado'), {
        orden_ids:    ids,
        nuevo_estado: 2,
        mensaje:      mensajeCorreccion.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; cerrarModal(); },
        onFinish:  () => { enviando.value = false; },
    });
}

function cerrarModal() {
    mostrarModalCorreccion.value = false;
    mensajeCorreccion.value      = '';
    modalOrdenIds.value          = [];
    errorMensaje.value           = '';
}
</script>
