<template>
    <AppLayout :title="apikey ? 'Editar API Key' : 'Crear API Key'">
        <div class="p-6 max-w-xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.integraciones')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.integraciones')" class="text-gray-400 hover:text-blue-600 text-xs">
                            Integraciones
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ apikey ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ apikey ? 'Editar API Key' : 'Nueva API Key' }}</h1>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="space-y-4">

                        <div>
                            <label class="block text-sm font-medium mb-1">Red Salud (Holding) *</label>
                            <Select v-model="form.holding_id" :options="holdings"
                                optionLabel="label" optionValue="value"
                                placeholder="Seleccionar holding" class="w-full"
                                :class="{ 'p-invalid': form.errors.holding_id }" />
                            <small class="text-red-500">{{ form.errors.holding_id }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Descripción *</label>
                            <InputText v-model="form.descripcion" class="w-full"
                                placeholder="Ej: Integración HIS, App móvil..."
                                :class="{ 'p-invalid': form.errors.descripcion }" />
                            <small class="text-red-500">{{ form.errors.descripcion }}</small>
                        </div>

                        <div v-if="apikey" class="flex items-center gap-2">
                            <input type="checkbox" id="activo" v-model="form.activo" class="rounded" />
                            <label for="activo" class="text-sm font-medium">API Key activa</label>
                        </div>

                        <div v-if="!apikey" class="bg-blue-50 rounded-lg p-3">
                            <p class="text-xs text-blue-600">
                                <i class="pi pi-info-circle mr-1" />
                                La API Key se generará automáticamente de forma segura.
                            </p>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.integraciones')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button :label="apikey ? 'Guardar Cambios' : 'Crear API Key'"
                            icon="pi pi-save" type="submit" :loading="form.processing"
                            style="background-color:#3452ff;border-color:#3452ff;" />
                    </div>
                </form>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const props = defineProps({ apikey: Object, holdings: Array });

const form = useForm({
    holding_id:  props.apikey?.holding_id  ?? null,
    descripcion: props.apikey?.descripcion ?? '',
    activo:      props.apikey?.activo      ?? true,
});

const submit = () => {
    if (props.apikey) {
        form.put(route('admin.integraciones.update', props.apikey.id));
    } else {
        form.post(route('admin.integraciones.store'));
    }
};
</script>
