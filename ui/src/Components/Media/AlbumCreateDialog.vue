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
        <label for="album-title" class="block text-sm font-medium text-gray-700 mb-1">
          Title <span class="text-red-500">*</span>
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
        <label for="album-description" class="block text-sm font-medium text-gray-700 mb-1">
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
        <label for="album-parent" class="block text-sm font-medium text-gray-700 mb-1">
          Parent Set
        </label>
        <select
          id="album-parent"
          v-model="form.parent_id"
          class="ui-input w-full"
        >
          <option :value="null">None (root level)</option>
          <option v-for="set in sets" :key="set.id" :value="set.id">
            {{ '  '.repeat(set.level - 1) }}{{ set.title }}
          </option>
        </select>
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          type="button"
          @click="close"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
          Cancel
        </button>
        <button
          @click="handleSubmit"
          :disabled="isSubmitting || !form.title.trim()"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update' : 'Create') }}
        </button>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { Dialog } from '@modufolio/panel'

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  albumType: { type: Number, default: 0 }, // 0 = album, 1 = set
  editAlbum: { type: Object, default: null }, // if provided, we're editing
  albums: { type: Array, default: () => [] }, // flat album list for parent selection
  defaultParentId: { type: [Number, String, null], default: null }, // preselect parent for new albums
})

const emit = defineEmits(['close', 'submit'])

const titleInput = ref(null)
const isSubmitting = ref(false)

const isEditing = computed(() => props.editAlbum !== null)

const form = ref({
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
    album_type: isEditing.value ? props.editAlbum.album_type : props.albumType,
    parent_id: form.value.parent_id,
  })

  isSubmitting.value = false
  close()
}

const close = () => {
  emit('close')
}
</script>
