<template>
  <!-- ── COLLAPSED: only Content / Favorites / Last Import icons ── -->
  <div v-if="sidebarCollapsed" class="px-3 py-4 space-y-1">
    <button
      @click="$emit('select-album', null)"
      :class="[
        'w-full flex items-center justify-center rounded-lg px-2 py-2 transition-colors duration-75',
        activeFilter === 'all' && selectedAlbumId === null
          ? 'bg-pressed text-ink'
          : 'text-ink hover:bg-hover focus-visible:bg-hover'
      ]"
      title="Content"
    >
      <icon name="photo" class="nav-icon w-4 h-4 shrink-0" />
    </button>

    <button
      @click="$emit('filter-change', 'favorites')"
      :class="[
        'w-full flex items-center justify-center rounded-lg px-2 py-2 transition-colors duration-75',
        activeFilter === 'favorites'
          ? 'bg-pressed text-ink'
          : 'text-ink hover:bg-hover focus-visible:bg-hover'
      ]"
      title="Favorites"
    >
      <icon name="heart" class="nav-icon w-4 h-4 shrink-0" />
    </button>

    <button
      @click="$emit('filter-change', 'recent')"
      :class="[
        'w-full flex items-center justify-center rounded-lg px-2 py-2 transition-colors duration-75',
        activeFilter === 'recent'
          ? 'bg-pressed text-ink'
          : 'text-ink hover:bg-hover focus-visible:bg-hover'
      ]"
      title="Last import"
    >
      <icon name="clock" class="nav-icon w-4 h-4 shrink-0" />
    </button>

    <button
      @click="$emit('filter-change', 'rated')"
      :class="[
        'w-full flex items-center justify-center rounded-lg px-2 py-2 transition-colors duration-75',
        activeFilter === 'rated'
          ? 'bg-pressed text-ink'
          : 'text-ink hover:bg-hover focus-visible:bg-hover'
      ]"
      title="Rated"
    >
      <icon name="star" class="nav-icon w-4 h-4 shrink-0" />
    </button>

    <button
      @click="$emit('filter-change', 'similar')"
      :class="[
        'w-full flex items-center justify-center rounded-lg px-2 py-2 transition-colors duration-75',
        activeFilter === 'similar'
          ? 'bg-pressed text-ink'
          : 'text-ink hover:bg-hover focus-visible:bg-hover'
      ]"
      title="Similar"
    >
      <icon name="archive" class="nav-icon w-4 h-4 shrink-0" />
    </button>
  </div>

  <!-- ── EXPANDED: full sidebar content ────────────────────────── -->
  <div v-else class="py-4">

    <!-- ── LIBRARY SECTION ──────────────────────────────────── -->
    <div class="px-3 pb-1">
      <button
        type="button"
        class="w-full flex items-center justify-between px-3 mb-1 text-xs font-semibold text-label uppercase tracking-wider hover:text-ink transition-colors"
        @click="libraryOpen = !libraryOpen"
      >
        <span>Library</span>
        <svg class="w-3 h-3 transition-transform duration-200" :class="libraryOpen ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <div v-if="libraryOpen" class="space-y-0.5">
        <!-- Content / All Media -->
        <button
          @click="$emit('select-album', null)"
          :class="navItem(activeFilter === 'all' && selectedAlbumId === null)"
        >
          <span class="flex items-center gap-3">
            <icon name="photo" class="nav-icon w-4 h-4 shrink-0" />
            Content
          </span>
          <span class="text-xs text-ink-2 shrink-0">{{ totalMediaCount }}</span>
        </button>

        <!-- Favorites -->
        <button
          @click="$emit('filter-change', 'favorites')"
          :class="navItem(activeFilter === 'favorites')"
        >
          <span class="flex items-center gap-3">
            <icon name="heart" class="nav-icon w-4 h-4 shrink-0" />
            Favorites
          </span>
          <span v-if="favoritesCount > 0" class="text-xs text-ink-2 shrink-0">{{ favoritesCount }}</span>
        </button>

        <!-- Last Import -->
        <button
          @click="$emit('filter-change', 'recent')"
          :class="navItem(activeFilter === 'recent')"
        >
          <span class="flex items-center gap-3">
            <icon name="clock" class="nav-icon w-4 h-4 shrink-0" />
            Last import
          </span>
        </button>

        <!-- Rated -->
        <button
          @click="$emit('filter-change', 'rated')"
          :class="navItem(activeFilter === 'rated')"
        >
          <span class="flex items-center gap-3">
            <icon name="star" class="nav-icon w-4 h-4 shrink-0" />
            Rated
          </span>
        </button>

        <!-- Similar -->
        <button
          @click="$emit('filter-change', 'similar')"
          :class="navItem(activeFilter === 'similar')"
        >
          <span class="flex items-center gap-3">
            <icon name="archive" class="nav-icon w-4 h-4 shrink-0" />
            Similar
          </span>
        </button>
      </div>
    </div>

    <!-- ── SMART ALBUMS (code-defined, read-only) ────────────── -->
    <template v-if="smartAlbums.length > 0">
      <div class="mx-3 my-1 border-t border-line" />
      <div class="px-3 pt-1">
        <button
          @click="smartOpen = !smartOpen"
          class="w-full flex items-center justify-between px-3 mb-1 text-xs font-semibold text-label uppercase tracking-wider hover:text-ink transition-colors"
        >
          <span>Smart albums</span>
          <svg
            :class="['w-3 h-3 transition-transform', smartOpen ? 'rotate-90' : '']"
            fill="currentColor" viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </button>

        <div v-if="smartOpen">
          <button
            v-for="album in smartAlbums"
            :key="album.slug"
            type="button"
            class="w-full flex items-center gap-2 px-3 py-1.5 rounded-md text-sm transition-colors"
            :class="$page.url.startsWith(album.url.replace('/panel', ''))
              ? 'bg-pressed text-ink'
              : 'text-ink-2 hover:bg-hover'"
            :title="album.description || album.title"
            @click="router.visit(album.url)"
          >
            <!-- Sparkles: the smart-album mark -->
            <svg class="w-3.5 h-3.5 shrink-0 text-ink-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path d="M10 1l1.7 4.3L16 7l-4.3 1.7L10 13 8.3 8.7 4 7l4.3-1.7L10 1zM4.5 12l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1L1.5 15l2.1-.9.9-2.1zM15.5 12l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9.9-2.1z" />
            </svg>
            <span class="truncate flex-1 text-left">{{ album.title }}</span>
            <span class="text-xs tabular-nums text-ink-3">{{ album.count }}</span>
          </button>
        </div>
      </div>
    </template>

    <!-- ── COLLECTIONS SECTION ───────────────────────────────── -->
    <div class="mx-3 my-1 border-t border-line" />
    <div class="px-3 pt-1">

      <!-- Section header -->
      <button
        @click="collectionsOpen = !collectionsOpen"
        class="w-full flex items-center justify-between px-3 mb-1 text-xs font-semibold text-label uppercase tracking-wider hover:text-ink transition-colors"
      >
        <span>Collections</span>
        <svg
          :class="['w-3 h-3 transition-transform', collectionsOpen ? 'rotate-90' : '']"
          fill="currentColor" viewBox="0 0 20 20"
        >
          <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
      </button>

      <div v-if="collectionsOpen">

        <!-- Empty state -->
        <p v-if="albumTree.length === 0" class="text-xs text-ink-3 px-2 py-2">
          No albums yet
        </p>

        <template v-else>

          <!-- Public albums — draggable list with insertion indicators -->
          <DraggableAlbumList
            :items="localPublic"
            :drop-state="publicDrop"
            :selected-album-id="selectedAlbumId"
            class="mt-0.5"
            @dragover-item="onPublicDragOver($event.index, $event.event)"
            @dragleave="onPublicDragLeave($event)"
            @drop-item="onPublicDrop"
            @select="$emit('select-album', $event)"
            @edit="$emit('edit-album', $event)"
            @delete="$emit('delete-album', $event)"
            @drop-media="$emit('drop-media', $event)"
            @reorder-children="$emit('reorder-children', $event)"
            @move-into-set="$emit('move-album', { albumId: $event.albumId, parentId: $event.setId })"
          />

          <!-- Unlisted group -->
          <template v-if="localUnlisted.length > 0">
            <div class="flex items-center gap-0.5 mt-1">
              <button
                @click.stop="unlistedOpen = !unlistedOpen"
                class="shrink-0 flex items-center justify-center w-5 h-6 text-ink hover:text-ink-2 rounded"
              >
                <svg :class="['w-3 h-3 transition-transform', unlistedOpen ? 'rotate-90' : '']" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
              <span class="text-xs text-ink-3 font-medium select-none">Unlisted</span>
            </div>
            <DraggableAlbumList
              v-if="unlistedOpen"
              :items="localUnlisted"
              :drop-state="unlistedDrop"
              :selected-album-id="selectedAlbumId"
              @dragover-item="onUnlistedDragOver($event.index, $event.event)"
              @dragleave="onUnlistedDragLeave($event)"
              @drop-item="onUnlistedDrop"
              @select="$emit('select-album', $event)"
              @edit="$emit('edit-album', $event)"
              @delete="$emit('delete-album', $event)"
              @drop-media="$emit('drop-media', $event)"
              @reorder-children="$emit('reorder-children', $event)"
              @move-into-set="$emit('move-album', { albumId: $event.albumId, parentId: $event.setId })"
            />
          </template>

          <!-- Private group -->
          <template v-if="localPrivate.length > 0">
            <div class="flex items-center gap-0.5 mt-1">
              <button
                @click.stop="privateOpen = !privateOpen"
                class="shrink-0 flex items-center justify-center w-5 h-6 text-ink hover:text-ink-2 rounded"
              >
                <svg :class="['w-3 h-3 transition-transform', privateOpen ? 'rotate-90' : '']" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
              <span class="text-xs text-ink-3 font-medium select-none">Private</span>
            </div>
            <DraggableAlbumList
              v-if="privateOpen"
              :items="localPrivate"
              :drop-state="privateDrop"
              :selected-album-id="selectedAlbumId"
              @dragover-item="onPrivateDragOver($event.index, $event.event)"
              @dragleave="onPrivateDragLeave($event)"
              @drop-item="onPrivateDrop"
              @select="$emit('select-album', $event)"
              @edit="$emit('edit-album', $event)"
              @delete="$emit('delete-album', $event)"
              @drop-media="$emit('drop-media', $event)"
              @reorder-children="$emit('reorder-children', $event)"
              @move-into-set="$emit('move-album', { albumId: $event.albumId, parentId: $event.setId })"
            />
          </template>

        </template>

        <!-- Action Buttons -->
        <div class="mt-3 space-y-1">
          <button
            @click="$emit('create-album', 0)"
            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-hover rounded-lg transition-colors"
          >
            <icon name="plus" class="nav-icon w-4 h-4" />
            New Album
          </button>
          <button
            @click="$emit('create-album', 1)"
            class="w-full flex items-center gap-2 px-3 py-2 text-sm text-ink hover:bg-hover rounded-lg transition-colors"
          >
            <icon name="folder" class="nav-icon w-4 h-4" />
            New Set
          </button>
        </div>

      </div>
    </div>

    <!-- ── TAGS SECTION ──────────────────────────────────── -->
    <template v-if="libraryTags.length > 0">
      <div class="mx-3 my-1 border-t border-line" />
      <div class="px-3 pt-1 pb-2">

        <button
          @click="tagsOpen = !tagsOpen"
          class="w-full flex items-center justify-between px-3 mb-1 text-xs font-semibold text-label uppercase tracking-wider hover:text-ink transition-colors"
        >
          <span>Tags</span>
          <svg
            :class="['w-3 h-3 transition-transform', tagsOpen ? 'rotate-90' : '']"
            fill="currentColor" viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </button>

        <div v-if="tagsOpen" class="mt-0.5 space-y-0.5">
          <button
            v-for="tag in libraryTags"
            :key="tag.id"
            @click="router.visit(panelUrl(`/library/tags/${tag.slug ?? tag.id}`))"
            :class="navItem(activeFilter === `tag:${tag.slug ?? tag.id}`)"
          >
            <span class="flex items-center gap-2 truncate">
              <icon name="tag" class="nav-icon w-3.5 h-3.5 shrink-0 text-ink-3" />
              <span class="truncate">{{ tag.name }}</span>
            </span>
            <span class="text-xs tabular-nums shrink-0 text-ink-3">
              {{ tag.count }}
            </span>
          </button>
        </div>

      </div>
    </template>

    <!-- ── TRASH DROP ZONE ───────────────────────────────────── -->
    <div class="mx-3 my-1 border-t border-line" />
    <div class="px-3 pb-2">
      <div
        data-testid="trash-drop-zone"
        :class="[
          'flex items-center justify-center gap-2 rounded-lg border-2 border-dashed px-3 py-3 transition-colors',
          isTrashDragOver
            ? 'border-danger bg-danger-surface text-danger'
            : 'border-line text-ink-2 hover:text-ink-3',
        ]"
        @dragover.prevent="onTrashDragOver"
        @dragenter.prevent="onTrashDragEnter"
        @dragleave.prevent="onTrashDragLeave"
        @drop.prevent="onTrashDrop"
      >
        <icon name="trash" class="nav-icon w-5 h-5 shrink-0" />
        <span class="text-xs font-medium">{{ isTrashDragOver ? 'Drop to delete' : 'Trash' }}</span>
      </div>
    </div>

  </div>

</template>

<script setup>
import { ref, computed, reactive, watch, inject } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useLocalStorage } from '@vueuse/core'
import { SidebarCollapsedKey } from '@modufolio/panel'
import { panelUrl, useQuery } from '@modufolio/panel'
import { apiFetch } from '@modufolio/panel'
import { Icon } from '@modufolio/panel'
import DraggableAlbumList from './DraggableAlbumList.vue'
import { activeDragAlbumId } from './albumDragState.js'

// ── Sidebar collapsed state (provided by AppLayout) ──────────────
const sidebarCollapsed = inject(SidebarCollapsedKey, ref(false))

// ── Props / Emits ─────────────────────────────────────────────────
// Smart albums are code-defined (config/smart_albums.php) and arrive as a
// page prop from the library controllers; absent on pages that don't send it.
// Optional-chained: outside a mounted Inertia app (component tests) there is
// no page object to read from.
const smartAlbums = computed(() => usePage()?.props?.smartAlbums ?? [])

const props = defineProps({
  albumTree:       { type: Array,          default: () => [] },
  selectedAlbumId: { type: [String, null], default: null },
  totalMediaCount: { type: Number,         default: 0 },
  mediaFiles:      { type: Array,          default: () => [] },
  activeFilter:    { type: String,         default: null },
})

// ── Section collapse state (persisted to localStorage) ───────────
const libraryOpen       = useLocalStorage('sidebar.libraryOpen',     true)
const collectionsOpen   = useLocalStorage('sidebar.collectionsOpen', true)
const smartOpen         = useLocalStorage('sidebar.smartOpen',       true)
const tagsOpen          = useLocalStorage('sidebar.tagsOpen',        true)
const unlistedOpen      = useLocalStorage('sidebar.unlistedOpen',    true)
const privateOpen       = useLocalStorage('sidebar.privateOpen',     true)

// ── Library tags (for tag-filter sidebar) ────────────────────────
// Shared cache entry; tag mutations (useMediaTags, bulk tagging) invalidate
// 'tags:library', so counts refresh here without any navigation heuristics.
const libraryTagsQuery = useQuery(
  'tags:library',
  ({ signal }) => apiFetch('/panel/api/tags/library', { signal }),
)
const libraryTags = computed(() => libraryTagsQuery.data.value ?? [])

// ── Safe media files (guard against non-array from deferred data) ─
const safeMediaFiles = computed(() => Array.isArray(props.mediaFiles) ? props.mediaFiles : [])

// ── Favorites count ──────────────────────────────────────────────
const favoritesCount = computed(() => safeMediaFiles.value.filter(f => f.is_favorite).length)

// ── Nav item class helper ─────────────────────────────────────────
const navItem = (isActive, compact = false) => [
  'w-full flex items-center justify-between rounded-lg text-sm font-medium transition-colors duration-75',
  compact ? 'px-3 py-1' : 'px-3 py-2',
  isActive
    ? 'bg-pressed text-ink'
    : 'text-ink hover:bg-hover focus-visible:bg-hover',
]

// ── Albums grouped by visibility ──────────────────────────────────
const albumsByVisibility = computed(() => {
  const result = { public: [], unlisted: [], private: [] }
  for (const node of props.albumTree) {
    const vis = node.visibility ?? 'public'
    if (vis in result) {
      result[vis].push(node)
    } else {
      result.public.push(node)
    }
  }
  return result
})

// ── Local ordered lists (optimistic reorder UI) ───────────────────
const localPublic   = ref([])
const localUnlisted = ref([])
const localPrivate  = ref([])

// Genuine synchronization (not derivable): the local lists are an optimistic
// reorder buffer, so prop updates must be suppressed while a drag is active.
watch(albumsByVisibility, (groups) => {
  if (activeDragAlbumId.value) return
  localPublic.value   = [...groups.public]
  localUnlisted.value = [...groups.unlisted]
  localPrivate.value  = [...groups.private]
}, { immediate: true })

// ── Drag-reorder state per visibility group ───────────────────────
const publicDrop   = reactive({ dropIndex: null })
const unlistedDrop = reactive({ dropIndex: null })
const privateDrop  = reactive({ dropIndex: null })

const emit = defineEmits([
  'select-album', 'create-album', 'edit-album', 'delete-album',
  'drop-media', 'filter-change', 'reorder-root', 'reorder-children', 'move-album',
  'delete-media',
])

// ── Drag handlers — closures keep ref identity in JS scope ────────
// (Vue template auto-unwraps refs, so passing localPublic through the
//  template would give the raw array, not the ref. Closures avoid that.)

const makeGroupHandlers = (localList, dropState) => ({
  dragOver (index, event) {
    if (!event.dataTransfer.types.includes('application/x-album-id')) return
    const rect = event.currentTarget.getBoundingClientRect()
    dropState.dropIndex = event.clientY < rect.top + rect.height / 2 ? index : index + 1
  },
  dragLeave (event) {
    if (!event.currentTarget?.contains(event.relatedTarget)) {
      dropState.dropIndex = null
    }
  },
  drop () {
    const dragId = activeDragAlbumId.value
    if (dragId === null || dropState.dropIndex === null) { dropState.dropIndex = null; return }

    const fromIndex = localList.value.findIndex(a => a.id === dragId)

    if (fromIndex === -1) {
      // Album is not in this root group — it's a child being moved to root
      dropState.dropIndex = null
      emit('move-album', { albumId: dragId, parentId: null })
      return
    }

    // Reorder within the same root group
    const newList = [...localList.value]
    const [moved] = newList.splice(fromIndex, 1)
    let insertAt = dropState.dropIndex
    if (insertAt > fromIndex) insertAt--
    newList.splice(insertAt, 0, moved)

    localList.value = newList
    dropState.dropIndex = null
    emit('reorder-root', { albumIds: newList.map(a => a.id) })
  },
})

const publicHandlers   = makeGroupHandlers(localPublic,   publicDrop)
const unlistedHandlers = makeGroupHandlers(localUnlisted, unlistedDrop)
const privateHandlers  = makeGroupHandlers(localPrivate,  privateDrop)

const onPublicDragOver    = (i, e) => publicHandlers.dragOver(i, e)
const onPublicDragLeave   = (e)    => publicHandlers.dragLeave(e)
const onPublicDrop        = ()     => publicHandlers.drop()

const onUnlistedDragOver  = (i, e) => unlistedHandlers.dragOver(i, e)
const onUnlistedDragLeave = (e)    => unlistedHandlers.dragLeave(e)
const onUnlistedDrop      = ()     => unlistedHandlers.drop()

const onPrivateDragOver   = (i, e) => privateHandlers.dragOver(i, e)
const onPrivateDragLeave  = (e)    => privateHandlers.dragLeave(e)
const onPrivateDrop       = ()     => privateHandlers.drop()

// ── Trash drop zone ───────────────────────────────────────────
const isTrashDragOver = ref(false)
let trashDragCounter = 0

const onTrashDragEnter = () => {
  trashDragCounter++
  isTrashDragOver.value = true
}

const onTrashDragOver = (event) => {
  event.dataTransfer.dropEffect = 'copy'
}

const onTrashDragLeave = () => {
  trashDragCounter--
  if (trashDragCounter <= 0) {
    trashDragCounter = 0
    isTrashDragOver.value = false
  }
}

const onTrashDrop = (event) => {
  trashDragCounter = 0
  isTrashDragOver.value = false

  // Album drag from sidebar tree items (uses application/x-album-id)
  if (activeDragAlbumId.value !== null) {
    emit('delete-album', { id: activeDragAlbumId.value })
    return
  }

  try {
    const raw = event.dataTransfer.getData('application/json')
    if (!raw) return
    const data = JSON.parse(raw)
    if (data.type === 'media' && data.mediaId) {
      emit('delete-media', { mediaId: data.mediaId })
    } else if (data.type === 'album' && data.albumId) {
      // Album drag from set card view
      emit('delete-album', { id: data.albumId })
    }
  } catch { /* ignore invalid data */ }
}
</script>
