<template>
    <AppLayout :title="feriado ? 'Editar Feriado' : 'Crear Feriado'">
        <div class="p-6 max-w-xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.feriados')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.feriados')" class="text-gray-400 hover:text-blue-600 text-xs">
                            Feriados
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ feriado ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ feriado ? 'Editar Feriado' : 'Nuevo Feriado' }}
                    </h1>
                </div>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="space-y-4">

                        <div>
                            <label class="block text-sm font-medium mb-1">Fecha *</label>
                            <InputText v-model="form.fecha" type="date" class="w-full"
                                :class="{ 'p-invalid': form.errors.fecha }" />
                            <small class="text-red-500">{{ form.errors.fecha }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Descripción *</label>
                            <InputText v-model="form.descripcion" class="w-full"
                                placeholder="Ej: Año Nuevo, Navidad..."
                                :class="{ 'p-invalid': form.errors.descripcion }" />
                            <small class="text-red-500">{{ form.errors.descripcion }}</small>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.feriados')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="feriado ? 'Guardar Cambios' : 'Crear Feriado'"
                            icon="pi pi-save"
                            type="submit"
                            :loading="form.processing"
                            style="background-color:#3452ff;border-color:#3452ff;"
                        />
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
import Message from 'primevue/message';

const props = defineProps({ feriado: Object });

const form = useForm({
    fecha:       props.feriado?.fecha ?? '',
    descripcion: props.feriado?.descripcion ?? '',
});

const submit = () => {
    if (props.feriado) {
        form.put(route('admin.feriados.update', props.feriado.id));
    } else {
        form.post(route('admin.feriados.store'));
    }
};
</script>
