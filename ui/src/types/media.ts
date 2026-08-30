/**
 * Media / file upload entity type definitions.
 */

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
