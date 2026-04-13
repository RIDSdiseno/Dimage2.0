import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// ── Terminología por región ────────────────────────────────────────────────

const TERMS = {
    CL: {
        // Campo de identificación del paciente
        id_label:       'RUT',
        id_placeholder: '12345678-9',
        id_hint:        'Ej: 12345678-9 o pasaporte',

        // Validación client-side del identificador
        validateId: (value) => {
            if (!value) return 'El RUT es requerido.';
            // Acepta cualquier formato sin validación estricta en frontend
            // (la validación estricta de formato se hace en backend si es necesario)
            return null;
        },
    },

    UY: {
        id_label:       'C.I.',
        id_placeholder: '1234567-8',
        id_hint:        'Ej: 1234567-8 (Cédula de Identidad)',

        validateId: (value) => {
            if (!value) return 'La C.I. es requerida.';
            return null;
        },
    },
};

// ── Mapa de nombres de exámenes CL → UY ───────────────────────────────────
// Mapeo exacto de cada nombre completo tal como viene de la BD.

const EXAM_NAME_MAP_UY = {
    // Retroalveolar → Periapical
    'Retroalveolar Unitaria Adulto':          'Periapical Unitaria Adulto',
    'Retroalveolar Total Adulto':             'Periapical Total Adulto',
    'Retroalveolar Unitaria Niño':            'Periapical Unitaria Niño',
    'Retroalveolar Total Niño':               'Periapical Total Niño',
    // Sin sufijo de grupo (por si acaso)
    'Retroalveolar Unitaria':                 'Periapical Unitaria',
    'Retroalveolar Total':                    'Periapical Total',
    // Panorámica
    'Panorámica':                             'Ortopantomografía',
    // Telerradiografía
    'Telerradiografía PA':                    'Telerradiografía Frontal',
    // Carpo
    'Carpo':                                  'Carpal',
};

const translateExamName = (name, region) => {
    if (region !== 'UY' || !name) return name;

    // Mapeo exacto
    if (EXAM_NAME_MAP_UY[name] !== undefined) return EXAM_NAME_MAP_UY[name];

    // Reemplaza "Cone Beam" por "Tomografía" en cualquier variante
    if (name.includes('Cone Beam')) {
        return name.replace('Cone Beam', 'Tomografía');
    }

    return name;
};

// ── Composable principal ───────────────────────────────────────────────────

export function useTerms() {
    const page   = usePage();
    const region = computed(() => page.props.region ?? 'CL');
    const terms  = computed(() => TERMS[region.value] ?? TERMS.CL);

    /** Traduce un nombre de examen individual */
    const examLabel = (name) => translateExamName(name, region.value);

    /**
     * Traduce una lista concatenada de exámenes (ej: "Panorámica, Carpo")
     * que viene del backend ya procesada.
     */
    const examListLabel = (list) => {
        if (!list || list === '-') return list;
        return list
            .split(', ')
            .map(name => translateExamName(name.trim(), region.value))
            .join(', ');
    };

    return {
        region,
        terms,
        examLabel,
        examListLabel,
    };
}
