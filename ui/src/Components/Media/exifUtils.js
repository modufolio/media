/**
 * Parses an EXIF numeric value. EXIF stores most numbers as rationals
 * ("141/1", "1/800"); parseFloat stops at the slash, so "1/800" would read as
 * 1 — divide the parts out instead.
 *
 * @param {string|number|null|undefined} value
 * @returns {number|null}
 */
export function exifNumber(value) {
  if (value === null || value === undefined) return null
  const [num, den] = String(value).split('/')
  const n = parseFloat(num)
  if (Number.isNaN(n)) return null
  if (den === undefined) return n
  const d = parseFloat(den)
  return Number.isNaN(d) || d === 0 ? null : n / d
}

/**
 * Distils a raw EXIF metadata object into the handful of display strings the
 * inspector shows. Returns null when there is nothing worth showing.
 *
 * @param {Record<string, unknown>|null|undefined} raw
 * @returns {{ model: string|null, aperture: string|null, shutter: string|null,
 *             iso: string|null, focalLength: string|null, taken: string|null }|null}
 */
export function exifSummary(raw) {
  if (!raw || typeof raw !== 'object') return null

  const fnum = exifNumber(raw.FNumber ?? raw.ApertureValue)
  const aperture = fnum ? `f/${fnum.toFixed(1)}` : null

  const exp = exifNumber(raw.ExposureTime)
  let shutter = null
  if (exp) {
    shutter = exp < 1 ? `1/${Math.round(1 / exp)}s` : `${exp}s`
  }

  const iso = raw.ISOSpeedRatings ?? raw.ISO ?? null
  const flValue = exifNumber(raw.FocalLength)
  const fl = flValue ? `${flValue.toFixed(0)} mm` : null
  const model = raw.Model ?? raw.CameraModel ?? null

  let taken = null
  if (raw.dateTaken) {
    const d = new Date(raw.dateTaken)
    if (!Number.isNaN(d.getTime())) {
      taken = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    }
  }

  if (!aperture && !shutter && !iso && !fl && !model && !taken) return null
  return { model, aperture, shutter, iso: iso ? String(iso) : null, focalLength: fl, taken }
}
