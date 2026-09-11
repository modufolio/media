import { describe, it, expect, vi, beforeEach } from 'vitest'

const post = vi.fn()
const put = vi.fn()
const del = vi.fn()

type Album = {
  id: number
  title: string
  left_id: number
  right_id: number
  album_type: number
  position: number
}
let pageProps: { albums: Album[]; initialAlbumId: number | null }

function httpForm() {
  return {
    media_ids: [],
    delete: vi.fn((_url: string, opts: { onSuccess?: () => void }) => {
      opts.onSuccess?.()
      return Promise.resolve()
    }),
    put: vi.fn((_url: string, opts: { onSuccess?: () => void }) => {
      opts.onSuccess?.()
      return Promise.resolve()
    }),
  }
}
const useHttp = vi.fn(httpForm)

vi.mock('@inertiajs/vue3', () => ({
  router: { post, put, delete: del },
  usePage: () => ({ props: pageProps }),
  useHttp,
}))

const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('@modufolio/panel', () => ({
  panelUrl: (path: string) => path,
  useToast: () => ({ success: toastSuccess, error: toastError }),
}))

const invalidateLibraryCounts = vi.fn()
vi.mock('../src/Composables/useLibraryCounts', () => ({ invalidateLibraryCounts }))

const { useAlbums } = await import('../src/Composables/useAlbums')

function album(id: number, left: number, right: number, opts: { type?: number; position?: number } = {}): Album {
  return {
    id,
    title: `Album ${id}`,
    left_id: left,
    right_id: right,
    album_type: opts.type ?? 0,
    position: opts.position ?? 0,
  }
}

type TreeNode = { id: number; children: TreeNode[] }
const shape = (nodes: Array<{ id: number; children: unknown[] }>): TreeNode[] =>
  nodes.map((n) => ({ id: n.id, children: shape(n.children as Array<{ id: number; children: unknown[] }>) }))

describe('useAlbums', () => {
  beforeEach(() => {
    pageProps = { albums: [], initialAlbumId: null }
    post.mockClear()
    put.mockClear()
    del.mockClear()
    toastSuccess.mockClear()
    toastError.mockClear()
    invalidateLibraryCounts.mockClear()
    useHttp.mockClear()
  })

  describe('library counts invalidation', () => {
    it('invalidates library counts after adding media to an album', () => {
      post.mockImplementation((_url, _data, opts) => opts.onSuccess?.())
      const { addMediaToAlbum } = useAlbums()

      const onSuccess = vi.fn()
      addMediaToAlbum(1, [10, 11], { onSuccess })

      expect(invalidateLibraryCounts).toHaveBeenCalledTimes(1)
      expect(onSuccess).toHaveBeenCalledTimes(1)
    })

    it('invalidates library counts after removing media from an album', () => {
      del.mockImplementation((_url, opts) => opts.onSuccess?.())
      const { removeMediaFromAlbum } = useAlbums()

      removeMediaFromAlbum(1, 10)

      expect(invalidateLibraryCounts).toHaveBeenCalledTimes(1)
      expect(toastSuccess).toHaveBeenCalledWith('Removed from album', 'Removed')
    })

    it('invalidates library counts after a bulk removal, and reports the count removed', () => {
      const { removeMultipleFromAlbum } = useAlbums()

      const onSuccess = vi.fn()
      removeMultipleFromAlbum(1, [10, 11, 12], { onSuccess })

      expect(invalidateLibraryCounts).toHaveBeenCalledTimes(1)
      expect(toastSuccess).toHaveBeenCalledWith('Removed 3 item(s) from album', 'Removed')
      expect(onSuccess).toHaveBeenCalledTimes(1)
    })

    it('does not invalidate library counts when adding media fails', () => {
      post.mockImplementation((_url, _data, opts) => opts.onError?.())
      const { addMediaToAlbum } = useAlbums()

      addMediaToAlbum(1, [10])

      expect(invalidateLibraryCounts).not.toHaveBeenCalled()
      expect(toastError).toHaveBeenCalledWith('Failed to add media', 'Error')
    })
  })

  describe('albumTree', () => {
    it('is empty when there are no albums', () => {
      expect(useAlbums().albumTree.value).toEqual([])
    })

    it('nests albums under the set whose nested-set bounds contain them', () => {
      pageProps.albums = [
        album(1, 1, 8, { type: 1 }), // set: Travel
        album(2, 2, 3), //   album: Japan
        album(3, 4, 5), //   album: Italy
        album(4, 6, 7, { type: 1 }), //   set: empty nested set
        album(5, 9, 10), // album at root, after Travel closed
      ]

      expect(shape(useAlbums().albumTree.value)).toEqual([
        { id: 1, children: [{ id: 2, children: [] }, { id: 3, children: [] }, { id: 4, children: [] }] },
        { id: 5, children: [] },
      ])
    })

    it('sorts every level by position rather than by nested-set order', () => {
      pageProps.albums = [
        album(1, 1, 6, { type: 1, position: 1 }),
        album(2, 2, 3, { position: 2 }), // lower left_id but later position
        album(3, 4, 5, { position: 1 }),
        album(5, 7, 8, { position: 0 }), // root album sorts before the set
      ]

      expect(shape(useAlbums().albumTree.value)).toEqual([
        { id: 5, children: [] },
        { id: 1, children: [{ id: 3, children: [] }, { id: 2, children: [] }] },
      ])
    })

    it('breaks position ties by left_id', () => {
      pageProps.albums = [album(7, 3, 4), album(6, 1, 2)]
        .sort((a, b) => a.left_id - b.left_id)

      expect(shape(useAlbums().albumTree.value)).toEqual([
        { id: 6, children: [] },
        { id: 7, children: [] },
      ])
    })

    it('never gives a plain album children, even if a later node sits inside its bounds', () => {
      // A non-set album is never pushed onto the traversal stack, so a node
      // that would fit inside it lands at the root instead.
      pageProps.albums = [album(1, 1, 4), album(2, 2, 3)]

      expect(shape(useAlbums().albumTree.value)).toEqual([
        { id: 1, children: [] },
        { id: 2, children: [] },
      ])
    })

    it('resolves selectedAlbum from initialAlbumId', () => {
      pageProps.albums = [album(1, 1, 2), album(2, 3, 4)]
      pageProps.initialAlbumId = 2

      expect(useAlbums().selectedAlbum.value?.id).toBe(2)
    })
  })
})
