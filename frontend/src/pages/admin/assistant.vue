<template>
  <v-container class="pa-4" style="max-width: 860px">
    <!-- Header -->
    <div class="d-flex align-center mb-5">
      <v-icon color="primary" class="mr-3" :icon="mdiRobotOutline" size="32" />
      <div>
        <h2 class="text-h5 font-weight-bold">Vern · Trip Assistant</h2>
        <p class="text-body-2 text-grey-darken-1 mb-0">Caving recommendations, conditions and weekend planning</p>
      </div>
      <v-spacer />
      <v-chip color="warning" variant="tonal" size="small" class="mr-2">
        Admin Preview
      </v-chip>
      <v-btn
        variant="text"
        size="small"
        :icon="mdiHistory"
        class="mr-1"
        :disabled="!store.savedConversations.length"
        title="Conversation history"
        @click="store.historyDrawerOpen = true"
      />
      <v-btn
        variant="text"
        size="small"
        :disabled="!store.hasMessages && !store.error"
        @click="store.clearConversation"
      >
        Clear
      </v-btn>
    </div>

    <!-- Chat window -->
    <v-card
      ref="chatWindow"
      variant="outlined"
      class="mb-3 overflow-y-auto"
      style="height: calc(100vh - 280px); min-height: 400px"
    >
      <v-card-text class="pa-4">
        <!-- Welcome screen -->
        <div v-if="!store.hasMessages && !store.error" class="text-center py-8">
          <v-avatar color="primary" size="64" class="mb-4 welcome-avatar">
            <v-icon :icon="mdiRobotOutline" size="36" color="white" />
          </v-avatar>
          <p class="text-h6 font-weight-medium mb-1">Hi, I'm Vern</p>
          <p class="text-body-2 text-grey-darken-1 mb-6 mx-auto" style="max-width: 460px">
            I can recommend caves based on your experience, summarise recent trip reports,
            check weather and river conditions, and help you plan a caving weekend.
          </p>
          <div class="d-flex flex-wrap justify-center ga-2" style="max-width: 720px; margin: 0 auto">
            <v-chip
              v-for="suggestion in welcomeSuggestions"
              :key="suggestion.text"
              variant="tonal"
              color="primary"
              class="cursor-pointer"
              @click="sendSuggestion(suggestion.text)"
            >
              {{ suggestion.label }}
            </v-chip>
          </div>
        </div>

        <!-- Message thread -->
        <template v-for="(msg, index) in store.messages" :key="index">
          <!-- User message -->
          <div v-if="msg.role === 'user'" class="d-flex justify-end mb-1">
            <v-card
              color="primary"
              variant="flat"
              class="pa-3 text-white"
              style="max-width: 78%; border-radius: 16px 16px 4px 16px"
            >
              <p class="mb-0 text-body-2" style="white-space: pre-wrap">{{ msg.content }}</p>
            </v-card>
          </div>

          <!-- Assistant message -->
          <div v-else class="d-flex justify-start mb-1">
            <div style="max-width: 82%; width: 100%">
              <!-- Tool call status: show only when pending AND no streamed content yet -->
              <div v-if="msg.pending && !msg.content" class="d-flex align-center flex-wrap ga-1 mb-2">
                <template v-if="store.activeToolCalls.length">
                  <v-chip
                    v-for="tool in store.activeToolCalls"
                    :key="tool"
                    size="small"
                    color="info"
                    variant="tonal"
                  >
                    <v-progress-circular
                      size="10"
                      width="2"
                      indeterminate
                      class="mr-1"
                    />
                    {{ store.toolLabel(tool) }}
                  </v-chip>
                </template>
                <v-chip v-else size="small" color="grey" variant="tonal">
                  <v-progress-circular size="10" width="2" indeterminate class="mr-1" />
                  Thinking…
                </v-chip>
              </div>

              <!-- Rendered assistant reply (shows during streaming and after) -->
              <div v-if="msg.content" class="message-bubble" style="position: relative;">
                <v-card
                  variant="tonal"
                  color="grey-lighten-4"
                  class="pa-3"
                  style="border-radius: 4px 16px 16px 16px"
                >
                  <MarkdownRenderer :source="msg.content" />
                  <!-- Streaming cursor -->
                  <span v-if="msg.streaming" class="streaming-cursor" />
                </v-card>

                <!-- Copy button (visible on hover) -->
                <v-btn
                  v-if="!msg.pending && !msg.streaming"
                  class="copy-btn"
                  size="x-small"
                  variant="tonal"
                  :icon="copiedIndex === index ? mdiCheck : mdiContentCopy"
                  :color="copiedIndex === index ? 'success' : 'grey'"
                  @click="copyMessage(msg.content, index)"
                />
              </div>

              <!-- Elapsed time (shows after response completes) -->
              <div
                v-if="!msg.pending && !msg.streaming && msg.elapsedMs"
                class="d-flex align-center ga-1 mt-1"
              >
                <v-chip size="x-small" variant="text" color="grey" class="text-caption">
                  <v-icon :icon="mdiClockOutline" size="10" class="mr-1" />
                  {{ formatElapsed(msg.elapsedMs) }}
                </v-chip>
              </div>

              <!-- Cave system result cards (from search_caves / get_cave_details) -->
              <div
                v-if="!msg.pending && msg.cards && msg.cards.length"
                class="cards-row mt-2"
              >
                <CaveAssistantCard
                  v-for="sys in msg.cards"
                  :key="sys.id || sys.slug"
                  :system="sys"
                />
              </div>

              <!-- Hut cards (from find_nearby_huts) -->
              <div
                v-if="!msg.pending && msg.huts && msg.huts.huts && msg.huts.huts.length"
                class="mt-3"
              >
                <div class="d-flex align-center mb-1 px-1">
                  <v-icon :icon="mdiHomeRoof" size="14" class="mr-1" color="grey-darken-1" />
                  <span class="text-caption text-grey-darken-1 font-weight-medium">
                    {{ msg.huts.count || msg.huts.huts.length }} huts within {{ msg.huts.max_distance_km || 50 }}km<span v-if="msg.huts.reference_cave"> of {{ msg.huts.reference_cave }}</span>
                  </span>
                </div>
                <div class="cards-row">
                  <HutAssistantCard
                    v-for="hut in msg.huts.huts"
                    :key="hut.id"
                    :hut="hut"
                  />
                </div>
              </div>

              <!-- Trip report cards -->
              <div
                v-if="!msg.pending && msg.reports && msg.reports.length"
                class="mt-3"
              >
                <div class="d-flex align-center mb-1 px-1">
                  <v-icon :icon="mdiNotebookOutline" size="14" class="mr-1" color="grey-darken-1" />
                  <span class="text-caption text-grey-darken-1 font-weight-medium">
                    Recent trip reports
                  </span>
                </div>
                <div class="cards-row">
                  <TripReportAssistantCard
                    v-for="report in msg.reports"
                    :key="report.short_id || report.url"
                    :report="report"
                  />
                </div>
              </div>

              <!-- Contextual follow-up suggestion chips -->
              <div
                v-if="!msg.pending && !msg.streaming && msg.suggestions && msg.suggestions.length"
                class="d-flex flex-wrap ga-2 mt-2"
              >
                <v-chip
                  v-for="suggestion in msg.suggestions"
                  :key="suggestion"
                  size="small"
                  variant="tonal"
                  color="primary"
                  class="cursor-pointer"
                  @click="sendSuggestion(suggestion)"
                >
                  {{ suggestion }}
                </v-chip>
              </div>
            </div>
          </div>

          <!-- Spacing between turns -->
          <div class="mb-3" />
        </template>

        <!-- Error notice -->
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
      </v-card-text>
    </v-card>

    <!-- Input row -->
    <v-row no-gutters align="start" class="ga-2">
      <v-col>
        <v-textarea
          v-model="inputText"
          placeholder="Ask about caves, check conditions, or plan a weekend away…"
          variant="outlined"
          density="comfortable"
          hide-details
          auto-grow
          rows="1"
          max-rows="6"
          :disabled="store.isLoading"
          @keydown.enter.exact.prevent="send"
          @keydown.shift.enter="inputText += '\n'"
        />
      </v-col>
      <v-col cols="auto" class="d-flex flex-column ga-2">
        <v-btn
          :color="isRecording ? 'error' : 'default'"
          size="large"
          :icon="isRecording ? mdiMicrophoneOff : mdiMicrophone"
          :class="{ 'recording-pulse': isRecording }"
          :title="isRecording ? 'Stop recording' : 'Voice input'"
          :disabled="!speechAvailable"
          @click="toggleVoice"
        />
        <v-btn
          color="primary"
          size="large"
          :loading="store.isLoading"
          :disabled="!inputText.trim()"
          :icon="mdiSend"
          @click="send"
        />
      </v-col>
    </v-row>

    <p class="text-caption text-grey mt-2 text-center">
      AI recommendations are a starting point. Always verify conditions, access, and gear before your trip.
      Your trip history may be used to personalise responses.
    </p>
  </v-container>

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
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import {
  mdiCheck,
  mdiClockOutline,
  mdiClose,
  mdiContentCopy,
  mdiDelete,
  mdiHistory,
  mdiHomeRoof,
  mdiMicrophone,
  mdiMicrophoneOff,
  mdiNotebookOutline,
  mdiRobotOutline,
  mdiSend,
} from '@mdi/js'
import { useAssistantStore } from '@/stores/assistant'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveAssistantCard from '@/components/CaveAssistantCard.vue'
import HutAssistantCard from '@/components/HutAssistantCard.vue'
import TripReportAssistantCard from '@/components/TripReportAssistantCard.vue'

defineOptions({ name: 'AdminAssistant' })

const store = useAssistantStore()
const route = useRoute()
const inputText = ref('')
const chatWindow = ref(null)
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

// ── Welcome suggestions ──────────────────────────────────────────────────────
const welcomeSuggestions = [
  { label: 'What should I try next?', text: 'Based on my caving experience, what cave systems would you recommend I try next?' },
  { label: 'Plan a Yorkshire weekend', text: 'Can you plan me a caving weekend in the Yorkshire Dales? I\'d like two caves across the weekend.' },
  { label: 'Unvisited sporting caves', text: 'What sporting caves haven\'t I done yet? I\'m happy with anything up to a hard grade.' },
  { label: 'Beginner-friendly caves', text: 'What\'s a good cave system to take someone new to caving?' },
  { label: 'Recent OFD trips', text: 'What have people been saying in recent trip reports for Ogof Ffynnon Ddu?' },
  { label: 'Huts near Swildon\'s', text: 'What caving huts are near Swildon\'s Hole that I could stay at for a weekend?' },
]

function send() {
  const text = inputText.value.trim()
  if (!text || store.isLoading) return
  inputText.value = ''
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

// Scroll to bottom whenever content grows (new messages or streaming chunks)
watch(
  () => store.messages.map(m => m.content).join(''),
  async () => {
    await nextTick()
    const el = chatWindow.value?.$el ?? chatWindow.value
    if (el) el.scrollTop = el.scrollHeight
  }
)

// Pre-populate from ?context= query param (e.g. deep link from a cave system page)
onMounted(() => {
  const context = route.query.context
  if (context && typeof context === 'string' && !store.hasMessages) {
    inputText.value = context
    send()
  }
})
</script>

<style scoped>
.message-bubble {
  position: relative;
}

.message-bubble .copy-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  opacity: 0;
  transition: opacity 0.15s;
}

.message-bubble:hover .copy-btn {
  opacity: 1;
}

.streaming-cursor {
  display: inline-block;
  width: 2px;
  height: 1em;
  background: currentColor;
  margin-left: 2px;
  vertical-align: text-bottom;
  animation: blink 1s step-end infinite;
}

.welcome-avatar {
  box-shadow: 0 6px 20px rgba(33, 150, 243, 0.25);
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0; }
}

/* Horizontal scrolling row of cards (caves, huts, trip reports) */
.cards-row {
  display: flex;
  flex-direction: row;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 4px;
  padding-top: 4px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}

.cards-row::-webkit-scrollbar {
  height: 4px;
}

.cards-row::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
}

/* Pulsing animation for the recording button */
@keyframes recording-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(229, 57, 53, 0.5); }
  50%       { box-shadow: 0 0 0 8px rgba(229, 57, 53, 0); }
}

.recording-pulse {
  animation: recording-pulse 1.2s ease-in-out infinite;
}
</style>
