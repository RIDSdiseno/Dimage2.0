<template>
    <AppLayout title="Nuevo Paciente">
        <div class="p-6 max-w-2xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('pacientes.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <h1 class="text-2xl font-bold text-gray-800">Nuevo Paciente</h1>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre completo *</label>
                            <InputText v-model="form.name" class="w-full" :class="{'p-invalid': form.errors.name}" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ terms.id_label }} / Pasaporte *</label>
                            <InputText v-model="form.rut" class="w-full" :placeholder="terms.id_placeholder" :class="{'p-invalid': form.errors.rut}" />
                            <small class="text-red-500">{{ form.errors.rut }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Fecha de nacimiento *</label>
                            <DatePicker v-model="form.dateofbirth" class="w-full" dateFormat="dd/mm/yy" :class="{'p-invalid': form.errors.dateofbirth}" />
                            <small class="text-red-500">{{ form.errors.dateofbirth }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Email *</label>
                            <InputText v-model="form.email" type="email" class="w-full" :class="{'p-invalid': form.errors.email}" />
                            <small class="text-red-500">{{ form.errors.email }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Clínicas *</label>
                            <MultiSelect
                                v-model="form.clinics"
                                :options="clinics"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Selecciona clínica(s)"
                                class="w-full"
                                :class="{'p-invalid': form.errors.clinics}"
                            />
                            <small class="text-red-500">{{ form.errors.clinics }}</small>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('pacientes.index')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button label="Guardar Paciente" icon="pi pi-save" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTerms } from '@/composables/useTerms.js';

const { terms } = useTerms();
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';

const props = defineProps({
    clinics: Array,
});

const form = useForm({
    name:        '',
    rut:         '',
    email:       '',
    dateofbirth: null,
    clinics:     [],
    derivado_de: '',
});

const submit = () => {
    const data = {
        ...form.data(),
        dateofbirth: form.dateofbirth
            ? new Date(form.dateofbirth).toISOString().split('T')[0]
            : null,
    };
    form.transform(() => data).post(route('pacientes.store'));
};
</script>
