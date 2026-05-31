<template>
  <v-container>
    <div class="mb-8">
      <h2 class="text-h3 font-weight-bold mb-1">Cave Registry Sync</h2>
      <p class="text-subtitle-1 text-grey-darken-1">
        Trigger a sync from an external cave registry to import new caves and update descriptions.
      </p>
    </div>

    <v-alert type="info" variant="tonal" class="mb-6">
      Syncs run as a background task and can take a couple of minutes to complete — the MCRA registry in particular has a large number of caves.
      Once finished, any new or updated cave descriptions will appear as suggested edits for you to review on the
      <router-link to="/admin/suggested-edits" class="text-decoration-none font-weight-medium">Suggested Edits</router-link> page.
    </v-alert>

    <v-row>
      <v-col v-for="reg in registries" :key="reg.id" cols="12" sm="6" md="4">
        <v-card>
          <v-card-item>
            <template #prepend>
              <v-icon color="teal" :icon="mdiDatabaseSync" />
            </template>
            <v-card-title>{{ reg.label }}</v-card-title>
            <v-card-subtitle>{{ reg.url }}</v-card-subtitle>
          </v-card-item>
          <v-card-text class="text-body-2 text-grey-darken-1">
            {{ reg.description }}
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn
              color="teal"
              variant="tonal"
              :loading="loading[reg.id]"
              :disabled="loading[reg.id]"
              @click="triggerSync(reg.id)"
            >
              Sync now
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="6000">
      {{ snackbar.message }}
      <template v-if="snackbar.color === 'success'" #actions>
        <v-btn variant="text" to="/admin/suggested-edits" @click="snackbar.show = false">
          Review edits
        </v-btn>
      </template>
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { mdiDatabaseSync } from '@mdi/js'
import { reactive } from 'vue'
import { api } from '@/plugins/api'

defineOptions({ name: 'CaveRegistrySync' })

const registries = [
  {
    id: 'mcra',
    label: 'MCRA (Mendip)',
    url: 'mcra.org.uk',
    description: 'Mendip Cave Registry and Archive — covers Mendip and Portland.',
  },
  {
    id: 'fod',
    label: 'FoD (Forest of Dean)',
    url: 'fodccag.org.uk',
    description: 'Forest of Dean Cave and Caving Advisory Group registry.',
  },
  {
    id: 'gsg',
    label: 'GSG (Scotland)',
    url: 'registry.gsg.org.uk',
    description: 'Grampian Speleological Group — Scottish cave and mine database.',
  },
  {
    id: 'cncc',
    label: 'CNCC (Yorkshire)',
    url: 'cncc.org.uk',
    description: 'Council of Northern Caving Clubs — Yorkshire cave index.',
  },
  {
    id: 'pdc',
    label: 'Peak District Caving',
    url: 'peakdistrictcaving.info',
    description: 'Derbyshire Caving Association — Peak District cave database.',
  },
]

const loading = reactive({ mcra: false, fod: false, gsg: false, cncc: false, pdc: false })

const snackbar = reactive({ show: false, message: '', color: 'success' })

async function triggerSync(registryId) {
  loading[registryId] = true
  try {
    await api.post(`/api/admin/cave-registry-sync/${registryId}`)
    snackbar.message = `${registryId.toUpperCase()} sync queued — check back in a couple of minutes.`
    snackbar.color = 'success'
  } catch (e) {
    snackbar.message = e?.response?.data?.message ?? 'Failed to queue sync.'
    snackbar.color = 'error'
  } finally {
    loading[registryId] = false
    snackbar.show = true
  }
}
</script>
