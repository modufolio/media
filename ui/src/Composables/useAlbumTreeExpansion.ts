import { useLocalStoragePersistence } from '@modufolio/panel'

interface UseAlbumTreeExpansionOptions {
  albumId: string
  defaultExpanded?: boolean
  prefix?: string
  onError?: (error: Error) => void
}

interface UseAlbumTreeExpansionReturn {
  isExpanded: import('vue').Ref<boolean>
  toggle: () => void
  expand: () => void
  collapse: () => void
  clearPersistence: () => void
}

/**
 * Composable for managing album tree expansion state with localStorage persistence
 */
export function useAlbumTreeExpansion({
  albumId,
  defaultExpanded = false,
  prefix = 'album-tree-expanded',
  onError,
}: UseAlbumTreeExpansionOptions): UseAlbumTreeExpansionReturn {
  const storageKey = `${prefix}-${albumId}`

  const { value: isExpanded, setValue, removeValue } = useLocalStoragePersistence({
    key: storageKey,
    defaultValue: defaultExpanded,
    onError,
  })

  const toggle = () => setValue(!isExpanded.value)
  const expand = () => setValue(true)
  const collapse = () => setValue(false)
  const clearPersistence = () => removeValue()

  return {
    isExpanded,
    toggle,
    expand,
    collapse,
    clearPersistence,
  }
}

/**
 * Utility function to clean up old album expansion keys that no longer exist
 */
export function cleanupOrphanedAlbumExpansionKeys(existingAlbumIds: string[], prefix = 'album-tree-expanded'): void {
  try {
    const keysToRemove: string[] = []

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(`${prefix}-`)) {
        const albumId = key.replace(`${prefix}-`, '')
        if (!existingAlbumIds.includes(albumId)) {
          keysToRemove.push(key)
        }
      }
    }

    keysToRemove.forEach(key => localStorage.removeItem(key))
  } catch (error) {
    console.warn('Failed to cleanup orphaned album expansion keys:', error)
  }
}