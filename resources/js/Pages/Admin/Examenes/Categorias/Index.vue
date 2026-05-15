<template>
    <AppLayout title="Categorías de Examen">
        <div class="p-6 max-w-4xl mx-auto">

            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('admin.examenes')" class="text-gray-400 hover:text-blue-600 text-sm">Tipos de Examen</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-sm text-gray-600">Categorías</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Categorías de Examen</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Agrupa los tipos de examen por pestaña y columna.</p>
                </div>
                <Link :href="route('admin.examenes.categorias.create')">
                    <Button label="Nueva categoría" icon="pi pi-plus" style="background-color:#3452ff;border-color:#3452ff;" />
                </Link>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">{{ $page.props.flash.success }}</Message>
            <Message v-if="$page.props.flash?.error"   severity="error"   class="mb-4">{{ $page.props.flash.error }}</Message>

            <!-- Intraorales -->
            <div v-for="(tabLabel, tabKey) in tabs" :key="tabKey" class="mb-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3 flex items-center gap-2">
                    <i class="pi pi-list text-xs" /> {{ tabLabel }}
                </h2>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead style="background-color:#f8fafc;">
                            <tr>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-16">#</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">Nombre</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-20">Orden</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wide w-20">Exámenes</th>
                                <th class="w-28 px-5 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wide">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="gruposPorTab(tabKey).length">
                                <tr v-for="g in gruposPorTab(tabKey)" :key="g.id"
                                    class="border-t border-gray-50 hover:bg-blue-50/30 transition">
                                    <td class="px-5 py-3.5 text-gray-400 text-xs">{{ g.id }}</td>
                                    <td class="px-5 py-3.5 font-medium text-gray-800">{{ g.nombre }}</td>
                                    <td class="px-5 py-3.5 text-gray-500">{{ g.orden }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-medium">
                                            {{ kindCount(g.id) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right flex items-center justify-end gap-1">
                                        <Link :href="route('admin.examenes.categorias.edit', g.id)">
                                            <Button icon="pi pi-pencil" size="small" text v-tooltip.top="'Editar'" />
                                        </Link>
                                        <Button icon="pi pi-trash" size="small" text severity="danger"
                                            v-tooltip.top="'Eliminar'" @click="confirmarEliminar(g)" />
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="5" class="px-5 py-10 text-center text-gray-400 italic text-sm">
                                    Sin categorías en esta pestaña.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Confirm delete dialog -->
            <Dialog v-model:visible="deleteDialog" modal header="Eliminar categoría" :style="{ width: '380px' }">
                <p class="text-sm text-gray-600 mb-4">
                    ¿Eliminar la categoría <strong>{{ toDelete?.nombre }}</strong>?
                    Esta acción no se puede deshacer.
                </p>
                <template #footer>
                    <Button label="Cancelar" severity="secondary" @click="deleteDialog = false" />
                    <Button label="Eliminar" severity="danger" icon="pi pi-trash" :loading="deleting" @click="doDelete" />
                </template>
            </Dialog>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Message from 'primevue/message';
import Dialog from 'primevue/dialog';

const props = defineProps({
    grupos: Array,
    tabs:   Object,
});

const deleteDialog = ref(false);
const toDelete     = ref(null);
const deleting     = ref(false);

const gruposPorTab = (tab) => props.grupos.filter(g => g.tab === tab);

const kindCount = (groupId) => {
    const g = props.grupos.find(g => g.id === groupId);
    return g?.kinds_count ?? '—';
};

const confirmarEliminar = (g) => {
    toDelete.value = g;
    deleteDialog.value = true;
};

const doDelete = () => {
    deleting.value = true;
    router.delete(route('admin.examenes.categorias.destroy', toDelete.value.id), {
        onFinish: () => {
            deleting.value = false;
            deleteDialog.value = false;
        },
    });
};
</script>
