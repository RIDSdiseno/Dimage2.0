<template>
    <AppLayout :title="user ? `Editar Usuario - ${user.name}` : 'Crear Usuario'">
        <div class="p-6 max-w-2xl mx-auto">

            <!-- Breadcrumb + back -->
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.usuarios')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 transition text-xs">
                            Administración
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route('admin.usuarios')" class="text-gray-400 hover:text-blue-600 transition text-xs">
                            Usuarios
                        </Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ user ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ user ? user.name : 'Nuevo Usuario' }}
                    </h1>
                </div>
            </div>

            <!-- Flash -->
            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre *</label>
                            <InputText v-model="form.name" class="w-full"
                                :class="{ 'p-invalid': form.errors.name }" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Username *</label>
                            <InputText v-model="form.username" class="w-full"
                                :class="{ 'p-invalid': form.errors.username }" />
                            <small class="text-red-500">{{ form.errors.username }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Email</label>
                            <InputText v-model="form.mail" type="email" class="w-full" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Contraseña {{ user ? '' : '*' }}
                            </label>
                            <InputText v-model="form.password" type="password" class="w-full"
                                :class="{ 'p-invalid': form.errors.password }"
                                :placeholder="user ? 'Dejar en blanco para no cambiar' : ''" />
                            <small v-if="user" class="text-gray-400">
                                Dejar en blanco para no cambiar la contraseña
                            </small>
                            <small class="text-red-500">{{ form.errors.password }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Tipo *</label>
                            <Select
                                v-model="form.type_id"
                                :options="tipos"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar tipo"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.type_id }"
                            />
                            <small class="text-red-500">{{ form.errors.type_id }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Teléfono</label>
                            <InputText v-model="form.telephone" class="w-full" />
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.usuarios')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="user ? 'Guardar Cambios' : 'Crear Usuario'"
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
    user:  Object,
    tipos: Array,
});

const form = useForm({
    name:      props.user?.name ?? '',
    username:  props.user?.username ?? '',
    mail:      props.user?.mail ?? '',
    password:  '',
    type_id:   props.user?.type_id ?? null,
    telephone: props.user?.telephone ?? '',
});

const submit = () => {
    if (props.user) {
        form.put(route('admin.usuarios.update', props.user.id));
    } else {
        form.post(route('admin.usuarios.store'));
    }
};
</script>
