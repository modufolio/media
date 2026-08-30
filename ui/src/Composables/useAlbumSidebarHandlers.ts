import { ref } from 'vue'
import type { Ref, ComputedRef } from 'vue'
// @ts-expect-error - useAlbums.js has no type declarations
import { useAlbums } from './useAlbums.js'

interface Album {
    id: number | string
    album_type: number
    title: string
    [key: string]: unknown
}

interface AlbumSubmitData {
    id?: number | string
    title: string
    description?: string
    album_type?: number
    parent_id?: number | string | null
}

interface UseAlbumSidebarHandlersOptions {
    selectedIds: Ref<(number | string)[]>
    onSelectAlbum: (albumId: number | string | null) => void
    onFilterChange: (filter: string) => void
}

interface UseAlbumSidebarHandlersReturn {
    albums: ComputedRef<Album[]>
    albumTree: ComputedRef<Album[]>
    showAlbumDialog: Ref<boolean>
    dialogAlbumType: Ref<number>
    editingAlbum: Ref<Album | null>
    confirmDeleteAlbum: Ref<Album | null>
    handleSelectAlbum: (albumId: number | string | null) => void
    handleFilterChange: (filter: string) => void
    handleCreateAlbum: (albumType: number) => void
    handleEditAlbum: (album: Album) => void
    handleDeleteAlbum: (album: Album) => void
    doDeleteAlbum: () => void
    closeAlbumDialog: () => void
    handleAlbumSubmit: (data: AlbumSubmitData) => void
    handleDropMedia: (payload: { albumId: number | string; mediaId: number | string }) => void
    handleReorderRoot: (payload: { albumIds: (number | string)[] }) => void
    handleReorderChildren: (payload: { setId: number | string; albumIds: (number | string)[] }) => void
    handleMoveAlbum: (payload: { albumId: number | string; parentId: number | string }) => void
}

export function useAlbumSidebarHandlers(options: UseAlbumSidebarHandlersOptions): UseAlbumSidebarHandlersReturn {
    const { selectedIds, onSelectAlbum, onFilterChange } = options

    const {
        albums,
        albumTree,
        createAlbum,
        updateAlbum,
        deleteAlbum: deleteAlbumApi,
        moveAlbum,
        addMediaToAlbum,
        reorderRootAlbums,
        reorderAlbumChildren,
    } = useAlbums()

    const showAlbumDialog = ref(false)
    const dialogAlbumType = ref(0)
    const editingAlbum = ref<Album | null>(null)
    const confirmDeleteAlbum = ref<Album | null>(null)

    const handleSelectAlbum = (albumId: number | string | null) => onSelectAlbum(albumId)
    const handleFilterChange = (filter: string) => onFilterChange(filter)

    const handleCreateAlbum = (albumType: number) => {
        dialogAlbumType.value = albumType
        editingAlbum.value = null
        showAlbumDialog.value = true
    }

    const handleEditAlbum = (album: Album) => {
        dialogAlbumType.value = album.album_type
        editingAlbum.value = album
        showAlbumDialog.value = true
    }

    const handleDeleteAlbum = (album: Album) => {
        confirmDeleteAlbum.value = album
    }

    const doDeleteAlbum = () => {
        if (!confirmDeleteAlbum.value) return
        deleteAlbumApi(confirmDeleteAlbum.value.id)
        confirmDeleteAlbum.value = null
    }

    const closeAlbumDialog = () => {
        showAlbumDialog.value = false
        editingAlbum.value = null
    }

    const handleAlbumSubmit = (data: AlbumSubmitData) => {
        if (data.id) {
            updateAlbum(data.id, { title: data.title, description: data.description, visibility: 'public' })
        } else {
            createAlbum({ title: data.title, description: data.description, album_type: data.album_type, parent_id: data.parent_id })
        }
    }

    const handleDropMedia = ({ albumId, mediaId }: { albumId: number | string; mediaId: number | string }) => {
        const idsToAdd = selectedIds.value.length > 0 && selectedIds.value.includes(mediaId)
            ? selectedIds.value
            : [mediaId]
        addMediaToAlbum(albumId, idsToAdd)
    }

    const handleReorderRoot = ({ albumIds }: { albumIds: (number | string)[] }) => reorderRootAlbums(albumIds)
    const handleReorderChildren = ({ setId, albumIds }: { setId: number | string; albumIds: (number | string)[] }) => reorderAlbumChildren(setId, albumIds)
    const handleMoveAlbum = ({ albumId, parentId }: { albumId: number | string; parentId: number | string }) => moveAlbum(albumId, parentId)

    return {
        albums,
        albumTree,
        showAlbumDialog,
        dialogAlbumType,
        editingAlbum,
        confirmDeleteAlbum,
        handleSelectAlbum,
        handleFilterChange,
        handleCreateAlbum,
        handleEditAlbum,
        handleDeleteAlbum,
        doDeleteAlbum,
        closeAlbumDialog,
        handleAlbumSubmit,
        handleDropMedia,
        handleReorderRoot,
        handleReorderChildren,
        handleMoveAlbum,
    }
}
