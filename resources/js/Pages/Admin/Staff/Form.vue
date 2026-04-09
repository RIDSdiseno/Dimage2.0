<template>
    <AppLayout :title="staff ? `Editar ${tipo.label} - ${staff.name}` : `Crear ${tipo.label}`">
        <div class="p-6 max-w-2xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route(tipo.route_index)">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-xs">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route(tipo.route_index)" class="text-gray-400 hover:text-blue-600 text-xs">{{ tipo.label_plural }}</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ staff ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ staff ? staff.name : `Nuevo ${tipo.label}` }}
                    </h1>
                </div>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6 space-y-6">

                <!-- User data -->
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                        Datos del usuario
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre completo *</label>
                            <InputText v-model="form.name" class="w-full" :class="{ 'p-invalid': form.errors.name }" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Username *</label>
                            <InputText v-model="form.username" class="w-full" :class="{ 'p-invalid': form.errors.username }"
                                :disabled="!!staff" />
                            <small v-if="staff" class="text-gray-400">El username no se puede cambiar</small>
                            <small class="text-red-500">{{ form.errors.username }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">
                                Contraseña {{ staff ? '' : '*' }}
                            </label>
                            <InputText v-model="form.password" type="password" class="w-full"
                                :placeholder="staff ? 'Dejar en blanco para no cambiar' : ''"
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
                </div>

                <!-- Staff data -->
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4 pb-2 border-b border-gray-100">
                        Datos profesionales
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <label class="block text-sm font-medium mb-1">RUT</label>
                            <InputText v-model="form.rut" class="w-full" placeholder="12.345.678-9" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Clínicas asignadas</label>
                            <MultiSelect
                                v-model="form.clinica_ids"
                                :options="clinicasList"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar clínicas"
                                class="w-full"
                                display="chip"
                            />
                        </div>

                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <Link :href="route(tipo.route_index)">
                        <Button label="Cancelar" severity="secondary" type="button" />
                    </Link>
                    <Button
                        :label="staff ? 'Guardar Cambios' : `Crear ${tipo.label}`"
                        icon="pi pi-save"
                        @click="submit"
                        :loading="form.processing"
                        style="background-color:#3452ff;border-color:#3452ff;"
                    />
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Message from 'primevue/message';

const props = defineProps({
    staff:        Object,
    tipo:         Object,
    clinicasList: Array,
});

const form = useForm({
    name:        props.staff?.name ?? '',
    username:    props.staff?.username ?? '',
    password:    '',
    mail:        props.staff?.mail ?? '',
    telephone:   props.staff?.telephone ?? '',
    rut:         props.staff?.rut ?? '',
    clinica_ids: props.staff?.clinica_ids ?? [],
});

const submit = () => {
    if (props.staff) {
        form.put(route(props.tipo.route_update, props.staff.id));
    } else {
        form.post(route(props.tipo.route_store));
    }
};
</script>
