<template>
  <div class="pip-shell">
    <!-- Header -->
    <header class="pip-header">
      <div class="pip-header-inner">
        <v-avatar size="36" class="pip-avatar mr-3">
          <v-icon :icon="mdiCompassOutline" size="20" color="white" />
        </v-avatar>
        <div class="flex-grow-1 min-width-0">
          <div class="d-flex align-center ga-2">
            <h2 class="pip-title">Pip</h2>
            <v-chip color="warning" variant="tonal" size="x-small" density="compact">Preview</v-chip>
          </div>
          <p class="pip-subtitle">Your caving guide</p>
        </div>
        <v-btn
          variant="text"
          size="small"
          :icon="mdiHistory"
          :disabled="!store.savedConversations.length"
          title="Conversation history"
          @click="store.historyDrawerOpen = true"
        />
        <v-btn
          variant="text"
          size="small"
          :icon="mdiBroom"
          title="New conversation"
          :disabled="!store.hasMessages && !store.error"
          @click="store.clearConversation"
        />
      </div>
    </header>

    <!-- Message stream (the only scroller) -->
    <div ref="streamEl" class="pip-stream">
      <!-- Welcome screen -->
      <div v-if="!store.hasMessages && !store.error" class="pip-welcome">
        <div class="pip-welcome-avatar">
          <v-icon :icon="mdiCompassOutline" size="44" color="white" />
        </div>
        <h3 class="pip-welcome-title">Hi, I'm Pip</h3>
        <p class="pip-welcome-tagline">
          Cave recommendations, conditions, trip reports and weekend planning — pick a starter or just ask.
        </p>
        <div class="pip-suggestions">
          <button
            v-for="s in welcomeSuggestions"
            :key="s.text"
            class="pip-suggestion"
            @click="sendSuggestion(s.text)"
          >
            <v-icon :icon="s.icon" size="16" class="mr-2" />
            <span>{{ s.label }}</span>
          </button>
        </div>
      </div>

      <!-- Message thread -->
      <div class="pip-messages" :class="{ 'pip-messages--has-content': store.hasMessages }">
        <template v-for="(msg, index) in store.messages" :key="index">
          <!-- User message -->
          <div v-if="msg.role === 'user'" class="pip-row pip-row--user">
            <div class="pip-bubble pip-bubble--user">{{ msg.content }}</div>
          </div>

          <!-- Assistant message -->
          <div v-else class="pip-row pip-row--assistant">
            <v-avatar size="28" class="pip-msg-avatar">
              <v-icon :icon="mdiCompassOutline" size="16" color="white" />
            </v-avatar>
            <div class="pip-msg-body">
              <!-- Tool status (only while pending and no streamed content yet) -->
              <div v-if="msg.pending && !msg.content" class="pip-tool-row">
                <template v-if="store.activeToolCalls.length">
                  <span
                    v-for="tool in store.activeToolCalls"
                    :key="tool"
                    class="pip-tool-chip"
                  >
                    <v-progress-circular size="10" width="2" indeterminate class="mr-1" />
                    {{ store.toolLabel(tool) }}
                  </span>
                </template>
                <span v-else class="pip-tool-chip pip-tool-chip--idle">
                  <v-progress-circular size="10" width="2" indeterminate class="mr-1" />
                  Thinking…
                </span>
              </div>

              <!-- Rendered reply -->
              <div v-if="msg.content" class="pip-bubble pip-bubble--assistant">
                <MarkdownRenderer :source="msg.content" />
                <span v-if="msg.streaming" class="pip-cursor" />
              </div>

              <!-- Meta row: elapsed time + copy -->
              <div v-if="!msg.pending && !msg.streaming && msg.content" class="pip-meta">
                <span v-if="msg.elapsedMs" class="pip-meta-time">
                  <v-icon :icon="mdiClockOutline" size="11" class="mr-1" />
                  {{ formatElapsed(msg.elapsedMs) }}
                </span>
                <button
                  class="pip-meta-btn"
                  :title="copiedIndex === index ? 'Copied!' : 'Copy reply'"
                  @click="copyMessage(msg.content, index)"
                >
                  <v-icon
                    :icon="copiedIndex === index ? mdiCheck : mdiContentCopy"
                    size="12"
                    :color="copiedIndex === index ? 'success' : undefined"
                  />
                </button>
              </div>

              <!-- Card rows (cave / collection / hut / trip-report) -->
              <div
                v-if="!msg.pending && msg.cards && msg.cards.length"
                class="pip-cardrow"
              >
                <CaveAssistantCard
                  v-for="sys in msg.cards"
                  :key="sys.id || sys.slug"
                  :system="sys"
                />
              </div>

              <div
                v-if="!msg.pending && msg.collections && msg.collections.length"
                class="pip-cardrow"
              >
                <CollectionAssistantCard
                  v-for="coll in msg.collections"
                  :key="coll.id || coll.slug"
                  :collection="coll"
                />
              </div>

              <div
                v-if="!msg.pending && msg.huts && msg.huts.huts && msg.huts.huts.length"
                class="pip-cardrow-wrap"
              >
                <div class="pip-cardrow-label">
                  <v-icon :icon="mdiHomeRoof" size="13" class="mr-1" />
                  {{ msg.huts.count || msg.huts.huts.length }} huts within {{ msg.huts.max_distance_km || 50 }}km<span v-if="msg.huts.reference_cave"> of {{ msg.huts.reference_cave }}</span>
                </div>
                <div class="pip-cardrow">
                  <HutAssistantCard
                    v-for="hut in msg.huts.huts"
                    :key="hut.id"
                    :hut="hut"
                  />
                </div>
              </div>

              <div
                v-if="!msg.pending && msg.reports && msg.reports.length"
                class="pip-cardrow-wrap"
              >
                <div class="pip-cardrow-label">
                  <v-icon :icon="mdiNotebookOutline" size="13" class="mr-1" />
                  Recent trip reports
                </div>
                <div class="pip-cardrow">
                  <TripReportAssistantCard
                    v-for="report in msg.reports"
                    :key="report.short_id || report.url"
                    :report="report"
                  />
                </div>
              </div>

              <!-- Follow-up suggestions -->
              <div
                v-if="!msg.pending && !msg.streaming && msg.suggestions && msg.suggestions.length"
                class="pip-followups"
              >
                <button
                  v-for="suggestion in msg.suggestions"
                  :key="suggestion"
                  class="pip-followup"
                  @click="sendSuggestion(suggestion)"
                >
                  {{ suggestion }}
                </button>
              </div>
            </div>
          </div>
        </template>

        <!-- Error -->
        <v-alert
          v-if="store.error"
          type="error"
          variant="tonal"
          class="mt-2"
          :text="store.error"
          closable
          @click:close="store.error = null"
        >
          <template #append>
            <v-btn
              size="small"
              variant="tonal"
              color="error"
              class="ml-2"
              @click="store.retry()"
            >
              Try again
            </v-btn>
          </template>
        </v-alert>
      </div>
    </div>

    <!-- Composer -->
    <div class="pip-composer">
      <div class="pip-composer-inner">
        <button
          class="pip-mic"
          :class="{ 'pip-mic--recording': isRecording }"
          :title="isRecording ? 'Stop recording' : 'Voice input'"
          :disabled="!speechAvailable"
          @click="toggleVoice"
        >
          <v-icon :icon="isRecording ? mdiMicrophoneOff : mdiMicrophone" size="20" />
        </button>
        <textarea
          ref="inputEl"
          v-model="inputText"
          class="pip-input"
          rows="1"
          :placeholder="isRecording ? 'Listening…' : 'Ask about caves, conditions, or weekend plans…'"
          :disabled="store.isLoading"
          @keydown.enter.exact.prevent="send"
          @keydown.shift.enter="inputText += '\n'"
          @input="autosize"
        />
        <button
          class="pip-send"
          :disabled="!inputText.trim() || store.isLoading"
          :title="store.isLoading ? 'Working…' : 'Send'"
          @click="send"
        >
          <v-progress-circular v-if="store.isLoading" size="18" width="2" indeterminate />
          <v-icon v-else :icon="mdiSend" size="20" />
        </button>
      </div>
      <p class="pip-disclaimer">
        Pip can make mistakes — always verify conditions, access and gear before a trip.
      </p>
    </div>

    <!-- Conversation history dialog -->
    <v-dialog v-model="store.historyDrawerOpen" max-width="520" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center pa-4 pb-2">
          <v-icon :icon="mdiHistory" class="mr-2" />
          Past conversations
          <v-spacer />
          <v-btn :icon="mdiClose" variant="text" size="small" @click="store.historyDrawerOpen = false" />
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-0" style="max-height: 480px">
          <v-list lines="two">
            <v-list-item
              v-for="conv in [...store.savedConversations].reverse()"
              :key="conv.id"
              :subtitle="formatDate(conv.createdAt)"
              :title="conv.title"
            >
              <template #append>
                <div class="d-flex ga-1">
                  <v-btn
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    @click="store.loadSavedConversation(conv)"
                  >
                    Restore
                  </v-btn>
                  <v-btn
                    size="x-small"
                    variant="text"
                    color="grey"
                    :icon="mdiDelete"
                    @click="store.deleteSavedConversation(conv.id)"
                  />
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import {
  mdiBroom,
  mdiCalendarOutline,
  mdiCheck,
  mdiClockOutline,
  mdiClose,
  mdiCompassOutline,
  mdiContentCopy,
  mdiDelete,
  mdiFormatListChecks,
  mdiHistory,
  mdiHomeRoof,
  mdiMicrophone,
  mdiMicrophoneOff,
  mdiNotebookOutline,
  mdiSchoolOutline,
  mdiSend,
  mdiWeatherCloudy,
} from '@mdi/js'
import { useAssistantStore } from '@/stores/assistant'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveAssistantCard from '@/components/CaveAssistantCard.vue'
import CollectionAssistantCard from '@/components/CollectionAssistantCard.vue'
import HutAssistantCard from '@/components/HutAssistantCard.vue'
import TripReportAssistantCard from '@/components/TripReportAssistantCard.vue'

defineOptions({ name: 'AdminAssistant' })

const store = useAssistantStore()
const route = useRoute()
const inputText = ref('')
const inputEl = ref(null)
const streamEl = ref(null)
const copiedIndex = ref(null)

// ── Voice input ──────────────────────────────────────────────────────────────
const SpeechRecognition = typeof window !== 'undefined'
  ? (window.SpeechRecognition || window.webkitSpeechRecognition)
  : null
const speechAvailable = !!SpeechRecognition
const isRecording = ref(false)
let recognition = null

function toggleVoice() {
  if (!SpeechRecognition) return
  if (isRecording.value) {
    recognition?.stop()
    isRecording.value = false
    return
  }
  recognition = new SpeechRecognition()
  recognition.lang = 'en-GB'
  recognition.interimResults = false
  recognition.maxAlternatives = 1
  recognition.onresult = (e) => {
    const transcript = e.results[0][0].transcript
    inputText.value = inputText.value
      ? `${inputText.value.trimEnd()} ${transcript}`
      : transcript
    nextTick(autosize)
  }
  recognition.onerror = () => { isRecording.value = false }
  recognition.onend = () => { isRecording.value = false }
  recognition.start()
  isRecording.value = true
}

onBeforeUnmount(() => { recognition?.stop() })

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatElapsed(ms) {
  if (!ms) return null
  return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-GB', {
    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

// Auto-grow the textarea up to ~6 rows worth
function autosize() {
  const el = inputEl.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 180) + 'px'
}

// ── Welcome suggestions ──────────────────────────────────────────────────────
const welcomeSuggestions = [
  { icon: mdiCompassOutline,    label: 'What should I try next?',         text: 'Based on my caving experience, what cave systems would you recommend I try next?' },
  { icon: mdiCalendarOutline,   label: 'Plan a Yorkshire weekend',        text: 'Can you plan me a caving weekend in the Yorkshire Dales? Two caves over Saturday and Sunday with a hut to stay at.' },
  { icon: mdiSchoolOutline,     label: 'Cave for a beginner',             text: "I'm taking a friend who has never been caving — what's a good first cave for them?" },
  { icon: mdiFormatListChecks,  label: 'How am I doing on collections?',  text: 'How am I doing on the curated cave collections?' },
  { icon: mdiNotebookOutline,   label: "Recent OFD trip reports",         text: "What have people been saying in recent trip reports for Lancaster Hole?" },
  { icon: mdiHomeRoof,          label: "Huts near Swildon's",             text: "What caving huts are near Swildon's Hole? I'd like somewhere to stay for a weekend." },
  { icon: mdiWeatherCloudy,     label: 'Streamway conditions this weekend', text: 'Are conditions OK for a streamway trip in the Dales this weekend?' },
]

function send() {
  const text = inputText.value.trim()
  if (!text || store.isLoading) return
  inputText.value = ''
  nextTick(autosize)
  store.sendMessage(text)
}

function sendSuggestion(text) {
  if (store.isLoading) return
  inputText.value = text
  send()
}

async function copyMessage(content, index) {
  try {
    await navigator.clipboard.writeText(content)
    copiedIndex.value = index
    setTimeout(() => { copiedIndex.value = null }, 2000)
  } catch {
    // Clipboard unavailable — silently ignore
  }
}

// Scroll to bottom whenever content grows
watch(
  () => store.messages.map(m => m.content).join(''),
  async () => {
    await nextTick()
    const el = streamEl.value
    if (el) el.scrollTop = el.scrollHeight
  }
)

onMounted(() => {
  // Pre-populate from ?context= query param (e.g. deep link from a cave system page)
  const context = route.query.context
  if (context && typeof context === 'string' && !store.hasMessages) {
    inputText.value = context
    send()
  }
  nextTick(autosize)
})
</script>

<style scoped>
/*
 * Pip — single-page chat shell. Uses dynamic viewport height so we fit
 * cleanly between the platform's app bar and any bottom navigation,
 * and only the message stream scrolls (no double-scrollbar).
 */
.pip-shell {
  --pip-gutter: 16px;
  --pip-radius: 18px;
  --pip-bg: #f7f8fb;
  --pip-stream-max: 760px;

  display: flex;
  flex-direction: column;
  height: calc(100dvh - var(--v-layout-top, 64px) - var(--v-layout-bottom, 0px));
  min-height: 480px;
  background: var(--pip-bg);
}

/* ── Header ─────────────────────────────────────────────────────────── */
.pip-header {
  flex: 0 0 auto;
  background: white;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  z-index: 2;
}
.pip-header-inner {
  max-width: var(--pip-stream-max);
  margin: 0 auto;
  display: flex;
  align-items: center;
  padding: 12px var(--pip-gutter);
}
.pip-avatar {
  background: linear-gradient(135deg, #1867c0 0%, #5b9bff 100%);
  box-shadow: 0 2px 8px rgba(24, 103, 192, 0.25);
}
.pip-title {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.01em;
  margin: 0;
  line-height: 1.1;
}
.pip-subtitle {
  font-size: 11px;
  color: #6b7280;
  margin: 2px 0 0;
  line-height: 1.1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.min-width-0 { min-width: 0; }

/* ── Stream (the only scrolling area) ───────────────────────────────── */
.pip-stream {
  flex: 1 1 auto;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  scroll-behavior: smooth;
}

/* ── Welcome ─────────────────────────────────────────────────────────── */
.pip-welcome {
  max-width: var(--pip-stream-max);
  margin: 0 auto;
  padding: 48px var(--pip-gutter) 32px;
  text-align: center;
}
.pip-welcome-avatar {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1867c0 0%, #5b9bff 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 18px;
  box-shadow: 0 12px 32px rgba(24, 103, 192, 0.3);
}
.pip-welcome-title {
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -0.02em;
  margin: 0 0 8px;
  color: #111827;
}
.pip-welcome-tagline {
  color: #4b5563;
  font-size: 14px;
  line-height: 1.55;
  max-width: 460px;
  margin: 0 auto 28px;
}
.pip-suggestions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
}
.pip-suggestion {
  display: inline-flex;
  align-items: center;
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 999px;
  padding: 8px 14px;
  font-size: 13px;
  color: #1f2937;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
}
.pip-suggestion:hover {
  border-color: rgba(24, 103, 192, 0.4);
  box-shadow: 0 4px 14px rgba(24, 103, 192, 0.1);
  transform: translateY(-1px);
}

/* ── Messages ────────────────────────────────────────────────────────── */
.pip-messages {
  max-width: var(--pip-stream-max);
  margin: 0 auto;
  padding: 16px var(--pip-gutter) 8px;
}
.pip-row {
  display: flex;
  margin-bottom: 18px;
}
.pip-row--user {
  justify-content: flex-end;
}
.pip-row--assistant {
  align-items: flex-start;
  gap: 10px;
}
.pip-msg-avatar {
  background: linear-gradient(135deg, #1867c0 0%, #5b9bff 100%);
  flex-shrink: 0;
  margin-top: 2px;
}
.pip-msg-body {
  flex: 1 1 auto;
  min-width: 0;
}
.pip-bubble--user {
  background: linear-gradient(135deg, #1867c0 0%, #2196f3 100%);
  color: white;
  padding: 10px 14px;
  border-radius: 18px 18px 4px 18px;
  max-width: 78%;
  white-space: pre-wrap;
  font-size: 14px;
  line-height: 1.5;
  box-shadow: 0 2px 8px rgba(24, 103, 192, 0.18);
}
.pip-bubble--assistant {
  background: white;
  color: #1f2937;
  padding: 12px 14px;
  border-radius: 4px 18px 18px 18px;
  border: 1px solid rgba(0, 0, 0, 0.05);
  font-size: 14px;
  line-height: 1.6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.pip-bubble--assistant :deep(p) { margin-bottom: 12px; }
.pip-bubble--assistant :deep(p:last-child) { margin-bottom: 0; }
.pip-bubble--assistant :deep(h1),
.pip-bubble--assistant :deep(h2),
.pip-bubble--assistant :deep(h3),
.pip-bubble--assistant :deep(h4) {
  font-size: 15px;
  margin-top: 14px;
  margin-bottom: 6px;
  border: none;
  padding: 0;
}
.pip-cursor {
  display: inline-block;
  width: 2px;
  height: 1em;
  background: currentColor;
  margin-left: 2px;
  vertical-align: text-bottom;
  animation: pip-blink 1s step-end infinite;
}
@keyframes pip-blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}

/* Tool call status chips */
.pip-tool-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 6px;
}
.pip-tool-chip {
  display: inline-flex;
  align-items: center;
  background: rgba(24, 103, 192, 0.08);
  color: #1867c0;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 500;
}
.pip-tool-chip--idle {
  background: rgba(0, 0, 0, 0.04);
  color: #6b7280;
}

/* Meta row under the message (elapsed + copy) */
.pip-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
  padding-left: 4px;
  font-size: 11px;
  color: #9ca3af;
}
.pip-meta-time {
  display: inline-flex;
  align-items: center;
}
.pip-meta-btn {
  background: transparent;
  border: 0;
  padding: 4px;
  border-radius: 6px;
  cursor: pointer;
  color: #9ca3af;
  display: inline-flex;
  align-items: center;
  transition: background 0.15s, color 0.15s;
}
.pip-meta-btn:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #1f2937;
}

/* ── Card rows: full-bleed horizontal scroll on mobile ────────────────── */
.pip-cardrow-wrap {
  margin-top: 12px;
}
.pip-cardrow-label {
  display: flex;
  align-items: center;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 8px;
  padding-left: 4px;
}
.pip-cardrow {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  /* Bleed past the message gutter so cards reach the screen edge */
  margin-left: calc(-1 * var(--pip-gutter));
  margin-right: calc(-1 * var(--pip-gutter));
  padding: 4px var(--pip-gutter);
  margin-top: 12px;
  scroll-snap-type: x proximity;
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}
.pip-cardrow > :deep(*) {
  scroll-snap-align: start;
}
.pip-cardrow::-webkit-scrollbar { height: 0; }

/* Follow-up suggestions under a reply */
.pip-followups {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}
.pip-followup {
  background: white;
  border: 1px solid rgba(24, 103, 192, 0.25);
  color: #1867c0;
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, transform 0.15s;
}
.pip-followup:hover {
  background: rgba(24, 103, 192, 0.06);
  transform: translateY(-1px);
}

/* ── Composer (sticky to bottom of shell) ────────────────────────────── */
.pip-composer {
  flex: 0 0 auto;
  background: white;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
  padding: 10px var(--pip-gutter) calc(10px + env(safe-area-inset-bottom, 0));
}
.pip-composer-inner {
  max-width: var(--pip-stream-max);
  margin: 0 auto;
  display: flex;
  align-items: flex-end;
  gap: 8px;
  background: #f3f4f6;
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 24px;
  padding: 6px 6px 6px 10px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pip-composer-inner:focus-within {
  border-color: rgba(24, 103, 192, 0.5);
  box-shadow: 0 0 0 4px rgba(24, 103, 192, 0.08);
}
.pip-input {
  flex: 1 1 auto;
  border: 0;
  outline: 0;
  background: transparent;
  resize: none;
  padding: 8px 4px;
  font-family: inherit;
  font-size: 14px;
  line-height: 1.5;
  color: #111827;
  max-height: 180px;
  min-height: 36px;
}
.pip-input::placeholder { color: #9ca3af; }
.pip-input:disabled { color: #9ca3af; }
.pip-mic, .pip-send {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, transform 0.15s;
}
.pip-mic {
  background: transparent;
  color: #6b7280;
}
.pip-mic:hover:not(:disabled) {
  background: rgba(0, 0, 0, 0.05);
  color: #1f2937;
}
.pip-mic:disabled { opacity: 0.4; cursor: not-allowed; }
.pip-mic--recording {
  background: rgba(229, 57, 53, 0.12);
  color: #c62828;
  animation: pip-pulse 1.2s ease-in-out infinite;
}
@keyframes pip-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(229, 57, 53, 0.45); }
  50%       { box-shadow: 0 0 0 8px rgba(229, 57, 53, 0); }
}
.pip-send {
  background: linear-gradient(135deg, #1867c0 0%, #2196f3 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(24, 103, 192, 0.3);
}
.pip-send:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(24, 103, 192, 0.4);
}
.pip-send:disabled {
  background: #e5e7eb;
  color: #9ca3af;
  box-shadow: none;
  cursor: not-allowed;
}

.pip-disclaimer {
  max-width: var(--pip-stream-max);
  margin: 6px auto 0;
  font-size: 10px;
  color: #9ca3af;
  text-align: center;
}

/* ── Responsive tweaks ───────────────────────────────────────────────── */
@media (min-width: 600px) {
  .pip-shell {
    --pip-gutter: 24px;
  }
  .pip-bubble--user { max-width: 70%; }
}
</style>
