import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { post: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { markCaveAsDone } = await import('@/stores/markAsDone')
const { useNotificationStore } = await import('@/stores/notifications')

const cave = { id: 42, name: 'Swildons Hole', system: { id: 7 } }

describe('markCaveAsDone', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.post.mockReset()
  })

  it('creates a private single-participant trip and reports success', async () => {
    apiMock.post.mockResolvedValue({ data: {} })

    const result = await markCaveAsDone({ cave, userId: 9 })

    expect(result).toBe(true)
    expect(apiMock.post).toHaveBeenCalledWith('/api/trips', {
      name: 'Marked as Done',
      entrance_cave_id: 42,
      exit_cave_id: 42,
      participants: [9],
      cave_system_id: 7,
      visibility: 'private',
    })
    const notifications = useNotificationStore()
    expect(notifications.type).toBe('success')
    expect(notifications.message).toBe('Cave marked as done!')
  })

  it('returns false without calling the API when the cave is missing', async () => {
    expect(await markCaveAsDone({ cave: null, userId: 9 })).toBe(false)
    expect(apiMock.post).not.toHaveBeenCalled()
  })

  it('returns false without calling the API when the user is missing', async () => {
    expect(await markCaveAsDone({ cave, userId: null })).toBe(false)
    expect(apiMock.post).not.toHaveBeenCalled()
  })

  it('surfaces the server validation message on failure', async () => {
    apiMock.post.mockRejectedValue({ response: { data: { message: 'Trip already logged' } } })

    const result = await markCaveAsDone({ cave, userId: 9 })

    expect(result).toBe(false)
    const notifications = useNotificationStore()
    expect(notifications.type).toBe('error')
    expect(notifications.message).toBe('Failed to mark cave as done: Trip already logged')
  })

  it('falls back to the error message when the server sent no body', async () => {
    apiMock.post.mockRejectedValue(new Error('Network Error'))

    await markCaveAsDone({ cave, userId: 9 })

    expect(useNotificationStore().message).toBe('Failed to mark cave as done: Network Error')
  })

  it('falls back to "Unknown error" when there is nothing to report', async () => {
    apiMock.post.mockRejectedValue({})

    await markCaveAsDone({ cave, userId: 9 })

    expect(useNotificationStore().message).toBe('Failed to mark cave as done: Unknown error')
  })
})
