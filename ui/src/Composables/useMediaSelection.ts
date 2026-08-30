import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'

interface MediaFile {
    id: number | string
    [key: string]: unknown
}

interface MediaGridRef {
    clearSelection: () => void
    selectAll: () => void
}

interface UseMediaSelectionOptions {
    files: Ref<MediaFile[]>
}

interface UseMediaSelectionReturn {
    selectedIds: Ref<(number | string)[]>
    mediaGridRef: Ref<MediaGridRef | null>
    inspectorOpen: Ref<boolean>
    selectedMedia: ComputedRef<MediaFile | null>
    handleSelectionChange: (ids: (number | string)[]) => void
}

export function useMediaSelection(options: UseMediaSelectionOptions): UseMediaSelectionReturn {
    const { files } = options

    const selectedIds = ref<(number | string)[]>([])
    const mediaGridRef = ref<MediaGridRef | null>(null)
    const inspectorOpen = ref(false)

    const selectedMedia = computed<MediaFile | null>(() => {
        if (selectedIds.value.length !== 1) return null
        const id = selectedIds.value[0]
        return files.value.find((f) => f.id === id) ?? null
    })

    const handleSelectionChange = (ids: (number | string)[]) => {
        selectedIds.value = ids
    }

    return { selectedIds, mediaGridRef, inspectorOpen, selectedMedia, handleSelectionChange }
}
