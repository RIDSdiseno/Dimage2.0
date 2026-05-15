<template>
    <Teleport to="body">
        <div v-if="src" class="fixed inset-0 z-50 flex flex-col bg-black select-none"
            @keydown.esc="$emit('close')" tabindex="0" ref="container">

            <!-- Toolbar -->
            <div class="flex items-center justify-between px-4 py-2 bg-black/80 z-10 shrink-0">
                <span class="text-white/70 text-sm truncate max-w-xs">{{ name }}</span>
                <div class="flex items-center gap-1">
                    <button @click="zoomOut" title="Alejar" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-search-minus" /></button>
                    <span class="text-white/60 text-xs w-12 text-center">{{ Math.round(scale * 100) }}%</span>
                    <button @click="zoomIn"  title="Acercar" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-search-plus" /></button>
                    <button @click="fit"     title="Ajustar a pantalla" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-expand" /></button>
                    <button @click="reset"   title="Tamaño real (100%)" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-xs font-bold transition-colors cursor-pointer">1:1</button>
                    <button @click="rotate"  title="Rotar" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer"><i class="pi pi-refresh" /></button>
                    <a :href="src" target="_blank" title="Abrir original" class="text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer">
                        <i class="pi pi-external-link" />
                    </a>
                    <button @click="emit('close')" title="Cerrar (Esc)" class="text-red-400 hover:text-red-300 bg-white/10 hover:bg-white/20 rounded px-2 py-1 text-sm transition-colors cursor-pointer ml-2">
                        <i class="pi pi-times text-lg" />
                    </button>
                </div>
            </div>

            <!-- Image area -->
            <div class="flex-1 overflow-hidden relative cursor-grab active:cursor-grabbing"
                ref="viewport"
                @mousedown="startDrag"
                @mousemove="onDrag"
                @mouseup="endDrag"
                @mouseleave="endDrag"
                @wheel.prevent="onWheel"
                @click.self="$emit('close')">

                <img :src="src" :alt="name"
                    ref="img"
                    draggable="false"
                    class="absolute pointer-events-none"
                    :style="imgStyle"
                    @load="onImgLoad" />
            </div>

            <!-- Footer hint -->
            <div class="shrink-0 text-center text-white/30 text-xs py-1 bg-black/80">
                Rueda para zoom · Arrastra para mover · Esc para cerrar
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    src:  { type: String, default: null },
    name: { type: String, default: '' },
});
const emit = defineEmits(['close']);

const container = ref(null);
const viewport  = ref(null);
const img       = ref(null);

const scale   = ref(1);
const deg     = ref(0);
const tx      = ref(0);
const ty      = ref(0);
const dragging = ref(false);
const dragStart = reactive({ x: 0, y: 0, tx: 0, ty: 0 });

const imgStyle = computed(() => ({
    transform: `translate(calc(-50% + ${tx.value}px), calc(-50% + ${ty.value}px)) scale(${scale.value}) rotate(${deg.value}deg)`,
    top: '50%',
    left: '50%',
    transition: dragging.value ? 'none' : 'transform 0.15s ease',
    maxWidth: 'none',
    maxHeight: 'none',
}));

function onImgLoad() {
    fit();
    nextTick(() => container.value?.focus());
}

function fit() {
    if (!viewport.value || !img.value) return;
    const vw = viewport.value.clientWidth  - 40;
    const vh = viewport.value.clientHeight - 40;
    const iw = img.value.naturalWidth  || img.value.clientWidth;
    const ih = img.value.naturalHeight || img.value.clientHeight;
    scale.value = Math.min(vw / iw, vh / ih, 1);
    tx.value = 0;
    ty.value = 0;
}

function reset() { scale.value = 1; tx.value = 0; ty.value = 0; }
function zoomIn()  { scale.value = Math.min(scale.value * 1.25, 8); }
function zoomOut() { scale.value = Math.max(scale.value / 1.25, 0.05); }
function rotate()  { deg.value = (deg.value + 90) % 360; }

function onWheel(e) {
    const delta = e.deltaY < 0 ? 1.15 : 1 / 1.15;
    scale.value = Math.min(Math.max(scale.value * delta, 0.05), 8);
}

function startDrag(e) {
    dragging.value = true;
    dragStart.x  = e.clientX;
    dragStart.y  = e.clientY;
    dragStart.tx = tx.value;
    dragStart.ty = ty.value;
}
function onDrag(e) {
    if (!dragging.value) return;
    tx.value = dragStart.tx + (e.clientX - dragStart.x);
    ty.value = dragStart.ty + (e.clientY - dragStart.y);
}
function endDrag() { dragging.value = false; }

// Keyboard shortcuts
function onKey(e) {
    if (!props.src) return;
    if (e.key === 'Escape') emit('close');
    if (e.key === '+' || e.key === '=') zoomIn();
    if (e.key === '-') zoomOut();
    if (e.key === '0') reset();
    if (e.key === 'r' || e.key === 'R') rotate();
}
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

// Reset state when image changes
watch(() => props.src, () => {
    scale.value = 1; tx.value = 0; ty.value = 0; deg.value = 0;
    nextTick(() => container.value?.focus());
});
</script>

