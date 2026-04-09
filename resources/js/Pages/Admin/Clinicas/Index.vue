<template>
    <AppLayout title="Clínicas - Admin">
        <div class="p-6">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-sm">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-sm text-gray-600">Clínicas</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Clínicas</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} clínicas encontradas</p>
                </div>
                <Link :href="route('admin.clinicas.create')">
                    <Button label="Crear clínica" icon="pi pi-plus" style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5 flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filters.search" placeholder="Nombre o dirección..." class="w-full" @input="onSearch" />
                    </IconField>
                </div>
                <div class="min-w-52">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Holding</label>
                    <Select v-model="filters.holding_id" :options="holdingOptions" optionLabel="label" optionValue="value"
                        placeholder="Todos los holdings" class="w-full" @change="applyFilters" />
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Holding</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Dirección</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Teléfono</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Staff</th>
                            <th class="w-24 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-40" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-32" /></td>
                                <td class="px-5 py-3.5 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-48" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-20" /></td>
                                <td class="px-5 py-3.5"><div class="h-5 bg-gray-100 rounded-full w-8" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>
                        <template v-else-if="clinicas.length">
                            <tr v-for="c in clinicas" :key="c.id" class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ c.name }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ c.holding_name }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden lg:table-cell">{{ c.address || '-' }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ c.telephoneone || '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                        {{ c.staff_count }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Link :href="route('admin.clinicas.edit', c.id)">
                                        <Button icon="pi pi-pencil" size="small" text v-tooltip.top="'Editar'" />
                                    </Link>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <i class="pi pi-building text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No se encontraron clínicas.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Página {{ currentPage }} de {{ totalPages }}</span>
                    <div class="flex gap-2">
                        <Button label="Anterior" icon="pi pi-chevron-left" size="small" :disabled="currentPage <= 1"
                            @click="changePage(currentPage - 1)" severity="secondary" text />
                        <Button label="Siguiente" icon="pi pi-chevron-right" iconPos="right" size="small"
                            :disabled="currentPage >= totalPages" @click="changePage(currentPage + 1)" severity="secondary" text />
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Message from 'primevue/message';

const props = defineProps({
    clinicas:     Array,
    total:        Number,
    currentPage:  Number,
    perPage:      Number,
    filters:      Object,
    holdingsList: Array,
});

const loading = ref(false);
const filters = ref({ search: props.filters?.search ?? '', holding_id: props.filters?.holding_id ?? '' });
let debounce = null;

const totalPages   = computed(() => Math.ceil(props.total / props.perPage));
const holdingOptions = computed(() => [{ value: '', label: 'Todos los holdings' }, ...props.holdingsList]);

const applyFilters = (page = 1) => {
    loading.value = true;
    router.get(route('admin.clinicas'), {
        search:     filters.value.search,
        holding_id: filters.value.holding_id ?? '',
        page,
    }, { preserveState: true, replace: true, onFinish: () => { loading.value = false; } });
};

const onSearch = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(1), 350);
};

const changePage = (page) => applyFilters(page);
</script>
