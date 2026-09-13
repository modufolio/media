<template>
  <MediaInspector
    :media="media"
    :files="files"
    :album-id="albumId"
    :selected-ids="selectedIds"
    :media-tags="mediaTags"
    :filtered-available-tags="filteredAvailableTags"
    :can-create-tag="canCreateTag"
    :show-tag-dropdown="showTagDropdown"
    :tag-search="tagSearch"
    :bulk-save-status="bulkSaveStatus"
    :bulk-tags="bulkTags"
    @update:show-tag-dropdown="showTagDropdown = $event"
    @update:tag-search="tagSearch = $event"
    @open-tag-dropdown="openTagDropdown"
    @attach-tag="handleAttachTag"
    @detach-tag="handleDetachTag"
    @create-tag="handleCreateAndAttachTag"
    @bulk-save="bulkSave"
    @bulk-attach-tag="bulkAttachTag"
    @bulk-create-attach-tag="bulkCreateAndAttachTag"
    @bulk-detach-tag="bulkDetachTag"
  />
</template>

<script setup lang="ts">
import { toRef, ref, watch } from 'vue'
import MediaInspector from './MediaInspector.vue'
import { useMediaTags } from '../../Composables/useMediaTags'
import { panelUrl, useFieldSaver } from '@modufolio/panel'
import { apiFetch } from '@modufolio/panel'
import { invalidateQueries } from '@modufolio/panel'
import type { AlbumId, BulkTag, MediaFile, MediaId, Tag } from '../../types/media'

const props = withDefaults(defineProps<{
  media?: MediaFile | null
  files?: MediaFile[]
  albumId?: AlbumId | null
  selectedIds?: MediaId[]
}>(), {
  media: null,
  files: () => [],
  albumId: null,
  selectedIds: () => [],
})

// Fired whenever any tag attach/detach completes (single-media or bulk),
// with { action: 'attach' | 'detach', tagId }. A tag-filtered page (e.g.
// Media/Tags.vue) needs this to refetch when a 'detach' removes the SPECIFIC
// tag it's filtered by — that's the only case that can drop the current item
// out of that view. It must ignore 'attach' entirely, and must also ignore a
// 'detach' of any tag other than its own (e.g. removing an unrelated second
// tag from an item while viewing it on this page) — reacting to either would
// kick the user out of whatever they're still editing for no reason.
const emit = defineEmits<{
  'tags-changed': [payload: { action: 'attach' | 'detach'; tagId?: Tag['id'] }]
}>()

const {
  mediaTags,
  filteredAvailableTags,
  canCreateTag,
  showTagDropdown,
  tagSearch,
  openTagDropdown,
  attachTag,
  detachTag,
  createAndAttachTag,
} = useMediaTags(toRef(props, 'media'))

// useMediaTags' own methods catch their errors internally (see its source),
// so awaiting them and always emitting after is consistent with that: a
// failed attach/detach already fails silently from the caller's point of
// view either way.
const handleAttachTag = async (tagId: Tag['id']) => {
  await attachTag(tagId)
  emit('tags-changed', { action: 'attach' })
}

const handleDetachTag = async (tagId: Tag['id']) => {
  await detachTag(tagId)
  emit('tags-changed', { action: 'detach', tagId })
}

const handleCreateAndAttachTag = async (name: string) => {
  await createAndAttachTag(name)
  emit('tags-changed', { action: 'attach' })
}

// ── Bulk actions (metadata save + tag attach) ─────────────────────
// One shared status/timer (via useFieldSaver) since all three actions
// report through the same "Saving…/Saved ✓/Error" indicator in the panel.
type BulkAction =
  | { type: 'metadata'; fields: Record<string, string | null> }
  | { type: 'attach-tag'; tagId: Tag['id'] }
  | { type: 'create-attach-tag'; name: string }

const { saveStatus: bulkSaveStatus, save: runBulkAction } = useFieldSaver<[BulkAction]>(async (action) => {
  if (action.type === 'metadata') {
    if (!props.selectedIds.length) return

    // Normalise empty strings to null (matches server behaviour)
    const normalised: Record<string, string | null> = Object.fromEntries(
      Object.entries(action.fields).map(([k, v]) => [k, v || null])
    )

    // Optimistic update — mutate matching files in place
    props.files.forEach((file) => {
      if (props.selectedIds.includes(file.id)) {
        Object.assign(file, normalised)
      }
    })

    await apiFetch(panelUrl('/api/media/bulk/metadata'), {
      method: 'PATCH',
      body: { media_ids: props.selectedIds, ...normalised },
    })
    return
  }

  const tagId: Tag['id'] = action.type === 'attach-tag'
    ? action.tagId
    : (await apiFetch<{ id: Tag['id'] }>('/panel/api/tags', { method: 'POST', body: { name: action.name } })).id

  await apiFetch('/panel/api/tags/bulk/media', {
    method: 'POST',
    body: { tag_id: tagId, ids: props.selectedIds },
  })
  void invalidateQueries('tags:library')
})

const bulkSave = (fields: Record<string, string | null>) => runBulkAction({ type: 'metadata', fields })

const bulkAttachTag = async (tagId: Tag['id']) => {
  showTagDropdown.value = false
  await runBulkAction({ type: 'attach-tag', tagId })
  void loadBulkTags()
  emit('tags-changed', { action: 'attach' })
}

const bulkCreateAndAttachTag = async (name: string) => {
  showTagDropdown.value = false
  const trimmed = name.trim()
  if (!trimmed) return
  await runBulkAction({ type: 'create-attach-tag', name: trimmed })
  void loadBulkTags()
  emit('tags-changed', { action: 'attach' })
}

// ── Bulk tags display (which tags the selected files already carry) ──
// One request for the whole selection via GET /panel/api/tags/bulk/media, rather
// than fanning out a request per file. Kept as a per-file map so bulk-detach
// only targets the files that actually have the tag being removed.
const bulkTags = ref<BulkTag[]>([]) // count = how many selected files carry it
let perFileTags = new Map<MediaId, Tag[]>() // mediaId (uuid) -> Tag[]

const loadBulkTags = async () => {
  const ids = [...props.selectedIds]
  if (ids.length < 2) {
    bulkTags.value = []
    perFileTags = new Map()
    return
  }

  const tagsByMediaId = await apiFetch<Record<string, Tag[]>>(`/panel/api/tags/bulk/media?ids=${ids.map(encodeURIComponent).join(',')}`)

  // Stale response guard: selection may have changed while the request was in flight.
  if (props.selectedIds.length !== ids.length || !ids.every((id) => props.selectedIds.includes(id))) return

  const nextPerFileTags = new Map<MediaId, Tag[]>()
  const counts = new Map<Tag['id'], { tag: Tag; count: number }>()

  for (const id of ids) {
    const tags = tagsByMediaId[id] ?? []
    nextPerFileTags.set(id, tags)
    tags.forEach((tag) => {
      const entry = counts.get(tag.id) ?? { tag, count: 0 }
      entry.count++
      counts.set(tag.id, entry)
    })
  }

  perFileTags = nextPerFileTags
  bulkTags.value = [...counts.values()]
    .sort((a, b) => b.count - a.count || a.tag.name.localeCompare(b.tag.name))
    .map(({ tag, count }) => ({ id: tag.id, name: tag.name, count }))
}

watch(() => props.selectedIds, () => void loadBulkTags(), { immediate: true })

const bulkDetachTag = async (tagId: Tag['id']) => {
  const idsWithTag = [...perFileTags.entries()]
    .filter(([, tags]) => tags.some((tag) => tag.id === tagId))
    .map(([mediaId]) => mediaId)

  // Optimistic: drop the chip immediately, reconcile with the server after.
  bulkTags.value = bulkTags.value.filter((tag) => tag.id !== tagId)

  await Promise.allSettled(
    idsWithTag.map((mediaId) => apiFetch(`/panel/api/tags/media/${mediaId}/${tagId}`, { method: 'DELETE' }))
  )
  void invalidateQueries('tags:library')
  void loadBulkTags()
  emit('tags-changed', { action: 'detach', tagId })
}
</script>
