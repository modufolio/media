<template>
  <div v-if="totalPages > 1" class="flex items-center justify-center gap-1 mt-4 px-6 pb-6">
    <button
      type="button"
      :disabled="currentPage <= 1"
      class="px-2.5 py-1.5 text-sm rounded-md border border-line text-ink-2 disabled:opacity-40 hover:bg-hover transition-colors"
      @click="$emit('navigate', currentPage - 1)"
    >←</button>

    <template v-for="item in items" :key="item.key">
      <button
        v-if="item.type === 'page'"
        type="button"
        class="min-w-8 px-2.5 py-1.5 text-sm rounded-md border transition-colors"
        :class="item.value === currentPage
          ? 'border-primary bg-primary-surface text-primary-on-surface font-medium'
          : 'border-line text-ink-2 hover:bg-hover'"
        @click="$emit('navigate', item.value)"
      >{{ item.value }}</button>
      <span v-else class="px-1 text-ink-3">…</span>
    </template>

    <button
      type="button"
      :disabled="currentPage >= totalPages"
      class="px-2.5 py-1.5 text-sm rounded-md border border-line text-ink-2 disabled:opacity-40 hover:bg-hover transition-colors"
      @click="$emit('navigate', currentPage + 1)"
    >→</button>
  </div>
</template>

<script setup>
// Shared pagination control for the media pages (Content / Tags / Similar).
// `items` is the windowed page list produced by usePagination().
defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  items: { type: Array, required: true },
})

defineEmits(['navigate'])
</script>
