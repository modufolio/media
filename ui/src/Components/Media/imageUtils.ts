/**
 * Converts a focus point string (e.g. "50%, 30%") to a CSS-compatible
 * object-position value (e.g. "50% 30%").
 */
export function focusToCss(focus: string | null | undefined): string | null {
  if (!focus) return null
  return focus.replace(/,\s*/g, ' ')
}
