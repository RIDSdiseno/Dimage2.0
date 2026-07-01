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
                            <label class="block text-sm font-medium mb-1">Nombre *</label>
                            <InputText v-model="form.name" placeholder="Nombre" class="w-full" :class="{'p-invalid': form.errors.name}" />
                            <small class="text-red-500">{{ form.errors.name }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">{{ terms.id_label }} *</label>
                            <InputText
                                :value="form.rut"
                                class="w-full"
                                :placeholder="terms.id_placeholder"
                                :class="{'p-invalid': form.errors.rut || rutError}"
                                @input="handleIdInput"
                                @blur="touchRut"
                            />
                            <small v-if="form.errors.rut" class="text-red-500">{{ form.errors.rut }}</small>
                            <small v-else-if="rutError" class="text-red-500">{{ rutError }}</small>

                            <div v-if="region === 'CL'" class="flex items-center gap-2 mt-2">
                                <Checkbox v-model="isPassport" :binary="true" inputId="esPassport" />
                                <label for="esPassport" class="text-sm cursor-pointer">Es pasaporte extranjero.</label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">E-mail *</label>
                            <InputText v-model="form.email" type="email" placeholder="E-mail" class="w-full" :class="{'p-invalid': form.errors.email}" />
                            <small class="text-red-500">{{ form.errors.email }}</small>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Fecha de Nacimiento *</label>
                            <DatePicker
                                v-model="dateValue"
                                dateFormat="dd-mm-yy"
                                showIcon
                                placeholder="dd-mm-aaaa"
                                class="w-full"
                                :class="{'p-invalid': form.errors.dateofbirth}"
                            />
                            <small class="text-red-500">{{ form.errors.dateofbirth }}</small>
                        </div>

                        <div v-if="puedeDerivacion" class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Paciente derivado de</label>
                            <InputText v-model="form.derivado_de" placeholder="Clínica o médico derivador" class="w-full" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1">Pertenece a: *</label>
                            <AutoComplete
                                ref="clinicAutoRef"
                                v-model="selectedClinics"
                                :suggestions="clinicSuggestions"
                                @complete="searchClinics"
                                @focus="onClinicFocus"
                                @item-select="onClinicSelect"
                                optionLabel="name"
                                multiple
                                forceSelection
                                dropdown
                                placeholder="Buscar clínica..."
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
import { ref, computed, watch, nextTick } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import { useTerms } from '@/composables/useTerms';

const props = defineProps({
    clinics: Array,
});

const page = usePage();
const puedeDerivacion = computed(() => !!page.props.auth?.user?.puede_derivacion_clinica);

const { region, terms } = useTerms();

const dateValue  = ref(null);
const isPassport = ref(false);
const rutTouched = ref(false);

const selectedClinics    = ref([]);
const clinicSuggestions  = ref([]);
const clinicAutoRef      = ref(null);

function searchClinics(event) {
    const q = (event.query ?? '').toLowerCase().trim();
    clinicSuggestions.value = q
        ? props.clinics.filter(c => c.name.toLowerCase().includes(q))
        : [...props.clinics];
}

let clinicJustSelected = false;

function onClinicFocus() {
    if (clinicJustSelected) { clinicJustSelected = false; return; }
    clinicSuggestions.value = [...props.clinics];
    nextTick(() => clinicAutoRef.value?.show());
}

function onClinicSelect() {
    clinicJustSelected = true;
    clinicAutoRef.value?.hide();
}

const form = useForm({
    name:        '',
    rut:         '',
    email:       '',
    clinics:     [],
    derivado_de: '',
});

watch(selectedClinics, (val) => {
    form.clinics = val.map(c => c.id);
});

watch(isPassport, () => {
    form.rut         = '';
    rutTouched.value = false;
});

watch(region, () => {
    form.rut         = '';
    rutTouched.value = false;
    isPassport.value = false;
});

function handleIdInput(event) {
    if (isPassport.value) {
        form.rut = event.target.value;
        return;
    }
    form.rut = terms.value.formatId(event.target.value);
}

const rutError = computed(() => {
    if (isPassport.value || !rutTouched.value) return null;
    return terms.value.validateId(form.rut);
});

function touchRut() { rutTouched.value = true; }

const submit = () => {
    rutTouched.value = true;
    if (!isPassport.value && terms.value.validateId(form.rut)) return;

    let dateofbirth = null;
    if (dateValue.value instanceof Date) {
        const d = dateValue.value;
        dateofbirth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    const data = { ...form.data(), dateofbirth, es_pasaporte: isPassport.value };
    form.transform(() => data).post(route('pacientes.store'));
};
</script>
