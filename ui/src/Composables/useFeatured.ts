import { useToast } from '@modufolio/panel'
import { panelUrl, apiFetch } from '@modufolio/panel'
import { optimistic, usePendingKeys, writeKey } from '@modufolio/panel'

interface FeaturableFile {
  id: number | string
  is_featured: boolean
  [key: string]: unknown
}

/**
 * Toggle "Feature on homepage" with optimistic updates.
 *
 * Mirrors useFavorites: the server appends newly featured media at the end of
 * the curated order (Koken semantics) and clears the slot on unfeature.
 *
 *   const { toggleFeatured, isFeaturedLoading } = useFeatured()
 *   await toggleFeatured(fileRef)  // fileRef.is_featured flips optimistically
 */
export function useFeatured(): {
  toggleFeatured: (file: FeaturableFile) => Promise<void>
  isFeaturedLoading: (fileId: number | string) => boolean
} {
  const toast = useToast()
  const { isPending, run } = usePendingKeys()

  const toggleFeatured = async (file: FeaturableFile): Promise<void> => {
    await run(file.id, async () => {
      const ok = await optimistic(
        () => {
          const previous = file.is_featured
          file.is_featured = !previous
          return () => { file.is_featured = previous }
        },
        () => apiFetch(panelUrl(`/api/media/${file.id}`), {
          method: 'PATCH',
          body: { is_featured: file.is_featured },
        }),
        writeKey('media', file.id, 'is_featured'),
      )

      if (!ok) toast.error('Could not update the homepage selection', 'Error')
    })
  }

  return {
    toggleFeatured,
    isFeaturedLoading: isPending,
  }
}
