import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// ── Validadores ───────────────────────────────────────────────────────────────

function validateChileanRut(value, required = true) {
    if (!value) return required ? 'El RUT es requerido.' : null;
    const clean = value.replace(/[\.\s]/g, '').toUpperCase();
    if (!/^\d{7,8}-[\dK]$/.test(clean)) return 'Formato inválido. Ej: 12345678-9';
    const [body, dv] = clean.split('-');
    let sum = 0, mult = 2;
    for (let i = body.length - 1; i >= 0; i--) {
        sum += parseInt(body[i]) * mult;
        mult = mult === 7 ? 2 : mult + 1;
    }
    const rem      = sum % 11;
    const expected = rem === 0 ? '0' : rem === 1 ? 'K' : String(11 - rem);
    if (dv !== expected) return 'El RUT ingresado no es válido.';
    return null;
}

// Cédula de Identidad Uruguay: dígito verificador módulo 10
// Pesos [2,9,8,7,6,3,4] aplicados al cuerpo de 7 dígitos (relleno con ceros a la izquierda)
// Verificador = (10 - (suma % 10)) % 10
function validateUruguayanCI(value, required = true) {
    if (!value) return required ? 'La C.I. es requerida.' : null;
    const clean = value.replace(/[\.\s]/g, '');
    if (!/^\d{6,8}-\d$/.test(clean)) return 'Formato inválido. Ej: 1.234.567-8';
    const [body, dvStr] = clean.split('-');
    const padded  = body.padStart(7, '0');
    const weights = [2, 9, 8, 7, 6, 3, 4];
    let sum = 0;
    for (let i = 0; i < 7; i++) sum += parseInt(padded[i]) * weights[i];
    const expected = (10 - (sum % 10)) % 10;
    if (parseInt(dvStr) !== expected) return 'La C.I. ingresada no es válida.';
    return null;
}

// ── Formateo de entrada ───────────────────────────────────────────────────────

/** Formatea RUT chileno mientras el usuario escribe: 12345678K → 1234567-8K */
export function formatRutCL(raw) {
    let clean = raw.replace(/[.\s-]/g, '').replace(/[^0-9kK]/g, '').toUpperCase();
    clean = clean.slice(0, 9);
    return clean.length > 1 ? clean.slice(0, -1) + '-' + clean.slice(-1) : clean;
}

/** Formatea C.I. uruguaya mientras el usuario escribe: 12345678 → 1.234.567-8 */
export function formatCI_UY(raw) {
    const digits = raw.replace(/[^0-9]/g, '').slice(0, 9);
    if (digits.length <= 1) return digits;
    const check  = digits.slice(-1);
    const body   = digits.slice(0, -1);
    const bodyFmt = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return bodyFmt + '-' + check;
}

// ── Terminología por región ────────────────────────────────────────────────

const TERMS = {
    CL: {
        id_label:       'RUT',
        id_placeholder: '12345678-9',
        id_hint:        'Ej: 12345678-9 o pasaporte',
        validateId:     (value, required = true) => validateChileanRut(value, required),
        formatId:       (raw) => formatRutCL(raw),
    },

    UY: {
        id_label:       'C.I.',
        id_placeholder: '1.234.567-8',
        id_hint:        'Ej: 1.234.567-8 (Cédula de Identidad)',
        validateId:     (value, required = true) => validateUruguayanCI(value, required),
        formatId:       (raw) => formatCI_UY(raw),
    },
};

// ── Mapa de nombres de exámenes CL → UY ───────────────────────────────────

const EXAM_NAME_MAP_UY = {
    'Retroalveolar Unitaria Adulto':          'Periapical Unitaria Adulto',
    'Retroalveolar Total Adulto':             'Periapical Total Adulto',
    'Retroalveolar Unitaria Niño':            'Periapical Unitaria Niño',
    'Retroalveolar Total Niño':               'Periapical Total Niño',
    'Retroalveolar Unitaria':                 'Periapical Unitaria',
    'Retroalveolar Total':                    'Periapical Total',
    'Panorámica':                             'Ortopantomografía',
    'Telerradiografía PA':                    'Telerradiografía Frontal',
    'Cone Beam por Zona o Diente':            'Tomografía por Zona o Diente',
    'Carpo':                                  'Carpal',
};

const translateExamName = (name, region) => {
    if (region !== 'UY' || !name) return name;
    if (EXAM_NAME_MAP_UY[name] !== undefined) return EXAM_NAME_MAP_UY[name];
    if (/cone beam/i.test(name)) return name.replace(/cone beam/i, 'Tomografía');
    return name;
};

// ── Composable principal ───────────────────────────────────────────────────

export function useTerms() {
    const page   = usePage();
    const region = computed(() => page.props.region ?? 'CL');
    const terms  = computed(() => TERMS[region.value] ?? TERMS.CL);

    const examLabel = (name) => translateExamName(name, region.value);

    const examListLabel = (list) => {
        if (!list || list === '-') return list;
        return list
            .split(', ')
            .map(name => translateExamName(name.trim(), region.value))
            .join(', ');
    };

    return { region, terms, examLabel, examListLabel };
}
