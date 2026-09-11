import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const visit = vi.fn()
vi.mock('@inertiajs/vue3', () => ({ router: { visit } }))

const niceSize = vi.fn((bytes: number) => `${bytes} bytes (mocked)`)
const date = vi.fn(
  (value: string): { format: () => string } | undefined => ({ format: () => `formatted(${value})` }),
)
const apiFetch = vi.fn()
vi.mock('@modufolio/panel', () => ({
  panelUrl: (path: string) => path,
  apiFetch,
  niceSize,
  date,
}))

const { default: MediaInspector } = await import('../src/Components/Media/MediaInspector.vue')

function baseMedia(overrides: Record<string, unknown> = {}) {
  return {
    id: 42,
    original_filename: 'sunset.jpg',
    file_size: 204800,
    is_image: true,
    thumbnail_url: 'https://cdn.example/thumb.jpg',
    url: 'https://cdn.example/full.jpg',
    created_at: '2026-01-15T10:00:00Z',
    is_public: true,
    is_favorite: false,
    rating: 0,
    ...overrides,
  }
}

describe('MediaInspector', () => {
  beforeEach(() => {
    visit.mockClear()
    niceSize.mockClear()
    date.mockClear()
    apiFetch.mockClear()
  })

  it('renders file size via niceSize and the created date via date().format()', () => {
    const wrapper = mount(MediaInspector, { props: { media: baseMedia() } })

    expect(wrapper.text()).toContain('204800 bytes (mocked)')
    expect(niceSize).toHaveBeenCalledWith(204800)

    expect(wrapper.text()).toContain('formatted(2026-01-15T10:00:00Z)')
    expect(date).toHaveBeenCalledWith('2026-01-15T10:00:00Z')
  })

  it('falls back to an em dash when date() returns nothing', () => {
    date.mockReturnValueOnce(undefined)
    const wrapper = mount(MediaInspector, { props: { media: baseMedia() } })

    expect(wrapper.text()).toContain('—')
  })

  it('renders nothing media-specific when no media is selected', () => {
    const wrapper = mount(MediaInspector)

    expect(wrapper.text()).not.toContain('File size')
  })

  it('navigates to the media view on double-clicking the thumbnail, scoped to the album', () => {
    const wrapper = mount(MediaInspector, { props: { media: baseMedia(), albumId: 7 } })

    wrapper.find('.cursor-pointer').trigger('dblclick')

    expect(visit).toHaveBeenCalledWith('/library/albums/7/view/42')
  })
})
