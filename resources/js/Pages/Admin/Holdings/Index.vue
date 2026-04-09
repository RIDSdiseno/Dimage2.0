<template>
    <AppLayout title="Holdings - Admin">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 transition text-sm">
                            Administración
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-sm text-gray-600">Holdings</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Holdings</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} holdings encontrados</p>
                </div>
                <Link :href="route('admin.holdings.create')">
                    <Button label="Crear holding" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <!-- Flash -->
            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Search -->
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText
                        v-model="search"
                        placeholder="Buscar por nombre, RUT o email..."
                        class="w-full md:w-96"
                        @input="onSearch"
                    />
                </IconField>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">RUT</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Email Responsable</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Teléfono</th>
                            <th class="text-center px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-24">Clínicas</th>
                            <th class="w-16 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Skeleton -->
                        <template v-if="loading">
                            <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-40" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-5 py-3.5 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-36" /></td>
                                <td class="px-5 py-3.5 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-20" /></td>
                                <td class="px-5 py-3.5 text-center"><div class="h-3 bg-gray-100 rounded w-6 mx-auto" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>

                        <!-- Data -->
                        <template v-else-if="holdings.length">
                            <tr v-for="h in holdings" :key="h.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ h.name }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell font-mono text-xs">{{ h.rutholding || '-' }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden lg:table-cell">{{ h.emailresponsable || '-' }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden lg:table-cell">{{ h.telefonoresponsable || '-' }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                                        style="background:#eff2ff;color:#3452ff;">
                                        {{ h.clinicas_count }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Link :href="route('admin.holdings.edit', h.id)">
                                        <Button icon="pi pi-pencil" size="small" text
                                            v-tooltip.top="'Editar'" />
                                    </Link>
                                </td>
                            </tr>
                        </template>

                        <!-- Empty state -->
                        <tr v-else>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <i class="pi pi-building text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No se encontraron holdings.</p>
                                <Link :href="route('admin.holdings.create')"
                                    class="inline-block mt-3 text-sm text-blue-600 hover:underline">
                                    + Crear primer holding
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="total > perPage" class="flex justify-between items-center px-5 py-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">
                        Página {{ currentPage }} de {{ totalPages }}
                    </span>
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
    holdings:    Array,
    total:       Number,
    currentPage: Number,
    perPage:     Number,
    filters:     Object,
});

const loading  = ref(false);
const search   = ref(props.filters?.search ?? '');
let   debounce = null;

const totalPages = computed(() => Math.ceil(props.total / props.perPage));

const applyFilters = (page = 1) => {
    loading.value = true;
    router.get(route('admin.holdings'), {
        search: search.value,
        page,
    }, {
        preserveState: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
};

const onSearch  = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(1), 350);
};

const changePage = (page) => applyFilters(page);
</script>
