<template>
  <v-container>
    <div class="mb-6">
      <h2 class="text-h3 font-weight-bold mb-1">Import from OpenStreetMap</h2>
      <p class="text-subtitle-1 text-grey-darken-1">
        Pick a region, choose the caves you recognise, and import only those.
      </p>
    </div>

    <v-alert type="info" variant="tonal" class="mb-6" :icon="mdiOpenSourceInitiative">
      OpenStreetMap data is open data under the
      <a href="https://opendatacommons.org/licenses/odbl/" target="_blank" rel="noopener">Open Database Licence (ODbL)</a>.
      <ul class="ml-4 mt-2">
        <li><strong>New</strong> caves are created as OpenStreetMap-sourced records and are always credited to OpenStreetMap contributors.</li>
        <li><strong>Already in Subterra</strong> caves are only linked to their OpenStreetMap entry. Their data is never changed.</li>
      </ul>
    </v-alert>

    <v-row align="center" class="mb-2">
      <v-col cols="12" sm="6" md="4">
        <v-select
          v-model="region"
          :items="regions"
          label="Region"
          density="comfortable"
          hide-details
          :disabled="loading || importing"
        />
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-btn color="teal" :loading="loading" :disabled="!region || importing" :prepend-icon="mdiMapSearch" @click="loadCandidates">
          Show caves
        </v-btn>
      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

    <template v-if="loaded">
      <div class="d-flex flex-wrap align-center ga-2 mb-3">
        <v-text-field
          v-model="search"
          :prepend-inner-icon="mdiMagnify"
          label="Filter by name"
          density="compact"
          hide-details
          clearable
          style="max-width: 320px;"
        />
        <v-spacer />
        <span class="text-body-2 text-grey-darken-1">{{ selected.length }} selected</span>
        <v-btn color="primary" :disabled="selected.length === 0" :loading="importing" @click="confirmOpen = true">
          Import selected
        </v-btn>
      </div>

      <v-data-table
        v-model="selected"
        :headers="headers"
        :items="candidates"
        :search="search"
        item-value="osm_id"
        :item-selectable="(item) => item.status !== 'linked'"
        show-select
        :items-per-page="50"
        density="comfortable"
        class="elevation-1"
      >
        <template #item.status="{ item }">
          <v-chip v-if="item.status === 'new'" size="small" color="success" variant="tonal">New</v-chip>
          <v-chip v-else-if="item.status === 'existing'" size="small" color="warning" variant="tonal" :to="`/caves/${item.cave.slug}`">
            Already in Subterra: {{ item.cave.name }}
          </v-chip>
          <v-chip v-else size="small" variant="tonal" :to="`/caves/${item.cave.slug}`">Linked</v-chip>
        </template>
        <template #item.access="{ item }">
          <span class="text-body-2">{{ item.access || '—' }}</span>
        </template>
        <template #item.links="{ item }">
          <a :href="item.osm_url" target="_blank" rel="noopener" class="mr-3">OpenStreetMap</a>
          <a v-if="safeUrl(item.website)" :href="safeUrl(item.website)" target="_blank" rel="noopener nofollow">Website</a>
        </template>
      </v-data-table>

      <p class="text-caption text-grey-darken-1 mt-2">{{ attribution }}</p>
    </template>

    <v-card v-if="results.length" class="mt-6" variant="outlined">
      <v-card-title>Import results</v-card-title>
      <v-card-text>
        <p class="mb-2">
          {{ counts.created }} created · {{ counts.linked }} linked · {{ counts.unchanged }} already linked
          <template v-if="counts.not_found"> · {{ counts.not_found }} no longer in OpenStreetMap</template>
        </p>
        <v-list density="compact">
          <v-list-item v-for="r in results" :key="r.osm_id" :to="r.cave ? `/caves/${r.cave.slug}` : undefined">
            <v-list-item-title>{{ r.cave ? r.cave.name : `Node ${r.osm_id}` }}</v-list-item-title>
            <v-list-item-subtitle>{{ actionLabel(r.action) }}</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-card-text>
    </v-card>

    <v-dialog v-model="confirmOpen" max-width="480">
      <v-card>
        <v-card-title>Import {{ selected.length }} caves?</v-card-title>
        <v-card-text>
          {{ selectedNew }} new caves will be created with OpenStreetMap attribution.
          {{ selectedExisting }} caves you already have will only be linked to OpenStreetMap.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="confirmOpen = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" @click="runImport">Import</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { mdiMagnify, mdiMapSearch, mdiOpenSourceInitiative } from '@mdi/js'
import { api } from '@/plugins/api'
import { usePageTitle } from '@/composables/usePageTitle'

defineOptions({ name: 'OsmImport' })
usePageTitle('Import from OpenStreetMap')

const regions = ref([])
const region = ref(null)
const candidates = ref([])
const attribution = ref('')
const selected = ref([])
const search = ref('')
const loading = ref(false)
const loaded = ref(false)
const importing = ref(false)
const confirmOpen = ref(false)
const error = ref('')
const results = ref([])

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Access', key: 'access', sortable: false },
  { title: 'Links', key: 'links', sortable: false },
]

const byId = computed(() => Object.fromEntries(candidates.value.map(c => [c.osm_id, c])))
const selectedNew = computed(() => selected.value.filter(id => byId.value[id]?.status === 'new').length)
const selectedExisting = computed(() => selected.value.filter(id => byId.value[id]?.status === 'existing').length)
const counts = computed(() => results.value.reduce((acc, r) => ({ ...acc, [r.action]: (acc[r.action] || 0) + 1 }),
  { created: 0, linked: 0, unchanged: 0, not_found: 0 }))

// OSM tags are user-edited: only ever link to http(s) URLs.
const safeUrl = (url) => (typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null)

const actionLabel = (action) => ({
  created: 'Created from OpenStreetMap',
  linked: 'Linked to OpenStreetMap (data unchanged)',
  unchanged: 'Already linked',
  not_found: 'No longer in OpenStreetMap — not imported',
}[action] || action)

onMounted(async () => {
  try {
    const res = await api.get('/api/admin/osm/regions')
    regions.value = res.data.data
  } catch {
    error.value = 'Could not load regions.'
  }
})

async function loadCandidates() {
  loading.value = true
  error.value = ''
  selected.value = []
  try {
    const res = await api.get('/api/admin/osm/candidates', { params: { region: region.value }, suppressErrorNotification: true })
    candidates.value = res.data.data
    attribution.value = res.data.attribution
    loaded.value = true
  } catch (e) {
    error.value = e?.response?.data?.message || 'Could not load caves from OpenStreetMap.'
  } finally {
    loading.value = false
  }
}

async function runImport() {
  confirmOpen.value = false
  importing.value = true
  error.value = ''
  try {
    const res = await api.post('/api/admin/osm/import', { region: region.value, node_ids: selected.value }, { suppressErrorNotification: true })
    results.value = res.data.data
    await loadCandidates()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Import failed part-way. Reload the region to see what was imported.'
  } finally {
    importing.value = false
  }
}
</script>
