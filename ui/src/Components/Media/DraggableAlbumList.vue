<template>
  <div>
    <template v-for="(node, index) in items" :key="node.id">

      <!-- Insertion line ABOVE this item -->
      <div
        v-show="dropState.dropIndex === index"
        class="h-0.5 mx-3 rounded-full bg-primary pointer-events-none"
      />

      <!-- Dragover zone (only the row wrapper, not the subtree) -->
      <div
        @dragover.prevent.stop="$emit('dragover-item', { index, event: $event })"
        @dragleave.stop="$emit('dragleave', $event)"
        @drop.prevent.stop="$emit('drop-item')"
      >
        <AlbumTreeItem
          :album="node"
          :selected-album-id="selectedAlbumId"
          :depth="0"
          @select="$emit('select', $event)"
          @edit="$emit('edit', $event)"
          @delete="$emit('delete', $event)"
          @drop-media="$emit('drop-media', $event)"
          @reorder-children="$emit('reorder-children', $event)"
          @move-into-set="$emit('move-into-set', $event)"
        />
      </div>

    </template>

    <!-- Insertion line AFTER the last item -->
    <div
      v-show="dropState.dropIndex === items.length"
      class="h-0.5 mx-3 rounded-full bg-primary pointer-events-none"
    />
  </div>
</template>

<script setup lang="ts">
import AlbumTreeItem from './AlbumTreeItem.vue'
import type { Album, AlbumId, AlbumNode, DropState, MediaId } from '../../types/media'

withDefaults(defineProps<{
  items: AlbumNode[]
  dropState: DropState
  selectedAlbumId?: AlbumId | null
}>(), {
  selectedAlbumId: null,
})

defineEmits<{
  'dragover-item': [payload: { index: number; event: DragEvent }]
  dragleave: [event: DragEvent]
  'drop-item': []
  select: [albumId: AlbumId]
  edit: [album: Album]
  delete: [album: Album]
  'drop-media': [payload: { albumId: AlbumId; mediaId: MediaId }]
  'reorder-children': [payload: { setId: AlbumId; albumIds: AlbumId[] }]
  'move-into-set': [payload: { albumId: AlbumId; setId: AlbumId }]
}>()
</script>
