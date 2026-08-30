<template>
  <div class="bg-white rounded-lg shadow divide-y divide-gray-100 self-start">

    <!-- Cover Slots (3 drag-drop targets) — albums only, not sets -->
    <div v-if="album.album_type !== 1" class="px-5 py-4">
      <button
        type="button"
        class="flex items-center justify-between w-full mb-3 group"
        @click="collapseCovers = !collapseCovers"
      >
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Covers</h3>
        <svg class="w-3 h-3 transition-transform duration-200" :class="!collapseCovers ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <template v-if="!collapseCovers">
        <div class="flex gap-2">
          <div
            v-for="(slot, index) in displayCovers"
            :key="index"
            class="relative flex-1 aspect-square rounded-lg overflow-hidden border-2 transition-colors"
            :class="slot
              ? 'border-transparent'
              : dragOverSlot === index
                ? 'border-primary-400 bg-primary-50'
                : 'border-dashed border-gray-200 bg-gray-50'"
            @dragover.prevent="dragOverSlot = index"
            @dragleave="dragOverSlot = null"
            @drop.prevent="onDrop($event, index)"
          >
            <!-- Filled slot: thumbnail + remove button -->
            <template v-if="slot">
              <img
                :src="slot.thumbnail_url || slot.url"
                class="w-full h-full object-cover"
                :alt="`Cover ${index + 1}`"
                :style="slot.focus ? { objectPosition: focusToCss(slot.focus) } : {}"
              />
              <button
                @click.stop="removeCover(index)"
                class="absolute top-1 right-1 w-5 h-5 bg-black/60 hover:bg-black/80 text-white rounded-full flex items-center justify-center transition-colors"
                title="Remove cover"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </template>

            <!-- Empty slot: drop hint -->
            <template v-else>
              <div class="absolute inset-0 flex flex-col items-center justify-center gap-1 pointer-events-none">
                <svg
                  class="w-6 h-6 transition-colors"
                  :class="dragOverSlot === index ? 'text-primary-400' : 'text-gray-300'"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span
                  class="text-xs transition-colors"
                  :class="dragOverSlot === index ? 'text-primary-400' : 'text-gray-300'"
                >{{ index + 1 }}</span>
              </div>
            </template>
          </div>
        </div>

        <p class="mt-2 text-xs text-gray-400 text-center">Drag photos from the grid to set covers</p>
      </template>
    </div>

    <!-- Editable Properties -->
    <div class="px-5 py-4">
      <button
        type="button"
        class="flex items-center justify-between w-full mb-3 group"
        @click="collapseProperties = !collapseProperties"
      >
        <span class="flex items-center gap-2">
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Properties</h3>
          <transition name="fade">
            <span v-if="!collapseProperties && saveStatus === 'saving'" class="text-xs text-gray-400">Saving…</span>
            <span v-else-if="!collapseProperties && saveStatus === 'saved'" class="text-xs text-green-600 font-medium">Saved ✓</span>
            <span v-else-if="!collapseProperties && saveStatus === 'error'" class="text-xs text-red-500">Error</span>
          </transition>
        </span>
        <svg class="w-3 h-3 transition-transform duration-200" :class="!collapseProperties ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <div v-if="!collapseProperties" class="space-y-4">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Title</label>
          <input
            v-model="fields.title"
            type="text"
            placeholder="Album title…"
            class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white placeholder-gray-300 transition-colors"
            @blur="save"
            @keydown.enter.prevent="$event.target.blur()"
          />
        </div>

        <!-- Slug field -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs text-gray-500">Slug</label>
            <transition name="fade">
              <span v-if="slugStatus === 'checking'" class="text-xs text-gray-400">Checking…</span>
              <span v-else-if="slugStatus === 'saving'" class="text-xs text-gray-400">Saving…</span>
              <span v-else-if="slugStatus === 'saved'" class="text-xs text-green-600 font-medium">Saved ✓</span>
            </transition>
          </div>
          <input
            v-model="fields.slug"
            type="text"
            placeholder="my-album-slug"
            class="w-full text-sm text-gray-900 bg-gray-50 border rounded px-2.5 py-1.5 focus:outline-none focus:bg-white placeholder-gray-300 transition-colors"
            :class="slugError
              ? 'border-red-300 focus:border-red-400'
              : 'border-transparent focus:border-gray-300'"
            @blur="saveSlug"
            @keydown.enter.prevent="$event.target.blur()"
            @input="fields.slug = normalizeSlug($event.target.value); slugError = null; slugStatus = null"
          />
          <transition name="fade">
            <p v-if="slugError" class="mt-1 text-xs text-red-500">{{ slugError }}</p>
            <p v-else class="mt-1 text-xs text-gray-400">Used in the public URL</p>
          </transition>
        </div>

        <div>
          <label class="block text-xs text-gray-500 mb-1">Subtitle</label>
          <input
            v-model="fields.subtitle"
            type="text"
            placeholder="Shown under the title on cards"
            class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white placeholder-gray-300 transition-colors"
            @blur="save"
            @keydown.enter.prevent="$event.target.blur()"
          />
        </div>

        <div>
          <label class="block text-xs text-gray-500 mb-1">Description</label>
          <textarea
            v-model="fields.description"
            rows="4"
            placeholder="Add a description…"
            class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white placeholder-gray-300 transition-colors resize-none"
            @blur="save"
          />
        </div>

        <!-- Sets declare the vocabulary; albums pick from their parent's -->
        <div v-if="album.album_type === 1">
          <label class="block text-xs text-gray-500 mb-1">Categories</label>
          <input
            v-model="fields.categories"
            type="text"
            placeholder="Travel, Nature, Fashion"
            class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white placeholder-gray-300 transition-colors"
            @blur="save"
            @keydown.enter.prevent="$event.target.blur()"
          />
          <p class="mt-1 text-xs text-gray-400">Comma-separated. Albums inside this set can be grouped by these.</p>
        </div>

        <div v-else>
          <label class="block text-xs text-gray-500 mb-1">Category</label>

          <!-- Picked from the parent set's vocabulary, like Kirby's
               `options: query / page.parent.categories.split`. -->
          <div v-if="parentCategories.length" class="flex flex-wrap gap-1.5">
            <button
              v-for="name in parentCategories"
              :key="name"
              type="button"
              class="px-2 py-0.5 text-xs rounded-full border transition-colors"
              :class="selectedCategories.includes(name)
                ? 'bg-gray-900 text-white border-gray-900'
                : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-gray-300'"
              @click="toggleCategory(name)"
            >{{ name }}</button>
          </div>

          <p v-else class="text-xs text-gray-400">
            No categories yet — add them on the parent set to pick from here.
          </p>
        </div>
      </div>
    </div>

    <!-- Layout. Mirrors Kirby's Options tab: pick the layout (its own page
         template there), then that layout's own options. Sets have no gallery
         of their own, so they only get the container toggle. -->
    <div class="px-5 py-4">
      <button
        type="button"
        class="flex items-center justify-between w-full mb-3 group"
        @click="collapseDisplay = !collapseDisplay"
      >
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Layout</h3>
        <svg class="w-3 h-3 transition-transform duration-200" :class="!collapseDisplay ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <div v-if="!collapseDisplay" class="space-y-4">
        <div v-if="album.album_type !== 1">
          <label class="block text-xs text-gray-500 mb-1">Layout</label>
          <select
            :value="fields.layout"
            class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white transition-colors"
            @change="changeLayout($event.target.value)"
          >
            <option value="grid">Grid</option>
            <option value="slider">Slider</option>
            <option value="list">List</option>
          </select>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="fields.fullwidth" type="checkbox" class="rounded border-gray-300" @change="save" />
          Fullwidth
        </label>

        <!-- Set: album-cover card grid -->
        <div v-if="album.album_type === 1">
          <label class="block text-xs text-gray-500 mb-1">Grid gap <span class="text-gray-400">(1–15 px)</span></label>
          <input
            v-model.number="options.gap"
            type="range" min="1" max="15" step="1"
            class="w-full"
            @change="save"
          />
          <p class="mt-1 text-xs text-gray-400">{{ options.gap }} px between album covers</p>
        </div>

        <!-- Grid -->
        <template v-if="album.album_type !== 1 && fields.layout === 'grid'">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Grid mode</label>
            <select
              v-model="options.mode"
              class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white transition-colors"
              @change="save"
            >
              <option value="original">Original</option>
              <option value="square">Square</option>
              <option value="landscape">Landscape</option>
              <option value="portrait">Portrait</option>
            </select>
            <p v-if="options.mode === 'original'" class="mt-1 text-xs text-gray-400">
              Justified rows — each photo keeps its own ratio.
            </p>
          </div>

          <div v-if="options.mode !== 'original'">
            <label class="block text-xs text-gray-500 mb-1">Columns <span class="text-gray-400">(2–8)</span></label>
            <input
              v-model.number="options.columns"
              type="range" min="2" max="8" step="1"
              class="w-full"
              @change="save"
            />
            <p class="mt-1 text-xs text-gray-400">{{ options.columns }} columns</p>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Gap <span class="text-gray-400">(1–15 px)</span></label>
            <input
              v-model.number="options.gap"
              type="range" min="1" max="15" step="1"
              class="w-full"
              @change="save"
            />
            <p class="mt-1 text-xs text-gray-400">{{ options.gap }} px</p>
          </div>
        </template>

        <!-- Slider -->
        <template v-if="album.album_type !== 1 && fields.layout === 'slider'">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="options.pagedots" type="checkbox" class="rounded border-gray-300" @change="save" />
            Page dots
          </label>

          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="options.autoplay" type="checkbox" class="rounded border-gray-300" @change="save" />
            Autoplay
          </label>

        </template>

        <!-- List -->
        <template v-if="album.album_type !== 1 && fields.layout === 'list'">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Caption alignment</label>
            <select
              v-model="options.caption_align"
              class="w-full text-sm text-gray-900 bg-gray-50 border border-transparent rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-300 focus:bg-white transition-colors"
              @change="save"
            >
              <option value="left">Left</option>
              <option value="center">Center</option>
              <option value="right">Right</option>
            </select>
          </div>
        </template>

        <!-- Grid and list use speed as the lightbox slideshow pause. The slider
             has no lightbox, so there it is flickity's autoplay interval and
             only applies while autoplay is on. -->
        <div v-if="album.album_type !== 1 && (fields.layout !== 'slider' || options.autoplay)">
          <label class="block text-xs text-gray-500 mb-1">
            {{ fields.layout === 'slider' ? 'Autoplay interval' : 'Slideshow speed' }}
            <span class="text-gray-400">({{ (options.speed / 1000).toFixed(1) }}s)</span>
          </label>
          <input
            v-model.number="options.speed"
            type="range" min="500" max="5000" step="100"
            class="w-full"
            @change="save"
          />
        </div>
      </div>
    </div>

    <!-- Details -->
    <div class="px-5 py-4">
      <button
        type="button"
        class="flex items-center justify-between w-full mb-3 group"
        @click="collapseDetails = !collapseDetails"
      >
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Details</h3>
        <svg class="w-3 h-3 transition-transform duration-200" :class="!collapseDetails ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <dl v-if="!collapseDetails" class="space-y-2.5 text-sm">
        <div class="flex justify-between">
          <dt class="text-gray-500">Type</dt>
          <dd class="text-gray-900 font-medium">{{ album.album_type === 1 ? 'Set' : 'Album' }}</dd>
        </div>
        <div class="flex items-center justify-between">
          <dt class="text-gray-500">Visibility</dt>
          <dd>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 text-sm font-medium transition-colors"
              :class="fields.visibility === 'public' ? 'text-green-600 hover:text-green-700' : 'text-gray-400 hover:text-gray-600'"
              @click="toggleVisibility"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="fields.visibility === 'public'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
              {{ fields.visibility === 'public' ? 'Public' : 'Private' }}
            </button>
          </dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500">Items</dt>
          <dd class="text-gray-900 font-medium">{{ album.album_type === 1 ? (Array.isArray(childAlbums) ? childAlbums.length : 0) : album.media_count }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500">Created</dt>
          <dd class="text-gray-900 font-medium">{{ formatDate(album.created_at) }}</dd>
        </div>
        <div class="flex justify-between">
          <dt class="text-gray-500">Modified</dt>
          <dd class="text-gray-900 font-medium">{{ formatDate(album.updated_at) }}</dd>
        </div>
      </dl>
    </div>

    <!-- Add to menu -->
    <div class="px-5 py-4">
      <button
        type="button"
        class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm border rounded-lg transition-colors"
        :class="inMenu
          ? 'text-gray-400 border-gray-200 cursor-default'
          : 'text-gray-600 border-gray-200 hover:bg-gray-50'"
        :disabled="inMenu || addingToMenu"
        @click="addToMenu"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path v-if="inMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h10M18 15v6m3-3h-6" />
        </svg>
        {{ inMenu ? 'In menu' : addingToMenu ? 'Adding…' : 'Add to menu' }}
      </button>
      <a
        v-if="inMenu"
        :href="panelUrl('/navigation')"
        class="mt-2 block text-center text-xs text-gray-400 hover:text-gray-600"
      >
        Edit navigation
      </a>
    </div>

    <!-- View on site -->
    <div v-if="portfolioUrl" class="px-5 py-4">
      <a
        :href="portfolioUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
        </svg>
        View on site
      </a>
    </div>

    <!-- Delete -->
    <div class="px-5 py-4">
      <button
        @click="$emit('delete')"
        class="w-full flex items-center justify-center gap-2 px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
        Delete {{ album.album_type === 1 ? 'Set' : 'Album' }}
      </button>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useLocalStorage } from '@vueuse/core'
import { focusToCss } from './imageUtils.js'
import { useToast } from '@modufolio/panel'
import { panelUrl, useFieldSaver } from '@modufolio/panel'
import { apiFetch, ApiError } from '@modufolio/panel'

const props = defineProps({
  album: { type: Object, required: true },
  mediaFiles: { type: Array, default: () => [] },
  portfolioUrl: { type: String, default: null },
  albumInMenu: { type: Boolean, default: false },
  childAlbums: { type: [Array, Object], default: () => [] },
  parentCategories: { type: Array, default: () => [] },
})

const emit = defineEmits(['updated', 'delete'])

// ── Add to navigation menu ──────────────────────────────────────

const toast = useToast()
const addedToMenu = ref(false)
const addingToMenu = ref(false)

// The server-side flag only refreshes on a full page load, so OR it with what
// this session added; both reset when a different album is inspected.
const inMenu = computed(() => props.albumInMenu || addedToMenu.value)

watch(() => props.album.id, () => { addedToMenu.value = false })

const addToMenu = async () => {
  if (inMenu.value || addingToMenu.value) return
  addingToMenu.value = true

  try {
    await apiFetch(panelUrl('/api/navigation/items'), {
      method: 'POST',
      body: {
        type: 'album',
        label: props.album.title || props.album.slug,
        page_slug: props.album.slug,
        url: null,
        target: '_self',
      },
    })
    addedToMenu.value = true
    toast.success(`"${props.album.title || props.album.slug}" added to the menu`, 'Added')
  } catch {
    toast.error('Failed to add to menu', 'Error')
  } finally {
    addingToMenu.value = false
  }
}

// ── Collapse state (persisted to localStorage) ───────────────────

const COLLAPSE_COVERS_KEY     = 'album.collapseCovers'
const COLLAPSE_PROPERTIES_KEY = 'album.collapseProperties'
const COLLAPSE_DISPLAY_KEY    = 'album.collapseDisplay'
const COLLAPSE_DETAILS_KEY    = 'album.collapseDetails'

const collapseCovers     = useLocalStorage(COLLAPSE_COVERS_KEY,     false)
const collapseProperties = useLocalStorage(COLLAPSE_PROPERTIES_KEY, false)
const collapseDisplay    = useLocalStorage(COLLAPSE_DISPLAY_KEY,    false)
const collapseDetails    = useLocalStorage(COLLAPSE_DETAILS_KEY,    false)

// ── Cover slots ─────────────────────────────────────────────────

const coverSlots = ref([null, null, null])
const dragOverSlot = ref(null)

const mediaMap = computed(() => {
  const map = {}
  for (const f of props.mediaFiles) map[f.id] = f
  return map
})

const syncCoversFromAlbum = () => {
  const covers = props.album.covers ?? []
  coverSlots.value = [covers[0] ?? null, covers[1] ?? null, covers[2] ?? null]
}

// Genuine synchronization: coverSlots is an edit buffer (drag/drop mutates it
// before persisting), reset when the inspected album changes.
watch(() => props.album.id, syncCoversFromAlbum, { immediate: true })

// Display data is derived: prefer the freshest media record for each slot, so
// thumbnails resolve whenever mediaFiles arrives — no re-sync watch needed.
const displayCovers = computed(() =>
  coverSlots.value.map((slot) => {
    if (!slot) return null
    const fresh = mediaMap.value[slot.id]
    return fresh
      ? { id: fresh.id, url: fresh.url, thumbnail_url: fresh.thumbnail_url, focus: fresh.focus ?? null }
      : slot
  }),
)

const onDrop = async (event, slotIndex) => {
  dragOverSlot.value = null
  let mediaId = null

  try {
    const raw = event.dataTransfer.getData('application/json')
    if (raw) {
      const data = JSON.parse(raw)
      if (data.type === 'media') mediaId = data.mediaId
    }
  } catch { return }

  if (!mediaId) return

  // If already in another slot, remove it from there first
  coverSlots.value = coverSlots.value.map(s => (s?.id === mediaId ? null : s))

  // Store the id; displayCovers resolves url/thumbnail/focus from mediaFiles.
  coverSlots.value[slotIndex] = { id: mediaId, url: null, thumbnail_url: null, focus: null }

  await persistCovers()
}

const removeCover = async (slotIndex) => {
  coverSlots.value[slotIndex] = null
  await persistCovers()
}

const persistCovers = async () => {
  const coverIds = coverSlots.value.filter(Boolean).map(s => s.id)

  try {
    const data = await apiFetch(panelUrl(`/api/albums/${props.album.id}/cover`), {
      method: 'PUT',
      body: { covers: coverIds },
    })
    // Re-sync from server response (preserves order + server-resolved thumbnails)
    const serverCovers = data.album.covers ?? []
    coverSlots.value = [serverCovers[0] ?? null, serverCovers[1] ?? null, serverCovers[2] ?? null]
    emit('updated', data.album)
  } catch {
    syncCoversFromAlbum()
  }
}

// ── Save title/description/display ──────────────────────────────

// Defaults mirror the settings classes in src/Album/Layout — the server clamps
// and validates, so these only need to be sane starting points for the controls.
const LAYOUT_DEFAULTS = {
  grid:   { mode: 'original', columns: 3, gap: 5, speed: 3000 },
  slider: { autoplay: false, speed: 3000, pagedots: false },
  list:   { speed: 3000, caption_align: 'center' },
}

const optionsFor = (layout, stored) => ({ ...LAYOUT_DEFAULTS[layout] ?? {}, ...(stored ?? {}) })

const albumLayout = () => props.album.layout ?? 'grid'

const fields = reactive({
  title: props.album.title ?? '',
  slug: props.album.slug ?? '',
  description: props.album.description ?? '',
  subtitle: props.album.subtitle ?? '',
  category: props.album.category ?? '',
  categories: props.album.categories ?? '',
  visibility: props.album.visibility ?? 'public',
  fullwidth: props.album.fullwidth ?? false,
  layout: albumLayout(),
})

/** Options for the currently selected layout, kept separate so switching
 *  layout swaps the whole option set rather than merging unrelated keys. */
const options = reactive(optionsFor(albumLayout(), props.album.layout_options))

const resetOptions = (layout, stored = null) => {
  Object.keys(options).forEach(k => delete options[k])
  Object.assign(options, optionsFor(layout, stored))
}

/**
 * Switching layout resets the controls to that layout's defaults, matching what
 * the server stores. This runs synchronously rather than through a watcher, so
 * the save that follows carries the new layout's options and not the old ones.
 */
const changeLayout = (layout) => {
  fields.layout = layout
  resetOptions(layout, layout === albumLayout() ? props.album.layout_options : null)
  save()
}

const selectedCategories = computed(() =>
  (fields.category ?? '').split(',').map(c => c.trim()).filter(Boolean)
)

const toggleCategory = (name) => {
  const current = selectedCategories.value
  const next = current.includes(name)
    ? current.filter(c => c !== name)
    : [...current, name]
  fields.category = next.join(', ')
  save()
}

watch(() => props.album.id, () => {
  fields.title = props.album.title ?? ''
  fields.slug = props.album.slug ?? ''
  fields.description = props.album.description ?? ''
  fields.subtitle = props.album.subtitle ?? ''
  fields.category = props.album.category ?? ''
  fields.categories = props.album.categories ?? ''
  fields.visibility = props.album.visibility ?? 'public'
  fields.fullwidth = props.album.fullwidth ?? false
  fields.layout = albumLayout()
  resetOptions(albumLayout(), props.album.layout_options)
  slugStatus.value = null
  slugError.value = null
})

const { saveStatus, save } = useFieldSaver(async () => {
  if (
    fields.title === props.album.title &&
    fields.description === (props.album.description ?? '') &&
    fields.subtitle === (props.album.subtitle ?? '') &&
    fields.category === (props.album.category ?? '') &&
    fields.categories === (props.album.categories ?? '') &&
    fields.visibility === (props.album.visibility ?? 'public') &&
    fields.fullwidth === (props.album.fullwidth ?? false) &&
    fields.layout === albumLayout() &&
    JSON.stringify(options) === JSON.stringify(optionsFor(albumLayout(), props.album.layout_options))
  ) return

  if (!fields.title.trim()) { fields.title = props.album.title; return }

  await new Promise((resolve, reject) => {
    router.put(panelUrl(`/albums/${props.album.id}`), {
      title: fields.title.trim(),
      description: fields.description || null,
      subtitle: fields.subtitle || null,
      category: fields.category || null,
      categories: fields.categories || null,
      visibility: fields.visibility,
      fullwidth: fields.fullwidth,
      layout: fields.layout,
      layout_options: { ...options },
    }, {
      preserveScroll: true,
      onSuccess: () => {
        emit('updated')
        resolve()
      },
      onError: () => {
        fields.title = props.album.title
        fields.description = props.album.description ?? ''
        fields.subtitle = props.album.subtitle ?? ''
        fields.category = props.album.category ?? ''
        fields.categories = props.album.categories ?? ''
        fields.visibility = props.album.visibility ?? 'public'
        fields.fullwidth = props.album.fullwidth ?? false
        fields.layout = albumLayout()
        resetOptions(albumLayout(), props.album.layout_options)
        reject()
      },
    })
  })
})

const toggleVisibility = () => {
  fields.visibility = fields.visibility === 'public' ? 'private' : 'public'
  save()
}

// ── Save slug ────────────────────────────────────────────────────

const slugStatus = ref(null) // null | 'checking' | 'saving' | 'saved' | 'taken' | 'invalid' | 'error'
const slugError = ref(null)
let slugTimer = null

const normalizeSlug = (val) =>
  val.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')

const saveSlug = async () => {
  const slug = fields.slug.trim()

  if (slug === props.album.slug) return

  if (slug === '') {
    slugError.value = 'Slug cannot be empty'
    fields.slug = props.album.slug ?? ''
    return
  }

  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
    slugError.value = 'Only lowercase letters, numbers, and hyphens — no leading or trailing hyphens'
    slugStatus.value = 'invalid'
    return
  }

  clearTimeout(slugTimer)
  slugStatus.value = 'checking'
  slugError.value = null

  try {
    const checkRes = await fetch(
      panelUrl(`/api/albums/slug/check?slug=${encodeURIComponent(slug)}&exclude_id=${props.album.id}`)
    )
    const checkData = await checkRes.json()

    if (!checkData.available) {
      slugStatus.value = 'taken'
      slugError.value = 'This slug is already taken by another album'
      return
    }

    slugStatus.value = 'saving'
    await apiFetch(panelUrl(`/api/albums/${props.album.id}/slug`), {
      method: 'PUT',
      body: { slug },
    })

    slugStatus.value = 'saved'
    emit('updated')
    slugTimer = setTimeout(() => { slugStatus.value = null }, 2000)
  } catch (error) {
    slugStatus.value = 'error'
    slugError.value = error instanceof ApiError ? error.message : 'Connection error — slug not saved'
    fields.slug = props.album.slug ?? ''
  }
}

// ── Helpers ─────────────────────────────────────────────────────

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
