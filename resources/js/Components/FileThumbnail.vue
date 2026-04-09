<template>
    <div style="width:150px; height:150px; flex-shrink:0; position:relative;"
        class="border border-gray-200 rounded overflow-hidden bg-gray-100 group cursor-pointer">

        <!-- PDF: icon card -->
        <template v-if="isPdf">
            <a :href="imgSrc" target="_blank" style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%;"
                class="hover:bg-red-50 transition-colors no-underline">
                <i class="pi pi-file-pdf" style="font-size:3rem; color:#e3342f;" />
                <span style="font-size:11px; margin-top:6px; padding:0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px; color:#6b7280; text-align:center;">
                    {{ file.name || 'PDF' }}
                </span>
            </a>
        </template>

        <!-- Image/unknown: try as image, fallback to icon -->
        <template v-else>
            <!-- While loading or after success: show img -->
            <img v-if="!imgFailed"
                :src="imgSrc"
                :alt="file.name"
                loading="lazy"
                style="width:100%; height:100%; object-fit:cover; display:block;"
                @click="$emit('lightbox', { ...file, url: imgSrc })"
                @error="imgFailed = true" />

            <!-- Failed: generic download card -->
            <a v-else :href="imgSrc" target="_blank"
                style="display:flex; flex-direction:column; align-items:center; justify-content:center; width:100%; height:100%;"
                class="hover:bg-blue-50 transition-colors no-underline">
                <i class="pi pi-file" style="font-size:3rem; color:#9ca3af;" />
                <span style="font-size:11px; margin-top:6px; padding:0 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:130px; color:#6b7280; text-align:center;">
                    {{ file.name || 'Archivo' }}
                </span>
            </a>

            <!-- Zoom overlay (only when image loaded) -->
            <div v-if="!imgFailed"
                style="position:absolute; inset:0; background:rgba(0,0,0,0); transition:background 0.2s; display:flex; align-items:flex-end; justify-content:flex-end;"
                class="group-hover:bg-black/30"
                @click="$emit('lightbox', { ...file, url: imgSrc })">
                <span style="margin:6px; background:rgba(255,255,255,0.9); border-radius:4px; padding:3px 5px;"
                    class="opacity-0 group-hover:opacity-100 transition-opacity">
                    <i class="pi pi-search" style="font-size:12px; color:#2563eb;" />
                </span>
            </div>
        </template>

        <!-- Tooltip bar -->
        <div style="position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,0.6); color:white; font-size:10px; padding:2px 4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
            class="opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
            {{ file.name }}
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    file: { type: Object, required: true },
});

defineEmits(['lightbox']);

const imgFailed = ref(false);
const isPdf = computed(() => (props.file.extension || '').toLowerCase() === 'pdf');
const serveUrl = computed(() => route('archivos.serve', props.file.id));
// Prefer the pre-signed S3 URL (already provided by controller) over the server proxy
const imgSrc = computed(() => props.file.url || serveUrl.value);
</script>
