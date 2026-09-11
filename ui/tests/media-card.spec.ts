import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const toggleFavorite = vi.fn()
vi.mock('../src/Composables/useFavorites', () => ({
  useFavorites: () => ({ toggleFavorite, isFavoriteLoading: () => false }),
}))

const niceSize = vi.fn((bytes: number) => `${bytes} bytes (mocked)`)
vi.mock('@modufolio/panel', () => ({ niceSize }))

const { default: MediaCard } = await import('../src/Components/Media/MediaCard.vue')

function baseFile(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    original_filename: 'sunset.jpg',
    file_size: 204800,
    is_image: true,
    is_video: false,
    is_favorite: false,
    thumbnail_url: 'https://cdn.example/thumb.jpg',
    url: 'https://cdn.example/full.jpg',
    width: 1920,
    height: 1080,
    rating: 0,
    blurhash: null,
    ...overrides,
  }
}

describe('MediaCard', () => {
  beforeEach(() => {
    toggleFavorite.mockClear()
    niceSize.mockClear()
  })

  it('renders the filename and file size via niceSize', () => {
    const wrapper = mount(MediaCard, { props: { file: baseFile() } })

    expect(wrapper.text()).toContain('sunset.jpg')
    expect(wrapper.text()).toContain('204800 bytes (mocked)')
    expect(niceSize).toHaveBeenCalledWith(204800)
  })

  it('shows dimensions when width and height are present', () => {
    const wrapper = mount(MediaCard, { props: { file: baseFile() } })

    expect(wrapper.text()).toContain('1920×1080')
  })

  it('emits select on click and view on double-click', async () => {
    const file = baseFile()
    const wrapper = mount(MediaCard, { props: { file } })

    await wrapper.trigger('click')
    expect(wrapper.emitted('select')?.[0][0]).toMatchObject({ file })

    await wrapper.trigger('dblclick')
    expect(wrapper.emitted('view')?.[0]).toEqual([file])
  })

  it('toggles favorite when the heart button is clicked, without triggering select', async () => {
    const file = baseFile()
    const wrapper = mount(MediaCard, { props: { file } })

    await wrapper.find('button[aria-label="Add to favorites"]').trigger('click')

    expect(toggleFavorite).toHaveBeenCalledWith(file)
    expect(wrapper.emitted('select')).toBeUndefined()
  })

  it('hides the favorite button when hideFavorite is set', () => {
    const wrapper = mount(MediaCard, { props: { file: baseFile(), hideFavorite: true } })

    expect(wrapper.find('button[aria-label="Add to favorites"]').exists()).toBe(false)
  })

  it('shows the VIDEO badge for video files', () => {
    const wrapper = mount(MediaCard, { props: { file: baseFile({ is_video: true, is_image: false }) } })

    expect(wrapper.text()).toContain('VIDEO')
  })
})
