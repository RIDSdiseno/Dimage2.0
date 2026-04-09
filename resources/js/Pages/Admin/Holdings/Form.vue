<template>
    <AppLayout :title="holding ? `Editar Holding - ${holding.name}` : 'Crear Holding'">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.holdings')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-xs">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route('admin.holdings')" class="text-gray-400 hover:text-blue-600 text-xs">Holdings</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ holding ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ holding ? holding.name : 'Nuevo Holding' }}
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
                            <label class="block text-sm font-medium mb-1">Nombre del Holding *</label>
                            <InputText v-model="form.name" class="w-full" :class="{ 'p-invalid': form.errors.name }" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div v-if="!holding">
                            <label class="block text-sm font-medium mb-1">Username *</label>
                            <InputText v-model="form.username" class="w-full" :class="{ 'p-invalid': form.errors.username }" />
                            <small class="text-red-500">{{ form.errors.username }}</small>
                        </div>

                        <div v-if="!holding">
                            <label class="block text-sm font-medium mb-1">Contraseña *</label>
                            <InputText v-model="form.password" type="password" class="w-full" :class="{ 'p-invalid': form.errors.password }" />
                            <small class="text-red-500">{{ form.errors.password }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">RUT Holding</label>
                            <InputText v-model="form.rutholding" class="w-full" placeholder="12.345.678-9" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Representante Legal</label>
                            <InputText v-model="form.representantelegal" class="w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Email Responsable</label>
                            <InputText v-model="form.emailresponsable" type="email" class="w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Teléfono Responsable</label>
                            <InputText v-model="form.telefonoresponsable" class="w-full" />
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.holdings')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="holding ? 'Guardar Cambios' : 'Crear Holding'"
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

const props = defineProps({ holding: Object });

const form = useForm({
    name:                props.holding?.name ?? '',
    username:            props.holding?.username ?? '',
    password:            '',
    rutholding:          props.holding?.rutholding ?? '',
    representantelegal:  props.holding?.representantelegal ?? '',
    emailresponsable:    props.holding?.emailresponsable ?? '',
    telefonoresponsable: props.holding?.telefonoresponsable ?? '',
});

const submit = () => {
    if (props.holding) {
        form.put(route('admin.holdings.update', props.holding.id));
    } else {
        form.post(route('admin.holdings.store'));
    }
};
</script>
