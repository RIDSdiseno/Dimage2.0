<template>
    <AppLayout title="Tipos de Examen - Admin">
        <div class="p-6">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-sm">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-sm text-gray-600">Tipos de Examen</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Tipos de Examen</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} tipo(s) encontrado(s)</p>
                </div>
                <Link :href="route('admin.examenes.create')">
                    <Button label="Crear tipo" icon="pi pi-plus" style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Filtros -->
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5 flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="search" placeholder="Nombre del examen..." class="w-full" @input="onSearch" />
                    </IconField>
                </div>
                <div class="min-w-52">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Grupo</label>
                    <Select
                        v-model="grupo"
                        :options="grupoOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Todos los grupos"
                        class="w-full"
                        showClear
                        @change="fetchExamenes(1)"
                    />
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-16">#</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Descripción</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Grupo</th>
                            <th class="w-24 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-8" /></td>
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-56" /></td>
                                <td class="px-5 py-3.5"><div class="h-5 bg-gray-100 rounded-full w-20" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>
                        <template v-else-if="examenes.length">
                            <tr v-for="e in examenes" :key="e.id" class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 text-gray-400 text-xs">{{ e.id }}</td>
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ examLabel(e.descipcion) }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="grupoBadge(e.group)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ grupos[e.group] ?? `Grupo ${e.group}` }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Link :href="route('admin.examenes.edit', e.id)">
                                        <Button icon="pi pi-pencil" size="small" text v-tooltip.top="'Editar'" />
                                    </Link>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="4" class="px-5 py-16 text-center">
                                <i class="pi pi-file-edit text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No se encontraron tipos de examen.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Mostrando {{ examenes.length }} de {{ total }}</span>
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
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Paginator from 'primevue/paginator';
import Message from 'primevue/message';
import { useTerms } from '@/composables/useTerms.js';

const { examLabel } = useTerms();

const props = defineProps({
    grupos: Object,
});

const examenes    = ref([]);
const loading     = ref(true);
const total       = ref(0);
const currentPage = ref(1);
const perPage     = ref(15);
const search      = ref('');
const grupo       = ref(null);
let   searchTimer = null;

const grupoOptions = Object.entries(props.grupos).map(([k, v]) => ({ value: Number(k), label: v }));

const grupoBadge = (group) => {
    const map = {
        1: 'bg-blue-50 text-blue-700',
        2: 'bg-green-50 text-green-700',
        3: 'bg-purple-50 text-purple-700',
        4: 'bg-orange-50 text-orange-700',
    };
    return map[group] ?? 'bg-gray-100 text-gray-600';
};

const fetchExamenes = async (page = currentPage.value) => {
    loading.value = true;
    try {
        const params = new URLSearchParams({ search: search.value, page });
        if (grupo.value !== null && grupo.value !== undefined) params.append('grupo', grupo.value);
        const res  = await fetch(route('admin.examenes.search') + '?' + params);
        const json = await res.json();
        examenes.value    = json.data;
        total.value       = json.total;
        currentPage.value = json.current_page;
    } finally {
        loading.value = false;
    }
};

const onSearch = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    searchTimer = setTimeout(() => fetchExamenes(1), 350);
};

const onPageChange = (e) => {
    fetchExamenes(e.page + 1);
};

onMounted(() => fetchExamenes(1));
</script>
