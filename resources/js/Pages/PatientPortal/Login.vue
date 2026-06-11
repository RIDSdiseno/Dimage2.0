<template>
    <div style="min-height:100vh; background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0f172a 100%); display:flex; align-items:center; justify-content:center; padding:24px;">
        <div style="width:100%; max-width:420px;">

            <!-- Logo / Branding -->
            <div style="text-align:center; margin-bottom:32px;">
                <div style="display:inline-flex; align-items:center; gap:10px; margin-bottom:8px;">
                    <div style="width:40px; height:40px; border-radius:10px; background:#3452ff; display:flex; align-items:center; justify-content:center;">
                        <i class="pi pi-image" style="color:#fff; font-size:20px;" />
                    </div>
                    <span style="font-size:22px; font-weight:700; color:#fff; letter-spacing:-0.5px;">Dimage</span>
                </div>
                <p style="color:#94a3b8; font-size:14px; margin:0;">Portal de Pacientes</p>
            </div>

            <!-- Card -->
            <div style="background:#fff; border-radius:16px; padding:32px; box-shadow:0 20px 60px rgba(0,0,0,0.4);">
                <h1 style="font-size:20px; font-weight:700; color:#0f172a; margin:0 0 6px;">Ver mi orden</h1>
                <p style="font-size:13px; color:#64748b; margin:0 0 24px;">Ingresa tu RUT y el número de orden para acceder a tu informe radiológico.</p>

                <!-- Session error (post redirect) -->
                <div v-if="sessionError"
                    style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
                    <i class="pi pi-exclamation-triangle" style="color:#ef4444; font-size:14px; flex-shrink:0;" />
                    <span style="font-size:13px; color:#b91c1c;">{{ sessionError }}</span>
                </div>

                <form @submit.prevent="submit">
                    <!-- RUT -->
                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">
                            RUT del paciente
                        </label>
                        <input
                            v-model="form.rut"
                            type="text"
                            placeholder="Ej: 12.345.678-9"
                            autocomplete="off"
                            :style="inputStyle(errors.rut)"
                            @input="errors.rut = null"
                        />
                        <p v-if="errors.rut" style="margin:5px 0 0; font-size:12px; color:#ef4444;">{{ errors.rut }}</p>
                    </div>

                    <!-- Número de orden -->
                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:6px;">
                            Número de orden
                        </label>
                        <input
                            v-model="form.orden_id"
                            type="number"
                            placeholder="Ej: 300196"
                            min="1"
                            :style="inputStyle(errors.orden_id)"
                            @input="errors.orden_id = null"
                        />
                        <p v-if="errors.orden_id" style="margin:5px 0 0; font-size:12px; color:#ef4444;">{{ errors.orden_id }}</p>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="loading"
                        style="width:100%; padding:12px; border-radius:10px; background:#3452ff; color:#fff; font-size:15px; font-weight:600; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:background 0.15s;"
                        @mouseover="e => e.currentTarget.style.background='#2541e0'"
                        @mouseleave="e => e.currentTarget.style.background=loading?'#94a3b8':'#3452ff'">
                        <i v-if="loading" class="pi pi-spin pi-spinner" style="font-size:15px;" />
                        <span>{{ loading ? 'Verificando...' : 'Acceder a mi orden' }}</span>
                    </button>
                </form>
            </div>

            <p style="text-align:center; margin-top:20px; font-size:12px; color:#475569;">
                ¿Eres profesional? <a href="/login" style="color:#60a5fa; text-decoration:none; font-weight:500;">Ingresar aquí</a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const sessionError = computed(() => page.props.errors?.session ?? null);

const form = ref({ rut: '', orden_id: '' });
const errors = ref({});
const loading = ref(false);

function inputStyle(hasError) {
    return {
        width: '100%',
        padding: '10px 14px',
        borderRadius: '8px',
        border: `1.5px solid ${hasError ? '#ef4444' : '#d1d5db'}`,
        fontSize: '14px',
        color: '#0f172a',
        outline: 'none',
        boxSizing: 'border-box',
        transition: 'border-color 0.15s',
    };
}

function submit() {
    errors.value = {};
    loading.value = true;
    router.post(route('paciente.auth'), {
        rut: form.value.rut,
        orden_id: form.value.orden_id,
    }, {
        onError: (e) => { errors.value = e; },
        onFinish: () => { loading.value = false; },
    });
}
</script>
