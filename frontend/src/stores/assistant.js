import { defineStore } from 'pinia'

const TOOL_LABELS = {
  get_user_experience: 'Looking up your experience',
  search_caves: 'Searching caves',
  get_cave_details: 'Fetching cave details',
  get_weather_forecast: 'Checking weather & river levels',
  get_upcoming_permits: 'Checking permit availability',
  list_routes: 'Loading routes',
  find_nearby_huts: 'Finding nearby huts',
  get_cave_system_activity: 'Checking community activity',
}

const STORAGE_KEY = 'vern_conversation_v1'
const HISTORY_KEY = 'vern_conversation_history_v1'
const MAX_PERSISTED = 50
const MAX_HISTORY = 15

function loadPersistedMessages() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    // Strip transient UI state from persisted messages
    return parsed
      .filter(m => !m.pending && m.content)
      .map(m => ({ role: m.role, content: m.content, suggestions: m.suggestions ?? [], cards: m.cards ?? [], elapsedMs: m.elapsedMs ?? null }))
  } catch {
    return []
  }
}

function persistMessages(messages) {
  try {
    const toSave = messages
      .filter(m => !m.pending && m.content)
      .slice(-MAX_PERSISTED)
      .map(m => ({ role: m.role, content: m.content, suggestions: m.suggestions ?? [], cards: m.cards ?? [], elapsedMs: m.elapsedMs ?? null }))
    localStorage.setItem(STORAGE_KEY, JSON.stringify(toSave))
  } catch {
    // Storage unavailable or quota exceeded — silently ignore
  }
}

function loadHistory() {
  try {
    const raw = localStorage.getItem(HISTORY_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function saveHistory(history) {
  try {
    localStorage.setItem(HISTORY_KEY, JSON.stringify(history.slice(-MAX_HISTORY)))
  } catch { /* ignore */ }
}

export const useAssistantStore = defineStore('assistant', {
  state: () => ({
    /** @type {{ role: string, content: string, pending?: boolean, streaming?: boolean, suggestions?: string[], usage?: object, cards?: object[], elapsedMs?: number }[]} */
    messages: loadPersistedMessages(),
    isLoading: false,
    /** @type {string[]} */
    activeToolCalls: [],
    error: null,
    /** @type {{ id: number, title: string, createdAt: string, messages: object[] }[]} */
    savedConversations: loadHistory(),
    historyDrawerOpen: false,
  }),

  getters: {
    hasMessages: (state) => state.messages.some(m => !m.pending),
  },

  actions: {
    /**
     * Send a user message and consume the SSE stream response.
     * @param {string} content
     */
    async sendMessage(content) {
      if (!content.trim() || this.isLoading) return

      this.messages.push({ role: 'user', content })
      this.isLoading = true
      this.activeToolCalls = []
      this.error = null

      // Placeholder while we wait for the stream
      this.messages.push({ role: 'assistant', content: '', pending: true, streaming: false, suggestions: [] })

      const history = this.messages
        .filter(m => !m.pending && m.content && m.content.trim())
        .map(m => ({ role: m.role, content: m.content.trim() }))

      try {
        const response = await fetch('/api/assistant/chat', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ messages: history }),
          credentials: 'same-origin',
        })

        if (!response.ok) {
          const body = await response.json().catch(() => ({}))
          const message = response.status === 429
            ? 'Daily request limit reached. Please try again tomorrow.'
            : body.message || `Error ${response.status}: unable to reach assistant.`
          this._failPending(message)
          return
        }

        const reader = response.body.getReader()
        const decoder = new TextDecoder()
        let buffer = ''

        while (true) {
          const { done, value } = await reader.read()
          if (done) break

          buffer += decoder.decode(value, { stream: true })
          const lines = buffer.split('\n')
          buffer = lines.pop() // retain any incomplete line

          for (const line of lines) {
            if (line.startsWith('data: ')) {
              try {
                const event = JSON.parse(line.slice(6))
                this._handleEvent(event)
              } catch {
                // Malformed SSE line — ignore
              }
            }
          }
        }
      } catch (err) {
        this._failPending('Connection to the assistant failed. Please check your network and try again.')
      } finally {
        this.isLoading = false
        this.activeToolCalls = []
        persistMessages(this.messages)
      }
    },

    /**
     * Retry by re-sending the last user message.
     */
    retry() {
      const lastUser = [...this.messages].reverse().find(m => m.role === 'user')
      if (!lastUser || this.isLoading) return
      this.error = null
      this.sendMessage(lastUser.content)
    },

    /**
     * Handle a single SSE event from the stream.
     * @param {{ type: string, data: any }} event
     */
    _handleEvent(event) {
      switch (event.type) {
        case 'thinking':
          // Loading state already shown via pending message
          break

        case 'tool_call': {
          const { name, status } = event.data || {}
          if (status === 'running') {
            // Clear any partial streamed content — we're now in tool-calling mode
            const pending = this.messages.findLast(m => m.pending)
            if (pending) {
              pending.content = ''
              pending.streaming = false
            }
            if (!this.activeToolCalls.includes(name)) {
              this.activeToolCalls.push(name)
            }
          } else if (status === 'done') {
            this.activeToolCalls = this.activeToolCalls.filter(t => t !== name)
          }
          break
        }

        case 'content_chunk': {
          // Progressive token streaming — append each chunk as it arrives
          const pending = this.messages.findLast(m => m.pending)
          if (pending) {
            pending.content = (pending.content || '') + (event.data?.text ?? '')
            pending.streaming = true
          }
          break
        }

        case 'content': {
          // Full content replacement (non-streaming fallback)
          const pending = this.messages.findLast(m => m.pending)
          if (pending) {
            pending.content = event.data?.text ?? ''
            pending.pending = false
            pending.streaming = false
          }
          break
        }

        case 'cave_cards': {
          // Structured cave system cards returned from search_caves
          const pending = this.messages.findLast(m => m.pending)
          if (pending) {
            pending.cards = (pending.cards || []).concat(event.data || [])
          }
          break
        }

        case 'thinking_elapsed': {
          // Total elapsed time for thinking + tool calls
          const pending = this.messages.findLast(m => m.pending)
          if (pending) {
            pending.elapsedMs = event.data?.ms ?? null
          }
          break
        }

        case 'suggestions': {
          // Contextual follow-up suggestions for the last completed message
          const last = this.messages.findLast(m => m.role === 'assistant' && !m.pending)
          if (last) {
            last.suggestions = event.data || []
          } else {
            // Suggestions arrived before the content event finalised (streaming case)
            const pending = this.messages.findLast(m => m.pending)
            if (pending) pending.suggestions = event.data || []
          }
          break
        }

        case 'usage': {
          // Token usage from the last OpenRouter call
          const target = this.messages.findLast(m => m.pending) ||
                         this.messages.findLast(m => m.role === 'assistant')
          if (target) {
            target.usage = event.data || null
          }
          break
        }

        case 'done':
          // Finalise any still-pending streaming message
          {
            const pending = this.messages.findLast(m => m.pending)
            if (pending) {
              pending.pending = false
              pending.streaming = false
            }
          }
          this.isLoading = false
          this.activeToolCalls = []
          break

        case 'error':
          this._failPending(event.data?.message || 'An error occurred.')
          break
      }
    },

    _failPending(message) {
      // Remove the pending assistant bubble and record the error
      const pendingIndex = this.messages.findLastIndex(m => m.pending)
      if (pendingIndex !== -1) {
        this.messages.splice(pendingIndex, 1)
      }
      this.error = message
      this.isLoading = false
      this.activeToolCalls = []
    },

    clearConversation() {
      // Archive current conversation to history before clearing
      const userMessages = this.messages.filter(m => m.role === 'user' && !m.pending && m.content)
      if (userMessages.length > 0) {
        const firstMsg = userMessages[0].content
        const title = firstMsg.length > 55 ? firstMsg.slice(0, 52) + '…' : firstMsg
        const history = loadHistory()
        history.push({
          id: Date.now(),
          title,
          createdAt: new Date().toISOString(),
          messages: this.messages
            .filter(m => !m.pending && m.content)
            .slice(-MAX_PERSISTED)
            .map(m => ({ role: m.role, content: m.content, suggestions: m.suggestions ?? [], cards: m.cards ?? [], elapsedMs: m.elapsedMs ?? null })),
        })
        saveHistory(history)
        this.savedConversations = history.slice(-MAX_HISTORY)
      }
      this.messages = []
      this.activeToolCalls = []
      this.error = null
      this.isLoading = false
      try { localStorage.removeItem(STORAGE_KEY) } catch { /* ignore */ }
    },

    loadSavedConversation(conv) {
      // Save current conversation first if it has content
      if (this.messages.filter(m => !m.pending && m.content).length > 0) {
        this.clearConversation()
      }
      this.messages = conv.messages.map(m => ({ ...m }))
      this.activeToolCalls = []
      this.error = null
      this.historyDrawerOpen = false
      persistMessages(this.messages)
    },

    deleteSavedConversation(id) {
      const history = this.savedConversations.filter(c => c.id !== id)
      this.savedConversations = history
      saveHistory(history)
    },

    toolLabel(toolName) {
      return TOOL_LABELS[toolName] ?? toolName
    },
  },
})
