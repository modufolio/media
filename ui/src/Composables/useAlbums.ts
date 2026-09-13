import { computed } from 'vue'
import type { ComputedRef } from 'vue'
import { router, usePage, useHttp } from '@inertiajs/vue3'
import { panelUrl, useToast } from '@modufolio/panel'
import { invalidateLibraryCounts } from './useLibraryCounts'
import type { Album, AlbumId, AlbumNode, AlbumVisibility, MediaId } from '../types/media'

export interface CreateAlbumInput {
    title: string
    description?: string | null
    visibility?: AlbumVisibility
    album_type?: number
    parent_id?: AlbumId | null
}

export interface UpdateAlbumInput {
    title?: string
    description?: string | null
    visibility?: AlbumVisibility
}

interface MembershipOptions {
    onSuccess?: () => void
}

export interface UseAlbums {
    albums: ComputedRef<Album[]>
    selectedAlbumId: ComputedRef<AlbumId | null>
    selectedAlbum: ComputedRef<Album | null>
    albumTree: ComputedRef<AlbumNode[]>
    createAlbum: (album: CreateAlbumInput) => void
    updateAlbum: (id: AlbumId, fields: UpdateAlbumInput) => void
    deleteAlbum: (id: AlbumId) => void
    moveAlbum: (id: AlbumId, parentId: AlbumId | null) => void
    addMediaToAlbum: (albumId: AlbumId, mediaIds: MediaId[], options?: MembershipOptions) => void
    removeMediaFromAlbum: (albumId: AlbumId, mediaId: MediaId) => void
    removeMultipleFromAlbum: (albumId: AlbumId, mediaIds: MediaId[], options?: MembershipOptions) => void
    reorderAlbumMedia: (albumId: AlbumId, mediaIds: MediaId[]) => void
    moveAlbumMedia: (albumId: AlbumId, mediaId: MediaId, newPosition: number) => Promise<unknown>
    reorderAlbumChildren: (setId: AlbumId, albumIds: AlbumId[]) => void
    reorderRootAlbums: (albumIds: AlbumId[]) => void
    setAlbumCover: (albumId: AlbumId, mediaId: MediaId | null) => void
}

/** The page props the library controllers send that this composable reads. */
interface AlbumPageProps {
    albums?: Album[]
    initialAlbumId?: AlbumId | null
}

export function useAlbums(): UseAlbums {
    const toast = useToast()
    const page = usePage()
    const pageProps = computed(() => page.props as AlbumPageProps)

    // Albums come from Inertia page props
    const albums = computed<Album[]>(() => pageProps.value.albums || [])

    // Selected album comes from page props (set by server on /library/albums/{id})
    const selectedAlbumId = computed<AlbumId | null>(() => pageProps.value.initialAlbumId ?? null)

    // ─── Album CRUD (Inertia router visits) ──────────────────────

    // A "Set" is just an album with album_type 1 (a folder of albums, rather
    // than a folder of photos) — keep user-facing copy honest about which
    // one the record actually is.
    const kindOf = (albumType: number | undefined): 'Set' | 'Album' => (albumType === 1 ? 'Set' : 'Album')
    const kindOfId = (id: AlbumId) => kindOf(albums.value.find(a => a.id === id)?.album_type)

    const createAlbum = ({ title, description = null, visibility = 'public', album_type = 0, parent_id = null }: CreateAlbumInput): void => {
        const kind = kindOf(album_type)
        router.post(panelUrl('/albums'), { title, description, visibility, album_type, parent_id }, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Created "${title}"`, `${kind} Created`),
            onError: () => toast.error(`Failed to create ${kind.toLowerCase()}`, 'Error'),
        })
    }

    const updateAlbum = (id: AlbumId, { title, description, visibility }: UpdateAlbumInput): void => {
        const kind = kindOfId(id)
        router.put(panelUrl(`/albums/${id}`), { title, description, visibility }, {
            preserveScroll: true,
            onSuccess: () => toast.success(`${kind} updated`, 'Updated'),
            onError: () => toast.error(`Failed to update ${kind.toLowerCase()}`, 'Error'),
        })
    }

    const deleteAlbum = (id: AlbumId): void => {
        const kind = kindOfId(id)
        router.delete(panelUrl(`/albums/${id}`), {
            preserveScroll: true,
            onSuccess: () => toast.success(`${kind} deleted`, 'Deleted'),
            onError: () => toast.error(`Failed to delete ${kind.toLowerCase()}`, 'Error'),
        })
    }

    // ─── Nested Set / Hierarchy (Inertia router visit) ───────────

    const moveAlbum = (id: AlbumId, parentId: AlbumId | null): void => {
        const kind = kindOfId(id)
        router.put(panelUrl(`/albums/${id}/move`), { parent_id: parentId }, {
            preserveScroll: true,
            onSuccess: () => toast.success(`${kind} moved`, 'Moved'),
            onError: () => toast.error(`Failed to move ${kind.toLowerCase()}`, 'Error'),
        })
    }

    // ─── Album-Media Membership (Inertia router visits) ──────────

    const addMediaToAlbum = (albumId: AlbumId, mediaIds: MediaId[], { onSuccess }: MembershipOptions = {}): void => {
        router.post(panelUrl(`/albums/${albumId}/media`), { media_ids: mediaIds }, {
            preserveScroll: true,
            onSuccess: () => {
                void invalidateLibraryCounts()
                onSuccess?.()
            },
            onError: () => toast.error('Failed to add media', 'Error'),
        })
    }

    const removeMediaFromAlbum = (albumId: AlbumId, mediaId: MediaId): void => {
        router.delete(panelUrl(`/albums/${albumId}/media/${mediaId}`), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Removed from album', 'Removed')
                void invalidateLibraryCounts()
            },
            onError: () => toast.error('Failed to remove media', 'Error'),
        })
    }

    const removeMultipleForm = useHttp<{ media_ids: MediaId[] }>({ media_ids: [] })

    const removeMultipleFromAlbum = (albumId: AlbumId, mediaIds: MediaId[], { onSuccess }: MembershipOptions = {}): void => {
        removeMultipleForm.media_ids = mediaIds
        void removeMultipleForm.delete(panelUrl(`/api/albums/${albumId}/media`), {
            onSuccess: () => {
                toast.success(`Removed ${mediaIds.length} item(s) from album`, 'Removed')
                void invalidateLibraryCounts()
                onSuccess?.()
            },
            onError: () => toast.error('Failed to remove media', 'Error'),
        })
    }

    // ─── Lightweight ops (useHttp — no page visit) ───────────────

    const reorderForm = useHttp<{ media_ids: MediaId[] }>({ media_ids: [] })

    const reorderAlbumMedia = (albumId: AlbumId, mediaIds: MediaId[]): void => {
        reorderForm.media_ids = mediaIds
        void reorderForm.put(panelUrl(`/api/albums/${albumId}/media/reorder`), {
            onError: () => toast.error('Failed to reorder', 'Error'),
        })
    }

    const moveForm = useHttp<{ new_position: number }>({ new_position: 0 })

    const moveAlbumMedia = (albumId: AlbumId, mediaId: MediaId, newPosition: number): Promise<unknown> => {
        moveForm.new_position = newPosition
        return moveForm.put(panelUrl(`/api/albums/${albumId}/media/${mediaId}/move`), {
            onError: () => toast.error('Failed to move photo', 'Error'),
        })
    }

    const reorderChildrenForm = useHttp<{ album_ids: AlbumId[] }>({ album_ids: [] })

    const reorderAlbumChildren = (setId: AlbumId, albumIds: AlbumId[]): void => {
        reorderChildrenForm.album_ids = albumIds
        void reorderChildrenForm.put(panelUrl(`/api/albums/${setId}/children/reorder`), {
            onError: () => toast.error('Failed to reorder albums', 'Error'),
        })
    }

    const reorderRootForm = useHttp<{ album_ids: AlbumId[] }>({ album_ids: [] })

    const reorderRootAlbums = (albumIds: AlbumId[]): void => {
        reorderRootForm.album_ids = albumIds
        void reorderRootForm.put(panelUrl('/api/albums/reorder'), {
            onError: () => toast.error('Failed to reorder albums', 'Error'),
        })
    }

    const coverForm = useHttp<{ media_id: MediaId | null }>({ media_id: null })

    const setAlbumCover = (albumId: AlbumId, mediaId: MediaId | null): void => {
        coverForm.media_id = mediaId
        void coverForm.put(panelUrl(`/api/albums/${albumId}/cover`), {
            onSuccess: () => toast.success('Cover updated', 'Updated'),
            onError: () => toast.error('Failed to set cover', 'Error'),
        })
    }

    // ─── Computed ────────────────────────────────────────────────

    const selectedAlbum = computed<Album | null>(() =>
        albums.value.find(a => a.id === selectedAlbumId.value) || null
    )

    /**
     * Build a tree structure from the flat albums array using nested set left_id/right_id.
     * Returns an array of root nodes, each with a `children` array.
     */
    const albumTree = computed<AlbumNode[]>(() => {
        const flat = albums.value
        if (!flat.length) return []

        const tree: AlbumNode[] = []
        const stack: AlbumNode[] = []

        for (const album of flat) {
            const node: AlbumNode = { ...album, children: [] }

            // Pop items from stack that are not ancestors of current node
            while (stack.length > 0 && (stack[stack.length - 1].right_id ?? 0) < (node.left_id ?? 0)) {
                stack.pop()
            }

            if (stack.length === 0) {
                tree.push(node)
            } else {
                stack[stack.length - 1].children.push(node)
            }

            // Only push sets (which can have children) onto the stack
            if (node.album_type === 1) {
                stack.push(node)
            }
        }

        // Sort each level by position so manual reordering is reflected.
        // findAllAsTree() orders by left_id (required for nested-set traversal),
        // but position is the user-controlled sort field.
        const sortByPosition = (nodes: AlbumNode[]): AlbumNode[] =>
            [...nodes]
                .sort((a, b) => (a.position ?? 0) - (b.position ?? 0) || (a.left_id ?? 0) - (b.left_id ?? 0))
                .map(node => ({ ...node, children: sortByPosition(node.children ?? []) }))

        return sortByPosition(tree)
    })

    return {
        albums,
        selectedAlbumId,
        selectedAlbum,
        albumTree,
        createAlbum,
        updateAlbum,
        deleteAlbum,
        moveAlbum,
        addMediaToAlbum,
        removeMediaFromAlbum,
        removeMultipleFromAlbum,
        reorderAlbumMedia,
        moveAlbumMedia,
        reorderAlbumChildren,
        reorderRootAlbums,
        setAlbumCover,
    }
}
