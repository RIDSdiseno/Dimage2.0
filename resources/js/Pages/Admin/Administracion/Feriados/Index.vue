<template>
    <AppLayout title="Feriados">
        <div class="p-6">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-gray-400 text-xs">Administración</span>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">Feriados</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Feriados</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} feriados registrados</p>
                </div>
                <Link :href="route('admin.feriados.create')">
                    <Button label="Crear Feriado" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Filtro -->
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters.search" placeholder="Buscar por descripción..."
                        class="w-full" @input="onSearch" />
                </IconField>
            </div>

            <!-- Tabla -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Fecha</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Descripción</th>
                            <th class="w-28 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="n in 5" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-56" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>
                        <template v-else-if="feriados.length">
                            <tr v-for="f in feriados" :key="f.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 font-mono text-gray-700">{{ formatFecha(f.fecha) }}</td>
                                <td class="px-5 py-3.5 text-gray-800">{{ f.descripcion }}</td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="route('admin.feriados.edit', f.id)">
                                            <Button icon="pi pi-pencil" size="small" text v-tooltip.top="'Editar'" />
                                        </Link>
                                        <Button icon="pi pi-trash" size="small" text severity="danger"
                                            v-tooltip.top="'Eliminar'" @click="eliminar(f.id)" />
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="3" class="px-5 py-16 text-center">
                                <i class="pi pi-calendar text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No se encontraron feriados.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Página {{ currentPage }} de {{ totalPages }}</span>
                    <div class="flex gap-2">
                        <Button label="Anterior" icon="pi pi-chevron-left" size="small"
                            :disabled="currentPage <= 1" @click="changePage(currentPage - 1)"
                            severity="secondary" text />
                        <Button label="Siguiente" icon="pi pi-chevron-right" iconPos="right" size="small"
                            :disabled="currentPage >= totalPages" @click="changePage(currentPage + 1)"
                            severity="secondary" text />
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
import Message from 'primevue/message';

const props = defineProps({
    feriados:    Array,
    total:       Number,
    currentPage: Number,
    perPage:     Number,
    filters:     Object,
});

const loading  = ref(false);
const filters  = ref({ search: props.filters?.search ?? '' });
let   debounce = null;

const totalPages = computed(() => Math.ceil(props.total / props.perPage));

const formatFecha = (f) => {
    if (!f) return '—';
    const [y, m, d] = f.split('-');
    return `${d}/${m}/${y}`;
};

const applyFilters = (page = 1) => {
    loading.value = true;
    router.get(route('admin.feriados'), { search: filters.value.search, page }, {
        preserveState: true, replace: true, onFinish: () => { loading.value = false; },
    });
};

const onSearch = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(1), 350);
};

const changePage = (page) => applyFilters(page);

const eliminar = (id) => {
    if (!confirm('¿Confirma eliminar este feriado?')) return;
    router.delete(route('admin.feriados.destroy', id), { preserveScroll: true });
};
</script>
