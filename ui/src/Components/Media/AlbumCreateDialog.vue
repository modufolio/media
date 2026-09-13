<template>
  <Dialog
    :is-open="isOpen"
    :title="isEditing ? 'Edit Album' : (albumType === 1 ? 'New Set' : 'New Album')"
    :description="isEditing ? 'Update album details.' : (albumType === 1 ? 'Create a set to group albums together.' : 'Create a new album to organize your media.')"
    width="md"
    @close="close"
    @update:is-open="(val) => !val && close()"
  >
    <form @submit.prevent="handleSubmit" class="space-y-4">
      <!-- Title -->
      <div>
        <label for="album-title" class="block text-sm font-medium text-label mb-1">
          Title <span class="text-danger">*</span>
        </label>
        <input
          id="album-title"
          ref="titleInput"
          v-model="form.title"
          type="text"
          required
          class="ui-input w-full"
          placeholder="Enter album name"
          @keydown.enter.prevent="handleSubmit"
        />
      </div>

      <!-- Description -->
      <div>
        <label for="album-description" class="block text-sm font-medium text-label mb-1">
          Description
        </label>
        <textarea
          id="album-description"
          v-model="form.description"
          rows="3"
          class="ui-input w-full"
          placeholder="Optional description"
        />
      </div>

      <!-- Parent Set (for nesting) -->
      <div v-if="sets.length > 0 && !isEditing">
        <label for="album-parent" class="block text-sm font-medium text-label mb-1">
          Parent Set
        </label>
        <select
          id="album-parent"
          v-model="form.parent_id"
          class="ui-input w-full"
        >
          <option :value="null">None (root level)</option>
          <option v-for="set in sets" :key="set.id" :value="set.id">
            {{ '  '.repeat((set.level ?? 1) - 1) }}{{ set.title }}
          </option>
        </select>
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          @click="close"
          class="px-4 py-2 text-sm font-medium text-ink-2 bg-surface border border-line-strong rounded-lg hover:bg-hover transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleSubmit"
          :disabled="isSubmitting || !form.title.trim()"
          class="px-4 py-2 text-sm font-medium text-primary-on-fill bg-primary-fill rounded-lg hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
        </button>
      </div>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue'
import { Dialog } from '@modufolio/panel'
import type { Album, AlbumId } from '../../types/media'

/** What the dialog hands back on submit; `id` is null when creating. */
export interface AlbumFormPayload {
  id: AlbumId | null
  title: string
  description: string | null
  album_type: number
  parent_id: AlbumId | null
}

const props = withDefaults(defineProps<{
  isOpen: boolean
  /** 0 = album, 1 = set */
  albumType?: number
  /** if provided, we're editing */
  editAlbum?: Album | null
  /** flat album list for parent selection */
  albums?: Album[]
  /** preselect parent for new albums */
  defaultParentId?: AlbumId | null
}>(), {
  albumType: 0,
  editAlbum: null,
  albums: () => [],
  defaultParentId: null,
})

const emit = defineEmits<{
  close: []
  submit: [payload: AlbumFormPayload]
}>()

const titleInput = ref<HTMLInputElement | null>(null)
const isSubmitting = ref(false)

const isEditing = computed(() => props.editAlbum !== null)

const form = ref<{ title: string; description: string; parent_id: AlbumId | null }>({
  title: '',
  description: '',
  parent_id: null,
})

// Available sets for parent selection (only album_type=1)
const sets = computed(() =>
  props.albums.filter(a => a.album_type === 1 && a.id !== props.editAlbum?.id)
)

// Reset form when dialog opens
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    if (props.editAlbum) {
      form.value.title = props.editAlbum.title
      form.value.description = props.editAlbum.description || ''
      form.value.parent_id = null
    } else {
      form.value.title = ''
      form.value.description = ''
      form.value.parent_id = props.defaultParentId || null
    }
    nextTick(() => titleInput.value?.focus())
  }
})

const handleSubmit = async () => {
  if (!form.value.title.trim() || isSubmitting.value) return

  isSubmitting.value = true

  emit('submit', {
    id: props.editAlbum?.id || null,
    title: form.value.title.trim(),
    description: form.value.description.trim() || null,
    album_type: props.editAlbum ? props.editAlbum.album_type : props.albumType,
    parent_id: form.value.parent_id,
  })

  isSubmitting.value = false
  close()
}

const close = () => {
  emit('close')
}
</script>
