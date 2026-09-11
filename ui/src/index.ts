/**
 * @modufolio/media — the media-library UI for Appkit portfolio sites:
 * album sidebar and grid, inspectors, upload queue, and the composables
 * that drive them. Compiled from source by the consuming app (the same
 * arrangement as @modufolio/panel).
 */
export const VERSION = '0.1.0'

// Components
export { default as AlbumCard } from './Components/Media/AlbumCard.vue'
export { default as AlbumCreateDialog } from './Components/Media/AlbumCreateDialog.vue'
export { default as AlbumInspector } from './Components/Media/AlbumInspector.vue'
export { default as AlbumSidebar } from './Components/Media/AlbumSidebar.vue'
export { default as AlbumTreeItem } from './Components/Media/AlbumTreeItem.vue'
export { default as BulkActionBar } from './Components/Media/BulkActionBar.vue'
export { default as DraggableAlbumList } from './Components/Media/DraggableAlbumList.vue'
export { default as ImageField } from './Components/Media/ImageField.vue'
export { default as InspectorLayout } from './Components/Media/InspectorLayout.vue'
export { default as MediaCard } from './Components/Media/MediaCard.vue'
export { default as MediaGrid } from './Components/Media/MediaGrid.vue'
export { default as MediaInspector } from './Components/Media/MediaInspector.vue'
export { default as MediaInspectorContainer } from './Components/Media/MediaInspectorContainer.vue'
export { default as MediaPagination } from './Components/Media/MediaPagination.vue'
export { default as VideoPlayer } from './Components/Media/VideoPlayer.vue'

// Non-component helpers shipped beside the components
export * from './Components/Media/albumDragState'
export * from './Components/Media/exifUtils'
export * from './Components/Media/imageUtils'

// Composables
export * from './Composables/useAlbumSidebarHandlers'
export * from './Composables/useAlbumTreeExpansion'
export * from './Composables/useAlbums'
export * from './Composables/useBulkFavorite'
export * from './Composables/useFavorites'
export * from './Composables/useFeatured'
export * from './Composables/useGridRatingKeys'
export * from './Composables/useGridState'
export * from './Composables/useLibraryCounts'
export * from './Composables/useMediaDelete'
export * from './Composables/useMediaSelection'
export * from './Composables/useMediaTags'
export * from './Composables/useRating'
export * from './Composables/useTusUpload'
export * from './Composables/useTusUploadQueue'

// Types
export * from './types/media'
