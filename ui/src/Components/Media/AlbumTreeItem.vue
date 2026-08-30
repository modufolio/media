<template>
  <div>
    <!-- Album / Set row -->
    <div
      :class="[
        'relative flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all cursor-pointer group',
        isSelected
          ? 'bg-gray-100 text-gray-950 font-medium dark:bg-white/5 dark:text-white'
          : 'text-gray-950 hover:bg-gray-50 focus-visible:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5',
        isDragOver
          ? 'ring-2 ring-gray-300 bg-gray-50 dark:ring-white/20 dark:bg-white/5'
          : '',
      ]"
      :style="{ paddingLeft: (depth * 16 + 12) + 'px' }"
      draggable="true"
      @click="$emit('select', album.id)"
      @dragstart.stop="onAlbumDragStart"
      @dragend.stop="onAlbumDragEnd"
      @dragover.prevent="onDragOver"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
    >
      <!-- Drag handle: absolutely positioned in the left indent area, never affects layout -->
      <span
        class="absolute top-1/2 -translate-y-1/2 pointer-events-none flex items-center justify-center opacity-0 group-hover:opacity-100 cursor-grab text-gray-300 transition-opacity"
        :style="{ left: (depth * 16 + 1) + 'px', width: '10px' }"
      >
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path d="M7 2a1 1 0 000 2 1 1 0 000-2zM7 9a1 1 0 000 2 1 1 0 000-2zM7 16a1 1 0 000 2 1 1 0 000-2zM13 2a1 1 0 000 2 1 1 0 000-2zM13 9a1 1 0 000 2 1 1 0 000-2zM13 16a1 1 0 000 2 1 1 0 000-2z" />
        </svg>
      </span>

      <!-- Expand/collapse: absolutely positioned just left of the icon, never affects layout -->
      <button
        v-if="album.album_type === 1 && album.children?.length > 0"
        @click.stop="isExpanded = !isExpanded"
        class="absolute top-1/2 -translate-y-1/2 flex items-center justify-center w-3.5 h-4 text-gray-950 hover:text-gray-600"
        :style="{ left: (depth * 16 - 2) + 'px' }"
      >
        <svg
          :class="['w-3 h-3 transition-transform', isExpanded ? 'rotate-90' : '']"
          fill="currentColor" viewBox="0 0 20 20"
        >
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <!-- Icon + title: icon now starts at paddingLeft, aligned with Content / Last Import -->
      <span class="flex items-center gap-2 min-w-0 flex-1">
        <svg v-if="album.album_type === 1" class="w-4 h-4 flex-shrink-0 text-gray-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
        </svg>
        <svg v-else class="w-4 h-4 flex-shrink-0 text-gray-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>

        <!-- Title -->
        <span class="truncate">{{ album.title }}</span>
      </span>

      <!-- Media count -->
      <span class="text-xs text-gray-950 flex-shrink-0">{{ albumCounts[album.id] ?? album.media_count }}</span>
    </div>

    <!-- Children — with drag-reorder wrappers -->
    <div v-if="isExpanded && localChildren.length > 0">
      <template v-for="(child, index) in localChildren" :key="child.id">

        <!-- Insertion indicator: above this child -->
        <div
          v-show="childDrop.dropIndex === index"
          :style="{ marginLeft: ((depth + 1) * 16 + 12) + 'px' }"
          class="h-0.5 mr-3 rounded-full bg-gray-300 dark:bg-white/20 pointer-events-none"
        />

        <!-- Drag-over zone wrapping each child row only (not its own subtree) -->
        <div
          @dragover.prevent.stop="onChildDragOver(index, $event)"
          @dragleave.stop="onChildDragLeave($event)"
          @drop.prevent.stop="onChildDrop"
        >
          <AlbumTreeItem
            :album="child"
            :selected-album-id="selectedAlbumId"
            :depth="depth + 1"
            @select="$emit('select', $event)"
            @edit="$emit('edit', $event)"
            @delete="$emit('delete', $event)"
            @drop-media="$emit('drop-media', $event)"
            @reorder-children="$emit('reorder-children', $event)"
          />
        </div>
      </template>

      <!-- Insertion indicator: after the last child -->
      <div
        v-show="childDrop.dropIndex === localChildren.length"
        :style="{ marginLeft: ((depth + 1) * 16 + 12) + 'px' }"
        class="h-0.5 mr-3 rounded-full bg-primary-400 pointer-events-none"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { activeDragAlbumId } from './albumDragState.js'
import { useLibraryCounts } from '../../Composables/useLibraryCounts'
import { useAlbumTreeExpansion } from '../../Composables/useAlbumTreeExpansion'

const props = defineProps({
  album:           { type: Object,          required: true },
  selectedAlbumId: { type: [String, null],  default: null },
  depth:           { type: Number,          default: 0 },
})

const emit = defineEmits(['select', 'edit', 'delete', 'drop-media', 'reorder-children', 'move-into-set'])

const { albumCounts } = useLibraryCounts()

const { isExpanded } = useAlbumTreeExpansion({
  albumId: props.album.id,
  defaultExpanded: false,
  onError: (error) => console.warn('Album expansion persistence error:', error),
})

const isDragOver = ref(false)

const isSelected = computed(() => props.selectedAlbumId === props.album.id)

// ── Album drag-to-reorder ─────────────────────────────────────────
const onAlbumDragStart = (event) => {
  activeDragAlbumId.value = props.album.id
  event.dataTransfer.setData('application/x-album-id', String(props.album.id))
  event.dataTransfer.effectAllowed = 'move'
}

const onAlbumDragEnd = () => {
  activeDragAlbumId.value = null
}

// ── Media drop + album-into-set ───────────────────────────────────
const isAlbumDrag = (event) => event.dataTransfer.types.includes('application/x-album-id')

const onDragOver = (event) => {
  if (isAlbumDrag(event)) {
    if (props.album.album_type === 1) {
      // Sets accept album drops — highlight and stop bubbling so no insertion
      // line appears in the DraggableAlbumList wrapper around this item.
      isDragOver.value = true
      event.stopPropagation()
    }
    // Leaf albums: let the event bubble up to DraggableAlbumList for reordering
    return
  }
  // Media drag: only leaf albums accept media drops
  if (props.album.album_type === 0) {
    isDragOver.value = true
    event.dataTransfer.dropEffect = 'copy'
  }
}

const onDragLeave = () => {
  isDragOver.value = false
}

const onDrop = (event) => {
  isDragOver.value = false
  if (isAlbumDrag(event)) {
    if (props.album.album_type === 1) {
      event.stopPropagation() // Don't let DraggableAlbumList treat this as a reorder
      const draggedId = activeDragAlbumId.value
      if (
        draggedId !== null &&
        draggedId !== props.album.id &&
        !localChildren.value.some(c => c.id === draggedId)
      ) {
        emit('move-into-set', { albumId: draggedId, setId: props.album.id })
      }
    }
    return
  }
  if (props.album.album_type !== 0) return
  try {
    const data = JSON.parse(event.dataTransfer.getData('application/json'))
    if (data.type === 'media') {
      emit('drop-media', { albumId: props.album.id, mediaId: data.mediaId })
    }
  } catch (e) {
    console.error('Drop parse error:', e)
  }
}

// ── Children drag-reorder (this album is a set) ───────────────────
const localChildren = ref([...(props.album.children ?? [])])

watch(
  () => props.album.children,
  (newChildren) => {
    if (activeDragAlbumId.value === null) {
      localChildren.value = [...(newChildren ?? [])]
    }
  },
  { deep: false },
)

const childDrop = reactive({ dropIndex: null })

const onChildDragOver = (index, event) => {
  if (!isAlbumDrag(event)) return
  const rect = event.currentTarget.getBoundingClientRect()
  childDrop.dropIndex = event.clientY < rect.top + rect.height / 2 ? index : index + 1
}

const onChildDragLeave = (event) => {
  if (!event.currentTarget.contains(event.relatedTarget)) {
    childDrop.dropIndex = null
  }
}

const onChildDrop = () => {
  const draggingId = activeDragAlbumId.value
  if (draggingId === null || childDrop.dropIndex === null) {
    childDrop.dropIndex = null
    return
  }

  const fromIndex = localChildren.value.findIndex(c => c.id === draggingId)
  if (fromIndex === -1) { childDrop.dropIndex = null; return }

  const newChildren = [...localChildren.value]
  const [moved] = newChildren.splice(fromIndex, 1)
  let insertAt = childDrop.dropIndex
  if (insertAt > fromIndex) insertAt--
  newChildren.splice(insertAt, 0, moved)

  localChildren.value = newChildren
  childDrop.dropIndex = null

  emit('reorder-children', { setId: props.album.id, albumIds: newChildren.map(c => c.id) })
}
</script>
