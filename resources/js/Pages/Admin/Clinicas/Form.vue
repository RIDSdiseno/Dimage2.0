<template>
    <AppLayout :title="clinica ? `Editar Clínica - ${clinica.name}` : 'Crear Clínica'">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.clinicas')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-xs">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route('admin.clinicas')" class="text-gray-400 hover:text-blue-600 text-xs">Clínicas</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ clinica ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ clinica ? clinica.name : 'Nueva Clínica' }}
                    </h1>
                </div>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre de la Clínica *</label>
                            <InputText v-model="form.name" class="w-full" :class="{ 'p-invalid': form.errors.name }" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div v-if="!clinica">
                            <label class="block text-sm font-medium mb-1">Username *</label>
                            <InputText v-model="form.username" class="w-full" :class="{ 'p-invalid': form.errors.username }" />
                            <small class="text-red-500">{{ form.errors.username }}</small>
                        </div>

                        <div v-if="!clinica">
                            <label class="block text-sm font-medium mb-1">Contraseña *</label>
                            <InputText v-model="form.password" type="password" class="w-full" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Holding *</label>
                            <Select v-model="form.holding_id" :options="holdingsList" optionLabel="label" optionValue="value"
                                placeholder="Seleccionar holding" class="w-full" :class="{ 'p-invalid': form.errors.holding_id }" />
                            <small class="text-red-500">{{ form.errors.holding_id }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Dirección</label>
                            <InputText v-model="form.address" class="w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Teléfono</label>
                            <InputText v-model="form.telephoneone" class="w-full" />
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.clinicas')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="clinica ? 'Guardar Cambios' : 'Crear Clínica'"
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
import Select from 'primevue/select';
import Message from 'primevue/message';

const props = defineProps({
    clinica:      Object,
    holdingsList: Array,
});

const form = useForm({
    name:         props.clinica?.name ?? '',
    username:     props.clinica?.username ?? '',
    password:     '',
    holding_id:   props.clinica?.holding_id ?? null,
    address:      props.clinica?.address ?? '',
    telephoneone: props.clinica?.telephoneone ?? '',
});

const submit = () => {
    if (props.clinica) {
        form.put(route('admin.clinicas.update', props.clinica.id));
    } else {
        form.post(route('admin.clinicas.store'));
    }
};
</script>
