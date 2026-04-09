<template>
    <AppLayout title="Órdenes">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Órdenes Radiográficas</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} órdenes encontradas</p>
                </div>
                <Link :href="route('ordenes.create')">
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
                        placeholder="Buscar por paciente, RUT, clínica, odontólogo..."
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

            <!-- Tabla con skeleton -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-16">#</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Paciente</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Clínica</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden xl:table-cell">Odontólogo</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden xl:table-cell">Radiólogo</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Examen</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-24 hidden md:table-cell">Creada</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-28">Estado</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Skeleton -->
                        <template v-if="loading">
                            <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-4 py-3"><div class="h-3 bg-gray-100 rounded w-8" /></td>
                                <td class="px-4 py-3">
                                    <div class="h-3 bg-gray-100 rounded w-32 mb-1.5" />
                                    <div class="h-2.5 bg-gray-100 rounded w-20" />
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-28" /></td>
                                <td class="px-4 py-3 hidden xl:table-cell"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-4 py-3 hidden xl:table-cell"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-4 py-3 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-20" /></td>
                                <td class="px-4 py-3 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-16" /></td>
                                <td class="px-4 py-3"><div class="h-5 bg-gray-100 rounded-full w-20" /></td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </template>

                        <!-- Datos -->
                        <template v-else-if="orders.length">
                            <tr v-for="orden in orders" :key="orden.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition cursor-pointer"
                                @click="goTo(orden.id)">
                                <td class="px-4 py-3 text-gray-400 font-mono text-xs">#{{ orden.id }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800">{{ orden.paciente }}</p>
                                    <p class="text-xs text-gray-400">{{ orden.rut }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">{{ orden.clinica }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden xl:table-cell">{{ orden.odontologo }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden xl:table-cell">{{ orden.radiologos }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs hidden lg:table-cell">{{ orden.tipo_examen }}</td>
                                <td class="px-4 py-3 text-gray-400 text-xs hidden md:table-cell">{{ orden.created_at }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <Tag :severity="orden.estado.color" :value="orden.estado.label" class="text-xs" />
                                        <span v-if="orden.prioridad === 'Urgente'"
                                            class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"
                                            v-tooltip="'Urgente'" />
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <i class="pi pi-chevron-right text-gray-300 text-xs" />
                                </td>
                            </tr>
                        </template>

                        <!-- Empty state -->
                        <tr v-else>
                            <td colspan="9" class="px-4 py-16 text-center">
                                <i class="pi pi-file-edit text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">
                                    {{ filters.q || filters.estado !== null ? 'No se encontraron órdenes con ese filtro.' : 'No hay órdenes disponibles.' }}
                                </p>
                                <Link v-if="!filters.q && filters.estado === null"
                                    :href="route('ordenes.create')"
                                    class="inline-block mt-3 text-sm text-blue-600 hover:underline">
                                    + Crear primera orden
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

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
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Paginator from 'primevue/paginator';

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Mostrar toggle solo para roles que tienen un subconjunto "mis órdenes" diferente al general
const showMisOrdenesToggle = computed(() => {
    const typeId = user.value?.type_id;
    const roles  = user.value?.roles ?? [];
    return [4, 6, 11].includes(typeId)                          // clínica, odontólogo, técnico
        || roles.some(r => ['clinica','odontologo','tecnico'].includes(r));
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

const goTo = (id) => router.visit(route('ordenes.show', id));

onMounted(fetchOrders);
</script>
