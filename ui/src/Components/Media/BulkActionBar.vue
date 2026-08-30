<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-2"
  >
    <div
      v-if="count > 0"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 bg-white rounded-xl shadow-xl ring-1 ring-gray-950/10 px-5 py-3"
    >
      <span class="text-sm font-medium text-gray-700">{{ count }} selected</span>

      <div class="h-4 w-px bg-gray-200" />

      <!-- Star ratings 1–5 -->
      <div class="flex items-center gap-0.5" title="Rate selected (or press 1–5)">
        <button
          v-for="star in 5"
          :key="star"
          type="button"
          class="p-1 rounded hover:bg-amber-50 transition-colors"
          :title="`Rate ${star}★`"
          @click="emit('rate', star)"
        >
          <svg class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
            <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </button>
      </div>

      <!-- Clear rating -->
      <template v-if="showClearRating">
        <div class="h-4 w-px bg-gray-200" />
        <button
          type="button"
          class="flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors"
          title="Clear rating"
          @click="emit('clear-rating')"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          Rating
        </button>
      </template>

      <!-- Favorite / Unfavorite -->
      <template v-if="favoriteLabel">
        <div class="h-4 w-px bg-gray-200" />
        <button
          type="button"
          class="flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-yellow-500 transition-colors"
          @click="emit('favorite')"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
          {{ favoriteLabel }}
        </button>
      </template>

      <!-- Remove from Album -->
      <template v-if="showRemoveFromAlbum">
        <div class="h-4 w-px bg-gray-200" />
        <button
          type="button"
          class="text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
          @click="emit('remove-from-album')"
        >
          Remove from Album
        </button>
      </template>

      <div class="h-4 w-px bg-gray-200" />

      <button
        type="button"
        class="text-sm font-medium text-red-600 hover:text-red-700 transition-colors"
        @click="emit('delete')"
      >
        Delete
      </button>
      <button
        type="button"
        class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
        @click="emit('cancel')"
      >
        Cancel
      </button>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  count: { type: Number, required: true },
  showClearRating: { type: Boolean, default: true },
  favoriteLabel: { type: String, default: null }, // 'Favorite' | 'Unfavorite' | null to hide
  showRemoveFromAlbum: { type: Boolean, default: false },
})

const emit = defineEmits(['rate', 'clear-rating', 'favorite', 'remove-from-album', 'delete', 'cancel'])
</script>
