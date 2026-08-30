import { panelUrl } from '@modufolio/panel'
import { ref, onScopeDispose } from 'vue'
import { invalidateLibraryCounts } from './useLibraryCounts'
import { useTusUploadQueue } from './useTusUploadQueue'
import type { UploadedFile } from '../types/media'

// Re-exported so existing consumers keep a single import site.
export type { UploadStatus, UploadItem, AddFilesOptions } from './useTusUploadQueue'

export interface LoadParams {
  page?: number
  sort?: string
  filter?: string
  search?: string
}

export interface UseTusUploadOptions {
  endpoint?: string
  /**
   * Run the media-library post-processing after each upload: look the saved
   * record up by its TUS filename, and refresh the shared uploaded-files list
   * once the batch settles.
   *
   * Turn off for a standalone endpoint (e.g. contact or project documents)
   * that only needs the filename handed back. Default `true`.
   */
  syncLibrary?: boolean
}

/**
 * Media-library upload composable: the generic TUS queue
 * (useTusUploadQueue, extraction-ready) plus the app-specific parts —
 * the uploaded-files listing backed by /panel/api/media, library-count
 * invalidation, and the by-filename lookup for per-file callbacks.
 */
export function useTusUpload(options: UseTusUploadOptions = {}) {
  const tusEndpoint = options.endpoint ?? panelUrl('/tus')
  const syncLibrary = options.syncLibrary ?? true

  // ── Uploaded-files listing (media library) ────────────────────────────────
  const uploadedFiles = ref<UploadedFile[]>([])
  let reloadTimer: ReturnType<typeof setTimeout> | null = null

  const debouncedLoadUploadedFiles = () => {
    if (reloadTimer) clearTimeout(reloadTimer)
    reloadTimer = setTimeout(() => { loadUploadedFiles() }, 500)
  }

  // Pagination / sort / filter state (used internally and by callers)
  const currentPage = ref(1)
  const currentSort = ref('newest')
  const currentFilter = ref('all')
  const currentSearch = ref('')
  const perPage = 100
  const pagination = ref({ total: 0, totalPages: 1 })
  const isLoading = ref(true)

  // Load uploaded files — accepts optional overrides for page/sort/filter
  const loadUploadedFiles = async (params: LoadParams = {}) => {
    if (params.page !== undefined) currentPage.value = params.page
    if (params.sort !== undefined) currentSort.value = params.sort
    if (params.filter !== undefined) currentFilter.value = params.filter
    if (params.search !== undefined) currentSearch.value = params.search

    isLoading.value = true

    const qs = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(perPage),
      sort: currentSort.value,
      filter: currentFilter.value,
    })
    if (currentSearch.value) qs.set('search', currentSearch.value)

    try {
      const response = await fetch(panelUrl('/api/media?') + qs)
      if (!response.ok) {
        uploadedFiles.value = []
        return
      }
      const data = await response.json()
      uploadedFiles.value = Array.isArray(data.files) ? data.files : []
      pagination.value = {
        total: data.total ?? uploadedFiles.value.length,
        totalPages: data.total_pages ?? 1,
      }
    } catch {
      uploadedFiles.value = []
    } finally {
      isLoading.value = false
    }
  }

  // ── Generic queue wired to the media library ──────────────────────────────
  const queue = useTusUploadQueue({
    endpoint: tusEndpoint,

    // New content changes the library counts wherever they're shown.
    // invalidateQueries() coalesces a burst of these (one per completed
    // file in a batch upload) into at most one extra request per entry.
    onUploaded: () => { void invalidateLibraryCounts() },

    // Look a completed file up by its TUS filename — avoids race conditions
    // caused by concurrent uploads all mutating the shared uploadedFiles list.
    // Callers that opt out (e.g. documents) fall back to `{ filename }`.
    resolveUploadedFile: syncLibrary
      ? async (tusFilename) => {
          let matchedFile: unknown = null
          if (tusFilename) {
            try {
              const res = await fetch(panelUrl(`/api/media/by-filename/${tusFilename}`))
              if (res.ok) matchedFile = await res.json()
            } catch { /* silent */ }
          }
          return matchedFile
        }
      : undefined,

    // Refresh the shared list once after the batch settles
    onSettled: syncLibrary ? debouncedLoadUploadedFiles : undefined,
  })

  // The queue aborts its own uploads/timers on scope teardown; the listing's
  // debounce timer is ours to clear.
  onScopeDispose(() => {
    if (reloadTimer) clearTimeout(reloadTimer)
  })

  return {
    ...queue,
    uploadedFiles,
    currentPage,
    currentSort,
    currentFilter,
    currentSearch,
    pagination,
    isLoading,
    loadUploadedFiles,
  }
}
