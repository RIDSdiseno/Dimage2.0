<template>
    <AppLayout :title="admin ? `Editar Administrador - ${admin.name}` : 'Crear Administrador'">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-xs">
                            Administradores
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ admin ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ admin ? admin.name : 'Nuevo Administrador' }}
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
                            <label class="block text-sm font-medium mb-1">Nombre completo *</label>
                            <InputText v-model="form.name" class="w-full" :class="{ 'p-invalid': form.errors.name }" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Username *</label>
                            <InputText v-model="form.username" class="w-full"
                                :class="{ 'p-invalid': form.errors.username }"
                                :disabled="!!admin" />
                            <small v-if="admin" class="text-gray-400">El username no se puede cambiar</small>
                            <small class="text-red-500">{{ form.errors.username }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Contraseña {{ admin ? '' : '*' }}
                            </label>
                            <InputText v-model="form.password" type="password" class="w-full"
                                :placeholder="admin ? 'Dejar en blanco para no cambiar' : ''"
                                :class="{ 'p-invalid': form.errors.password }" />
                            <small class="text-red-500">{{ form.errors.password }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Email</label>
                            <InputText v-model="form.mail" type="email" class="w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Teléfono</label>
                            <InputText v-model="form.telephone" class="w-full" />
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.index')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="admin ? 'Guardar Cambios' : 'Crear Administrador'"
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

const props = defineProps({ admin: Object });

const form = useForm({
    name:      props.admin?.name ?? '',
    username:  props.admin?.username ?? '',
    password:  '',
    mail:      props.admin?.mail ?? '',
    telephone: props.admin?.telephone ?? '',
});

const submit = () => {
    if (props.admin) {
        form.put(route('admin.update', props.admin.id));
    } else {
        form.post(route('admin.store'));
    }
};
</script>
