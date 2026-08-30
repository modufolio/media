import { computed, watch } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { useLocalStorage } from '@vueuse/core'

interface UseGridStateOptions {
    onSortChange?: (sort: string) => void
}

interface UseGridStateReturn {
    mediaCols: Ref<number>
    sortOrder: Ref<string>
    invertedMediaCols: ComputedRef<number>
}

// Grid columns run from 3 (fewest, largest tiles) to 10 (most, smallest tiles);
// invertedMediaCols flips that into the actual --media-cols column count so the
// same stored preference renders identically on every media page.
const COLS_BASE = 13

// Grid size and sort order are global preferences shared across every media
// page — not scoped per page — so the localStorage keys below are fixed.
export function useGridState(options: UseGridStateOptions = {}): UseGridStateReturn {
    const { onSortChange } = options

    // useLocalStorage persists automatically, serializes by the default's type
    // (number/string), and wraps every access in try/catch — no manual getItem
    // parsing or setItem watchers, and it survives private-mode / quota errors.
    const mediaCols = useLocalStorage('library.mediaCols', 7)
    const sortOrder = useLocalStorage('library.sortOrder', 'newest')

    const invertedMediaCols = computed(() => COLS_BASE - mediaCols.value)

    watch(sortOrder, (v) => {
        onSortChange?.(v)
    })

    return { mediaCols, sortOrder, invertedMediaCols }
}
