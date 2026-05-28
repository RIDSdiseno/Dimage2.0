<template>
    <AppLayout title="Órdenes">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Órdenes Radiográficas</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} órdenes encontradas</p>
                </div>
                <Link v-if="!esRadiologoRestringido" :href="route('ordenes.create')">
                    <Button label="Nueva Orden" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3 mb-5 bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <IconField class="flex-1 min-w-60">
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="filters.q"
                        :placeholder="`Buscar por paciente, ${terms.id_label}, clínica, radiólogo...`"
                        class="w-full"
                        @input="onSearch"
                    />
                </IconField>
                <Select
                    v-model="filters.estado"
                    :options="estadoOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Todos los estados"
                    class="w-48"
                    showClear
                    @change="fetchOrders"
                />
                <button
                    v-if="showMisOrdenesToggle"
                    @click="toggleMisOrdenes"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
                    :class="filters.solo_mis
                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                        : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'">
                    <i class="pi pi-user text-xs" />
                    Mis Órdenes
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead style="background-color:#f8fafc;">
                            <tr>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">N°</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ terms.id_label }}</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Clínica</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Paciente</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Radiólogo</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Odontólogo</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Prioridad</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Exámenes</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Ver/Editar</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Creada</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Enviada</th>
                                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Respondida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Skeleton -->
                            <template v-if="loading">
                                <tr v-for="n in 10" :key="n" class="border-t border-gray-50 animate-pulse">
                                    <td v-for="c in 13" :key="c" class="px-3 py-3">
                                        <div class="h-3 bg-gray-100 rounded w-16" />
                                    </td>
                                </tr>
                            </template>

                            <!-- Datos -->
                            <template v-else-if="orders.length">
                                <tr v-for="orden in orders" :key="orden.id"
                                    class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                    <td class="px-3 py-3 text-gray-400 font-mono text-xs">{{ orden.id }}</td>
                                    <td class="px-3 py-3 text-gray-600 text-xs">{{ orden.rut }}</td>
                                    <td class="px-3 py-3 text-gray-600">{{ orden.clinica }}</td>
                                    <td class="px-3 py-3 font-medium text-gray-800">{{ orden.paciente }}</td>
                                    <td class="px-3 py-3 text-gray-500 text-xs max-w-36 truncate">{{ orden.radiologos }}</td>
                                    <td class="px-3 py-3 text-gray-500 text-xs">{{ orden.odontologo }}</td>
                                    <td class="px-3 py-3">
                                        <span :class="prioridadBadgeClass(orden.prioridad)"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold">
                                            {{ orden.prioridad }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-gray-500 text-xs max-w-48 truncate" :title="orden.tipo_examen">
                                        {{ examListLabel(orden.tipo_examen) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <Tag :severity="orden.estado.color" :value="orden.estado.label" class="text-xs" />
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-1">
                                            <Link :href="route('ordenes.show', orden.id)">
                                                <Button icon="pi pi-eye" size="small" text v-tooltip.top="'Ver'" />
                                            </Link>
                                            <Link v-if="!esRadiologo && orden.estado.color !== 'success'"
                                                :href="route('ordenes.edit', orden.id)">
                                                <Button icon="pi pi-pencil" size="small" text severity="secondary"
                                                    v-tooltip.top="'Editar'" />
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-gray-400 text-xs">{{ orden.created_at }}</td>
                                    <td class="px-3 py-3 text-xs"
                                        :class="orden.enviada !== '-' ? 'text-blue-600' : 'text-gray-300'">
                                        {{ orden.enviada }}
                                    </td>
                                    <td class="px-3 py-3 text-xs"
                                        :class="orden.respondida !== '-' ? 'text-green-600 font-medium' : 'text-gray-300'">
                                        {{ orden.respondida }}
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty -->
                            <tr v-else>
                                <td colspan="13" class="px-4 py-16 text-center">
                                    <i class="pi pi-file-edit text-4xl text-gray-200 block mb-3" />
                                    <p class="text-gray-400 font-medium">
                                        {{ filters.q || filters.estado !== null ? 'No se encontraron órdenes con ese filtro.' : 'No hay órdenes disponibles.' }}
                                    </p>
                                    <Link v-if="!filters.q && filters.estado === null && !esRadiologoRestringido"
                                        :href="route('ordenes.create')"
                                        class="inline-block mt-3 text-sm text-blue-600 hover:underline">
                                        + Crear primera orden
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Mostrando {{ orders.length }} de {{ total }}</span>
                    <Paginator
                        :rows="perPage"
                        :totalRecords="total"
                        :first="(currentPage - 1) * perPage"
                        @page="onPageChange"
                        :pt="{ root: { class: 'text-sm' } }"
                    />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTerms } from '@/composables/useTerms.js';

const { terms, examListLabel } = useTerms();
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Paginator from 'primevue/paginator';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const esRadiologo = computed(() =>
    user.value?.roles?.includes('radiologo') || user.value?.type_id === 5
);

const esRadiologoRestringido = computed(() => esRadiologo.value);

function prioridadBadgeClass(p) {
    if (p === '1 día' || p === 'Urgente') return 'bg-red-100 text-red-700';
    if (p === '2 días') return 'bg-orange-100 text-orange-700';
    return 'bg-gray-100 text-gray-600';
}

const showMisOrdenesToggle = computed(() => {
    const typeId = user.value?.type_id;
    const roles  = user.value?.roles ?? [];
    return [4, 6, 11].includes(typeId)
        || roles.some(r => ['clinica', 'odontologo', 'tecnico'].includes(r));
});

const orders      = ref([]);
const loading     = ref(true);
const total       = ref(0);
const currentPage = ref(1);
const perPage     = ref(15);
let   searchTimer = null;

const filters = reactive({ q: '', estado: null, solo_mis: false });

const estadoOptions = [
    { label: 'No Informada', value: 0 },
    { label: 'Informada',    value: 1 },
    { label: 'Corrección',   value: 2 },
    { label: 'Guardada',     value: 4 },
];

const fetchOrders = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ q: filters.q, page: currentPage.value, per_page: perPage.value });
        if (filters.estado !== null && filters.estado !== undefined) params.append('estado', filters.estado);
        if (filters.solo_mis) params.append('solo_mis', '1');
        const res  = await fetch(route('ordenes.search') + '?' + params);
        const json = await res.json();
        orders.value = json.data;
        total.value  = json.total;
    } finally {
        loading.value = false;
    }
};

const toggleMisOrdenes = () => {
    filters.solo_mis  = !filters.solo_mis;
    currentPage.value = 1;
    fetchOrders();
};

const onSearch = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    searchTimer = setTimeout(fetchOrders, 350);
};

const onPageChange = (e) => {
    currentPage.value = e.page + 1;
    fetchOrders();
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('mis') === '1') {
        filters.solo_mis = true;
    }
    fetchOrders();
});
</script>
