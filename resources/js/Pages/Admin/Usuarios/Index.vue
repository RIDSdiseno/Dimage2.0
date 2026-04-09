<template>
    <AppLayout title="Usuarios - Admin">
        <div class="p-6">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 transition text-sm">
                            Administración
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-sm text-gray-600">Usuarios</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Usuarios</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} usuarios encontrados</p>
                </div>
                <Link :href="route('admin.usuarios.create')">
                    <Button label="Crear usuario" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <!-- Flash message -->
            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5 flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText
                            v-model="filters.search"
                            placeholder="Nombre, username o email..."
                            class="w-full"
                            @input="onSearch"
                        />
                    </IconField>
                </div>
                <div class="min-w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
                    <Select
                        v-model="filters.type_id"
                        :options="tiposOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Todos los tipos"
                        class="w-full"
                        @change="applyFilters"
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Nombre</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Username</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden lg:table-cell">Email</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Tipo</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Teléfono</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="w-28 px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Skeleton -->
                        <template v-if="loading">
                            <tr v-for="n in 8" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-36" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-24" /></td>
                                <td class="px-5 py-3.5 hidden lg:table-cell"><div class="h-3 bg-gray-100 rounded w-40" /></td>
                                <td class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-20" /></td>
                                <td class="px-5 py-3.5 hidden md:table-cell"><div class="h-3 bg-gray-100 rounded w-20" /></td>
                                <td class="px-5 py-3.5"><div class="h-5 bg-gray-100 rounded-full w-16" /></td>
                                <td class="px-5 py-3.5"></td>
                            </tr>
                        </template>

                        <!-- Data -->
                        <template v-else-if="usuarios.length">
                            <tr v-for="u in usuarios" :key="u.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ u.name }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell font-mono text-xs">{{ u.username }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden lg:table-cell">{{ u.mail || '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                        :style="typeStyle(u.type_id)">
                                        {{ u.type_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ u.telephone || '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="u.status ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ u.status ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="route('admin.usuarios.edit', u.id)">
                                            <Button icon="pi pi-pencil" size="small" text
                                                v-tooltip.top="'Editar'" />
                                        </Link>
                                        <form :action="route('admin.usuarios.toggle', u.id)" method="POST"
                                            @submit.prevent="toggleUser(u.id)">
                                            <Button
                                                :icon="u.status ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                                size="small" text
                                                :severity="u.status ? 'warning' : 'success'"
                                                v-tooltip.top="u.status ? 'Desactivar' : 'Activar'"
                                                type="submit"
                                            />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <!-- Empty state -->
                        <tr v-else>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <i class="pi pi-users text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No se encontraron usuarios.</p>
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
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Select from 'primevue/select';
import Message from 'primevue/message';

const props = defineProps({
    usuarios:    Array,
    total:       Number,
    currentPage: Number,
    perPage:     Number,
    filters:     Object,
    tipos:       Array,
});

const loading  = ref(false);
const filters  = ref({ search: props.filters?.search ?? '', type_id: props.filters?.type_id ?? '' });
let   debounce = null;

const totalPages  = computed(() => Math.ceil(props.total / props.perPage));
const tiposOptions = computed(() => [{ value: '', label: 'Todos los tipos' }, ...props.tipos]);

const applyFilters = (page = 1) => {
    loading.value = true;
    router.get(route('admin.usuarios'), {
        search:  filters.value.search,
        type_id: filters.value.type_id ?? '',
        page,
    }, {
        preserveState: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
};

const onSearch = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyFilters(1), 350);
};

const changePage = (page) => applyFilters(page);

const toggleUser = (id) => {
    router.post(route('admin.usuarios.toggle', id), {}, {
        preserveScroll: true,
    });
};

const typeStyle = (typeId) => {
    const map = {
        1:  'background:#eff2ff;color:#3452ff;',
        2:  'background:#f0fdf4;color:#16a34a;',
        3:  'background:#e8f0fb;color:#0b2a4a;',
        4:  'background:#fef3c7;color:#d97706;',
        5:  'background:#fce7f3;color:#be185d;',
        6:  'background:#f3e8ff;color:#7c3aed;',
        7:  'background:#fff7ed;color:#c2410c;',
        11: 'background:#ecfdf5;color:#047857;',
    };
    return map[typeId] ?? 'background:#f3f4f6;color:#4b5563;';
};
</script>
