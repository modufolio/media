/**
 * Converts a focus point string (e.g. "50%, 30%") to a CSS-compatible
 * object-position value (e.g. "50% 30%").
 *
 * @param {string|null|undefined} focus
 * @returns {string|null}
 */
export function focusToCss(focus) {
  if (!focus) return null
  return focus.replace(/,\s*/g, ' ')
}
