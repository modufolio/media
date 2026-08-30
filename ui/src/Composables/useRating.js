import { ref } from 'vue'

/**
 * Composable for managing star rating with toggle behavior.
 * Clicking the same star twice clears the rating.
 *
 * @param {number|null} initialRating - Starting rating value
 * @param {Function} onSave - Callback when rating is changed: (newRating) => Promise
 */
export function useRating(initialRating, onSave) {
  const localRating = ref(initialRating ?? null)
  const hoverRating = ref(null)

  const setRating = async (value) => {
    const newRating = localRating.value === value ? null : value
    localRating.value = newRating
    await onSave?.(newRating)
  }

  return {
    localRating,
    hoverRating,
    setRating,
  }
}
