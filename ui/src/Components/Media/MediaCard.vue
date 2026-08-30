<template>
  <div
    :class="[
      'group relative bg-gray-100 rounded overflow-hidden shadow-sm hover:shadow-md transition-shadow cursor-pointer aspect-square',
      selected ? 'ring-2 ring-primary-500 ring-offset-1' : '',
      isDragging ? 'opacity-50 scale-95' : '',
      file.is_image && !imgBroken && !imgLoaded && !file.blurhash ? 'animate-pulse' : '',
    ]"
    :draggable="true"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    @click="onClick"
    @dblclick="onDblClick"
  >
    <!-- Blurhash blur-up placeholder (shown while the real image loads) -->
    <canvas
      v-if="file.blurhash && file.is_image && !imgBroken"
      ref="blurhashCanvas"
      :width="32"
      :height="32"
      class="absolute inset-0 w-full h-full"
      style="image-rendering: pixelated; filter: blur(6px); transform: scale(1.1);"
      aria-hidden="true"
    />

    <!-- Real image (fades in on load) -->
    <img
      v-if="(file.thumbnail_url || file.is_image) && !imgBroken"
      :src="file.thumbnail_url || file.url"
      :alt="file.alt_text || file.original_filename"
      class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300"
      :class="imgLoaded ? 'opacity-100' : 'opacity-0'"
      loading="lazy"
      draggable="false"
      @load="imgLoaded = true"
      @error="onImgError"
    />

    <!-- Video pill overlay -->
    <div
      v-if="file.is_video"
      class="absolute top-2 left-2 bg-gray-800/50 backdrop-blur-sm text-white px-2.5 py-1 rounded-full text-xs font-semibold shadow-lg border border-white/20 z-10"
    >
      VIDEO
    </div>

    <!-- Broken image fallback -->
    <div v-else-if="imgBroken" class="absolute inset-0 w-full h-full flex items-center justify-center bg-gray-100">
      <svg class="h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
    </div>

    <!-- Non-image file icon -->
    <div v-else-if="!file.is_image" class="absolute inset-0 w-full h-full flex items-center justify-center bg-gray-200">
      <svg class="h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
      </svg>
    </div>

    <!-- Overlay on hover -->
    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none" />

    <!-- Heart (Favourite) button -->
    <button
      v-if="!hideFavorite"
      type="button"
      class="absolute top-2 right-2 p-1 rounded-full transition-all pointer-events-auto z-10 opacity-0 group-hover:opacity-100 text-white"
      :aria-label="file.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
      :aria-pressed="file.is_favorite"
      :title="file.is_favorite ? 'Remove from favorites' : 'Add to favorites'"
      @click.stop="onFavoriteClick"
    >
      <svg class="w-5 h-5" :fill="file.is_favorite ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
      </svg>
    </button>

    <!-- File info badge (hover) -->
    <div class="absolute bottom-0 left-0 right-0 bg-linear-to-t from-black/60 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
      <p class="text-white text-xs font-medium truncate">
        {{ file.original_filename }}
      </p>
      <p class="text-white/80 text-xs">
        {{ formatBytes(file.file_size) }}
        <span v-if="file.width && file.height"> &bull; {{ file.width }}&times;{{ file.height }}</span>
      </p>
    </div>

    <!-- Star rating overlay (Lightroom-style) -->
    <div
      v-if="showRating && file.rating > 0"
      class="absolute bottom-1.5 left-1.5 flex items-center gap-px pointer-events-none z-20"
    >
      <svg
        v-for="n in 5"
        :key="n"
        class="w-3 h-3 drop-shadow"
        :class="n <= file.rating ? 'text-amber-400' : 'text-white/30'"
        fill="currentColor"
        viewBox="0 0 24 24"
      >
        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
      </svg>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { decode } from 'blurhash'
import { useFavorites } from '../../Composables/useFavorites'

const props = defineProps({
  file: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  selectedCount: { type: Number, default: 0 },
  hideFavorite: { type: Boolean, default: false },
  showRating: { type: Boolean, default: false },
})

const emit = defineEmits(['view', 'select', 'drag-start', 'drag-end'])

const { toggleFavorite } = useFavorites()

const imgBroken = ref(false)
const imgLoaded = ref(false)
const isDragging = ref(false)
const blurhashCanvas = ref(null)

const onImgError = () => {
  imgBroken.value = true
  imgLoaded.value = false
}

onMounted(() => {
  const canvas = blurhashCanvas.value
  if (props.file.blurhash && canvas) {
    const pixels = decode(props.file.blurhash, 32, 32)
    const ctx = canvas.getContext('2d')
    if (ctx) ctx.putImageData(new ImageData(pixels, 32, 32), 0, 0)
  }
})

const onClick = (event) => {
  emit('select', { file: props.file, event })
}

const onDblClick = () => {
  emit('view', props.file)
}

const onFavoriteClick = () => {
  toggleFavorite(props.file)
}

const formatBytes = (bytes, decimals = 2) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

const onDragStart = (event) => {
  isDragging.value = true
  const data = {
    type: 'media',
    mediaId: props.file.id,
    selectedCount: props.selectedCount || 1,
  }
  event.dataTransfer.setData('application/json', JSON.stringify(data))
  event.dataTransfer.effectAllowed = 'copy'
  emit('drag-start', props.file)
}

const onDragEnd = () => {
  isDragging.value = false
  emit('drag-end', props.file)
}
</script>
