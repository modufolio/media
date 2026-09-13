import { ref } from 'vue'
import type { Ref } from 'vue'
import type { AlbumId } from '../../types/media'

/**
 * Shared reactive reference to the album ID currently being drag-reordered.
 * Module-scope is safe: only one HTML5 drag can be active at a time.
 */
export const activeDragAlbumId: Ref<AlbumId | null> = ref(null)
