<template>
    <AppLayout :title="examen ? `Editar - ${examen.descipcion}` : 'Crear Tipo de Examen'">
        <div class="p-6 max-w-xl mx-auto">

            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('admin.examenes')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <Link :href="route('admin.index')" class="text-gray-400 hover:text-blue-600 text-xs">Administración</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <Link :href="route('admin.examenes')" class="text-gray-400 hover:text-blue-600 text-xs">Tipos de Examen</Link>
                        <i class="pi pi-chevron-right text-gray-300 text-xs" />
                        <span class="text-xs text-gray-600">{{ examen ? 'Editar' : 'Crear' }}</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        {{ examen ? examen.descipcion : 'Nuevo Tipo de Examen' }}
                    </h1>
                </div>
            </div>

            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="flex flex-col gap-4">

                        <div>
                            <label class="block text-sm font-medium mb-1">Descripción *</label>
                            <InputText
                                v-model="form.descipcion"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.descipcion }"
                                placeholder="Ej: Retroalveolar Unitaria"
                            />
                            <small class="text-red-500">{{ form.errors.descipcion }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Grupo *</label>
                            <Select
                                v-model="form.group"
                                :options="grupoOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Seleccionar grupo"
                                class="w-full"
                                :class="{ 'p-invalid': form.errors.group }"
                            />
                            <small class="text-red-500">{{ form.errors.group }}</small>
                            <p class="text-xs text-gray-400 mt-1">
                                El grupo determina qué radiólogos pueden informar este examen.
                            </p>
                        </div>

                        <!-- Info de grupos -->
                        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-500 space-y-1">
                            <p class="font-semibold text-gray-600 mb-2">Referencia de grupos:</p>
                            <p v-for="(label, key) in grupos" :key="key">
                                <span class="font-medium">Grupo {{ key }}:</span> {{ label }}
                            </p>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('admin.examenes')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button
                            :label="examen ? 'Guardar Cambios' : 'Crear Tipo'"
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
import Select from 'primevue/select';
import Message from 'primevue/message';

const props = defineProps({
    examen: Object,
    grupos: Object,
});

const grupoOptions = computed(() =>
    Object.entries(props.grupos).map(([k, v]) => ({ value: String(k), label: `Grupo ${k} — ${v}` }))
);

const form = useForm({
    descipcion: props.examen?.descipcion ?? '',
    group:      props.examen?.group ? String(props.examen.group) : '',
});

const submit = () => {
    if (props.examen) {
        form.put(route('admin.examenes.update', props.examen.id));
    } else {
        form.post(route('admin.examenes.store'));
    }
};
</script>
