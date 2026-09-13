import type { DragPayload } from '../../types/media'

/**
 * Narrows the parsed `application/json` body of a drop event to the payload a
 * media card or album card wrote into it. Anything else — a browser-supplied
 * URL, another app's JSON — fails the guard and is ignored by the drop target.
 */
export const isDragPayload = (value: unknown): value is DragPayload =>
  typeof value === 'object'
  && value !== null
  && 'type' in value
  && (value.type === 'media' || value.type === 'album')
