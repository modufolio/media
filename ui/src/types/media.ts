/**
 * Media library entity types.
 *
 * Records arrive as Inertia page props or API payloads, so the shapes are
 * loose where the server is: optional fields are the ones a listing may omit,
 * and the index signature keeps a component from failing on a field the
 * server added before the type caught up.
 */

/** A media id: uuid on the wire, occasionally a number in older fixtures. */
export type MediaId = string | number

/** An album id: numeric on the server, a string once it has been through a URL. */
export type AlbumId = string | number

export type AlbumVisibility = 'public' | 'unlisted' | 'private'

export type AlbumLayout = 'grid' | 'slider' | 'list'

/** Legacy alias kept for consumers; new code should use {@link MediaFile}. */
export interface UploadedFile {
  id: string
  original_filename: string
  url: string
  thumbnail_url?: string
  is_image: boolean
  alt_text?: string
  file_size: number
  width?: number
  height?: number
}

export interface MediaFile {
  id: MediaId
  original_filename?: string
  url: string
  thumbnail_url?: string | null
  is_image?: boolean
  is_video?: boolean
  is_favorite?: boolean
  is_public?: boolean
  rating?: number | null
  title?: string | null
  alt_text?: string | null
  caption?: string | null
  blurhash?: string | null
  /** Focus point as "x%, y%"; see focusToCss(). */
  focus?: string | null
  mime_type?: string | null
  file_size?: number
  width?: number | null
  height?: number | null
  created_at?: string | null
  /** Raw EXIF as stored by the server; see exifSummary(). */
  metadata?: Record<string, unknown> | null
  [key: string]: unknown
}

/** One of the up-to-three cover slots of an album. */
export interface AlbumCover {
  id: MediaId
  url: string | null
  thumbnail_url: string | null
  focus: string | null
}

export interface Album {
  id: AlbumId
  title: string
  slug?: string | null
  description?: string | null
  subtitle?: string | null
  category?: string | null
  categories?: string | null
  visibility?: AlbumVisibility
  /** 0 = album (holds media), 1 = set (holds albums). */
  album_type: number
  parent_id?: AlbumId | null
  left_id?: number
  right_id?: number
  position?: number
  /** Nested-set depth, sent with flat album lists for indentation. */
  level?: number
  covers?: AlbumCover[]
  preview?: AlbumCover | null
  fullwidth?: boolean
  layout?: AlbumLayout
  layout_options?: Record<string, unknown> | null
  media_count?: number
  created_at?: string | null
  updated_at?: string | null
  children?: AlbumNode[]
  [key: string]: unknown
}

/** An album with its nested-set descendants resolved. */
export interface AlbumNode extends Album {
  children: AlbumNode[]
}

export interface Tag {
  id: number | string
  name: string
  [key: string]: unknown
}

/** A tag as shown in the bulk inspector: how many selected files carry it. */
export interface BulkTag extends Tag {
  count: number
}

/** Insertion-line state for a drag-reorder list. */
export interface DropState {
  dropIndex: number | null
}

/** Payload carried in `application/json` by a dragged media card. */
export interface MediaDragPayload {
  type: 'media'
  mediaId: MediaId
  selectedCount?: number
}

/** Payload carried in `application/json` by a dragged album card. */
export interface AlbumDragPayload {
  type: 'album'
  albumId: AlbumId
}

export type DragPayload = MediaDragPayload | AlbumDragPayload

/** The summary strings the inspector shows for a photo's EXIF block. */
export interface ExifSummary {
  model: string | null
  aperture: string | null
  shutter: string | null
  iso: string | null
  focalLength: string | null
  taken: string | null
}
