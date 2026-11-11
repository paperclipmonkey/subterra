<template>
  <v-dialog v-model="open" max-width="560" scrollable location="top" class="quick-jump">
    <v-card>
      <v-text-field
        ref="inputRef"
        v-model="query"
        :prepend-inner-icon="mdiMagnify"
        placeholder="Jump to a cave or page…"
        variant="solo"
        flat
        hide-details
        autofocus
        autocomplete="off"
        name="quick_jump_no_autofill"
        aria-label="Jump to a cave or page"
        data-testid="quick-jump-input"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="go(results[activeIndex])"
      />
      <v-divider />
      <v-card-text class="pa-0">
        <v-list density="compact" role="listbox">
          <v-list-item
            v-for="(item, index) in results"
            :key="item.key"
            :active="index === activeIndex"
            :prepend-icon="item.icon"
            :title="item.title"
            :subtitle="item.subtitle"
            color="primary"
            role="option"
            :aria-selected="index === activeIndex"
            @click="go(item)"
            @mousemove="activeIndex = index"
          />
          <v-list-item v-if="!results.length" class="text-medium-emphasis">
            {{ loading ? 'Loading caves…' : 'No matches' }}
          </v-list-item>
        </v-list>
      </v-card-text>
      <v-divider />
      <div class="d-flex ga-4 px-4 py-2 text-body-small text-medium-emphasis">
        <span><kbd>↑</kbd> <kbd>↓</kbd> to move</span>
        <span><kbd>Enter</kbd> to open</span>
        <span><kbd>Esc</kbd> to close</span>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  mdiAccount, mdiAlertOctagram, mdiBookOpenVariant, mdiCalendarCheck, mdiCloudDownload, mdiCog,
  mdiCompass, mdiEarth, mdiHome, mdiImageMultiple, mdiMagnify, mdiMedal, mdiNewspaper,
  mdiNotebookOutline, mdiPlus, mdiRobotHappy, mdiTerrain,
} from '@mdi/js'
import { api } from '@/plugins/api'
import { useAppStore } from '@/stores/app'
import { shouldOpenQuickJump } from '@/utilities/quickJump'

const MAX_CAVES = 8

const router = useRouter()
const userStore = useAppStore()

const open = ref(false)
const query = ref('')
const activeIndex = ref(0)
const caves = ref([])
const loading = ref(false)
let cavesLoaded = false

const hasRole = (...slugs) => (userStore.user?.roles ?? []).some(r => slugs.includes(r.slug))

const pages = computed(() => {
  const list = [
    { title: 'My Trips', href: '/trips', icon: mdiNotebookOutline },
    { title: 'Log a trip', href: '/create-trip', icon: mdiPlus, keywords: 'new create add' },
    { title: 'Caves', href: '/caves', icon: mdiEarth, keywords: 'map list' },
    { title: 'Discover trips', href: '/trips/discover', icon: mdiCompass },
    { title: 'Huts', href: '/huts', icon: mdiHome },
    { title: 'Collections', href: '/collections', icon: mdiImageMultiple },
    { title: 'News', href: '/news', icon: mdiNewspaper },
    { title: 'Bookings', href: '/bookings', icon: mdiCalendarCheck, keywords: 'permits' },
    { title: 'Medals', href: '/medals', icon: mdiMedal },
    { title: 'Offline caves', href: '/offline', icon: mdiCloudDownload },
    { title: 'Profile', href: '/profile/' + userStore.user?.id, icon: mdiAccount, keywords: 'me account' },
    { title: 'About', href: '/pages/about', icon: mdiBookOpenVariant },
  ]
  if (userStore.calloutsEnabled && hasRole('platform_admin', 'duty_officer', 'callout_access')) {
    list.push({ title: 'Callout', href: '/callout', icon: mdiAlertOctagram, keywords: 'rescue' })
  }
  if (hasRole('platform_admin', 'pip_access')) {
    list.push({ title: 'Pip assistant', href: '/pip', icon: mdiRobotHappy, keywords: 'ai chat' })
  }
  if (userStore.user?.is_admin) {
    list.push({ title: 'Admin', href: '/admin', icon: mdiCog })
  }
  return list.map(p => ({ ...p, key: 'page:' + p.href, haystack: normalise(`${p.title} ${p.keywords ?? ''}`) }))
})

const caveItems = computed(() => caves.value.map(cave => {
  const subtitle = [cave.location_name, cave.location_country].filter(Boolean).join(', ')
  return {
    key: 'cave:' + cave.id,
    title: cave.name,
    subtitle,
    href: '/caves/' + cave.slug,
    icon: mdiTerrain,
    name: normalise(cave.name),
    haystack: normalise(`${cave.name} ${subtitle}`),
  }
}))

const results = computed(() => {
  const terms = normalise(query.value).split(/\s+/).filter(Boolean)
  if (!terms.length) return pages.value

  const matches = item => terms.every(t => item.haystack.includes(t))
  const matchedPages = pages.value.filter(matches)
  // Caves whose name starts with the query first; the API already sorts curated caves first.
  const matchedCaves = caveItems.value.filter(matches)
  const first = terms.join(' ')
  matchedCaves.sort((a, b) => Number(b.name.startsWith(first)) - Number(a.name.startsWith(first)))

  return [...matchedPages, ...matchedCaves.slice(0, MAX_CAVES)]
})

watch(results, () => { activeIndex.value = 0 })

watch(open, isOpen => {
  if (isOpen) {
    query.value = ''
    activeIndex.value = 0
    loadCaves()
  }
})

function normalise (text) {
  return (text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

async function loadCaves () {
  if (cavesLoaded || loading.value) return
  loading.value = true
  try {
    caves.value = (await api.get('/api/caves/search')).data.data
    cavesLoaded = true
  } catch {
    // Pages still work without caves (e.g. offline); retry on next open.
  } finally {
    loading.value = false
  }
}

function move (step) {
  const count = results.value.length
  if (count) activeIndex.value = (activeIndex.value + step + count) % count
}

function go (item) {
  if (!item) return
  open.value = false
  router.push(item.href)
}

function onKeydown (event) {
  if (open.value || !shouldOpenQuickJump(event)) return
  event.preventDefault()
  open.value = true
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))

defineExpose({ open, query, results, activeIndex })
</script>

<style scoped>
kbd {
  font-family: inherit;
  font-size: 0.85em;
  padding: 0 4px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

:deep(.quick-jump .v-overlay__content) {
  margin-top: 12vh;
}
</style>
