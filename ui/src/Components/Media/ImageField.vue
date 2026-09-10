<template>
  <div class="ui-field-image" :class="widthClass">
    <div class="flex items-center justify-between mb-1.5">
      <label v-if="label" :for="id" class="ui-field-label block text-sm font-medium text-label">
        {{ label }}
      </label>
      <button
        v-if="preview"
        type="button"
        class="text-xs font-medium text-ink-3 hover:text-ink-2 transition-colors"
        @click="pickerOpen = true"
      >Change</button>
    </div>

    <!-- Empty: the whole box is the target, as in the album cover slots. -->
    <button
      v-if="!preview"
      :id="id"
      type="button"
      class="w-full aspect-square rounded-lg border-2 border-dashed border-line bg-surface-sunken text-ink-3 hover:border-line-strong hover:text-ink-2 transition-colors flex flex-col items-center justify-center gap-2"
      @click="pickerOpen = true"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <span class="text-sm">{{ loading ? 'Loading…' : 'Choose image' }}</span>
    </button>

    <!-- Filled: preview above, file details and the remove action below. -->
    <div v-else class="rounded-lg border border-line overflow-hidden bg-surface">
      <img :src="preview" :alt="alt" class="w-full aspect-square object-cover bg-media-placeholder" />

      <div class="flex items-center justify-between gap-3 px-3 py-2 border-t border-line">
        <div class="min-w-0">
          <p class="truncate text-xs text-ink-2">{{ filename || 'Selected image' }}</p>
          <p v-if="dimensions" class="text-xs text-ink-3">{{ dimensions }}</p>
        </div>

        <button
          type="button"
          title="Remove image"
          class="shrink-0 p-1.5 rounded text-ink-3 hover:text-danger hover:bg-danger-surface transition-colors"
          @click="clear"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>
      </div>
    </div>

    <p v-if="missing" class="mt-1.5 text-xs text-warning">
      This image is no longer in the media library.
    </p>
    <p v-else-if="isLegacyUrl" class="mt-1.5 text-xs text-warning">
      Stored as a URL. Pick the image again to store a reference instead.
    </p>
    <p v-if="help" class="mt-1.5 text-xs text-ink-3">{{ help }}</p>
    <p v-if="error" class="mt-1.5 text-xs text-danger">{{ error }}</p>

    <MediaPickerDialog :is-open="pickerOpen" @close="pickerOpen = false" @select="select" />
  </div>
</template>

<script setup lang="ts">
/**
 * Picks an image from the media library and stores its uuid.
 *
 * Storing the reference rather than a URL means the rendered page follows the
 * media — a change to the storage scheme, or a regenerated variant, is picked
 * up on the next render instead of leaving a stale string in the content file.
 * Values that are still a literal URL keep working and are flagged so they can
 * be re-picked.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { panelUrl, apiFetch, useFieldWidth, fieldWidthProp } from '@modufolio/panel'
import { MediaPickerDialog } from '@modufolio/panel'

interface MediaItem {
  id: string
  url: string
  thumbnail_url: string
  original_filename: string
  alt_text: string
  width?: number | null
  height?: number | null
}

interface MediaResponse {
  media?: MediaItem
}

const props = withDefaults(defineProps<{
  modelValue?: string
  label?: string
  help?: string
  error?: string
  id?: string
  width?: string
}>(), {
  modelValue: '',
  label: '',
  help: '',
  error: '',
  id: () => `field-${Math.random().toString(36).slice(2, 11)}`,
  width: fieldWidthProp.width.default,
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const widthClass = useFieldWidth(() => props.width)

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

const pickerOpen = ref(false)
const preview = ref<string | null>(null)
const alt = ref('')
const filename = ref('')
const size = ref<{ width?: number | null; height?: number | null }>({})
const loading = ref(false)
const missing = ref(false)

// modelValue can arrive as null: the generic form's initialValues() falls
// back to null (not '') for any field the record has no value for, and that
// explicit null overrides withDefaults' '' — a prop default only applies
// when the prop is undefined.
const isLegacyUrl = computed(() => {
  const value = props.modelValue ?? ''

  return value !== '' && !UUID.test(value.trim())
})

const dimensions = computed(() =>
  size.value.width && size.value.height ? `${size.value.width} × ${size.value.height}` : ''
)

const apply = (media: MediaItem) => {
  preview.value = media.thumbnail_url || media.url
  alt.value = media.alt_text ?? ''
  filename.value = media.original_filename ?? ''
  size.value = { width: media.width, height: media.height }
}

const reset = () => {
  preview.value = null
  alt.value = ''
  filename.value = ''
  size.value = {}
}

/** Resolve whatever the field holds into something previewable. */
const load = async () => {
  const value = (props.modelValue ?? '').trim()
  missing.value = false

  if (value === '') {
    reset()
    return
  }

  // A URL from before references were stored — show it as-is.
  if (!UUID.test(value)) {
    reset()
    preview.value = value
    filename.value = value.split('/').pop() ?? ''
    return
  }

  loading.value = true
  try {
    const res = await apiFetch(panelUrl(`/api/media/${value}`)) as MediaResponse
    if (res?.media) {
      apply(res.media)
    } else {
      reset()
      missing.value = true
    }
  } catch {
    // Deleted from the library, or not readable — say so rather than
    // rendering a broken image.
    reset()
    missing.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.modelValue, load)

const select = (image: MediaItem) => {
  pickerOpen.value = false
  missing.value = false
  apply(image)
  emit('update:modelValue', image.id)
}

const clear = () => {
  reset()
  missing.value = false
  emit('update:modelValue', '')
}
</script>
