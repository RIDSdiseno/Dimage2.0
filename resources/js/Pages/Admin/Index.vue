<template>
    <AppLayout title="Administradores">
        <div class="p-6">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Administradores</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} administradores encontrados</p>
                </div>
                <Link :href="route('admin.create')">
                    <Button label="Crear administrador" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters.search" placeholder="Buscar por nombre, username o email..."
                        class="w-full" @input="onSearch" />
                </IconField>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Username</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Email</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Teléfono</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="w-28 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="n in 6" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-36" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-5 py-3.5 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-40" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-20" /></td>
                                <td class="px-5 py-3.5"><div class="h-5 bg-gray-100 rounded-full w-16" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>
                        <template v-else-if="admins.length">
                            <tr v-for="a in admins" :key="a.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ a.name }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell font-mono text-xs">{{ a.username }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden lg:table-cell">{{ a.mail || '-' }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ a.telephone || '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="a.status ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ a.status ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="route('admin.edit', a.id)">
                                            <Button icon="pi pi-pencil" size="small" text v-tooltip.top="'Editar'" />
                                        </Link>
                                        <Button
                                            :icon="a.status ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                            size="small" text
                                            :severity="a.status ? 'warning' : 'success'"
                                            :v-tooltip.top="a.status ? 'Desactivar' : 'Activar'"
                                            @click="toggleAdmin(a.id)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <i class="pi pi-shield text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No se encontraron administradores.</p>
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
    admins:      Array,
    total:       Number,
    currentPage: Number,
    perPage:     Number,
    filters:     Object,
});

const loading  = ref(false);
const filters  = ref({ search: props.filters?.search ?? '' });
let   debounce = null;

const totalPages = computed(() => Math.ceil(props.total / props.perPage));

const applyFilters = (page = 1) => {
    loading.value = true;
    router.get(route('admin.index'), { search: filters.value.search, page }, {
        preserveState: true, replace: true, onFinish: () => { loading.value = false; },
    });
};

const onSearch = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(1), 350);
};

const changePage = (page) => applyFilters(page);

const toggleAdmin = (id) => {
    router.post(route('admin.toggle', id), {}, { preserveScroll: true });
};
</script>
