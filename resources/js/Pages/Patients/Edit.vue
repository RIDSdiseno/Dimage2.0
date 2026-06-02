<template>
    <AppLayout :title="`Editar Paciente - ${patient.name}`">
        <div class="p-6 max-w-2xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('pacientes.index')">
                    <Button icon="pi pi-arrow-left" text />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ patient.name }}</h1>
                    <span class="text-sm text-gray-500">{{ terms.id_label }}: {{ patient.rut }}</span>
                </div>
            </div>

            <!-- Mensaje de éxito -->
            <Message v-if="$page.props.flash?.success" severity="success" class="mb-4">
                {{ $page.props.flash.success }}
            </Message>

            <div class="bg-white rounded-xl shadow p-6">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Nombre completo *</label>
                            <InputText v-model="form.name" class="w-full" :class="{'p-invalid': form.errors.name}" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ terms.id_label }}</label>
                            <InputText :value="patient.rut" class="w-full" disabled />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Fecha de nacimiento *</label>
                            <InputMask v-model="rawDate" mask="99-99-9999" placeholder="DD-MM-AAAA"
                                class="w-full" :class="{'p-invalid': form.errors.dateofbirth}" />
                            <small class="text-red-500">{{ form.errors.dateofbirth }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Email *</label>
                            <InputText v-model="form.email" type="email" class="w-full" :class="{'p-invalid': form.errors.email}" />
                            <small class="text-red-500">{{ form.errors.email }}</small>
                        </div>

                        <div v-if="puedeDerivacion" class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Paciente derivado de</label>
                            <InputText v-model="form.derivado_de" placeholder="Clínica o médico derivador" class="w-full" />
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
                                closeOnSelect
                            />
                            <small class="text-red-500">{{ form.errors.clinics }}</small>
                        </div>

                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <Link :href="route('pacientes.index')">
                            <Button label="Cancelar" severity="secondary" type="button" />
                        </Link>
                        <Button label="Guardar Cambios" icon="pi pi-save" type="submit" :loading="form.processing" />
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTerms } from '@/composables/useTerms.js';

const { terms } = useTerms();
const page = usePage();
const puedeDerivacion = computed(() => !!page.props.auth?.user?.puede_derivacion_clinica);
import InputText from 'primevue/inputtext';
import InputMask from 'primevue/inputmask';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';
import Message from 'primevue/message';

const props = defineProps({
    patient:         Object,
    clinics:         Array,
    selectedClinics: Array,
});

function initRawDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    const dd = String(d.getUTCDate()).padStart(2, '0');
    const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
    const yyyy = d.getUTCFullYear();
    return `${dd}-${mm}-${yyyy}`;
}

const rawDate = ref(initRawDate(props.patient.dateofbirth));

const form = useForm({
    name:        props.patient.name,
    email:       props.patient.email,
    clinics:     props.selectedClinics ?? [],
    derivado_de: props.patient.derivado_de ?? '',
});

const submit = () => {
    let dateofbirth = null;
    if (rawDate.value && !rawDate.value.includes('_') && rawDate.value.length === 10) {
        const [d, m, y] = rawDate.value.split('-');
        dateofbirth = `${y}-${m}-${d}`;
    }
    const data = { ...form.data(), dateofbirth };
    form.transform(() => data).put(route('pacientes.update', props.patient.id));
};
</script>
