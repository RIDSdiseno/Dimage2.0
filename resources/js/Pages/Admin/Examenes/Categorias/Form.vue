<template>
    <AppLayout :title="grupo ? `Editar - ${grupo.nombre}` : 'Nueva Categoría'">
        <div class="p-6 max-w-xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.examenes.categorias')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.examenes')" class="text-gray-400 hover:text-blue-600 text-xs">Tipos de Examen</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route('admin.examenes.categorias')" class="text-gray-400 hover:text-blue-600 text-xs">Categorías</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ grupo ? 'Editar' : 'Nueva' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ grupo ? grupo.nombre : 'Nueva Categoría' }}
                    </h1>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="flex flex-col gap-4">

                        <div>
                            <label class="block text-sm font-medium mb-1">Nombre de la categoría *</label>
                            <InputText
                                v-model="form.nombre"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.nombre }"
                                placeholder="Ej: Examen Adultos"
                            />
                            <small class="text-red-500">{{ form.errors.nombre }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Pestaña *</label>
                            <Select
                                v-model="form.tab"
                                :options="tabOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar pestaña"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.tab }"
                            />
                            <small class="text-red-500">{{ form.errors.tab }}</small>
                            <p class="text-xs text-gray-400 mt-1">
                                Determina en qué pestaña aparecerá esta categoría al crear órdenes.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Orden</label>
                            <InputNumber
                                v-model="form.orden"
                                class="w-full"
                                :min="0"
                                placeholder="0"
                            />
                            <p class="text-xs text-gray-400 mt-1">
                                Las categorías con menor número aparecen primero dentro de la pestaña.
                            </p>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.examenes.categorias')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="grupo ? 'Guardar Cambios' : 'Crear Categoría'"
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
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';

const props = defineProps({
    grupo: Object,
    tabs:  Object,
});

const tabOptions = computed(() =>
    Object.entries(props.tabs).map(([value, label]) => ({ value, label }))
);

const form = useForm({
    nombre: props.grupo?.nombre ?? '',
    tab:    props.grupo?.tab    ?? 'intraorales',
    orden:  props.grupo?.orden  ?? 0,
});

const submit = () => {
    if (props.grupo) {
        form.put(route('admin.examenes.categorias.update', props.grupo.id));
    } else {
        form.post(route('admin.examenes.categorias.store'));
    }
};
</script>
