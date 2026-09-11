import { describe, it, expect } from 'vitest'
import { exifNumber, exifSummary } from '../src/Components/Media/exifUtils'

describe('exifNumber', () => {
  it('returns null for null and undefined', () => {
    expect(exifNumber(null)).toBeNull()
    expect(exifNumber(undefined)).toBeNull()
  })

  it('divides out EXIF rationals instead of stopping at the slash', () => {
    expect(exifNumber('141/1')).toBe(141)
    expect(exifNumber('1/800')).toBe(0.00125)
    expect(exifNumber('28/10')).toBe(2.8)
  })

  it('passes plain numbers and numeric strings through', () => {
    expect(exifNumber(2.8)).toBe(2.8)
    expect(exifNumber('5.6')).toBe(5.6)
  })

  it('returns null for non-numeric input', () => {
    expect(exifNumber('abc')).toBeNull()
    expect(exifNumber('1/x')).toBeNull()
  })

  it('returns null for a zero denominator rather than Infinity', () => {
    expect(exifNumber('1/0')).toBeNull()
  })
})

describe('exifSummary', () => {
  it('returns null when there is no metadata or nothing worth showing', () => {
    expect(exifSummary(null)).toBeNull()
    expect(exifSummary(undefined)).toBeNull()
    expect(exifSummary('not an object' as never)).toBeNull()
    expect(exifSummary({})).toBeNull()
    expect(exifSummary({ Unrelated: 'field' })).toBeNull()
  })

  it('formats aperture from FNumber, falling back to ApertureValue', () => {
    expect(exifSummary({ FNumber: '28/10' })?.aperture).toBe('f/2.8')
    expect(exifSummary({ ApertureValue: '4' })?.aperture).toBe('f/4.0')
    expect(exifSummary({ FNumber: '28/10', ApertureValue: '99' })?.aperture).toBe('f/2.8')
  })

  it('formats shutter speed as a fraction below one second and as seconds above', () => {
    expect(exifSummary({ ExposureTime: '1/800' })?.shutter).toBe('1/800s')
    expect(exifSummary({ ExposureTime: '1/3' })?.shutter).toBe('1/3s')
    expect(exifSummary({ ExposureTime: '2' })?.shutter).toBe('2s')
  })

  it('reads ISO from ISOSpeedRatings, falling back to ISO, always as a string', () => {
    expect(exifSummary({ ISOSpeedRatings: 400 })?.iso).toBe('400')
    expect(exifSummary({ ISO: '1600' })?.iso).toBe('1600')
  })

  it('formats focal length in whole millimetres', () => {
    expect(exifSummary({ FocalLength: '85/1' })?.focalLength).toBe('85 mm')
    expect(exifSummary({ FocalLength: '35' })?.focalLength).toBe('35 mm')
  })

  it('reads the camera model from Model, falling back to CameraModel', () => {
    expect(exifSummary({ Model: 'X-T5' })?.model).toBe('X-T5')
    expect(exifSummary({ CameraModel: 'A7 IV' })?.model).toBe('A7 IV')
  })

  it('formats a valid dateTaken and ignores an invalid one', () => {
    const iso = '2024-05-03T12:00:00Z'
    const expected = new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })

    expect(exifSummary({ dateTaken: iso })?.taken).toBe(expected)
    expect(exifSummary({ dateTaken: 'not a date' })).toBeNull()
  })

  it('leaves absent fields null while still returning the present ones', () => {
    expect(exifSummary({ Model: 'X-T5' })).toEqual({
      model: 'X-T5',
      aperture: null,
      shutter: null,
      iso: null,
      focalLength: null,
      taken: null,
    })
  })
})
