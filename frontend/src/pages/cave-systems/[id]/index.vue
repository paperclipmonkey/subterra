<template>
  <div>
    <div class="d-flex justify-end pa-4">
        <v-btn
            v-if="appStore.user"
            color="primary"
            variant="text"
            prepend-icon="mdi-pencil"
            :to="`/cave-systems/${route.params.id}/edit`"
        >
            {{ appStore.user?.is_admin ? 'Edit Cave System' : 'Suggest Edit' }}
        </v-btn>
        <v-btn
            v-else
            color="primary"
            variant="text"
            prepend-icon="mdi-pencil"
            to="/login"
        >
            Log in to Suggest Edit
        </v-btn>
    </div>
    <CaveSystem />
    
    <v-container v-if="caveSystem">
        <v-row>
            <v-col cols="12">
                <RouteList :routes="caveSystem.routes" :caveSystemId="caveSystem.id" />
            </v-col>
        </v-row>
    </v-container>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import RouteList from '@/components/cave-systems/RouteList.vue'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
const route = useRoute()
const caveSystem = ref(null)

const load = async () => {
  try {
    const response = await fetch(`/api/cave_systems/${route.params.id}/routes`)
    if (response.ok) {
      // The API returns the list of routes directly?
      // Wait, my RouteController@index returns the LIST of routes.
      // But I want the CaveSystem ID to pass to RouteList.
      // Actually RouteList needs routes. It also takes caveSystemId to build "Add" link.

      // Wait, I should fetch cave system routes.
      // But I also need caveSystem ID? I have it from route.params.id.

      // Let's refetch routes specific endpoint: /api/cave_systems/{id}/routes
      const routesData = await response.json()

      caveSystem.value = {
        id: route.params.id,
        routes: routesData
      }
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(load)

watch(() => route.params.id, load)
</script>
