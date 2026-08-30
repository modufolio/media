import { computed } from 'vue'
import { panelUrl, apiFetch } from '@modufolio/panel'
import { useQuery, invalidateQueries } from '@modufolio/panel'

interface LibraryCountsResponse {
  total_content?: number
  albums?: Record<string, number>
}

/** Query key for the global library counts; invalidate after any mutation that
 * adds or removes content (uploads, deletes, album changes). */
export const LIBRARY_COUNTS_KEY = 'library:counts'

export function useLibraryCounts() {
  const query = useQuery<LibraryCountsResponse>(
    LIBRARY_COUNTS_KEY,
    ({ signal }) => apiFetch(panelUrl('/api/library/counts'), { signal }),
  )

  return {
    totalContentCount: computed(() => query.data.value?.total_content ?? 0),
    albumCounts: computed(() => query.data.value?.albums ?? {}),
    refreshCounts: () => invalidateQueries(LIBRARY_COUNTS_KEY),
  }
}

/** Refetch the library counts everywhere they're displayed. */
export function invalidateLibraryCounts(): Promise<void> {
  return invalidateQueries(LIBRARY_COUNTS_KEY)
}
