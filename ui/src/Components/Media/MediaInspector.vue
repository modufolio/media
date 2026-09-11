<template>
  <div class="bg-surface rounded-lg shadow divide-y divide-line self-start">

    <!-- ── Single-media inspector ─────────────────────────────── -->
    <template v-if="media">

      <!-- Thumbnail preview -->
      <div class="px-5 py-4">
        <div
          class="rounded-lg overflow-hidden bg-media-placeholder cursor-pointer"
          :style="media.is_image && media.width && media.height
            ? { aspectRatio: `${media.width} / ${media.height}` }
            : { aspectRatio: '16 / 9' }"
          @dblclick="handleViewMedia"
        >
          <img
            v-if="media.is_image"
            :src="media.thumbnail_url || media.url"
            :alt="fields.alt_text || media.original_filename"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full flex items-center justify-center">
            <svg class="w-12 h-12 text-media-placeholder-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
        </div>
        <p class="mt-2 text-sm text-ink-2 font-medium truncate">{{ media.original_filename }}</p>
      </div>

      <!-- File info -->
      <div class="px-5 py-4">
        <button
          type="button"
          class="flex items-center justify-between w-full mb-3 group"
          @click="collapseFileInfo = !collapseFileInfo"
        >
          <h3 class="text-xs font-semibold text-label uppercase tracking-wider">File info</h3>
          <svg class="w-3 h-3 transition-transform duration-200" :class="!collapseFileInfo ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </button>
        <dl v-if="!collapseFileInfo" class="space-y-2.5 text-sm">
          <div v-if="media.width && media.height" class="flex justify-between">
            <dt class="text-ink-3">Dimensions</dt>
            <dd class="text-ink font-medium">{{ media.width }} × {{ media.height }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-ink-3">File size</dt>
            <dd class="text-ink font-medium">{{ niceSize(media.file_size) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-ink-3">Type</dt>
            <dd class="text-ink font-medium">{{ media.mime_type }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-ink-3">Uploaded</dt>
            <dd class="text-ink font-medium">{{ formatDate(media.created_at) }}</dd>
          </div>
        </dl>
      </div>

      <!-- Rating -->
      <div class="px-5 py-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-xs font-semibold text-label uppercase tracking-wider">Rating</h3>
          <button
            v-if="localRating !== null"
            type="button"
            class="text-xs text-ink-3 hover:text-danger transition-colors"
            @click="saveRating(null)"
          >Clear</button>
        </div>
        <div class="flex items-center gap-1" role="group" aria-label="Star rating">
          <button
            v-for="star in 5"
            :key="star"
            type="button"
            class="p-0.5 transition-colors focus:outline-none"
            :title="`Rate ${star} star${star !== 1 ? 's' : ''}`"
            @click="saveRating(localRating === star ? null : star)"
            @mouseenter="hoverRating = star"
            @mouseleave="hoverRating = null"
          >
            <svg
              class="w-5 h-5 transition-colors"
              :class="(hoverRating ?? localRating ?? 0) >= star ? 'text-media-star' : 'text-media-star-empty'"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Editable Metadata -->
      <div class="px-5 py-4">
        <button
          type="button"
          class="flex items-center justify-between w-full mb-3 group"
          @click="collapseMetadata = !collapseMetadata"
        >
          <span class="flex items-center gap-2">
            <h3 class="text-xs font-semibold text-label uppercase tracking-wider">Metadata</h3>
            <transition name="fade">
              <span v-if="!collapseMetadata && saveStatus === 'saving'" class="text-xs text-ink-3">Saving…</span>
              <span v-else-if="!collapseMetadata && saveStatus === 'saved'" class="text-xs text-success font-medium">Saved ✓</span>
              <span v-else-if="!collapseMetadata && saveStatus === 'error'" class="text-xs text-danger">Error</span>
            </transition>
          </span>
          <svg class="w-3 h-3 transition-transform duration-200" :class="!collapseMetadata ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </button>

        <div v-if="!collapseMetadata" class="space-y-4">
          <div>
            <label class="block text-xs text-label mb-1">Title</label>
            <input
              v-model="fields.title"
              type="text"
              placeholder="Add a title…"
              class="w-full text-sm text-ink bg-surface-sunken border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-line-strong focus:bg-surface placeholder:text-ink-3 transition-colors"
              @blur="save('title', fields.title)"
              @keydown.enter.prevent="$event.target.blur()"
            />
          </div>
          <div>
            <label class="block text-xs text-label mb-1">Alt text</label>
            <input
              v-model="fields.alt_text"
              type="text"
              placeholder="Describe the image…"
              class="w-full text-sm text-ink bg-surface-sunken border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-line-strong focus:bg-surface placeholder:text-ink-3 transition-colors"
              @blur="save('alt_text', fields.alt_text)"
              @keydown.enter.prevent="$event.target.blur()"
            />
          </div>
          <div>
            <label class="block text-xs text-label mb-1">Caption</label>
            <textarea
              v-model="fields.caption"
              placeholder="Add a caption…"
              rows="3"
              class="w-full text-sm text-ink bg-surface-sunken border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-line-strong focus:bg-surface placeholder:text-ink-3 transition-colors resize-none"
              @blur="save('caption', fields.caption)"
            />
          </div>
        </div>
      </div>

      <!-- Tags -->
      <div class="px-5 py-4">
        <h3 class="text-xs font-semibold text-label uppercase tracking-wider mb-3">Tags</h3>
        <div class="flex items-center gap-1.5 flex-wrap">
          <span
            v-for="tag in mediaTags"
            :key="tag.id"
            class="inline-flex items-center gap-1 rounded-full bg-gray-surface px-2.5 py-0.5 text-xs font-medium text-gray-on-surface"
          >
            {{ tag.name }}
            <button
              type="button"
              class="text-ink-3 hover:text-danger transition-colors"
              @click="emit('detach-tag', tag.id)"
            >
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
              </svg>
            </button>
          </span>

          <div class="relative">
            <div v-if="showTagDropdown" class="fixed inset-0 z-[40]" @click="emit('update:showTagDropdown', false)" />
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-full border border-dashed border-line px-2.5 py-0.5 text-xs font-medium text-ink-3 hover:border-line-strong hover:text-ink-2 transition-colors"
              @click="emit('open-tag-dropdown')"
            >
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              Add tag
            </button>
            <div
              v-if="showTagDropdown"
              class="absolute left-0 top-7 z-[50] w-48 rounded-lg border border-line bg-surface-raised shadow-lg overflow-hidden"
            >
              <input
                :value="tagSearch"
                type="text"
                placeholder="Search or create…"
                class="w-full px-3 py-2 text-sm bg-surface-raised text-ink border-b border-line placeholder:text-ink-3 focus:outline-none"
                @input="emit('update:tagSearch', $event.target.value)"
              />
              <div class="max-h-40 overflow-y-auto">
                <button
                  v-for="tag in filteredAvailableTags"
                  :key="tag.id"
                  type="button"
                  class="block w-full px-3 py-1.5 text-left text-sm text-ink-2 hover:bg-hover"
                  @click="emit('attach-tag', tag.id)"
                >
                  {{ tag.name }}
                </button>
                <button
                  v-if="canCreateTag"
                  type="button"
                  class="block w-full px-3 py-1.5 text-left text-sm text-primary font-medium hover:bg-hover"
                  @click="emit('create-tag', tagSearch)"
                >
                  Create "{{ tagSearch }}"
                </button>
                <p v-if="filteredAvailableTags.length === 0 && !canCreateTag" class="px-3 py-2 text-xs text-ink-3">
                  No tags available
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- EXIF / Camera metadata -->
      <div v-if="exifData" class="px-5 py-4">
        <h3 class="text-xs font-semibold text-label uppercase tracking-wider mb-3">Camera</h3>
        <dl class="space-y-2.5 text-sm">
          <div v-if="exifData.model" class="flex justify-between">
            <dt class="text-ink-3">Camera</dt>
            <dd class="text-ink font-medium text-right max-w-[140px] truncate" :title="exifData.model">{{ exifData.model }}</dd>
          </div>
          <div v-if="exifData.aperture" class="flex justify-between">
            <dt class="text-ink-3">Aperture</dt>
            <dd class="text-ink font-medium">{{ exifData.aperture }}</dd>
          </div>
          <div v-if="exifData.shutter" class="flex justify-between">
            <dt class="text-ink-3">Shutter</dt>
            <dd class="text-ink font-medium">{{ exifData.shutter }}</dd>
          </div>
          <div v-if="exifData.iso" class="flex justify-between">
            <dt class="text-ink-3">ISO</dt>
            <dd class="text-ink font-medium">{{ exifData.iso }}</dd>
          </div>
          <div v-if="exifData.focalLength" class="flex justify-between">
            <dt class="text-ink-3">Focal length</dt>
            <dd class="text-ink font-medium">{{ exifData.focalLength }}</dd>
          </div>
          <div v-if="exifData.taken" class="flex justify-between">
            <dt class="text-ink-3">Taken</dt>
            <dd class="text-ink font-medium">{{ exifData.taken }}</dd>
          </div>
        </dl>
      </div>

      <!-- Site link + visibility -->
      <div class="px-5 py-4">
        <h3 class="text-xs font-semibold text-label uppercase tracking-wider mb-3">Site</h3>
        <div class="space-y-3">
          <a
            :href="media.url"
            target="_blank"
            class="flex items-center gap-2 text-sm text-primary hover:text-primary-hover font-medium transition-colors"
          >
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            Link
          </a>
          <div class="flex items-center justify-between">
            <span class="text-sm text-ink-3">Visibility</span>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 text-sm font-medium transition-colors"
              :class="isPublic ? 'text-success hover:text-success-hover' : 'text-ink-3 hover:text-ink-2'"
              @click="togglePublic"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="isPublic" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
              {{ isPublic ? 'Public' : 'Private' }}
            </button>
          </div>
        </div>
      </div>

    </template>

    <!-- ── Multi-select bulk editor ──────────────────────────── -->
    <template v-else-if="selectedIds.length > 1">

      <!-- Header -->
      <div class="px-5 py-4">
        <p class="text-xs text-ink-3 leading-relaxed">
          <span class="font-semibold text-ink">{{ selectedIds.length }} items selected.</span>
          Edits below apply to all.
        </p>
      </div>

      <!-- Bulk metadata -->
      <div class="px-5 py-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-xs font-semibold text-label uppercase tracking-wider">Metadata</h3>
          <transition name="fade">
            <span v-if="bulkSaveStatus === 'saving'" class="text-xs text-ink-3">Saving…</span>
            <span v-else-if="bulkSaveStatus === 'saved'" class="text-xs text-success font-medium">Saved ✓</span>
            <span v-else-if="bulkSaveStatus === 'error'" class="text-xs text-danger">Error</span>
          </transition>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-xs text-label mb-1">Title</label>
            <input
              v-model="bulkFields.title"
              type="text"
              placeholder="Add a title…"
              class="w-full text-sm text-ink bg-surface-sunken border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-line-strong focus:bg-surface placeholder:text-ink-3 transition-colors"
              @blur="bulkSaveField('title', bulkFields.title)"
              @keydown.enter.prevent="$event.target.blur()"
            />
          </div>
          <div>
            <label class="block text-xs text-label mb-1">Alt text</label>
            <input
              v-model="bulkFields.alt_text"
              type="text"
              placeholder="Describe the image…"
              class="w-full text-sm text-ink bg-surface-sunken border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-line-strong focus:bg-surface placeholder:text-ink-3 transition-colors"
              @blur="bulkSaveField('alt_text', bulkFields.alt_text)"
              @keydown.enter.prevent="$event.target.blur()"
            />
          </div>
          <div>
            <label class="block text-xs text-label mb-1">Caption</label>
            <textarea
              v-model="bulkFields.caption"
              placeholder="Add a caption…"
              rows="3"
              class="w-full text-sm text-ink bg-surface-sunken border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-line-strong focus:bg-surface placeholder:text-ink-3 transition-colors resize-none"
              @blur="bulkSaveField('caption', bulkFields.caption)"
            />
          </div>
        </div>
      </div>

      <!-- Bulk tags -->
      <div class="px-5 py-4">
        <h3 class="text-xs font-semibold text-label uppercase tracking-wider mb-3">Tags</h3>
        <div class="flex items-center gap-1.5 flex-wrap mb-2">
          <span
            v-for="tag in bulkTags"
            :key="tag.id"
            class="inline-flex items-center gap-1 rounded-full bg-gray-surface px-2.5 py-0.5 text-xs font-medium text-gray-on-surface"
            :title="tag.count < selectedIds.length ? `On ${tag.count} of ${selectedIds.length} selected` : 'On all selected'"
          >
            {{ tag.name }}
            <span v-if="tag.count < selectedIds.length" class="text-ink-3">{{ tag.count }}/{{ selectedIds.length }}</span>
            <button
              type="button"
              class="text-ink-3 hover:text-danger transition-colors"
              @click="emit('bulk-detach-tag', tag.id)"
            >
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
              </svg>
            </button>
          </span>
        </div>
        <div class="relative">
          <div v-if="showTagDropdown" class="fixed inset-0 z-[40]" @click="emit('update:showTagDropdown', false)" />
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-dashed border-line px-2.5 py-0.5 text-xs font-medium text-ink-3 hover:border-line-strong hover:text-ink-2 transition-colors"
            @click="emit('open-tag-dropdown')"
          >
            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add tag to all
          </button>
          <div
            v-if="showTagDropdown"
            class="absolute left-0 top-7 z-[50] w-48 rounded-lg border border-line bg-surface-raised shadow-lg overflow-hidden"
          >
            <input
              :value="tagSearch"
              type="text"
              placeholder="Search or create…"
              class="w-full px-3 py-2 text-sm bg-surface-raised text-ink border-b border-line placeholder:text-ink-3 focus:outline-none"
              @input="emit('update:tagSearch', $event.target.value)"
            />
            <div class="max-h-40 overflow-y-auto">
              <button
                v-for="tag in filteredAvailableTags"
                :key="tag.id"
                type="button"
                class="block w-full px-3 py-1.5 text-left text-sm text-ink-2 hover:bg-hover"
                @click="emit('bulk-attach-tag', tag.id)"
              >{{ tag.name }}</button>
              <button
                v-if="canCreateTag"
                type="button"
                class="block w-full px-3 py-1.5 text-left text-sm text-primary font-medium hover:bg-hover"
                @click="emit('bulk-create-attach-tag', tagSearch)"
              >Create "{{ tagSearch }}"</button>
              <p v-if="filteredAvailableTags.length === 0 && !canCreateTag" class="px-3 py-2 text-xs text-ink-3">
                No tags available
              </p>
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- ── Library summary (no item selected) ────────────────── -->
    <template v-else>

      <!-- Properties -->
      <div class="px-5 py-4">
        <h3 class="text-xs font-semibold text-label uppercase tracking-wider mb-3">Properties</h3>
        <dl class="space-y-2.5 text-sm">
          <div class="flex justify-between">
            <dt class="text-ink-3 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              Photos
            </dt>
            <dd class="text-ink font-medium">{{ photoCount }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-ink-3 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
              Videos
            </dt>
            <dd class="text-ink font-medium">{{ videoCount }}</dd>
          </div>
        </dl>
      </div>

      <!-- Site link -->
      <div class="px-5 py-4">
        <h3 class="text-xs font-semibold text-label uppercase tracking-wider mb-3">Site</h3>
        <a
          href="/"
          class="flex items-center gap-2 text-sm text-primary hover:text-primary-hover font-medium transition-colors"
        >
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
          Link
        </a>
      </div>

    </template>

  </div>
</template>

<script setup>
import { reactive, computed, watch, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useLocalStorage } from '@vueuse/core'
import { panelUrl, apiFetch, niceSize, date } from '@modufolio/panel'
import { exifSummary } from './exifUtils'

const props = defineProps({
  media: { type: Object, default: null },
  files: { type: Array, default: () => [] },
  albumId: { type: [String, Number], default: null },
  selectedIds: { type: Array, default: () => [] },
  mediaTags: { type: Array, default: () => [] },
  filteredAvailableTags: { type: Array, default: () => [] },
  canCreateTag: { type: Boolean, default: false },
  showTagDropdown: { type: Boolean, default: false },
  tagSearch: { type: String, default: '' },
  bulkSaveStatus: { type: String, default: null },
  bulkTags: { type: Array, default: () => [] }, // [{ id, name, count }]
})

const emit = defineEmits([
  'update:showTagDropdown',
  'update:tagSearch',
  'open-tag-dropdown',
  'attach-tag',
  'detach-tag',
  'create-tag',
  'bulk-save',
  'bulk-attach-tag',
  'bulk-create-attach-tag',
  'bulk-detach-tag',
])

// ── Library summary counts ────────────────────────────────────────
const photoCount = computed(() => props.files.filter(f => f.is_image).length)
const videoCount = computed(() => props.files.filter(f => f.is_video).length)

// ── Rating ───────────────────────────────────────────────────────
// Derived from the media object itself: saveRating mutates file.rating
// optimistically (the same single-source-of-truth pattern as useFavorites),
// so grid shortcuts and the inspector can never disagree.
const localRating = computed(() => props.media?.rating ?? null)
const hoverRating = ref(null)

const saveRating = async (value) => {
  if (!props.media) return
  props.media.rating = value
  await save('rating', value)
}

// ── Single-media editable fields ──────────────────────────────────
const fields = reactive({
  title: '',
  alt_text: '',
  caption: '',
})

// Derived the same way as rating: togglePublic mutates the media object.
const isPublic = computed(() => props.media?.is_public !== false)

// ── Bulk edit fields ──────────────────────────────────────────────
const bulkFields = reactive({ title: '', alt_text: '', caption: '' })

const bulkSaveField = (field, value) => {
  emit('bulk-save', { [field]: value || null })
}

// Reset bulk fields when selection changes
watch(() => props.selectedIds, () => {
  bulkFields.title = ''
  bulkFields.alt_text = ''
  bulkFields.caption = ''
})

// ── Collapse state (persisted to localStorage) ─────────────────────
const collapseFileInfo = useLocalStorage('inspector.collapseFileInfo', false)
const collapseMetadata = useLocalStorage('inspector.collapseMetadata', false)

// Genuine synchronization: `fields` is a debounced edit buffer, reset when the
// inspected media changes. Everything display-only derives from props.media.
watch(() => props.media, (m) => {
  if (m) {
    fields.title = m.title ?? ''
    fields.alt_text = m.alt_text ?? ''
    fields.caption = m.caption ?? ''
    hoverRating.value = null
  }
}, { immediate: true })

// ── EXIF ─────────────────────────────────────────────────────────
const exifData = computed(() => exifSummary(props.media?.metadata))

// ── Save ─────────────────────────────────────────────────────────
const saveStatus = ref(null)
let saveTimer = null

const save = async (field, value) => {
  if (!props.media) return
  clearTimeout(saveTimer)
  saveStatus.value = 'saving'

  try {
    await apiFetch(panelUrl(`/api/media/${props.media.id}`), {
      method: 'PATCH',
      body: { [field]: value || null },
    })
    saveStatus.value = 'saved'
  } catch {
    saveStatus.value = 'error'
  }

  saveTimer = setTimeout(() => { saveStatus.value = null }, 2000)
}

const togglePublic = async () => {
  if (!props.media) return
  props.media.is_public = !isPublic.value
  await save('is_public', props.media.is_public)
}

// ── View Media ───────────────────────────────────────────────────
const handleViewMedia = () => {
  if (props.media) {
    if (props.albumId) {
      router.visit(panelUrl(`/library/albums/${props.albumId}/view/${props.media.id}`))
    } else {
      router.visit(panelUrl(`/library/media/${props.media.id}`))
    }
  }
}

// ── Helpers ──────────────────────────────────────────────────────
const formatDate = (dateStr) => date(dateStr)?.format('MMM D, YYYY') ?? '—'
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
