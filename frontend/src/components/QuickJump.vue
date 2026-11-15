<template>
  <v-dialog
    v-model="open"
    max-width="600"
    location="top"
    content-class="quick-jump"
    scrim="rgba(24, 38, 31, 0.35)"
    transition="fade-transition"
  >
    <v-card rounded="lg" elevation="12" class="quick-jump__card">
      <div class="quick-jump__search">
        <v-icon :icon="mdiMagnify" size="22" class="text-medium-emphasis" />
        <input
          ref="inputRef"
          v-model="query"
          class="quick-jump__input"
          type="text"
          placeholder="Jump to a cave or page…"
          autocomplete="off"
          spellcheck="false"
          name="quick_jump_no_autofill"
          aria-label="Jump to a cave or page"
          role="combobox"
          aria-expanded="true"
          aria-controls="quick-jump-results"
          :aria-activedescendant="results.length ? `quick-jump-option-${activeIndex}` : undefined"
          data-testid="quick-jump-input"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="go(results[activeIndex])"
        >
        <kbd class="quick-jump__esc" @click="open = false">Esc</kbd>
      </div>

      <div id="quick-jump-results" ref="listRef" class="quick-jump__results" role="listbox">
        <template v-for="(item, index) in results" :key="item.key">
          <div v-if="index === 0 || results[index - 1].group !== item.group" class="quick-jump__group">
            {{ item.group }}
          </div>
          <div
            :id="`quick-jump-option-${index}`"
            class="quick-jump__item"
            :class="{ 'quick-jump__item--active': index === activeIndex }"
            role="option"
            :aria-selected="index === activeIndex"
            :data-index="index"
            @click="go(item)"
            @mousemove="activeIndex = index"
          >
            <v-icon :icon="item.icon" size="20" class="quick-jump__icon" />
            <div class="quick-jump__text">
              <div class="quick-jump__title">{{ item.title }}</div>
              <div v-if="item.subtitle" class="quick-jump__subtitle">{{ item.subtitle }}</div>
            </div>
            <v-icon v-if="index === activeIndex" :icon="mdiKeyboardReturn" size="16" class="quick-jump__enter" />
          </div>
        </template>
        <div v-if="!results.length" class="quick-jump__empty">
          {{ loading ? 'Loading caves…' : `No caves or pages match “${query.trim()}”` }}
        </div>
      </div>

      <div class="quick-jump__footer">
        <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
        <span><kbd>↵</kbd> open</span>
        <span class="ml-auto">Press <kbd>/</kbd> anywhere</span>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  mdiAccount, mdiAccountGroup, mdiAlertOctagram, mdiBookOpenVariant, mdiCalendarCheck, mdiCalendarClock,
  mdiChartLine, mdiClipboardCheck, mdiCloudDownload, mdiCog, mdiCompass, mdiDatabaseSync, mdiEarth,
  mdiEmailMultiple, mdiFileCompare, mdiFileDocumentEdit, mdiFileEdit, mdiFlagOutline, mdiHome,
  mdiImageMultiple, mdiKeyboardReturn, mdiMagnify, mdiMapMarkerPlus, mdiMapSearch, mdiMedal,
  mdiMonitorDashboard, mdiNewspaper, mdiNotebookOutline, mdiPlus, mdiRobotHappy, mdiRobotOutline,
  mdiShieldHome, mdiTerrain, mdiWaves,
} from '@mdi/js'
import { api } from '@/plugins/api'
import { adminRedirect } from '@/router/guard'
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
const inputRef = ref(null)
const listRef = ref(null)
let cavesLoaded = false

const hasRole = (...slugs) => (userStore.user?.roles ?? []).some(r => slugs.includes(r.slug))

const withSearch = (group, items) => items.map(item => ({
  ...item,
  group,
  key: `${group}:${item.href}`,
  haystack: normalise(`${item.title} ${item.subtitle ?? ''} ${item.keywords ?? ''} ${group}`),
}))

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
  return withSearch('Pages', list)
})

const clubs = computed(() => withSearch('Your clubs', (userStore.user?.clubs ?? [])
  .filter(club => club.slug && (club.status ?? 'approved') === 'approved')
  .map(club => ({ title: club.name, href: '/club/' + club.slug, icon: mdiShieldHome, keywords: 'club' }))))

// Only offer pages the admin route guard will let this user open.
const adminPages = computed(() => {
  const user = userStore.user ?? {}
  const links = [
    ['Admin home', '/admin', mdiCog],
    ['Callout admin', '/admin/callout', mdiMonitorDashboard, 'incidents live dashboard'],
    ['Duty rota', '/admin/rota', mdiCalendarClock, 'on-call shifts'],
    ['Users', '/admin/users', mdiAccountGroup, 'accounts roles'],
    ['Clubs admin', '/admin/clubs', mdiShieldHome, 'members'],
    ['Mass comms', '/admin/communications', mdiEmailMultiple, 'email announcements'],
    ['Pip feedback', '/admin/pip-feedback', mdiRobotOutline, 'assistant', 'platform_admin'],
    ['CMS pages', '/admin/pages', mdiFileEdit, 'content'],
    ['Reports', '/admin/reports', mdiFlagOutline, 'moderation objections', 'platform_admin'],
    ['Suggested edits', '/admin/suggested-edits', mdiFileCompare, 'suggestions'],
    ['Data quality', '/admin/tasks', mdiClipboardCheck, 'tasks validation'],
    ['Add cave', '/admin/cave-system-with-cave', mdiMapMarkerPlus, 'new system entrance wizard'],
    ['Catchments', '/admin/catchments', mdiWaves],
    ['OpenStreetMap import', '/admin/osm-import', mdiMapSearch, 'osm', 'data_admin'],
    ['Registry sync', '/admin/cave-sync', mdiDatabaseSync, '', 'data_admin'],
    ['Traffic dashboard', '/admin/dashboard', mdiChartLine, 'analytics stats'],
    ['Permits admin', '/admin/permits', mdiFileDocumentEdit],
    ['Bookings admin', '/admin/bookings', mdiCalendarCheck],
  ]
    // The guard doesn't gate every page; the last column mirrors the admin dashboard for those.
    .filter(([, href, , , role]) => !adminRedirect(user, href) && (!role || hasRole(role)))
    .map(([title, href, icon, keywords]) => ({ title, href, icon, keywords }))

  return withSearch('Admin', links)
})

const caveItems = computed(() => caves.value.map(cave => {
  const subtitle = [cave.location_name, cave.location_country].filter(Boolean).join(', ')
  return {
    group: 'Caves',
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
  const links = [...pages.value, ...clubs.value, ...adminPages.value]
  if (!terms.length) return links

  const matches = item => terms.every(t => item.haystack.includes(t))
  // Caves whose name starts with the query first; the API already sorts curated caves first.
  const first = terms.join(' ')
  const matchedCaves = caveItems.value.filter(matches)
    .sort((a, b) => Number(b.name.startsWith(first)) - Number(a.name.startsWith(first)))

  return [...links.filter(matches), ...matchedCaves.slice(0, MAX_CAVES)]
})

watch(results, () => { activeIndex.value = 0 })

watch(activeIndex, index => {
  nextTick(() => listRef.value?.querySelector(`[data-index="${index}"]`)?.scrollIntoView?.({ block: 'nearest' }))
})

watch(open, isOpen => {
  if (!isOpen) return
  query.value = ''
  activeIndex.value = 0
  loadCaves()
  nextTick(() => inputRef.value?.focus())
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

<style scoped lang="scss">
:global(.quick-jump) {
  margin-top: min(12vh, 120px) !important;
}

.quick-jump__card {
  display: flex;
  flex-direction: column;
  max-height: min(70vh, 560px);
  overflow: hidden;
}

.quick-jump__search {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.quick-jump__input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.0625rem;
  line-height: 1.5;

  &::placeholder {
    color: rgba(var(--v-theme-on-surface), 0.45);
  }
}

.quick-jump__esc {
  cursor: pointer;
}

.quick-jump__results {
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 6px 8px 8px;
}

.quick-jump__group {
  padding: 10px 10px 4px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.55);
}

.quick-jump__item {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 40px;
  padding: 6px 10px;
  border-radius: 8px;
  cursor: pointer;
  user-select: none;
}

.quick-jump__item--active {
  background: rgba(var(--v-theme-primary), 0.1);

  .quick-jump__icon,
  .quick-jump__title {
    color: rgb(var(--v-theme-primary));
  }
}

.quick-jump__icon {
  flex-shrink: 0;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.quick-jump__text {
  flex: 1;
  min-width: 0;
}

.quick-jump__title,
.quick-jump__subtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quick-jump__title {
  font-size: 0.9375rem;
  font-weight: 500;
}

.quick-jump__subtitle {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.quick-jump__enter {
  flex-shrink: 0;
  color: rgba(var(--v-theme-primary), 0.8);
}

.quick-jump__empty {
  padding: 24px 12px;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.quick-jump__footer {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 8px 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

kbd {
  display: inline-block;
  min-width: 20px;
  margin-right: 3px;
  padding: 1px 5px;
  border: 1px solid rgba(var(--v-border-color), 0.2);
  border-bottom-width: 2px;
  border-radius: 5px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  font-family: inherit;
  font-size: 0.6875rem;
  line-height: 1.4;
  text-align: center;
}

@media (max-width: 600px) {
  .quick-jump__footer span:not(.ml-auto) {
    display: none;
  }
}
</style>
