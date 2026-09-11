import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'

const instances: FakeUpload[] = []

class FakeUpload {
  url: string | null = 'https://tus.example/uploads/abc123'
  constructor(public file: File, public options: Record<string, unknown>) {
    instances.push(this)
  }
  findPreviousUploads() {
    return Promise.resolve([])
  }
  resumeFromPreviousUpload() {}
  start() {}
  abort() {}
}

vi.mock('tus-js-client', () => ({ Upload: FakeUpload }))

const niceSize = vi.fn((bytes: number) => `${bytes} bytes (mocked)`)
vi.mock('@modufolio/panel', () => ({
  getCsrfToken: () => 'test-csrf-token',
  niceSize,
}))

const { useTusUploadQueue } = await import('../src/Composables/useTusUploadQueue')

type TusCallbacks = {
  onShouldRetry: (err: unknown) => boolean
  onError: (err: unknown) => void
  onProgress: (bytesUploaded: number, bytesTotal: number) => void
  onSuccess: () => Promise<void>
}

/** The callbacks the queue handed to the (fake) tus.Upload it last created. */
const callbacks = () => instances[instances.length - 1].options as unknown as TusCallbacks

function file(name: string, type: string) {
  return new File(['x'], name, { type })
}

function tusError(status: number, body = '') {
  return { originalResponse: { getStatus: () => status, getBody: () => body } }
}

describe('useTusUploadQueue', () => {
  beforeEach(() => {
    niceSize.mockClear()
    instances.length = 0
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('formatBytes delegates to niceSize', () => {
    const { formatBytes } = useTusUploadQueue({ endpoint: 'https://tus.example' })

    expect(formatBytes(2048)).toBe('2048 bytes (mocked)')
    expect(niceSize).toHaveBeenCalledWith(2048)
  })

  describe('queueing files', () => {
    it('accepts an allowed file and starts uploading it', () => {
      const { uploads, addFilesToUpload } = useTusUploadQueue({ endpoint: 'https://tus.example' })

      const result = addFilesToUpload([file('photo.jpg', 'image/jpeg')])

      expect(result).toEqual({ accepted: 1, rejected: 0 })
      expect(uploads.value).toHaveLength(1)
      expect(uploads.value[0].status).toBe('uploading')
      expect(uploads.value[0].tusUpload).toBeInstanceOf(FakeUpload)
    })

    it('rejects a blocked MIME type without starting an upload', () => {
      const { uploads, addFilesToUpload } = useTusUploadQueue({ endpoint: 'https://tus.example' })
      const onRejected = vi.fn()

      const result = addFilesToUpload([file('evil.svg', 'image/svg+xml')], { onRejected })

      expect(result).toEqual({ accepted: 0, rejected: 1 })
      expect(uploads.value).toHaveLength(0)
      expect(onRejected).toHaveBeenCalledWith([expect.objectContaining({ name: 'evil.svg' })])
    })

    it('rejects a folder entry (no MIME type) alongside accepted files', () => {
      const { uploads, addFilesToUpload } = useTusUploadQueue({ endpoint: 'https://tus.example' })

      const result = addFilesToUpload([file('photo.jpg', 'image/jpeg'), file('a-folder', '')])

      expect(result).toEqual({ accepted: 1, rejected: 1 })
      expect(uploads.value).toHaveLength(1)
    })

    it('cancelUpload removes the item and aborts its tus upload', () => {
      const { uploads, addFilesToUpload, cancelUpload } = useTusUploadQueue({ endpoint: 'https://tus.example' })
      addFilesToUpload([file('photo.jpg', 'image/jpeg')])
      const item = uploads.value[0]
      if (!item.tusUpload) throw new Error('expected tusUpload to be set')
      const abortSpy = vi.spyOn(item.tusUpload, 'abort')

      cancelUpload(item)

      expect(abortSpy).toHaveBeenCalled()
      expect(uploads.value).toHaveLength(0)
    })
  })

  describe('onShouldRetry', () => {
    const shouldRetry = (status: number) => {
      useTusUploadQueue({ endpoint: 'https://tus.example' }).addFilesToUpload([file('p.jpg', 'image/jpeg')])
      return callbacks().onShouldRetry(tusError(status))
    }

    it('never retries a conflict or an unprocessable upload', () => {
      expect(shouldRetry(409)).toBe(false)
      expect(shouldRetry(422)).toBe(false)
    })

    it('never retries any other client error', () => {
      expect(shouldRetry(403)).toBe(false)
      expect(shouldRetry(404)).toBe(false)
      expect(shouldRetry(413)).toBe(false)
    })

    it('retries server errors and network failures', () => {
      expect(shouldRetry(500)).toBe(true)
      expect(shouldRetry(503)).toBe(true)
      expect(shouldRetry(0)).toBe(true)
    })

    it('does not retry anything outside those ranges', () => {
      expect(shouldRetry(302)).toBe(false)
    })
  })

  describe('onError', () => {
    const fail = (err: unknown, onComplete?: (f: unknown) => void) => {
      const queue = useTusUploadQueue({ endpoint: 'https://tus.example' })
      queue.addFilesToUpload([file('p.jpg', 'image/jpeg')], { onComplete })
      callbacks().onError(err)
      return queue.uploads.value[0]
    }

    it('marks the upload as errored', () => {
      const item = fail(tusError(500))

      expect(item.status).toBe('error')
    })

    it('prefers the message from a JSON response body', () => {
      const item = fail(tusError(500, JSON.stringify({ message: 'Disk is full' })))

      expect(item.error).toBe('Disk is full')
    })

    it('falls back to a status-specific message when the body is malformed', () => {
      const item = fail(tusError(413, '<html>oops'))

      expect(item.error).toBe('File is too large to upload')
    })

    it.each([
      [413, 'File is too large to upload'],
      [409, 'File already exists and cannot be resumed'],
      [422, 'File already exists and cannot be resumed'],
      [403, "You don't have permission to upload files"],
      [0, 'Network error — check your connection'],
      [500, 'Server error — please try again'],
      [503, 'Server error — please try again'],
      [418, 'Upload failed'],
    ])('maps status %i to "%s"', (status, message) => {
      expect(fail(tusError(status)).error).toBe(message)
    })

    it('signals completion with null so batch trackers can settle', () => {
      const onComplete = vi.fn()

      fail(tusError(500), onComplete)

      expect(onComplete).toHaveBeenCalledWith(null)
    })
  })

  describe('onProgress', () => {
    it('updates the item progress as a percentage, capped at 100', () => {
      const { uploads, addFilesToUpload } = useTusUploadQueue({ endpoint: 'https://tus.example' })
      addFilesToUpload([file('p.jpg', 'image/jpeg')])

      callbacks().onProgress(50, 200)
      expect(uploads.value[0].progress).toBe(25)

      callbacks().onProgress(300, 200)
      expect(uploads.value[0].progress).toBe(100)
    })
  })

  describe('onSuccess', () => {
    it('marks the item completed and reports the tus filename from the upload URL', async () => {
      const onUploaded = vi.fn()
      const onSettled = vi.fn()
      const queue = useTusUploadQueue({ endpoint: 'https://tus.example', onUploaded, onSettled })
      queue.addFilesToUpload([file('p.jpg', 'image/jpeg')])

      await callbacks().onSuccess()

      expect(queue.uploads.value[0]).toMatchObject({ status: 'completed', progress: 100 })
      expect(onUploaded).toHaveBeenCalledWith('abc123', expect.objectContaining({ id: 1 }))
      expect(onSettled).toHaveBeenCalledTimes(1)
    })

    it('resolves the per-file onComplete with { filename } by default', async () => {
      const onComplete = vi.fn()
      useTusUploadQueue({ endpoint: 'https://tus.example' }).addFilesToUpload([file('p.jpg', 'image/jpeg')], { onComplete })

      await callbacks().onSuccess()

      expect(onComplete).toHaveBeenCalledWith({ filename: 'abc123' })
    })

    it('lets resolveUploadedFile replace what onComplete receives', async () => {
      const onComplete = vi.fn()
      const queue = useTusUploadQueue({
        endpoint: 'https://tus.example',
        resolveUploadedFile: async (name) => ({ id: 99, name }),
      })
      queue.addFilesToUpload([file('p.jpg', 'image/jpeg')], { onComplete })

      await callbacks().onSuccess()

      expect(onComplete).toHaveBeenCalledWith({ id: 99, name: 'abc123' })
    })

    it('drops a completed item from the queue after three seconds', async () => {
      vi.useFakeTimers()
      const { uploads, addFilesToUpload } = useTusUploadQueue({ endpoint: 'https://tus.example' })
      addFilesToUpload([file('p.jpg', 'image/jpeg')])

      await callbacks().onSuccess()
      await nextTick()
      expect(uploads.value).toHaveLength(1)

      vi.advanceTimersByTime(3000)
      expect(uploads.value).toHaveLength(0)
    })
  })
})
