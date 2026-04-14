<template>
  <div>
    <div class="d-flex justify-end pa-4">
      <v-btn
        v-if="appStore.user && (appStore.user.is_admin || appStore.canSuggest)"
        color="primary"
        variant="text"
        :prepend-icon="mdiPencil"
        :to="`/cave-systems/${route.params.id}/edit`"
      >
        {{ appStore.user?.is_admin ? 'Edit Cave System' : 'Suggest Edit' }}
      </v-btn>
      <v-btn
        v-else-if="appStore.user"
        color="grey"
        variant="text"
        disabled
        :prepend-icon="mdiPencilOff"
      >
        <v-tooltip activator="parent" location="top">
          Your account must be approved to suggest edits
        </v-tooltip>
        Suggest Edit
      </v-btn>
      <v-btn
        v-else
        color="primary"
        variant="text"
        :prepend-icon="mdiPencil"
        to="/login"
      >
        Log in to Suggest Edit
      </v-btn>
    </div>
    <CaveSystem />
    
    <v-container v-if="caveSystem">
      <v-row>
        <v-col cols="12">
          <AnnotationMapViewer
            :annotation="caveSystem.annotation"
            :caves="caveSystem.caves"
          />
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <RouteList :routes="caveSystem.routes" :cave-system-id="caveSystem.id" />
        </v-col>
      </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { mdiPencil, mdiPencilOff } from '@mdi/js'

import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import RouteList from '@/components/cave-systems/RouteList.vue'
import AnnotationMapViewer from '@/components/cave-systems/AnnotationMapViewer.vue'
import { useAppStore } from '@/stores/app'
import { api } from '@/plugins/api'

const appStore = useAppStore()
const route = useRoute()
const caveSystem = ref(null)

const load = async () => {
  try {
    const [routesResponse, systemResponse] = await Promise.all([
      api.get(`/api/cave_systems/${route.params.id}/routes`),
      api.get(`/api/cave_systems/${route.params.id}`),
    ])

    const systemData = systemResponse.data.data
    const routesData = routesResponse.data

    caveSystem.value = {
      ...systemData,
      id: route.params.id,
      routes: routesData,
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(load)

watch(() => route.params.id, load)
</script>
