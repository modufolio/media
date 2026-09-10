<template>
  <div
    @dragover.prevent="onDragOver"
    @dragenter.prevent="onDragEnter"
    @dragleave.prevent="onDragLeave"
    @drop.prevent="onDrop"
    class="relative"
  >
    <!-- Dropzone overlay (only for OS file drags when upload is allowed) -->
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="allowUpload && isFileDragging"
        class="absolute inset-0 z-20 border-2 border-dashed border-primary bg-primary-surface/80 rounded-lg flex items-center justify-center pointer-events-none"
      >
        <div class="text-center">
          <svg class="mx-auto h-10 w-10 text-primary" stroke="currentColor" fill="none" viewBox="0 0 48 48">
            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <p class="mt-2 text-sm font-semibold text-primary">Drop files to upload</p>
        </div>
      </div>
    </Transition>

    <!-- Exact item count is known ahead of the fetch (e.g. an album's media_count) -->
    <div v-if="loading && files.length === 0 && skeletonCount !== null">
      <div class="media-grid-loader">
        <div
          v-for="n in skeletonCount"
          :key="n"
          class="aspect-square bg-media-placeholder rounded animate-pulse"
        />
      </div>
    </div>

    <!-- Count isn't knowable ahead of the fetch — a fixed-size skeleton grid
         would just be a guess, so show a plain spinner instead. -->
    <div v-else-if="loading && files.length === 0" class="flex items-center justify-center py-20">
      <svg class="w-8 h-8 animate-spin text-ink-3" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </div>

    <!-- Loading overlay when refreshing existing content (keeps height stable) -->
    <div v-else-if="loading && files.length > 0" class="relative">
      <div class="media-grid opacity-40 pointer-events-none">
        <MediaCard
          v-for="file in files"
          :key="file.id"
          :file="file"
          :selected="false"
          :selected-count="0"
        />
      </div>
      <div class="absolute inset-0 flex items-center justify-center">
        <svg class="w-8 h-8 animate-spin text-ink-3" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
      </div>
    </div>

    <div v-else-if="files.length > 0">
      <div class="media-grid">
        <!-- v-memo skips vdom diffing for rows whose inputs are unchanged
             (reconcile keeps row identity, so this is safe). The array must
             list every file field this subtree renders that can change after
             first render, plus selection, position and drop-highlight state. -->
        <div
          v-for="(file, index) in files"
          :key="file.id"
          v-memo="[
            file.thumbnail_url, file.url, file.blurhash, file.alt_text,
            file.original_filename, file.file_size, file.width, file.height,
            file.is_favorite, file.rating,
            selectedIds.has(file.id), selectedIds.size,
            index, showRating, hideFavorite,
            reorderable && reorderDropIndex === index && reorderDragIndex !== null && reorderDragIndex !== index,
          ]"
          class="relative"
          :class="reorderable && reorderDropIndex === index && reorderDragIndex !== null && reorderDragIndex !== index
            ? 'ring-2 ring-primary ring-offset-1 rounded overflow-hidden'
            : ''"
          @dragover="reorderable ? onReorderDragOver($event, index) : undefined"
          @dragleave="reorderable ? onReorderDragLeave($event) : undefined"
          @drop="reorderable ? onReorderDrop($event, index) : undefined"
        >
          <MediaCard
            :file="file"
            :selected="selectedIds.has(file.id)"
            :selected-count="selectedIds.has(file.id) ? selectedIds.size : 1"
            :hide-favorite="hideFavorite"
            :show-rating="showRating"
            @view="$emit('view', $event)"
            @select="handleSelect"
            @drag-start="reorderable ? onCardDragStart($event, index) : $emit('drag-start', $event)"
            @drag-end="reorderable ? onCardDragEnd($event) : $emit('drag-end', $event)"
          />
        </div>
      </div>
    </div>

    <div
      v-else
      class="text-center py-12"
      :class="allowUpload ? 'cursor-pointer' : ''"
      @click="allowUpload && triggerFileInput()"
    >
      <svg class="mx-auto h-12 w-12 text-ink-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <p class="mt-2 text-ink-2">{{ emptyMessage }}</p>
      <p class="text-sm text-ink-3">{{ allowUpload ? emptySubMessageUpload : emptySubMessage }}</p>
    </div>

    <!-- Hidden file input for click-to-upload -->
    <input
      v-if="allowUpload"
      ref="fileInput"
      type="file"
      @change="handleFileSelect"
      multiple
      accept="image/*,video/*"
      class="hidden"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import MediaCard from './MediaCard.vue'

const props = defineProps({
  files: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  skeletonCount: { type: Number, default: null },
  emptyMessage: { type: String, default: 'No media found.' },
  emptySubMessage: { type: String, default: 'Upload files to get started.' },
  emptySubMessageUpload: { type: String, default: 'Drag and drop files here or click to upload.' },
  allowUpload: { type: Boolean, default: false },
  hideFavorite: { type: Boolean, default: false },
  showRating: { type: Boolean, default: false },
  reorderable: { type: Boolean, default: false },
})

const emit = defineEmits(['view', 'select', 'selection-change', 'drag-start', 'drag-end', 'files-dropped', 'reorder'])

const selectedIds = ref(new Set())
const lastSelectedIndex = ref(null)
const isFileDragging = ref(false)
const fileInput = ref(null)

// Track nested dragenter/dragleave via a counter
let dragCounter = 0

const hasFiles = (event) => {
  return event.dataTransfer?.types?.includes('Files')
}

const isMediaDrag = (event) => {
  return event.dataTransfer?.types?.includes('application/json')
}

// ── Drag-to-reorder state (only active when reorderable = true) ─
const reorderDragIndex = ref(null)
const reorderDropIndex = ref(null)

const onCardDragStart = (file, index) => {
  reorderDragIndex.value = index
  emit('drag-start', file)
}

const onCardDragEnd = (file) => {
  reorderDragIndex.value = null
  reorderDropIndex.value = null
  emit('drag-end', file)
}

const onReorderDragOver = (event, index) => {
  if (reorderDragIndex.value === null || !isMediaDrag(event)) return
  event.preventDefault()
  reorderDropIndex.value = index
}

const onReorderDragLeave = (event) => {
  if (!event.currentTarget?.contains(event.relatedTarget)) {
    reorderDropIndex.value = null
  }
}

const onReorderDrop = (event, index) => {
  event.preventDefault()
  const fromIndex = reorderDragIndex.value
  reorderDragIndex.value = null
  reorderDropIndex.value = null
  if (fromIndex === null || fromIndex === index) return
  emit('reorder', { fromIndex, toIndex: index })
}

const onDragEnter = (event) => {
  if (!props.allowUpload || !hasFiles(event)) return
  dragCounter++
  isFileDragging.value = true
}

const onDragOver = (event) => {
  if (!props.allowUpload || !hasFiles(event)) return
  event.dataTransfer.dropEffect = 'copy'
}

const onDragLeave = (event) => {
  if (!props.allowUpload || !hasFiles(event)) return
  dragCounter--
  if (dragCounter <= 0) {
    dragCounter = 0
    isFileDragging.value = false
  }
}

const onDrop = (event) => {
  if (!props.allowUpload || !hasFiles(event)) return
  dragCounter = 0
  isFileDragging.value = false

  const files = Array.from(event.dataTransfer?.files ?? [])
  if (files.length > 0) {
    emit('files-dropped', files)
  }
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileSelect = (event) => {
  const files = Array.from(event.target?.files ?? [])
  if (files.length > 0) {
    emit('files-dropped', files)
  }
  if (event.target) {
    event.target.value = ''
  }
}

const handleSelect = ({ file, event }) => {
  const isMetaOrCtrl = event.metaKey || event.ctrlKey
  const isShift = event.shiftKey
  const clickedIndex = props.files.findIndex(f => f.id === file.id)

  if (isShift && lastSelectedIndex.value !== null) {
    // Range select: select all items between last selected and current
    const start = Math.min(lastSelectedIndex.value, clickedIndex)
    const end = Math.max(lastSelectedIndex.value, clickedIndex)

    if (!isMetaOrCtrl) {
      // Without Ctrl/Cmd, replace selection with range
      selectedIds.value = new Set()
    }
    for (let i = start; i <= end; i++) {
      selectedIds.value.add(props.files[i].id)
    }
    selectedIds.value = new Set(selectedIds.value)
  } else if (isMetaOrCtrl) {
    // Toggle individual item
    if (selectedIds.value.has(file.id)) {
      selectedIds.value.delete(file.id)
    } else {
      selectedIds.value.add(file.id)
    }
    selectedIds.value = new Set(selectedIds.value)
    lastSelectedIndex.value = clickedIndex
  } else {
    // Plain click: select only this item
    selectedIds.value = new Set([file.id])
    lastSelectedIndex.value = clickedIndex
    emit('select', file)
  }

  emit('selection-change', [...selectedIds.value])
}

const clearSelection = () => {
  selectedIds.value = new Set()
  emit('selection-change', [])
}

const getSelectedIds = () => [...selectedIds.value]

const selectAll = () => {
  selectedIds.value = new Set(props.files.map(f => f.id))
  lastSelectedIndex.value = null
  emit('selection-change', [...selectedIds.value])
}

defineExpose({
  clearSelection,
  getSelectedIds,
  selectedIds,
  selectAll,
})
</script>
