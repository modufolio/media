<template>
  <div class="order-1 lg:order-2 lg:sticky lg:top-6 w-full lg:w-72 shrink-0">
    <button
      type="button"
      class="lg:hidden w-full flex items-center justify-between bg-surface rounded-lg shadow px-4 py-3 text-sm"
      @click="inspectorOpen = !inspectorOpen"
    >
      <span class="font-medium text-ink-2">{{ title }}</span>
      <svg
        class="w-4 h-4 text-ink-3 transition-transform duration-200"
        :class="{ 'rotate-180': inspectorOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <div :class="inspectorOpen ? 'block mt-2 lg:mt-0' : 'hidden lg:block'">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  inspectorOpen: Boolean,
  title: String
})

const emit = defineEmits(['update:inspectorOpen'])

const inspectorOpen = computed({
  get() {
    return props.inspectorOpen
  },
  set(value) {
    emit('update:inspectorOpen', value)
  }
})
</script>
