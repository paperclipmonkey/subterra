import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const STORAGE_KEY = 'pip_conversation_v1'
const HISTORY_KEY = 'pip_conversation_history_v1'
const MODE_KEY = 'pip_mode_v1'

/**
 * The store reads localStorage at module scope, so any test that cares about
 * restored state must seed storage first and then re-import the module.
 */
async function freshStore() {
  vi.resetModules()
  setActivePinia(createPinia())
  const { useAssistantStore } = await import('@/stores/assistant')
  return useAssistantStore()
}

/** Build a fetch Response whose body streams the given SSE lines. */
function sseResponse(lines, { ok = true, status = 200 } = {}) {
  const encoder = new TextEncoder()
  return {
    ok,
    status,
    body: {
      getReader() {
        let i = 0
        return {
          read: async () => (i < lines.length
            ? { done: false, value: encoder.encode(lines[i++]) }
            : { done: true, value: undefined }),
        }
      },
    },
  }
}

const sse = (type, data) => `data: ${JSON.stringify({ type, data })}\n`

describe('Assistant Store', () => {
  let store

  beforeEach(async () => {
    localStorage.clear()
    global.fetch = vi.fn()
    store = await freshStore()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('persisted state on load', () => {
    it('starts empty with no stored conversation', () => {
      expect(store.messages).toEqual([])
      expect(store.savedConversations).toEqual([])
      expect(store.mode).toBe('default')
      expect(store.hasMessages).toBe(false)
    })

    it('restores persisted messages, normalising their shape', async () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify([
        { role: 'user', content: 'Hi' },
        { role: 'assistant', content: 'Hello', suggestions: ['More?'] },
      ]))

      store = await freshStore()

      expect(store.messages).toHaveLength(2)
      expect(store.messages[1]).toMatchObject({
        role: 'assistant',
        content: 'Hello',
        suggestions: ['More?'],
        cards: [],
        huts: null,
      })
      expect(store.hasMessages).toBe(true)
    })

    it('drops pending and empty messages when restoring', async () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify([
        { role: 'user', content: 'Hi' },
        { role: 'assistant', content: '', pending: true },
        { role: 'assistant', content: '' },
      ]))

      store = await freshStore()

      expect(store.messages).toHaveLength(1)
    })

    it('ignores corrupted persisted state', async () => {
      localStorage.setItem(STORAGE_KEY, '{not json')
      localStorage.setItem(HISTORY_KEY, '{not json')

      store = await freshStore()

      expect(store.messages).toEqual([])
      expect(store.savedConversations).toEqual([])
    })

    it('ignores a persisted conversation that is not an array', async () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ role: 'user' }))
      localStorage.setItem(HISTORY_KEY, JSON.stringify({ id: 1 }))

      store = await freshStore()

      expect(store.messages).toEqual([])
      expect(store.savedConversations).toEqual([])
    })

    it('restores the data-steward mode', async () => {
      localStorage.setItem(MODE_KEY, 'data')
      store = await freshStore()
      expect(store.mode).toBe('data')
    })

    it('treats an unrecognised stored mode as default', async () => {
      localStorage.setItem(MODE_KEY, 'nonsense')
      store = await freshStore()
      expect(store.mode).toBe('default')
    })

    it('migrates the legacy Vern storage keys once', async () => {
      localStorage.setItem('vern_conversation_v1', JSON.stringify([{ role: 'user', content: 'Legacy' }]))
      localStorage.setItem('vern_mode_v1', 'data')

      store = await freshStore()

      expect(store.messages[0].content).toBe('Legacy')
      expect(store.mode).toBe('data')
      expect(localStorage.getItem('vern_conversation_v1')).toBeNull()
      expect(localStorage.getItem('vern_mode_v1')).toBeNull()
    })

    it('does not let a legacy key clobber an existing Pip conversation', async () => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify([{ role: 'user', content: 'Current' }]))
      localStorage.setItem('vern_conversation_v1', JSON.stringify([{ role: 'user', content: 'Legacy' }]))

      store = await freshStore()

      expect(store.messages[0].content).toBe('Current')
      expect(localStorage.getItem('vern_conversation_v1')).toBeNull()
    })
  })

  describe('_handleEvent', () => {
    beforeEach(() => {
      store.messages = [{ role: 'assistant', content: '', pending: true }]
    })

    const pending = () => store.messages.find(m => m.pending) ?? store.messages.at(-1)

    it('appends streamed content chunks in order', () => {
      store._handleEvent({ type: 'content_chunk', data: { text: 'Swildons ' } })
      store._handleEvent({ type: 'content_chunk', data: { text: 'Hole' } })

      expect(pending().content).toBe('Swildons Hole')
      expect(pending().streaming).toBe(true)
    })

    it('tolerates a content chunk with no text', () => {
      store._handleEvent({ type: 'content_chunk', data: {} })
      expect(pending().content).toBe('')
    })

    it('finalises the message on a content event', () => {
      store._handleEvent({ type: 'content', data: { text: 'Full reply' } })

      expect(store.messages[0].content).toBe('Full reply')
      expect(store.messages[0].pending).toBe(false)
      expect(store.messages[0].streaming).toBe(false)
    })

    it('does not overwrite already-streamed content with the final content event', () => {
      store._handleEvent({ type: 'content_chunk', data: { text: 'Streamed answer' } })
      store._handleEvent({ type: 'content', data: { text: 'Let me look that up' } })

      expect(store.messages[0].content).toBe('Streamed answer')
      expect(store.messages[0].pending).toBe(false)
    })

    it('tracks running and finished tool calls', () => {
      store._handleEvent({ type: 'tool_call', data: { name: 'search_caves', status: 'running' } })
      store._handleEvent({ type: 'tool_call', data: { name: 'get_weather_forecast', status: 'running' } })

      expect(store.activeToolCalls).toEqual(['search_caves', 'get_weather_forecast'])
      expect(pending().streaming).toBe(false)

      store._handleEvent({ type: 'tool_call', data: { name: 'search_caves', status: 'done' } })

      expect(store.activeToolCalls).toEqual(['get_weather_forecast'])
    })

    it('does not list the same running tool twice', () => {
      store._handleEvent({ type: 'tool_call', data: { name: 'search_caves', status: 'running' } })
      store._handleEvent({ type: 'tool_call', data: { name: 'search_caves', status: 'running' } })

      expect(store.activeToolCalls).toEqual(['search_caves'])
    })

    it('keeps content already written before a tool call', () => {
      store._handleEvent({ type: 'content_chunk', data: { text: 'Let me check…' } })
      store._handleEvent({ type: 'tool_call', data: { name: 'search_caves', status: 'running' } })

      expect(pending().content).toBe('Let me check…')
    })

    it.each([
      ['cave_cards', 'cards', [{ id: 1 }], [{ id: 2 }]],
      ['trip_report_cards', 'reports', [{ id: 1 }], [{ id: 2 }]],
      ['collection_cards', 'collections', [{ id: 1 }], [{ id: 2 }]],
      ['proposals_created', 'proposals', [{ id: 1 }], [{ id: 2 }]],
      ['trips_created', 'created_trips', [{ id: 1 }], [{ id: 2 }]],
      ['collections_changed', 'collections_changed', [{ id: 1 }], [{ id: 2 }]],
    ])('accumulates %s onto the pending message', (type, field, first, second) => {
      store._handleEvent({ type, data: first })
      store._handleEvent({ type, data: second })

      expect(pending()[field]).toEqual([...first, ...second])
    })

    it.each([
      ['hut_cards', 'huts', { huts: [{ id: 1 }] }],
      ['weather_charts', 'weather_charts', { rain: [] }],
      ['medal_progress', 'medal_progress', { medals: [] }],
    ])('replaces %s on the pending message', (type, field, data) => {
      store._handleEvent({ type, data })
      expect(pending()[field]).toEqual(data)
    })

    it('records elapsed thinking time', () => {
      store._handleEvent({ type: 'thinking_elapsed', data: { ms: 4200 } })
      expect(pending().elapsedMs).toBe(4200)

      store._handleEvent({ type: 'thinking_elapsed', data: {} })
      expect(pending().elapsedMs).toBeNull()
    })

    it('ignores a thinking event', () => {
      expect(() => store._handleEvent({ type: 'thinking' })).not.toThrow()
    })

    it('ignores an unknown event type', () => {
      expect(() => store._handleEvent({ type: 'not_a_real_event', data: {} })).not.toThrow()
    })

    it('attaches suggestions to the last finalised assistant message', () => {
      store._handleEvent({ type: 'content', data: { text: 'Answer' } })
      store._handleEvent({ type: 'suggestions', data: ['Tell me more'] })

      expect(store.messages[0].suggestions).toEqual(['Tell me more'])
    })

    it('attaches suggestions to the pending message when nothing has finalised', () => {
      store._handleEvent({ type: 'suggestions', data: ['Tell me more'] })

      expect(pending().suggestions).toEqual(['Tell me more'])
    })

    it('attaches usage to the pending message', () => {
      store._handleEvent({ type: 'usage', data: { total_tokens: 120 } })
      expect(pending().usage).toEqual({ total_tokens: 120 })
    })

    it('falls back to the last assistant message for usage', () => {
      store.messages = [{ role: 'assistant', content: 'Done' }]
      store._handleEvent({ type: 'usage', data: { total_tokens: 5 } })

      expect(store.messages[0].usage).toEqual({ total_tokens: 5 })
    })

    it('finalises everything on a done event', () => {
      store.isLoading = true
      store.activeToolCalls = ['search_caves']

      store._handleEvent({ type: 'done' })

      expect(store.messages[0].pending).toBe(false)
      expect(store.isLoading).toBe(false)
      expect(store.activeToolCalls).toEqual([])
    })

    it('drops the pending bubble and records the message on an error event', () => {
      store.isLoading = true
      store.activeToolCalls = ['search_caves']

      store._handleEvent({ type: 'error', data: { message: 'Rate limited' } })

      expect(store.messages).toEqual([])
      expect(store.error).toBe('Rate limited')
      expect(store.isLoading).toBe(false)
      expect(store.activeToolCalls).toEqual([])
    })

    it('uses a default message for an error event with no detail', () => {
      store._handleEvent({ type: 'error', data: {} })
      expect(store.error).toBe('An error occurred.')
    })

    it('ignores content events when nothing is pending', () => {
      store.messages = []
      expect(() => store._handleEvent({ type: 'content', data: { text: 'x' } })).not.toThrow()
      expect(store.messages).toEqual([])
    })
  })

  describe('sendMessage', () => {
    it('streams a reply, finalises it and persists the conversation', async () => {
      global.fetch.mockResolvedValue(sseResponse([
        sse('content_chunk', { text: 'Swildons ' }),
        sse('content_chunk', { text: 'is great.' }),
        sse('suggestions', ['Which entrance?']),
        sse('done', {}),
      ]))

      await store.sendMessage('Tell me about Swildons')

      expect(store.messages).toHaveLength(2)
      expect(store.messages[0]).toMatchObject({ role: 'user', content: 'Tell me about Swildons' })
      expect(store.messages[1].content).toBe('Swildons is great.')
      expect(store.messages[1].pending).toBe(false)
      expect(store.isLoading).toBe(false)

      const [url, options] = global.fetch.mock.calls[0]
      expect(url).toBe('/api/assistant/chat')
      expect(JSON.parse(options.body)).toEqual({
        messages: [{ role: 'user', content: 'Tell me about Swildons' }],
        mode: 'default',
      })

      const persisted = JSON.parse(localStorage.getItem(STORAGE_KEY))
      expect(persisted).toHaveLength(2)
      expect(persisted[1].content).toBe('Swildons is great.')
    })

    it('reassembles SSE events split across chunk boundaries', async () => {
      const full = sse('content', { text: 'Reassembled' })
      global.fetch.mockResolvedValue(sseResponse([full.slice(0, 12), full.slice(12)]))

      await store.sendMessage('Hi')

      expect(store.messages[1].content).toBe('Reassembled')
    })

    it('ignores malformed SSE lines', async () => {
      global.fetch.mockResolvedValue(sseResponse([
        'data: {broken json\n',
        'ignored line without prefix\n',
        sse('content', { text: 'Fine' }),
      ]))

      await store.sendMessage('Hi')

      expect(store.messages[1].content).toBe('Fine')
    })

    it('sends the mode with the request', async () => {
      store.mode = 'data'
      global.fetch.mockResolvedValue(sseResponse([sse('done', {})]))

      await store.sendMessage('Scan for issues')

      expect(JSON.parse(global.fetch.mock.calls[0][1].body).mode).toBe('data')
    })

    it('ignores blank input', async () => {
      await store.sendMessage('   ')

      expect(global.fetch).not.toHaveBeenCalled()
      expect(store.messages).toEqual([])
    })

    it('ignores a send while another request is in flight', async () => {
      store.isLoading = true

      await store.sendMessage('Hi')

      expect(global.fetch).not.toHaveBeenCalled()
    })

    it('reports a friendly message on a 429', async () => {
      global.fetch.mockResolvedValue({ ok: false, status: 429, json: async () => ({}) })

      await store.sendMessage('Hi')

      expect(store.error).toBe('Daily request limit reached. Please try again tomorrow.')
      expect(store.messages).toHaveLength(1) // pending bubble removed, user message kept
      expect(store.isLoading).toBe(false)
    })

    it('surfaces the server message on other HTTP errors', async () => {
      global.fetch.mockResolvedValue({ ok: false, status: 400, json: async () => ({ message: 'Bad prompt' }) })

      await store.sendMessage('Hi')

      expect(store.error).toBe('Bad prompt')
    })

    it('falls back to a status message when the error body is unreadable', async () => {
      global.fetch.mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => { throw new Error('not json') },
      })

      await store.sendMessage('Hi')

      expect(store.error).toBe('Error 500: unable to reach assistant.')
    })

    it('reports a connection failure when fetch rejects', async () => {
      global.fetch.mockRejectedValue(new Error('Network Error'))

      await store.sendMessage('Hi')

      expect(store.error).toContain('Connection to the assistant failed')
      expect(store.isLoading).toBe(false)
    })

    it('sends prior turns as history', async () => {
      store.messages = [
        { role: 'user', content: 'First' },
        { role: 'assistant', content: 'Reply' },
      ]
      global.fetch.mockResolvedValue(sseResponse([sse('done', {})]))

      await store.sendMessage('Second')

      expect(JSON.parse(global.fetch.mock.calls[0][1].body).messages).toEqual([
        { role: 'user', content: 'First' },
        { role: 'assistant', content: 'Reply' },
        { role: 'user', content: 'Second' },
      ])
    })
  })

  describe('retry', () => {
    it('re-sends the most recent user message', async () => {
      global.fetch.mockResolvedValue(sseResponse([sse('done', {})]))
      store.messages = [
        { role: 'user', content: 'First' },
        { role: 'user', content: 'Second' },
      ]
      store.error = 'boom'

      store.retry()
      await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled())

      expect(JSON.parse(global.fetch.mock.calls[0][1].body).messages.at(-1))
        .toEqual({ role: 'user', content: 'Second' })
      expect(store.error).toBeNull()
    })

    it('does nothing when there is no user message', () => {
      store.retry()
      expect(global.fetch).not.toHaveBeenCalled()
    })

    it('does nothing while loading', () => {
      store.messages = [{ role: 'user', content: 'Hi' }]
      store.isLoading = true

      store.retry()

      expect(global.fetch).not.toHaveBeenCalled()
    })
  })

  describe('submitFeedback', () => {
    beforeEach(() => {
      store.messages = [
        { role: 'user', content: 'Question' },
        { role: 'assistant', content: 'Answer' },
      ]
    })

    it('posts the transcript up to the rated reply and marks it settled', async () => {
      global.fetch.mockResolvedValue({ ok: true })

      await expect(store.submitFeedback(1, 1, 'helpful')).resolves.toBe(true)

      const [url, options] = global.fetch.mock.calls[0]
      expect(url).toBe('/api/assistant/feedback')
      expect(JSON.parse(options.body)).toEqual({
        rating: 1,
        comment: 'helpful',
        messages: [
          { role: 'user', content: 'Question' },
          { role: 'assistant', content: 'Answer' },
        ],
      })
      expect(store.messages[1].feedback).toEqual({ rating: 1, pending: false })
    })

    it('rolls the optimistic rating back when the request fails', async () => {
      global.fetch.mockResolvedValue({ ok: false, status: 500 })

      await expect(store.submitFeedback(1, -1)).resolves.toBe(false)
      expect(store.messages[1].feedback).toBeNull()
    })

    it('restores a previous rating when a re-rate fails', async () => {
      store.messages[1].feedback = { rating: 1, pending: false }
      global.fetch.mockRejectedValue(new Error('offline'))

      await store.submitFeedback(1, -1)

      expect(store.messages[1].feedback).toEqual({ rating: 1, pending: false })
    })

    it('refuses to rate a user message', async () => {
      await expect(store.submitFeedback(0, 1)).resolves.toBe(false)
      expect(global.fetch).not.toHaveBeenCalled()
    })

    it('refuses to rate a pending message', async () => {
      store.messages.push({ role: 'assistant', content: '', pending: true })

      await expect(store.submitFeedback(2, 1)).resolves.toBe(false)
      expect(global.fetch).not.toHaveBeenCalled()
    })

    it('refuses to rate a missing index', async () => {
      await expect(store.submitFeedback(99, 1)).resolves.toBe(false)
    })
  })

  describe('acceptPipAgreement', () => {
    it('returns the response body on success', async () => {
      global.fetch.mockResolvedValue({ ok: true, json: async () => ({ accepted: true }) })

      await expect(store.acceptPipAgreement()).resolves.toEqual({ accepted: true })
      expect(global.fetch.mock.calls[0][0]).toBe('/api/assistant/agreement')
    })

    it('throws with the status on failure', async () => {
      global.fetch.mockResolvedValue({ ok: false, status: 403 })

      await expect(store.acceptPipAgreement()).rejects.toThrow('Failed to record agreement (403).')
    })
  })

  describe('uploadLogbookCsv', () => {
    const file = new File(['date,cave\n2024-01-01,Swildons'], 'log.csv', { type: 'text/csv' })

    it('uploads the file then injects the parsed CSV as a user message', async () => {
      global.fetch
        .mockResolvedValueOnce({ ok: true, json: async () => ({ csv_content: 'date,cave', filename: 'log.csv' }) })
        .mockResolvedValueOnce(sseResponse([sse('content', { text: 'Imported' })]))

      await store.uploadLogbookCsv(file)

      const [uploadUrl, uploadOptions] = global.fetch.mock.calls[0]
      expect(uploadUrl).toBe('/api/assistant/logbook-import')
      expect(uploadOptions.body).toBeInstanceOf(FormData)

      expect(store.messages[0].content).toContain('log.csv')
      expect(store.messages[0].content).toContain('date,cave')
      expect(store.messages[1].content).toBe('Imported')
    })

    it('throws the server error message when the upload fails', async () => {
      global.fetch.mockResolvedValue({ ok: false, status: 422, json: async () => ({ error: 'Not a CSV' }) })

      await expect(store.uploadLogbookCsv(file)).rejects.toThrow('Not a CSV')
      expect(store.messages).toEqual([])
    })

    it('falls back to a status message when the error body is unreadable', async () => {
      global.fetch.mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => { throw new Error('not json') },
      })

      await expect(store.uploadLogbookCsv(file)).rejects.toThrow('Upload failed (500)')
    })
  })

  describe('conversation history', () => {
    it('archives the conversation on clear, titled from the first user message', () => {
      store.messages = [
        { role: 'user', content: 'What is Swildons like?' },
        { role: 'assistant', content: 'Wet.' },
      ]

      store.clearConversation()

      expect(store.messages).toEqual([])
      expect(store.error).toBeNull()
      expect(store.savedConversations).toHaveLength(1)
      expect(store.savedConversations[0].title).toBe('What is Swildons like?')
      expect(store.savedConversations[0].messages).toHaveLength(2)
      expect(localStorage.getItem(STORAGE_KEY)).toBeNull()
      expect(JSON.parse(localStorage.getItem(HISTORY_KEY))).toHaveLength(1)
    })

    it('truncates a long title', () => {
      store.messages = [{ role: 'user', content: 'x'.repeat(80) }]

      store.clearConversation()

      const { title } = store.savedConversations[0]
      expect(title).toHaveLength(53)
      expect(title.endsWith('…')).toBe(true)
    })

    it('does not archive an empty conversation', () => {
      store.messages = [{ role: 'assistant', content: 'Hi there' }]

      store.clearConversation()

      expect(store.savedConversations).toEqual([])
    })

    it('caps the stored history', () => {
      const history = Array.from({ length: 15 }, (_, i) => ({ id: i, title: `t${i}`, messages: [] }))
      localStorage.setItem(HISTORY_KEY, JSON.stringify(history))
      store.messages = [{ role: 'user', content: 'Newest' }]

      store.clearConversation()

      expect(store.savedConversations).toHaveLength(15)
      expect(store.savedConversations.at(-1).title).toBe('Newest')
      expect(store.savedConversations[0].title).toBe('t1')
    })

    it('loads a saved conversation, archiving the current one first', () => {
      store.messages = [{ role: 'user', content: 'Current chat' }]
      store.historyDrawerOpen = true

      store.loadSavedConversation({
        id: 1,
        title: 'Older',
        messages: [{ role: 'user', content: 'Older chat' }],
      })

      expect(store.messages).toEqual([{ role: 'user', content: 'Older chat' }])
      expect(store.savedConversations[0].title).toBe('Current chat')
      expect(store.historyDrawerOpen).toBe(false)
      expect(JSON.parse(localStorage.getItem(STORAGE_KEY))[0].content).toBe('Older chat')
    })

    it('loads a saved conversation without archiving an empty one', () => {
      store.loadSavedConversation({ id: 1, messages: [{ role: 'user', content: 'Older chat' }] })

      expect(store.savedConversations).toEqual([])
      expect(store.messages).toHaveLength(1)
    })

    it('deletes a saved conversation and rewrites storage', () => {
      store.savedConversations = [{ id: 1, title: 'a' }, { id: 2, title: 'b' }]

      store.deleteSavedConversation(1)

      expect(store.savedConversations).toEqual([{ id: 2, title: 'b' }])
      expect(JSON.parse(localStorage.getItem(HISTORY_KEY))).toEqual([{ id: 2, title: 'b' }])
    })
  })

  describe('setMode', () => {
    it('switches mode and persists it', () => {
      store.setMode('data')

      expect(store.mode).toBe('data')
      expect(localStorage.getItem(MODE_KEY)).toBe('data')
    })

    it('archives the current conversation when switching', () => {
      store.messages = [{ role: 'user', content: 'Caving question' }]

      store.setMode('data')

      expect(store.messages).toEqual([])
      expect(store.savedConversations).toHaveLength(1)
    })

    it('is a no-op when the mode is unchanged', () => {
      store.messages = [{ role: 'user', content: 'Keep me' }]

      store.setMode('default')

      expect(store.messages).toHaveLength(1)
    })

    it('is a no-op while loading', () => {
      store.isLoading = true

      store.setMode('data')

      expect(store.mode).toBe('default')
    })
  })

  describe('toolLabel', () => {
    it('maps a known tool to a human label', () => {
      expect(store.toolLabel('search_caves')).toBe('Searching caves')
    })

    it('falls back to the raw tool name', () => {
      expect(store.toolLabel('some_new_tool')).toBe('some_new_tool')
    })
  })
})
