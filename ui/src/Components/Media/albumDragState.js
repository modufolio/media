import { ref } from 'vue'

/**
 * Shared reactive reference to the album ID currently being drag-reordered.
 * Module-scope is safe: only one HTML5 drag can be active at a time.
 */
export const activeDragAlbumId = ref(null)
