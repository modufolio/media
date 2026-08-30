import { type Ref } from 'vue'
import { useEventListener } from '@vueuse/core'
import { panelUrl, apiFetch } from '@modufolio/panel'
import { optimistic } from '@modufolio/panel'

type Rating = number | null

interface RatableFile {
  id: number | string
  rating: Rating
  [key: string]: unknown
}

interface GridRef {
  clearSelection: () => void
}

type MediaId = number | string

/**
 * Adds keyboard shortcuts 1–5 to bulk-rate selected media items in a grid view.
 *
 * @param selectedIds  Reactive array of selected media IDs
 * @param mediaFiles   Reactive array of media objects (mutated optimistically)
 * @param mediaGridRef Ref to the MediaGrid component (for clearSelection)
 * @param onAfterRate  Optional callback after a successful API call (e.g. reload)
 */
export function useGridRatingKeys(
  selectedIds: Ref<MediaId[]>,
  mediaFiles: Ref<RatableFile[]>,
  mediaGridRef: Ref<GridRef | null>,
  onAfterRate?: (ids: MediaId[], rating: Rating) => void,
) {
  // Optimistically set `newRating` on the selected files and return a rollback
  // thunk that restores each file's prior rating.
  const applyRating = (ids: MediaId[], newRating: Rating): (() => void) => {
    const affected = mediaFiles.value.filter((f) => ids.includes(f.id))
    const previous = affected.map((f) => [f, f.rating] as const)
    affected.forEach((f) => { f.rating = newRating })
    return () => previous.forEach(([f, value]) => { f.rating = value })
  }

  const bulkRate = async (rating: number) => {
    if (!selectedIds.value.length) return

    const ids = [...selectedIds.value]
    const isSingle = ids.length === 1

    // Toggle off if all selected items already have the same rating
    const allHaveSameRating = ids.every((id) => {
      const file = mediaFiles.value.find((f) => f.id === id)
      return file?.rating === rating
    })
    const newRating: Rating = allHaveSameRating ? null : rating

    // Keep selection for single image so inspector stays populated
    if (!isSingle) {
      selectedIds.value = []
      mediaGridRef.value?.clearSelection()
    }

    const ok = await optimistic(
      () => applyRating(ids, newRating),
      () => apiFetch(panelUrl('/api/media/bulk/rating'), {
        method: 'PATCH',
        body: { media_ids: ids, rating: newRating },
      }),
    )

    if (ok) onAfterRate?.(ids, newRating)
  }

  const onKeyDown = (e: KeyboardEvent) => {
    if (!selectedIds.value.length) return
    const target = e.target as HTMLElement | null
    if (target && (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable)) return
    if (e.metaKey || e.ctrlKey || e.altKey) return
    if (!['1', '2', '3', '4', '5'].includes(e.key)) return

    e.preventDefault()
    bulkRate(parseInt(e.key))
  }

  const bulkClearRating = async () => {
    if (!selectedIds.value.length) return

    const ids = [...selectedIds.value]

    selectedIds.value = []
    mediaGridRef.value?.clearSelection()

    const ok = await optimistic(
      () => applyRating(ids, null),
      () => apiFetch(panelUrl('/api/media/bulk/rating'), {
        method: 'PATCH',
        body: { media_ids: ids, rating: null },
      }),
    )

    if (ok) onAfterRate?.(ids, null)
  }

  useEventListener(window, 'keydown', onKeyDown)

  return { bulkRate, bulkClearRating }
}
