import type { Ref } from 'vue'
import { panelUrl, apiFetch } from '@modufolio/panel'
import { optimistic } from '@modufolio/panel'

interface MediaFile {
    id: number | string
    is_favorite: boolean
    [key: string]: unknown
}

interface MediaGridRef {
    clearSelection: () => void
}

interface UseBulkFavoriteOptions {
    direction?: 'favorite' | 'unfavorite'
    onSuccess?: () => void
}

export function useBulkFavorite(
    selectedIds: Ref<(number | string)[]>,
    files: Ref<MediaFile[]>,
    mediaGridRef: Ref<MediaGridRef | null>,
    options: UseBulkFavoriteOptions = {},
): { bulkFavorite: () => Promise<void> } {
    const { direction = 'favorite', onSuccess } = options

    const bulkFavorite = async () => {
        if (!selectedIds.value.length) return
        const ids = [...selectedIds.value]
        const isFavorite = direction === 'favorite'

        selectedIds.value = []
        mediaGridRef.value?.clearSelection()

        const ok = await optimistic(
            () => {
                const affected = files.value.filter((f) => ids.includes(f.id))
                const previous = affected.map((f) => [f, f.is_favorite] as const)
                affected.forEach((f) => { f.is_favorite = isFavorite })
                return () => previous.forEach(([f, value]) => { f.is_favorite = value })
            },
            () => apiFetch(panelUrl('/api/media/bulk/favorite'), {
                method: 'PATCH',
                body: { media_ids: ids, is_favorite: isFavorite },
            }),
        )

        if (ok) onSuccess?.()
    }

    return { bulkFavorite }
}
