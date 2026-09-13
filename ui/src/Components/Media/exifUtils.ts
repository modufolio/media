import type { ExifSummary } from '../../types/media'

/**
 * Parses an EXIF numeric value. EXIF stores most numbers as rationals
 * ("141/1", "1/800"); parseFloat stops at the slash, so "1/800" would read as
 * 1 — divide the parts out instead.
 */
export function exifNumber(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined) return null
  const [num, den] = String(value).split('/')
  const n = parseFloat(num)
  if (Number.isNaN(n)) return null
  if (den === undefined) return n
  const d = parseFloat(den)
  return Number.isNaN(d) || d === 0 ? null : n / d
}

/** EXIF values are untyped on the wire; only strings and numbers are readable. */
const scalar = (value: unknown): string | number | null =>
  typeof value === 'string' || typeof value === 'number' ? value : null

/**
 * Distils a raw EXIF metadata object into the handful of display strings the
 * inspector shows. Returns null when there is nothing worth showing.
 */
export function exifSummary(raw: Record<string, unknown> | null | undefined): ExifSummary | null {
  if (!raw || typeof raw !== 'object') return null

  const fnum = exifNumber(scalar(raw.FNumber ?? raw.ApertureValue))
  const aperture = fnum ? `f/${fnum.toFixed(1)}` : null

  const exp = exifNumber(scalar(raw.ExposureTime))
  let shutter: string | null = null
  if (exp) {
    shutter = exp < 1 ? `1/${Math.round(1 / exp)}s` : `${exp}s`
  }

  const iso = scalar(raw.ISOSpeedRatings ?? raw.ISO)
  const flValue = exifNumber(scalar(raw.FocalLength))
  const fl = flValue ? `${flValue.toFixed(0)} mm` : null
  const model = scalar(raw.Model ?? raw.CameraModel)

  let taken: string | null = null
  const dateTaken = scalar(raw.dateTaken)
  if (dateTaken) {
    const d = new Date(dateTaken)
    if (!Number.isNaN(d.getTime())) {
      taken = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    }
  }

  if (!aperture && !shutter && !iso && !fl && !model && !taken) return null
  return {
    model: model === null ? null : String(model),
    aperture,
    shutter,
    iso: iso ? String(iso) : null,
    focalLength: fl,
    taken,
  }
}
