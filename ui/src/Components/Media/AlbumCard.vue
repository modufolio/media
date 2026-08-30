<template>
  <button
    class="group relative aspect-square bg-gray-100 rounded-sm overflow-hidden shadow-sm hover:shadow-md transition-all cursor-pointer text-left w-full"
    :class="{ 'opacity-40 scale-95': dragging }"
    :draggable="draggable"
    @click="$emit('click', album)"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
  >
    <!-- Cover image -->
    <img
      v-if="preview"
      :src="preview.thumbnail_url || preview.url"
      :alt="album.title"
      class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
      :style="preview.focus ? { objectPosition: focusToCss(preview.focus) } : {}"
    />

    <!-- No cover placeholder -->
    <div v-else class="w-full h-full flex items-center justify-center bg-gray-200">
      <svg
        class="w-12 h-12 text-gray-400"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <!-- Set icon (stack of photos) -->
        <template v-if="album.album_type === 1">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </template>
        <!-- Album icon (photo frame) -->
        <template v-else>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </template>
      </svg>
    </div>

    <!-- Hover overlay -->
    <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity" />

    <!-- Bottom info bar -->
    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent px-3 py-3">
      <p class="text-white text-sm font-semibold truncate leading-tight">{{ album.title }}</p>
      <p class="text-white/70 text-xs mt-0.5">
        {{ album.media_count }} {{ album.album_type === 1 ? 'album' : 'photo' }}{{ album.media_count !== 1 ? 's' : '' }}
      </p>
    </div>

    <!-- Type badge -->
    <div class="absolute top-2 left-2">
      <span
        v-if="album.album_type === 1"
        class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-black/50 text-white text-xs rounded"
      >
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
        Set
      </span>
    </div>
  </button>
</template>

<script setup>
import { computed, ref } from 'vue'
import { focusToCss } from './imageUtils.js'

const props = defineProps({
  album: { type: Object, required: true },
  draggable: { type: Boolean, default: false },
})

const emit = defineEmits(['click', 'dragstart', 'dragend'])

const dragging = ref(false)

const preview = computed(() => props.album.preview ?? props.album.covers?.[0] ?? null)

const onDragStart = (event) => {
  dragging.value = true
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('application/json', JSON.stringify({
    type: 'album',
    albumId: props.album.id,
  }))
  emit('dragstart', props.album)
}

const onDragEnd = () => {
  dragging.value = false
  emit('dragend', props.album)
}
</script>
