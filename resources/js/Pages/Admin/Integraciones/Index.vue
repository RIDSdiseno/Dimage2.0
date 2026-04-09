<template>
    <AppLayout title="Integraciones">
        <div class="p-6">

            <!-- ── Modal: nueva API Key ── -->
            <Teleport to="body">
                <div v-if="nuevaKey"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-8">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                style="background:#eef2ff;">
                                <i class="pi pi-key text-blue-600" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-800">API Key creada</h2>
                                <p class="text-xs text-amber-600 font-medium flex items-center gap-1">
                                    <i class="pi pi-exclamation-triangle" />
                                    Cópiala ahora — no se volverá a mostrar
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
                            <code class="flex-1 text-sm text-gray-700 break-all select-all font-mono">
                                {{ nuevaKey }}
                            </code>
                            <button @click="copiarKey"
                                class="shrink-0 flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                :style="copiado
                                    ? 'background:#d1fae5;color:#065f46;'
                                    : 'background:#3452ff;color:#fff;'">
                                <i :class="copiado ? 'pi pi-check' : 'pi pi-copy'" />
                                {{ copiado ? '¡Copiada!' : 'Copiar' }}
                            </button>
                        </div>

                        <p class="text-xs text-gray-400 mt-3 mb-6">
                            Usa este valor en el header <code class="bg-gray-100 px-1 rounded">X-API-KEY</code>
                            de tus requests a la API v3.
                        </p>

                        <Button label="Entendido, cerrar" icon="pi pi-times" class="w-full"
                            severity="secondary" @click="nuevaKey = null" />
                    </div>
                </div>
            </Teleport>

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Integraciones</h1>
                    <p class="text-sm text-gray-400 mt-0.5">{{ total }} API keys</p>
                </div>
                <Link :href="route('admin.integraciones.create')">
                    <Button label="Crear API Key" icon="pi pi-plus"
                        style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <Message v-if="$page.props.flash?.success && !nuevaKey" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-5">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="filters.search" placeholder="Buscar por descripción o holding..."
                        class="w-full" @input="onSearch" />
                </IconField>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Descripción</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Red Salud</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide hidden md:table-cell">Creada</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Estado</th>
                            <th class="w-28 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr v-for="n in 5" :key="n" class="border-t border-gray-50 animate-pulse">
                                <td v-for="c in 5" :key="c" class="px-5 py-3.5"><div class="h-3 bg-gray-100 rounded w-28" /></td>
                            </tr>
                        </template>
                        <template v-else-if="apikeys.length">
                            <tr v-for="k in apikeys" :key="k.id"
                                class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                <td class="px-5 py-3.5 font-medium text-gray-800">{{ k.descripcion }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ k.holding }}</td>
                                <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell text-xs">{{ k.created_at }}</td>
                                <td class="px-5 py-3.5">
                                    <span :class="k.activo ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ k.activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="route('admin.integraciones.edit', k.id)">
                                            <Button icon="pi pi-pencil" size="small" text v-tooltip.top="'Editar'" />
                                        </Link>
                                        <Button v-if="k.activo" icon="pi pi-ban" size="small" text severity="danger"
                                            v-tooltip.top="'Desactivar'" @click="desactivar(k.id)" />
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="5" class="px-5 py-16 text-center">
                                <i class="pi pi-key text-4xl text-gray-200 block mb-3" />
                                <p class="text-gray-400 font-medium">No hay API keys registradas.</p>
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
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';

const props = defineProps({
    apikeys: Array, total: Number, currentPage: Number, perPage: Number, filters: Object,
});

const page     = usePage();
const nuevaKey = ref(page.props.flash?.nueva_key ?? null);
const copiado  = ref(false);
const loading  = ref(false);
const filters  = ref({ search: props.filters?.search ?? '' });

function copiarKey() {
    navigator.clipboard.writeText(nuevaKey.value).then(() => {
        copiado.value = true;
        setTimeout(() => { copiado.value = false; }, 2500);
    });
}
let   debounce = null;

const totalPages = computed(() => Math.ceil(props.total / props.perPage));

const applyFilters = (page = 1) => {
    loading.value = true;
    router.get(route('admin.integraciones'), { search: filters.value.search, page }, {
        preserveState: true, replace: true, onFinish: () => { loading.value = false; },
    });
};
const onSearch   = () => { clearTimeout(debounce); debounce = setTimeout(() => applyFilters(1), 350); };
const changePage = (page) => applyFilters(page);
const desactivar = (id) => {
    if (!confirm('¿Confirma desactivar esta API Key?')) return;
    router.post(route('admin.integraciones.destroy', id), {}, { preserveScroll: true });
};
</script>
