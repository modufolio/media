import { computed, ref, watch, type Ref } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { apiFetch } from '@modufolio/panel'
import { useAsyncData } from '@modufolio/panel'
import { invalidateQueries } from '@modufolio/panel'

export interface Tag {
  id: number | string
  name: string
  [key: string]: unknown
}

interface MediaLike {
  id: number | string
}

export function useMediaTags(mediaRef: Ref<MediaLike | null | undefined>) {
  const mediaTags = ref<Tag[]>([])
  const showTagDropdown = ref(false)
  const tagSearch = ref('')

  // Tag search runs through useAsyncData so a superseded response from fast
  // typing can't overwrite the results of a newer query.
  const search = useAsyncData(
    ({ signal }, query: string) =>
      apiFetch<Tag[]>(`/panel/api/tags/search?q=${encodeURIComponent(query)}`, { signal }),
    { initialData: [] },
  )
  const searchResults = computed<Tag[]>(() => search.data.value ?? [])

  const filteredAvailableTags = computed(() =>
    searchResults.value.filter((tag) => !mediaTags.value.some((attached) => attached.id === tag.id))
  )

  const canCreateTag = computed(() => {
    const query = tagSearch.value.trim()
    if (!query) return false
    return !searchResults.value.some((tag) => tag.name.toLowerCase() === query.toLowerCase())
  })

  const fetchMediaTags = async (mediaId: number | string) => {
    try {
      mediaTags.value = await apiFetch<Tag[]>(`/panel/api/tags/media/${mediaId}`)
    } catch {
      // silent
    }
  }

  watch(mediaRef, (media) => {
    mediaTags.value = []
    search.data.value = []
    showTagDropdown.value = false
    tagSearch.value = ''

    if (media?.id) {
      void fetchMediaTags(media.id)
    }
  }, { immediate: true })

  // watchDebounced owns the timer and clears it on scope dispose; the search
  // itself is stale-guarded by useAsyncData.
  watchDebounced(tagSearch, (query) => {
    void search.execute(query.trim())
  }, { debounce: 200 })

  const openTagDropdown = async () => {
    showTagDropdown.value = !showTagDropdown.value

    if (showTagDropdown.value) {
      tagSearch.value = ''
      await search.execute('')
    }
  }

  const attachTag = async (tagId: number | string) => {
    const media = mediaRef.value
    if (!media) return

    showTagDropdown.value = false

    try {
      await apiFetch(`/panel/api/tags/media/${media.id}`, {
        method: 'POST',
        body: { tag_id: tagId },
      })

      const tag = searchResults.value.find((result) => result.id === tagId)
      if (tag && !mediaTags.value.some((attached) => attached.id === tag.id)) {
        mediaTags.value.push(tag)
      }
      void invalidateQueries('tags:library')
    } catch {
      // silent
    }
  }

  const detachTag = async (tagId: number | string) => {
    const media = mediaRef.value
    if (!media) return

    try {
      await apiFetch(`/panel/api/tags/media/${media.id}/${tagId}`, { method: 'DELETE' })
      mediaTags.value = mediaTags.value.filter((tag) => tag.id !== tagId)
      void invalidateQueries('tags:library')
    } catch {
      // silent
    }
  }

  const createAndAttachTag = async (name: string) => {
    const media = mediaRef.value
    if (!media) return

    showTagDropdown.value = false
    const trimmed = name.trim()
    if (!trimmed) return

    try {
      const newTag = await apiFetch<Tag>('/panel/api/tags', {
        method: 'POST',
        body: { name: trimmed },
      })

      await apiFetch(`/panel/api/tags/media/${media.id}`, {
        method: 'POST',
        body: { tag_id: newTag.id },
      })

      if (!mediaTags.value.some((tag) => tag.id === newTag.id)) {
        mediaTags.value.push(newTag)
      }
      void invalidateQueries('tags:library')
    } catch {
      // silent
    }
  }

  return {
    mediaTags,
    filteredAvailableTags,
    canCreateTag,
    showTagDropdown,
    tagSearch,
    openTagDropdown,
    attachTag,
    detachTag,
    createAndAttachTag,
  }
}
