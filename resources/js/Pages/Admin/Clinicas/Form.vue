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
                        <div v-else>
                            <label class="block text-sm font-medium mb-1">Usuario</label>
                            <InputText :value="clinica.username" class="w-full" disabled />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ clinica ? 'Nueva Contraseña (opcional)' : 'Contraseña *' }}</label>
                            <InputText v-model="form.password" type="password" class="w-full" :placeholder="clinica ? 'Dejar vacío para no cambiar' : ''" />
                            <small class="text-red-500">{{ form.errors.password }}</small>
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

                        <!-- Logo -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Logo de la Clínica</label>
                            <div class="flex items-start gap-4">
                                <div v-if="logoPreview" class="shrink-0">
                                    <img :src="logoPreview" alt="Logo" class="h-20 w-auto object-contain border border-gray-200 rounded p-1 bg-gray-50" />
                                </div>
                                <div class="flex-1">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                        @change="onLogoChange"
                                    />
                                    <p class="text-xs text-gray-400 mt-1">PNG, JPG o GIF. Máximo 2 MB.</p>
                                    <small class="text-red-500">{{ form.errors.logo }}</small>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Permisos -->
                    <div class="mt-6 pt-5 border-t border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">
                            Permisos para este usuario
                        </h2>
                        <div class="flex items-center gap-3">
                            <Checkbox v-model="form.puede_ver_menu_busqueda" :binary="true" inputId="clinica_busqueda" />
                            <label for="clinica_busqueda" class="text-sm cursor-pointer">Permiso para ver menú de búsqueda de orden</label>
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
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Message from 'primevue/message';

const props = defineProps({
    clinica:      Object,
    holdingsList: Array,
});

const form = useForm({
    name:                        props.clinica?.name ?? '',
    username:                    props.clinica?.username ?? '',
    password:                    '',
    holding_id:                  props.clinica?.holding_id ?? null,
    address:                     props.clinica?.address ?? '',
    telephoneone:                props.clinica?.telephoneone ?? '',
    puede_seleccionar_radiologo: props.clinica?.puede_seleccionar_radiologo ?? false,
    puede_ver_menu_busqueda:     props.clinica?.puede_ver_menu_busqueda ?? false,
    logo:                        null,
});

// Muestra logo existente del servidor o preview del archivo seleccionado
const localPreview = ref(null);
const logoPreview = computed(() => localPreview.value ?? props.clinica?.logo_url ?? null);

const onLogoChange = (e) => {
    const file = e.target.files[0] ?? null;
    form.logo = file;
    localPreview.value = file ? URL.createObjectURL(file) : null;
};

const submit = () => {
    if (props.clinica) {
        // _method spoofing necesario para file uploads con PUT en Laravel
        form.transform(data => ({ ...data, _method: 'put' }))
            .post(route('admin.clinicas.update', props.clinica.id));
    } else {
        form.post(route('admin.clinicas.store'));
    }
};
</script>
