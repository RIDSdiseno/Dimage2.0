<template>
    <Teleport to="body">
        <div v-if="src" class="fixed inset-0 z-50 flex flex-col bg-black select-none"
            @keydown.esc="onEsc" tabindex="0" ref="container">

            <!-- Toolbar -->
            <div class="flex items-center justify-between px-4 py-2 bg-black/80 z-10 shrink-0">
                <div class="flex items-center gap-2">
                    <button @click="showTools = !showTools" title="Herramientas"
                        class="flex items-center gap-1 px-2.5 py-1.5 rounded text-sm transition-colors cursor-pointer"
                        :class="showTools ? 'text-blue-300 bg-blue-500/30' : 'text-white/70 bg-white/10 hover:text-white hover:bg-white/20'">
                        <i class="pi pi-wrench" />
                        <span class="text-xs hidden sm:inline">Herramientas</span>
                    </button>
                    <span class="text-white/70 text-sm truncate max-w-xs ml-2">{{ name }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="zoomOut"   title="Alejar"             class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-search-minus" /></button>
                    <span class="text-white/60 text-xs w-12 text-center">{{ Math.round(scale * 100) }}%</span>
                    <button @click="zoomIn"    title="Acercar"            class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-search-plus" /></button>
                    <button @click="fit"       title="Ajustar pantalla"   class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-expand" /></button>
                    <button @click="resetView" title="Tamaño real (100%)" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-xs font-bold transition-colors cursor-pointer">1:1</button>
                    <button @click="rotateImg" title="Rotar"              class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-refresh" /></button>
                    <a :href="src" target="_blank" title="Abrir original"
                        class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer">
                        <i class="pi pi-external-link" />
                    </a>
                    <button @click="emit('close')" title="Cerrar (Esc)"
                        class="text-red-400 hover:text-red-300 bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer ml-2">
                        <i class="pi pi-times text-lg" />
                    </button>
                </div>
            </div>

            <!-- Body: sidebar + viewport -->
            <div class="flex flex-1 overflow-hidden">

                <!-- Tools Sidebar -->
                <div v-show="showTools"
                    class="shrink-0 flex flex-col p-3 gap-5 overflow-y-auto border-r border-white/10"
                    style="width:175px; background:#0f1a2e;">

                    <!-- Activar Lupa -->
                    <div class="flex flex-col gap-2">
                        <span class="text-white/40 text-xs uppercase tracking-widest font-semibold">Activar lupa</span>
                        <button @click="toggleLupa"
                            class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-150"
                            :class="lupaActive ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/60' : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white'">
                            <i class="pi pi-search text-xl" />
                        </button>
                    </div>

                    <!-- Medir -->
                    <div class="flex flex-col gap-2">
                        <span class="text-white/40 text-xs uppercase tracking-widest font-semibold">Medir</span>
                        <button @click="toggleMedir"
                            class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-150"
                            :class="medirActive ? 'bg-blue-600 text-white shadow-lg shadow-blue-900/60' : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white'">
                            <i class="pi pi-chart-line text-xl" />
                        </button>
                        <!-- Resultado de la medición -->
                        <span v-if="measureDisplay" class="text-blue-300 text-xs font-mono leading-tight">
                            {{ measureDisplay }}
                        </span>
                        <!-- Botón recalibrar (si ya hay calibración) -->
                        <button v-if="mmPerPx && medirActive"
                            @click="recalibrar"
                            class="text-white/30 hover:text-white/60 text-xs transition-colors">
                            Recalibrar
                        </button>
                    </div>

                    <!-- Invertir -->
                    <div class="flex flex-col gap-2">
                        <span class="text-white/40 text-xs uppercase tracking-widest font-semibold">Invertir</span>
                        <div class="flex flex-col gap-1.5">
                            <button @click="flippedH = !flippedH"
                                class="py-1.5 rounded text-xs font-medium transition-all"
                                :class="flippedH ? 'bg-blue-600 text-white' : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white'">
                                Horizontal
                            </button>
                            <button @click="flippedV = !flippedV"
                                class="py-1.5 rounded text-xs font-medium transition-all"
                                :class="flippedV ? 'bg-blue-600 text-white' : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white'">
                                Vertical
                            </button>
                        </div>
                    </div>

                    <!-- Brillo -->
                    <div class="flex flex-col gap-2">
                        <span class="text-white/40 text-xs uppercase tracking-widest font-semibold">Brillo</span>
                        <input type="range" v-model.number="brightness" min="0" max="300" step="1"
                            class="w-full cursor-pointer" style="accent-color:#8b5cf6;" />
                    </div>

                    <!-- Contraste -->
                    <div class="flex flex-col gap-2">
                        <span class="text-white/40 text-xs uppercase tracking-widest font-semibold">Contraste</span>
                        <input type="range" v-model.number="contrast" min="0" max="300" step="1"
                            class="w-full cursor-pointer" style="accent-color:#ec4899;" />
                    </div>

                    <button @click="resetFilters"
                        class="text-white/30 hover:text-white/60 text-xs text-center mt-auto transition-colors">
                        Resetear filtros
                    </button>
                </div>

                <!-- Image Viewport -->
                <div class="flex-1 overflow-hidden relative"
                    ref="viewport"
                    :class="cursorClass"
                    @mousedown="onMousedown"
                    @mousemove="onMousemove"
                    @mouseup="onMouseup"
                    @mouseleave="onMouseleave"
                    @wheel.prevent="onWheel">

                    <!-- Main image -->
                    <img :src="src" :alt="name"
                        ref="img"
                        draggable="false"
                        class="absolute pointer-events-none"
                        :style="imgStyle"
                        @load="onImgLoad" />

                    <!-- Measure SVG overlay -->
                    <svg v-if="medirActive"
                        class="absolute inset-0 pointer-events-none"
                        style="width:100%; height:100%; z-index:5;">

                        <line v-if="measure.p1 && !measure.p2 && cursorPos.x != null"
                            :x1="measure.p1.x" :y1="measure.p1.y"
                            :x2="cursorPos.x" :y2="cursorPos.y"
                            stroke="#3452ff" stroke-width="2" stroke-dasharray="6,3"
                            stroke-linecap="round" />

                        <line v-if="measure.p1 && measure.p2"
                            :x1="measure.p1.x" :y1="measure.p1.y"
                            :x2="measure.p2.x" :y2="measure.p2.y"
                            stroke="#3452ff" stroke-width="2.5" stroke-linecap="round" />

                        <circle v-if="measure.p1" :cx="measure.p1.x" :cy="measure.p1.y" r="4" fill="#3452ff" />
                        <circle v-if="measure.p2" :cx="measure.p2.x" :cy="measure.p2.y" r="4" fill="#3452ff" />

                        <template v-if="measure.p1 && measure.p2 && measureDisplay">
                            <rect
                                :x="(measure.p1.x + measure.p2.x)/2 - 42"
                                :y="(measure.p1.y + measure.p2.y)/2 - 24"
                                width="84" height="18" rx="4"
                                fill="rgba(0,0,0,0.80)" />
                            <text
                                :x="(measure.p1.x + measure.p2.x)/2"
                                :y="(measure.p1.y + measure.p2.y)/2 - 10"
                                fill="white" font-size="12" text-anchor="middle"
                                font-family="Arial,sans-serif">{{ measureDisplay }}</text>
                        </template>
                    </svg>

                    <!-- Lupa — CSS background-image approach -->
                    <div v-if="lupaActive && lupa.visible && lupaContainerStyle"
                        class="absolute pointer-events-none"
                        style="border-radius:50%; border:3px solid #3452ff; z-index:10;
                               box-shadow: 0 0 0 2px rgba(52,82,255,0.3), 0 4px 24px rgba(0,0,0,0.8);"
                        :style="lupaContainerStyle" />

                    <!-- Calibration dialog overlay -->
                    <Transition name="fade">
                    <div v-if="calibDialog"
                        class="absolute inset-0 z-20 flex items-center justify-center"
                        style="background:rgba(0,0,0,0.65);"
                        @mousedown.stop @click.stop>
                        <div class="rounded-xl shadow-2xl p-5 w-80"
                            style="background:#1a2540; border:1px solid rgba(255,255,255,0.12);">
                            <h3 class="text-white font-semibold text-sm mb-1">Calibrar medida</h3>
                            <p class="text-white/60 text-xs mb-4 leading-relaxed">
                                Indique la medida de la línea trazada, en milímetros:
                            </p>
                            <input
                                ref="calibInputEl"
                                v-model="calibInput"
                                type="number"
                                min="0.01"
                                step="0.1"
                                placeholder="ej: 25.5"
                                class="w-full rounded-lg px-3 py-2 text-white text-sm outline-none"
                                style="background:rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.2);"
                                @keydown.enter.stop="acceptCalib"
                                @keydown.esc.stop="cancelCalib"
                                @focus="$event.target.select()" />
                            <div class="flex justify-end gap-2 mt-4">
                                <button @click="cancelCalib"
                                    class="px-4 py-1.5 rounded-lg text-sm text-white/60 hover:text-white transition-colors"
                                    style="background:rgba(255,255,255,0.1);">
                                    Cancelar
                                </button>
                                <button @click="acceptCalib"
                                    class="px-4 py-1.5 rounded-lg text-sm text-white font-medium transition-colors"
                                    style="background:#7c3aed;">
                                    Aceptar
                                </button>
                            </div>
                        </div>
                    </div>
                    </Transition>
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 text-center text-white/30 text-xs py-1 bg-black/80">
                Rueda para zoom · Arrastra para mover · Esc para cerrar
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';

const LUPA_SIZE = 200;
const LUPA_ZOOM = 3;

const props = defineProps({
    src:  { type: String, default: null },
    name: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const container    = ref(null);
const viewport     = ref(null);
const img          = ref(null);
const calibInputEl = ref(null);

// View state
const scale    = ref(1);
const deg      = ref(0);
const tx       = ref(0);
const ty       = ref(0);
const dragging = ref(false);
const dragStart = reactive({ x: 0, y: 0, tx: 0, ty: 0 });

// Natural image dimensions (set on load)
const imgNatW = ref(0);
const imgNatH = ref(0);

// Tools panel
const showTools   = ref(true);
const lupaActive  = ref(false);
const medirActive = ref(false);
const flippedH    = ref(false);
const flippedV    = ref(false);
const brightness  = ref(100);
const contrast    = ref(100);

// Lupa tracking
const lupa      = reactive({ x: 0, y: 0, visible: false });
const cursorPos = reactive({ x: null, y: null });

// Measure state
const measure = reactive({ p1: null, p2: null });

// Calibration
const calibDialog = ref(false);
const calibInput  = ref('');
const mmPerPx     = ref(null); // mm per natural image pixel

// Raw pixel distance of current measurement
const measurePx = computed(() => {
    if (!measure.p1 || !measure.p2) return null;
    const dx = (measure.p2.x - measure.p1.x) / scale.value;
    const dy = (measure.p2.y - measure.p1.y) / scale.value;
    return Math.sqrt(dx * dx + dy * dy);
});

// Displayed distance string (mm if calibrated, px otherwise)
const measureDisplay = computed(() => {
    if (measurePx.value == null) return null;
    if (mmPerPx.value) {
        return (measurePx.value * mmPerPx.value).toFixed(2) + ' mm';
    }
    return Math.round(measurePx.value) + ' px';
});

// Image style
const imgStyle = computed(() => {
    const sx = flippedH.value ? -scale.value : scale.value;
    const sy = flippedV.value ? -scale.value : scale.value;
    return {
        transform: `translate(calc(-50% + ${tx.value}px), calc(-50% + ${ty.value}px)) scale(${sx}, ${sy}) rotate(${deg.value}deg)`,
        top:    '50%',
        left:   '50%',
        transition: dragging.value ? 'none' : 'transform 0.15s ease',
        filter: `brightness(${brightness.value}%) contrast(${contrast.value}%)`,
        maxWidth:  'none',
        maxHeight: 'none',
    };
});

// Lupa via CSS background-image
const lupaContainerStyle = computed(() => {
    if (!lupa.visible || !viewport.value || !imgNatW.value || !imgNatH.value) return null;

    const vpW  = viewport.value.clientWidth;
    const vpH  = viewport.value.clientHeight;
    const natW = imgNatW.value;
    const natH = imgNatH.value;
    const L    = LUPA_ZOOM;
    const S    = scale.value;
    const half = LUPA_SIZE / 2;

    const nat_x = flippedH.value
        ? natW / 2 - (lupa.x - vpW / 2 - tx.value) / S
        : (lupa.x - vpW / 2 - tx.value) / S + natW / 2;
    const nat_y = flippedV.value
        ? natH / 2 - (lupa.y - vpH / 2 - ty.value) / S
        : (lupa.y - vpH / 2 - ty.value) / S + natH / 2;

    const bgNat_x = flippedH.value ? natW - nat_x : nat_x;
    const bgNat_y = flippedV.value ? natH - nat_y : nat_y;

    const bgSizeW = natW * L * S;
    const bgSizeH = natH * L * S;
    const bgPosX  = half - bgNat_x * L * S;
    const bgPosY  = half - bgNat_y * L * S;

    const fX = flippedH.value ? -1 : 1;
    const fY = flippedV.value ? -1 : 1;

    return {
        width:  LUPA_SIZE + 'px',
        height: LUPA_SIZE + 'px',
        left:   (lupa.x - half) + 'px',
        top:    (lupa.y - half) + 'px',
        backgroundImage:    `url('${props.src}')`,
        backgroundSize:     `${bgSizeW}px ${bgSizeH}px`,
        backgroundPosition: `${bgPosX}px ${bgPosY}px`,
        backgroundRepeat:   'no-repeat',
        filter:    `brightness(${brightness.value}%) contrast(${contrast.value}%)`,
        transform: `scaleX(${fX}) scaleY(${fY})`,
    };
});

const cursorClass = computed(() => {
    if (lupaActive.value)  return 'cursor-none';
    if (medirActive.value) return 'cursor-crosshair';
    if (dragging.value)    return 'cursor-grabbing';
    return 'cursor-grab active:cursor-grabbing';
});

// ── View helpers ──────────────────────────────────────────────────────────────

function onImgLoad() {
    imgNatW.value = img.value?.naturalWidth  || 0;
    imgNatH.value = img.value?.naturalHeight || 0;
    fit();
    nextTick(() => container.value?.focus());
}

function fit() {
    if (!viewport.value || !img.value) return;
    const vw = viewport.value.clientWidth  - 40;
    const vh = viewport.value.clientHeight - 40;
    const iw = imgNatW.value || img.value.naturalWidth  || img.value.clientWidth  || 0;
    const ih = imgNatH.value || img.value.naturalHeight || img.value.clientHeight || 0;
    if (!iw || !ih) return;
    scale.value = Math.min(vw / iw, vh / ih);
    tx.value = 0;
    ty.value = 0;
}

function resetView()  { scale.value = 1; tx.value = 0; ty.value = 0; }
function zoomIn()     { scale.value = Math.min(scale.value * 1.25, 8); }
function zoomOut()    { scale.value = Math.max(scale.value / 1.25, 0.05); }
function rotateImg()  { deg.value = (deg.value + 90) % 360; }

function resetFilters() {
    brightness.value = 100;
    contrast.value   = 100;
    flippedH.value   = false;
    flippedV.value   = false;
}

// ── Tools ─────────────────────────────────────────────────────────────────────

function toggleLupa() {
    lupaActive.value = !lupaActive.value;
    if (lupaActive.value) { medirActive.value = false; }
}

function toggleMedir() {
    medirActive.value = !medirActive.value;
    if (medirActive.value) {
        lupaActive.value = false;
        measure.p1 = null;
        measure.p2 = null;
    }
}

// ── Calibration dialog ────────────────────────────────────────────────────────

function openCalibDialog() {
    calibInput.value  = '';
    calibDialog.value = true;
    nextTick(() => calibInputEl.value?.focus());
}

function acceptCalib() {
    const mm = parseFloat(calibInput.value);
    if (mm > 0 && measurePx.value && measurePx.value > 0) {
        mmPerPx.value = mm / measurePx.value;
    }
    calibDialog.value = false;
    nextTick(() => container.value?.focus());
}

function cancelCalib() {
    calibDialog.value = false;
    nextTick(() => container.value?.focus());
}

function recalibrar() {
    mmPerPx.value = null;
    measure.p1 = null;
    measure.p2 = null;
}

// ── Mouse events ──────────────────────────────────────────────────────────────

function onWheel(e) {
    if (calibDialog.value) return;
    const delta = e.deltaY < 0 ? 1.15 : 1 / 1.15;
    scale.value = Math.min(Math.max(scale.value * delta, 0.05), 8);
}

function onMousedown(e) {
    if (calibDialog.value) return;
    if (lupaActive.value) return;
    if (medirActive.value) {
        const rect = viewport.value.getBoundingClientRect();
        const cx = e.clientX - rect.left;
        const cy = e.clientY - rect.top;
        if (!measure.p1 || measure.p2) {
            // Start new line
            measure.p1 = { x: cx, y: cy };
            measure.p2 = null;
        } else {
            // Complete line → open calibration if not yet calibrated
            measure.p2 = { x: cx, y: cy };
            if (!mmPerPx.value) {
                nextTick(() => openCalibDialog());
            }
        }
        return;
    }
    dragging.value = true;
    dragStart.x  = e.clientX;
    dragStart.y  = e.clientY;
    dragStart.tx = tx.value;
    dragStart.ty = ty.value;
}

function onMousemove(e) {
    if (calibDialog.value) return;
    const rect = viewport.value.getBoundingClientRect();
    const cx = e.clientX - rect.left;
    const cy = e.clientY - rect.top;
    cursorPos.x = cx;
    cursorPos.y = cy;

    if (lupaActive.value) {
        lupa.x = cx;
        lupa.y = cy;
        lupa.visible = true;
    }

    if (!lupaActive.value && !medirActive.value && dragging.value) {
        tx.value = dragStart.tx + (e.clientX - dragStart.x);
        ty.value = dragStart.ty + (e.clientY - dragStart.y);
    }
}

function onMouseup()    { dragging.value = false; }
function onMouseleave() {
    dragging.value  = false;
    lupa.visible    = false;
    cursorPos.x     = null;
    cursorPos.y     = null;
}

// ── Keyboard shortcuts ────────────────────────────────────────────────────────

function onEsc() {
    if (calibDialog.value) { cancelCalib(); return; }
    emit('close');
}

function onKey(e) {
    if (!props.src) return;
    if (calibDialog.value) return;
    if (e.key === '+' || e.key === '=') zoomIn();
    if (e.key === '-')  zoomOut();
    if (e.key === '0')  resetView();
    if (e.key === 'r' || e.key === 'R') rotateImg();
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

// Reset state when image changes
watch(() => props.src, () => {
    scale.value    = 1;
    tx.value       = 0;
    ty.value       = 0;
    deg.value      = 0;
    imgNatW.value  = 0;
    imgNatH.value  = 0;
    brightness.value  = 100;
    contrast.value    = 100;
    flippedH.value    = false;
    flippedV.value    = false;
    lupaActive.value  = false;
    medirActive.value = false;
    measure.p1 = null;
    measure.p2 = null;
    lupa.visible    = false;
    calibDialog.value = false;
    mmPerPx.value     = null;

    nextTick(() => {
        container.value?.focus();
        if (img.value?.complete && img.value?.naturalWidth > 0) {
            imgNatW.value = img.value.naturalWidth;
            imgNatH.value = img.value.naturalHeight;
            fit();
        }
    });
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
