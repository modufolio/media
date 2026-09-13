import { ref } from 'vue'
import type { Ref } from 'vue'

export interface UseRating {
  localRating: Ref<number | null>
  hoverRating: Ref<number | null>
  setRating: (value: number) => Promise<void>
}

/**
 * Composable for managing star rating with toggle behavior.
 * Clicking the same star twice clears the rating.
 *
 * @param initialRating Starting rating value
 * @param onSave Called with the new rating (null when cleared)
 */
export function useRating(
  initialRating: number | null | undefined,
  onSave?: (rating: number | null) => Promise<unknown> | unknown,
): UseRating {
  const localRating = ref<number | null>(initialRating ?? null)
  const hoverRating = ref<number | null>(null)

  const setRating = async (value: number): Promise<void> => {
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
