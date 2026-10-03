<template>
  <div ref="shellEl" class="pip-shell">
    <!-- Header -->
    <header class="pip-header">
      <div class="pip-header-inner">
        <v-avatar size="36" class="pip-avatar mr-3" image="/pip.png" alt="Pip" />

        <div class="flex-grow-1 min-width-0">
          <div class="d-flex align-center ga-2">
            <h2 class="pip-title">Pip</h2>
            <v-chip color="warning" variant="tonal" size="x-small" density="compact">Preview</v-chip>
          </div>
          <p class="pip-subtitle">{{ modeCopy.subtitle }}</p>
        </div>
        <v-btn-toggle
          v-if="canUseDataMode || canUsePlanMode"
          :model-value="store.mode"
          density="compact"
          mandatory
          class="pip-mode-toggle mr-1"
          @update:model-value="switchMode"
        >
          <v-btn value="default" size="small" variant="text" :disabled="store.isLoading">
            <v-icon :icon="mdiUpload" size="16" class="pip-mode-icon mr-1" />
            <span class="pip-mode-label">Import</span>
          </v-btn>
          <v-btn v-if="canUsePlanMode" value="plan" size="small" variant="text" :disabled="store.isLoading">
            <v-icon :icon="mdiCompassOutline" size="16" class="pip-mode-icon mr-1" />
            <span class="pip-mode-label">Plan</span>
          </v-btn>
          <v-btn v-if="canUseDataMode" value="data" size="small" variant="text" :disabled="store.isLoading">
            <v-icon :icon="mdiDatabaseCogOutline" size="16" class="pip-mode-icon mr-1" />
            <span class="pip-mode-label">Data</span>
          </v-btn>
        </v-btn-toggle>
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
        <img src="/pip.png" alt="Pip" class="pip-welcome-avatar">

        <h3 class="pip-welcome-title">{{ modeCopy.welcomeTitle }}</h3>
        <p class="pip-welcome-tagline">{{ modeCopy.tagline }}</p>

        <div v-if="isImportMode && hasOpenImport" class="pip-resume">
          <div class="pip-resume-text">
            <strong>You have an import in progress</strong><span v-if="store.openImport.filename"> from {{ store.openImport.filename }}</span>:
            {{ openImportSummary }}
          </div>
          <v-btn size="small" color="primary" variant="flat" @click="sendSuggestion('Let\'s carry on with my import.')">
            Carry on
          </v-btn>
        </div>

        <div class="pip-suggestions">
          <button
            v-for="s in modeCopy.suggestions"
            :key="s.label"
            class="pip-suggestion"
            @click="s.action === 'upload' ? openFilePicker() : sendSuggestion(s.text)"
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
            <v-avatar size="28" class="pip-msg-avatar" image="/pip.png" alt="Pip" />

            <div class="pip-msg-body">
              <!-- Initial idle indicator. Shown when the message is pending,
                   no tools are running, AND nothing meaningful has streamed.
                   We use msg.content?.trim() because some models (Kimi K2.6)
                   return whitespace-only content alongside a tool call, which
                   would otherwise hide this chip and leave the user staring at
                   an empty bubble. -->
              <div v-if="msg.pending && !msg.content?.trim() && !store.activeToolCalls.length" class="pip-tool-row">
                <span class="pip-tool-chip pip-tool-chip--idle">
                  <v-progress-circular size="10" width="2" indeterminate class="mr-1" />
                  Thinking…
                </span>
              </div>

              <!-- Rendered reply: keeps growing across iterations. Mid-iteration
                   reasoning ("you've done lots of Mendip…") is preserved instead
                   of being wiped when a tool starts. Whitespace-only content is
                   treated as no content. -->
              <div v-if="msg.content?.trim()" class="pip-bubble pip-bubble--assistant">
                <MarkdownRenderer :source="msg.content" :streaming="!!msg.pending" :allow-images="false" />
                <span v-if="msg.streaming" class="pip-cursor" />
              </div>

              <!-- Active tool-call chips: shown alongside the bubble while a tool
                   is running. They appear above the bubble before any content is
                   streamed, and below it after content has begun streaming, so the
                   user always sees what's happening. -->
              <div v-if="msg.pending && store.activeToolCalls.length" class="pip-tool-row">
                <span
                  v-for="tool in store.activeToolCalls"
                  :key="tool"
                  class="pip-tool-chip"
                >
                  <v-progress-circular size="10" width="2" indeterminate class="mr-1" />
                  {{ store.toolLabel(tool) }}
                </span>
              </div>

              <!-- Meta row: elapsed time + copy + rate -->
              <div v-if="!msg.pending && !msg.streaming && msg.content?.trim()" class="pip-meta">
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
                <button
                  class="pip-meta-btn"
                  :class="{ 'pip-meta-btn--active': msg.feedback?.rating === 1 }"
                  :title="msg.feedback?.rating === 1 ? 'Thanks for the feedback' : 'Good response'"
                  :disabled="msg.feedback?.pending"
                  @click="rateMessage(index, 1)"
                >
                  <v-icon
                    :icon="msg.feedback?.rating === 1 ? mdiThumbUp : mdiThumbUpOutline"
                    size="12"
                    :color="msg.feedback?.rating === 1 ? 'success' : undefined"
                  />
                </button>
                <button
                  class="pip-meta-btn"
                  :class="{ 'pip-meta-btn--active': msg.feedback?.rating === -1 }"
                  :title="msg.feedback?.rating === -1 ? 'Thanks for the feedback' : 'Flag this response'"
                  :disabled="msg.feedback?.pending"
                  @click="rateMessage(index, -1)"
                >
                  <v-icon
                    :icon="msg.feedback?.rating === -1 ? mdiThumbDown : mdiThumbDownOutline"
                    size="12"
                    :color="msg.feedback?.rating === -1 ? 'error' : undefined"
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
                v-if="!msg.pending && msg.weather_charts"
                class="pip-cardrow-wrap"
              >
                <WeatherChartCard :data="msg.weather_charts" />
              </div>

              <div
                v-if="!msg.pending && msg.medal_progress"
                class="pip-cardrow-wrap"
              >
                <MedalProgressAssistantCard :data="msg.medal_progress" />
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

              <div
                v-if="!msg.pending && msg.proposals && msg.proposals.length"
                class="pip-cardrow-wrap"
              >
                <div class="pip-cardrow-label">
                  <v-icon :icon="mdiClipboardCheckOutline" size="13" class="mr-1" />
                  Proposals awaiting your approval
                </div>
                <div class="pip-cardrow">
                  <ProposalAssistantCard
                    v-for="(proposal, pIndex) in msg.proposals"
                    :key="proposal.suggested_edit_id || proposal.batch_id || pIndex"
                    :proposal="proposal"
                  />
                </div>
              </div>

              <div
                v-if="!msg.pending && msg.created_trips && msg.created_trips.length"
                class="pip-cardrow-wrap"
              >
                <div class="pip-cardrow-label">
                  <v-icon :icon="mdiNotebookOutline" size="13" class="mr-1" />
                  {{ msg.created_trips.length === 1 ? 'Trip saved' : `${msg.created_trips.length} trips saved` }}
                </div>
                <div class="pip-cardrow">
                  <TripCreatedAssistantCard
                    v-for="trip in msg.created_trips"
                    :key="trip.trip_id"
                    :trip="trip"
                  />
                </div>
              </div>

              <div
                v-if="!msg.pending && (msg.trips_imported || msg.import_status)"
                class="pip-cardrow-wrap"
              >
                <ImportProgressAssistantCard :status="msg.import_status" :imported="msg.trips_imported" />
              </div>

              <div
                v-if="!msg.pending && msg.collections_changed && msg.collections_changed.length"
                class="pip-cardrow-wrap"
              >
                <div class="pip-cardrow-label">
                  <v-icon :icon="mdiFormatListChecks" size="13" class="mr-1" />
                  {{ msg.collections_changed.length === 1 ? 'Collection change' : 'Collection changes' }}
                </div>
                <div class="pip-cardrow">
                  <CollectionChangedAssistantCard
                    v-for="(coll, cIndex) in msg.collections_changed"
                    :key="`${coll.action}-${coll.slug || cIndex}`"
                    :collection="coll"
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
          :placeholder="isRecording ? 'Listening…' : modeCopy.placeholder"
          :disabled="store.isLoading"
          @keydown.enter.exact.prevent="send"
          @keydown.shift.enter="inputText += '\n'"
          @input="autosize"
        />
        <input
          ref="fileInputEl"
          type="file"
          accept=".csv,.tsv,.txt"
          class="pip-file-input"
          @change="onFileSelected"
        >
        <button
          v-if="isImportMode"
          class="pip-attach"
          :disabled="store.isLoading || csvUploading"
          title="Import caving logbook (CSV)"
          @click="openFilePicker"
        >
          <v-progress-circular v-if="csvUploading" size="16" width="2" indeterminate />
          <v-icon v-else :icon="mdiPaperclip" size="20" />
        </button>
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
        {{ modeCopy.disclaimer }}
      </p>
    </div>

    <!-- First-run agreement dialog -->
    <v-dialog v-model="agreementDialog" max-width="540" persistent>
      <v-card>
        <v-card-title class="d-flex align-center pa-4 pb-2">
          <v-avatar size="32" class="mr-3" image="/pip.png" alt="Pip" />
          <span>Welcome to Pip</span>
          <v-chip color="warning" variant="tonal" size="x-small" class="ml-2">Beta</v-chip>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4" style="font-size: 14px; line-height: 1.6;">
          <p class="mb-3">
            Before you start, please read and agree to the following:
          </p>
          <ul class="mb-3" style="padding-left: 18px;">
            <li class="mb-2">
              Pip is a <strong>beta product</strong> and may make mistakes.
            </li>
            <li class="mb-2">
              Pip provides <strong>general advice only</strong>. Always use your own
              judgement — verify conditions, access, gear and any safety-critical
              information before relying on it.
            </li>
            <li class="mb-2">
              Conversations may be used to <strong>improve and continue training</strong>
              the assistant. Avoid sharing personal information you don't want
              retained for that purpose.
            </li>
            <li class="mb-2">
              You can flag bad responses with the thumbs-down button — flagged
              conversations are sent to administrators for review.
            </li>
          </ul>
          <p class="mb-0 text-medium-emphasis" style="font-size: 12px;">
            By clicking "I agree" you confirm you have read and accept the above.
          </p>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-3">
          <v-btn variant="text" :to="{ path: '/trips' }">
            No thanks
          </v-btn>
          <v-spacer />
          <v-btn
            color="primary"
            variant="flat"
            :loading="acceptingAgreement"
            @click="acceptAgreement"
          >
            I agree
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
import { ref, watch, nextTick, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRoute } from 'vue-router'
import {
  mdiBroom,
  mdiCalendarOutline,
  mdiCheck,
  mdiClipboardCheckOutline,
  mdiClockOutline,
  mdiClose,
  mdiCompassOutline,
  mdiContentCopy,
  mdiDatabaseCogOutline,
  mdiDelete,
  mdiFormatListChecks,
  mdiHistory,
  mdiHomeRoof,
  mdiLinkVariant,
  mdiMicrophone,
  mdiMicrophoneOff,
  mdiNotebookOutline,
  mdiPaperclip,
  mdiRulerSquare,
  mdiSchoolOutline,
  mdiSend,
  mdiStethoscope,
  mdiTagMultipleOutline,
  mdiThumbDown,
  mdiThumbDownOutline,
  mdiThumbUp,
  mdiThumbUpOutline,
  mdiUpload,
  mdiWeatherCloudy,
} from '@mdi/js'
import { useAssistantStore } from '@/stores/assistant'
import { useAppStore } from '@/stores/app'
import { useNotificationStore } from '@/stores/notifications'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import CaveAssistantCard from '@/components/CaveAssistantCard.vue'
import CollectionAssistantCard from '@/components/CollectionAssistantCard.vue'
import CollectionChangedAssistantCard from '@/components/CollectionChangedAssistantCard.vue'
import HutAssistantCard from '@/components/HutAssistantCard.vue'
import MedalProgressAssistantCard from '@/components/MedalProgressAssistantCard.vue'
import ImportProgressAssistantCard from '@/components/ImportProgressAssistantCard.vue'
import ProposalAssistantCard from '@/components/ProposalAssistantCard.vue'
import TripCreatedAssistantCard from '@/components/TripCreatedAssistantCard.vue'
import TripReportAssistantCard from '@/components/TripReportAssistantCard.vue'
import WeatherChartCard from '@/components/WeatherChartCard.vue'

defineOptions({ name: 'Pip' })

const store = useAssistantStore()
const appStore = useAppStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const inputText = ref('')
const inputEl = ref(null)
const streamEl = ref(null)
const shellEl = ref(null)
const copiedIndex = ref(null)

// ── Data-steward mode (admins only) ──────────────────────────────────────────
const canUseDataMode = computed(() => {
  const roles = appStore.user?.roles || []
  return roles.some(r => r.slug === 'platform_admin' || r.slug === 'data_admin')
})
const isDataMode = computed(() => store.mode === 'data')

// ── Planner mode (platform admins only) ──────────────────────────────────────
// Ordinary Pip users only get the trip importer; recommending trips is kept
// for admins.
const canUsePlanMode = computed(() => {
  const roles = appStore.user?.roles || []
  return roles.some(r => r.slug === 'platform_admin')
})
const isPlanMode = computed(() => store.mode === 'plan')
const isImportMode = computed(() => !isDataMode.value && !isPlanMode.value)

function switchMode(mode) {
  if (!mode || mode === store.mode) return
  store.setMode(mode)
}

// The mode is persisted across page loads. If the user can no longer use data
// mode (e.g. admin role revoked), drop them back to the default assistant —
// otherwise they'd be stuck in a mode whose toggle is hidden and whose
// requests 403.
watch([canUseDataMode, canUsePlanMode, () => appStore.user], ([dataAllowed, planAllowed, user]) => {
  if (!user) return
  if ((store.mode === 'data' && !dataAllowed) || (store.mode === 'plan' && !planAllowed)) {
    store.setMode('default')
  }
}, { immediate: true })

// ── Import in progress ───────────────────────────────────────────────────────
const hasOpenImport = computed(() => (store.openImport?.counts?.total ?? 0) > 0
  && ((store.openImport.counts.ready ?? 0) + (store.openImport.counts.needs_review ?? 0) + (store.openImport.counts.duplicate ?? 0)) > 0)

const openImportSummary = computed(() => {
  const c = store.openImport?.counts || {}
  return [
    c.ready && `${c.ready} ready`,
    c.needs_review && `${c.needs_review} to check`,
    c.duplicate && `${c.duplicate} possible duplicates`,
    c.imported && `${c.imported} already imported`,
  ].filter(Boolean).join(', ') + '.'
})

// ── Agreement gate ───────────────────────────────────────────────────────────
const hasAgreed = computed(() => !!appStore.user?.pip_agreement_signed_at)
const agreementDialog = ref(false)
const acceptingAgreement = ref(false)

async function acceptAgreement() {
  acceptingAgreement.value = true
  try {
    const result = await store.acceptPipAgreement()
    if (appStore.user) {
      appStore.user.pip_agreement_signed_at = result.pip_agreement_signed_at
    }
    agreementDialog.value = false
  } catch (e) {
    notificationStore.showError('Could not record agreement. Please try again.')
  } finally {
    acceptingAgreement.value = false
  }
}

async function rateMessage(index, rating) {
  if (rating === -1) {
    notificationStore.showInfo('Thanks — this conversation will be reviewed.')
  } else {
    notificationStore.showSuccess('Thanks for the feedback!')
  }
  const ok = await store.submitFeedback(index, rating)
  if (!ok) {
    notificationStore.showError('Could not record your feedback. Please try again.')
  }
}

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

onBeforeUnmount(() => {
  recognition?.stop()
  document.documentElement.classList.remove('pip-fullscreen')
  window.removeEventListener('resize', scheduleHeightUpdate)
  window.visualViewport?.removeEventListener('resize', scheduleHeightUpdate)
  layoutObserver?.disconnect()
  clearTimeout(heightTimer)
  clearTimeout(settleTimer)
})

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

// ── Shell sizing ──────────────────────────────────────────────────────────────
// The app nests two v-apps: the top system bar lives in the outer layout while
// the bottom nav dock lives in the inner one. Neither Vuetify layout variable
// captures *both*, so a pure CSS `calc(100dvh − top − bottom)` is always off by
// the system-bar height (causing a phantom page scrollbar) and over-reserves the
// dock's footprint (leaving a big gap above the menu). Instead we measure the
// real chrome: the shell's distance from the top of the viewport already folds
// in the system bar, and the dock's own rect tells us exactly how much room to
// leave at the bottom. Recomputed whenever the viewport or app chrome changes.
let layoutObserver = null
let heightTimer = 0

function updateShellHeight() {
  const el = shellEl.value || document.querySelector('.pip-shell')
  if (!el) return
  const top = el.getBoundingClientRect().top
  const dock = document.querySelector('.nav-dock')
  const reserve = dock
    ? Math.round(window.innerHeight - dock.getBoundingClientRect().top) + 8
    : 80
  el.style.height = `${Math.max(window.innerHeight - top - reserve, 320)}px`
}

// Coalesce bursts (resize, ResizeObserver) via timers rather than rAF — timers
// still fire when the tab isn't painting, so the height stays correct even in a
// backgrounded/offscreen context. We recompute immediately and again after a
// short settle, since app-chrome toggles (system bar, banners) register their
// layout space a frame or two after the triggering DOM change.
let settleTimer = 0
function scheduleHeightUpdate() {
  clearTimeout(heightTimer)
  clearTimeout(settleTimer)
  heightTimer = setTimeout(updateShellHeight, 0)
  settleTimer = setTimeout(updateShellHeight, 150)
}

// ── Welcome suggestions ──────────────────────────────────────────────────────
const importWelcomeSuggestions = [
  { icon: mdiUpload,            label: 'Upload my logbook (CSV)',          action: 'upload' },
  { icon: mdiNotebookOutline,   label: 'Log a trip I did recently',        text: "I'd like to log a caving trip I did recently." },
  { icon: mdiFormatListChecks,  label: 'What should my logbook look like?', text: 'What format does my logbook spreadsheet need to be in for you to import it?' },
]

// ── Planner welcome suggestions (admins only) ────────────────────────────────
const planWelcomeSuggestions = [
  { icon: mdiCompassOutline,    label: 'What should I try next?',         text: 'Based on my caving experience, what cave systems would you recommend I try next?' },
  { icon: mdiCalendarOutline,   label: 'Plan a Yorkshire weekend',        text: 'Can you plan me a caving weekend in the Yorkshire Dales? Two caves over Saturday and Sunday with a hut to stay at.' },
  { icon: mdiSchoolOutline,     label: 'Cave for a beginner',             text: "I'm taking a friend who has never been caving — what's a good first cave for them?" },
  { icon: mdiFormatListChecks,  label: 'How am I doing on collections?',  text: 'How am I doing on the curated cave collections?' },
  { icon: mdiNotebookOutline,   label: "Recent Lancaster Hole reports",   text: "What have people been saying in recent trip reports for Lancaster Hole?" },
  { icon: mdiHomeRoof,          label: "Huts near Swildon's",             text: "What caving huts are near Swildon's Hole? I'd like somewhere to stay for a weekend." },
  { icon: mdiWeatherCloudy,     label: 'Streamway conditions this weekend', text: 'Are conditions OK for a streamway trip in the Dales this weekend?' },
  { icon: mdiNotebookOutline,   label: 'Log a trip report',               text: "I'd like to log a trip report. Can you help me?" },
]

// ── Data-steward welcome suggestions ─────────────────────────────────────────
const dataWelcomeSuggestions = [
  { icon: mdiStethoscope,       label: 'Data health summary',             text: 'Give me a summary of all data issues in the cave database.' },
  { icon: mdiRulerSquare,       label: 'Missing length & depth',          text: 'Find cave systems that are missing length or depth information, and propose fixes where the description states the values.' },
  { icon: mdiLinkVariant,       label: 'Find unlinked entrances',         text: 'Scan for caves in different systems whose entrances are very close together — they may be the same system that needs merging.' },
  { icon: mdiTagMultipleOutline, label: 'Bulk tag caves',                 text: "I want to add a tag to several caves at once. Show me the tag taxonomy and I'll tell you which caves." },
  { icon: mdiCompassOutline,    label: 'Caves missing region tags',       text: 'Find caves that have no region tag, and suggest the right region from their location.' },
]

// ── Per-mode copy ────────────────────────────────────────────────────────────
const modeCopy = computed(() => {
  if (isDataMode.value) {
    return {
      subtitle: 'Data steward — fixes need your approval',
      welcomeTitle: 'Pip — data steward',
      tagline: 'Find and fix data problems — missing lengths, unlinked entrances, tags. Every fix is filed as a suggested edit for your approval.',
      suggestions: dataWelcomeSuggestions,
      placeholder: 'Ask about data issues, or describe a fix…',
      disclaimer: 'Cave & system fixes are filed as suggested edits for review; collection changes apply immediately.',
    }
  }
  if (isPlanMode.value) {
    return {
      subtitle: 'Trip planner — admin preview',
      welcomeTitle: "Hi, I'm Pip",
      tagline: 'Cave recommendations, conditions, trip reports and weekend planning — pick a starter or just ask.',
      suggestions: planWelcomeSuggestions,
      placeholder: 'Ask about caves, conditions, or weekend plans…',
      disclaimer: 'Pip can make mistakes — always verify conditions, access and gear before a trip.',
    }
  }
  return {
    subtitle: 'Import your caving trips',
    welcomeTitle: "Hi, I'm Pip",
    tagline: "I'll get your past caving trips into Subterra. Upload your logbook spreadsheet, or just tell me about a trip — I'll match the caves and people, and check anything I'm unsure of with you before saving.",
    suggestions: importWelcomeSuggestions,
    placeholder: 'Tell me about a trip, or answer my question…',
    disclaimer: 'Nothing is saved until you confirm. Pip can make mistakes — check the trips it imports.',
  }
})

// ── CSV logbook import ───────────────────────────────────────────────────────
const fileInputEl = ref(null)
const csvUploading = ref(false)

function openFilePicker() {
  if (store.isLoading || csvUploading.value) return
  if (!hasAgreed.value) {
    agreementDialog.value = true
    return
  }
  fileInputEl.value?.click()
}

async function onFileSelected(event) {
  const file = event.target.files?.[0]
  if (!fileInputEl.value) return
  // Reset so the same file can be re-selected
  fileInputEl.value.value = ''

  if (!file) return
  if (!hasAgreed.value) {
    agreementDialog.value = true
    return
  }

  csvUploading.value = true
  try {
    await store.uploadLogbookCsv(file)
  } catch (err) {
    notificationStore.showError(err.message || 'Could not upload file. Please try again.')
  } finally {
    csvUploading.value = false
  }
}

function send() {
  const text = inputText.value.trim()
  if (!text || store.isLoading) return
  if (!hasAgreed.value) {
    agreementDialog.value = true
    return
  }
  inputText.value = ''
  nextTick(autosize)
  store.sendMessage(text)
}

function sendSuggestion(text) {
  if (store.isLoading) return
  if (!hasAgreed.value) {
    agreementDialog.value = true
    return
  }
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
  // Lock the page scroller: only the message stream should scroll, never the
  // page itself. (See updateShellHeight for why the layout overflows otherwise.)
  document.documentElement.classList.add('pip-fullscreen')
  scheduleHeightUpdate()
  window.addEventListener('resize', scheduleHeightUpdate)
  window.visualViewport?.addEventListener('resize', scheduleHeightUpdate)
  // Recompute when app chrome (system bar, offline banner) toggles — the outer
  // v-app's height changes when those register/deregister layout space.
  const outerApp = document.querySelector('.v-application')
  if (outerApp && 'ResizeObserver' in window) {
    layoutObserver = new ResizeObserver(scheduleHeightUpdate)
    layoutObserver.observe(outerApp)
  }

  store.fetchOpenImport()

  // Show the agreement dialog immediately on first arrival if not yet signed.
  if (!hasAgreed.value) {
    agreementDialog.value = true
  }

  // Pre-populate from ?context= query param (e.g. deep link from a cave system page)
  const context = route.query.context
  if (context && typeof context === 'string' && !store.hasMessages) {
    inputText.value = context
    if (hasAgreed.value) send()
  }
  nextTick(autosize)
})
</script>

<style scoped>
.pip-resume {
  display: flex;
  align-items: center;
  gap: 12px;
  max-width: 520px;
  margin: 0 auto 16px;
  padding: 10px 14px;
  border-radius: 12px;
  background: #e3f2fd;
  font-size: 13px;
  text-align: left;
}

.pip-resume-text {
  flex: 1;
  min-width: 0;
}

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
  background: #fff;
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
.pip-mode-toggle {
  height: 32px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 16px;
}

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
  display: block;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  margin: 0 auto 18px;
  object-fit: cover;
  background: #fff;
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
  background: #fff;
  flex-shrink: 0;
  margin-top: 2px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
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
.pip-meta-btn:disabled {
  cursor: default;
  opacity: 0.7;
}
.pip-meta-btn--active {
  background: rgba(0, 0, 0, 0.04);
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
.pip-mic, .pip-send, .pip-attach {
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
.pip-attach {
  background: transparent;
  color: #6b7280;
}
.pip-attach:hover:not(:disabled) {
  background: rgba(0, 0, 0, 0.05);
  color: #1f2937;
}
.pip-attach:disabled { opacity: 0.4; cursor: not-allowed; }
.pip-file-input { display: none; }
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

/* Narrow phones: keep the header from crowding. The Caving/Data toggle (admins
   only) collapses to icons so it stops pushing the history/new-chat buttons off
   the edge, and the header packs a little tighter. */
@media (max-width: 599px) {
  .pip-header-inner { padding: 10px var(--pip-gutter); }
  .pip-mode-label { display: none; }
  .pip-mode-toggle :deep(.v-btn) { min-width: 36px; padding: 0 6px; }
  .pip-mode-toggle :deep(.pip-mode-icon) { margin-right: 0 !important; }
}
</style>

<!--
  Non-scoped: while the Pip chat is mounted the root element must not scroll, so
  the message stream is the only scroller. The class is added/removed in the
  component's mount/unmount hooks. It survives Vuetify's dialog scroll-lock
  (which toggles an inline overflow on <html>) because it's a class, not inline.
-->
<style>
html.pip-fullscreen { overflow: hidden; }
</style>
