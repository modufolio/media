import { useToast } from '@modufolio/panel'
import { panelUrl, apiFetch } from '@modufolio/panel'
import { optimistic, usePendingKeys, writeKey } from '@modufolio/panel'

interface FavoritableFile {
  id: number | string
  is_favorite: boolean
  [key: string]: unknown
}

/**
 * Toggle media favorites with optimistic updates.
 *
 *   const { toggleFavorite, isFavoriteLoading } = useFavorites()
 *   await toggleFavorite(fileRef)  // fileRef.is_favorite is flipped optimistically
 */
export function useFavorites(): {
  toggleFavorite: (file: FavoritableFile) => Promise<void>
  isFavoriteLoading: (fileId: number | string) => boolean
} {
  const toast = useToast()
  // Per-file in-flight tracking, so the star that is saving is the one that
  // spins — and a double-click cannot dispatch the toggle twice.
  const { isPending, run } = usePendingKeys()

  const toggleFavorite = async (file: FavoritableFile): Promise<void> => {
    await run(file.id, async () => {
      const ok = await optimistic(
        () => {
          const previous = file.is_favorite
          file.is_favorite = !previous
          return () => { file.is_favorite = previous }
        },
        () => apiFetch(panelUrl(`/api/media/${file.id}`), {
          method: 'PATCH',
          body: { is_favorite: file.is_favorite },
        }),
        // Orders overlapping toggles of one file: a stale failure must not
        // roll back a change a newer toggle already owns.
        writeKey('media', file.id, 'is_favorite'),
      )

      if (!ok) toast.error('Could not update favorite', 'Error')
    })
  }

  return { toggleFavorite, isFavoriteLoading: isPending }
}
