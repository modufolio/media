import { ref } from 'vue'
import type { Ref } from 'vue'
import { useToast } from '@modufolio/panel'
import { panelUrl, apiFetch, ApiError } from '@modufolio/panel'
import { invalidateLibraryCounts } from './useLibraryCounts'

interface MediaFile {
    id: number | string
    original_filename?: string
    [key: string]: unknown
}

interface MediaGridRef {
    clearSelection: () => void
}

interface ConfirmDeleteMedia {
    show: boolean
    ids: (number | string)[]
    label: string
}

interface UseMediaDeleteOptions {
    files: Ref<MediaFile[]>
    selectedIds: Ref<(number | string)[]>
    mediaGridRef: Ref<MediaGridRef | null>
    onAfterDelete?: () => void
    /** Index.vue's localMediaFiles — kept in sync with files on delete */
    extraFiles?: Ref<MediaFile[]>
}

interface UseMediaDeleteReturn {
    confirmDeleteMedia: Ref<ConfirmDeleteMedia>
    handleDeleteMedia: (payload: { mediaId: number | string }) => void
    deleteSelected: () => void
    doDeleteMedia: () => Promise<void>
}

export function useMediaDelete(options: UseMediaDeleteOptions): UseMediaDeleteReturn {
    const { files, selectedIds, mediaGridRef, onAfterDelete, extraFiles } = options
    const toast = useToast()

    const confirmDeleteMedia = ref<ConfirmDeleteMedia>({ show: false, ids: [], label: '' })

    const handleDeleteMedia = ({ mediaId }: { mediaId: number | string }) => {
        const idsToDelete = selectedIds.value.length > 0 && selectedIds.value.includes(mediaId)
            ? [...selectedIds.value]
            : [mediaId]

        const count = idsToDelete.length
        const label = count === 1
            ? (files.value.find((f) => f.id === mediaId)?.original_filename ?? 'this file')
            : `${count} files`

        confirmDeleteMedia.value = { show: true, ids: idsToDelete, label }
    }

    const deleteSelected = () => {
        if (selectedIds.value.length === 0) return
        const label = `${selectedIds.value.length} file${selectedIds.value.length === 1 ? '' : 's'}`
        confirmDeleteMedia.value = { show: true, ids: [...selectedIds.value], label }
    }

    const doDeleteMedia = async () => {
        const idsToDelete = confirmDeleteMedia.value.ids
        confirmDeleteMedia.value = { show: false, ids: [], label: '' }

        const rollback = files.value.filter((f) => idsToDelete.includes(f.id))
        const extraRollback = extraFiles?.value.filter((f) => idsToDelete.includes(f.id))

        files.value = files.value.filter((f) => !idsToDelete.includes(f.id))
        if (extraFiles) extraFiles.value = extraFiles.value.filter((f) => !idsToDelete.includes(f.id))

        selectedIds.value = []
        mediaGridRef.value?.clearSelection()

        try {
            const data = await apiFetch<{ message?: string }>(panelUrl('/api/media/bulk'), {
                method: 'DELETE',
                body: { media_ids: idsToDelete },
            })
            toast.success(data?.message || 'Files deleted', 'Deleted')
            void invalidateLibraryCounts()
            onAfterDelete?.()
        } catch (error) {
            files.value = [...rollback, ...files.value]
            if (extraFiles && extraRollback) extraFiles.value = [...extraRollback, ...extraFiles.value]

            if (error instanceof ApiError) {
                const body = error.body as { message?: string } | null
                toast.error(body?.message || 'Failed to delete files', 'Error')
            } else {
                toast.error('Error deleting files: ' + ((error as Error)?.message || 'Unknown error'), 'Error')
            }
        }
    }

    return { confirmDeleteMedia, handleDeleteMedia, deleteSelected, doDeleteMedia }
}
