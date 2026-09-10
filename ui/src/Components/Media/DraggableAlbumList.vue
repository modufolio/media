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

<script setup>
import AlbumTreeItem from './AlbumTreeItem.vue'

defineProps({
  items:           { type: Array,          required: true },
  dropState:       { type: Object,         required: true },
  selectedAlbumId: { type: [String, null], default: null },
})

defineEmits([
  'dragover-item', 'dragleave', 'drop-item',
  'select', 'edit', 'delete', 'drop-media', 'reorder-children', 'move-into-set',
])
</script>
