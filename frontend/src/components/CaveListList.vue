<template>
  <v-container class="pa-0">
    <template v-if="caveStore.loading">
      <div class="d-flex justify-center my-8">
        <v-progress-circular indeterminate color="primary" size="48" />
      </div>
    </template>

    <template v-else>
      <div v-if="caveStore.caves.length === 0" class="text-center py-8">
        <v-icon size="64" color="grey lighten-10" icon="mdi-map-marker-off" class="mb-4" />
        <h3 class="text-h6 font-weight-medium text-grey-darken-1">No caves found</h3>
        <p class="text-body-2 text-grey-darken-1">Try adjusting your filters or search.</p>
      </div>

      <v-row v-else class="px-2">
        <v-col v-for="cave in caveStore.caves" :key="cave.id" cols="12" sm="6" md="4" lg="3">
          <v-card hover elevation="2" class="fill-height d-flex flex-column cave-card"
                  :to="'/caves/' + cave.slug">
            <v-img :src="cave.hero_image?.url || cave.entrance_image?.url || '/placeholder-cave.jpg'" height="160" cover
                   class="bg-grey-lighten-2">
              <template #placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <v-icon color="grey-lighten-1" size="large">mdi-image-off-outline</v-icon>
                </div>
              </template>
              <div v-if="cave.previously_done" class="d-flex justify-end pa-2">
                <v-chip color="success" size="small" variant="elevated" prepend-icon="mdi-check">Done</v-chip>
              </div>
            </v-img>

            <div class="pa-4 d-flex flex-column flex-grow-1">
              <div class="mb-2">
                <h3 class="text-h6 font-weight-bold lh-tight mb-1 text-truncate">{{ cave.name }}</h3>
                <div class="d-flex align-center text-caption text-grey-darken-1">
                  <v-icon size="small" icon="mdi-map-marker" class="mr-1" />
                  <span class="text-truncate">{{ cave.location_name }}, {{ cave.location_country }}</span>
                </div>
              </div>

              <div class="d-flex align-center ga-4 mb-3">
                <div class="d-flex flex-column">
                  <span class="text-caption text-grey">Length</span>
                  <span class="font-weight-medium">
                    {{ cave.system?.length ? Math.round((cave.system.length / 1000) * 10) / 10 + ' km' : '-' }}
                  </span>
                </div>
                <!-- Add vertical divider if needed -->
                <div class="d-flex flex-column">
                  <span class="text-caption text-grey">Vertical</span>
                  <span class="font-weight-medium">
                    {{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '-' }}
                  </span>
                </div>
              </div>

              <div class="mt-auto">
                <v-chip-group class="mb-0">
                  <v-chip
                    v-for="tag in (cave.tags || []).slice(0, 3)"
                    :key="tag.tag"
                    size="x-small"
                    variant="tonal"
                    style="cursor: pointer;"
                    @click.stop.prevent="emit('tag-click', tag.tag)"
                  >
                    {{ tag.tag }}
                  </v-chip>
                  <v-chip v-if="(cave.tags || []).length > 3" size="x-small" variant="text" class="px-1 text-grey">
                    +{{ cave.tags.length - 3 }}
                  </v-chip>
                </v-chip-group>
              </div>
            </div>

            <v-divider />

            <div class="pa-2 d-flex justify-end">
              <v-btn v-if="!cave.previously_done" variant="text" color="primary" size="small"
                     @click.stop.prevent="showConfirmModal = true; caveToMark = cave">
                Mark as Done
              </v-btn>
              <v-btn variant="text" size="small" color="grey-darken-1" :to="'/caves/' + cave.slug">
                Details
              </v-btn>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <v-dialog v-model="showConfirmModal" max-width="400">
      <v-card class="rounded-lg">
        <v-card-title class="text-h6 pa-4">Mark Cave as Done?</v-card-title>
        <v-card-text class="pt-0 pb-4 text-body-1">
          Are you sure you want to mark <strong>{{ caveToMark?.name }}</strong> as visited?
        </v-card-text>
        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn variant="text" @click="showConfirmModal = false; caveToMark = null">Cancel</v-btn>
          <v-btn color="primary" variant="flat" @click="markAsDone(caveToMark)">Confirm</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
<script setup>

import { ref, defineEmits } from 'vue'

const emit = defineEmits(['tag-click'])
import { useCaveStore } from '@/stores/caves'
import { useAppStore } from '@/stores/app'
import { markCaveAsDone } from '@/stores/markAsDone'

const caveStore = useCaveStore()
const appStore = useAppStore()
const showConfirmModal = ref(false)
const caveToMark = ref(null)

const markAsDone = async (cave) => {
  if (!cave) return
  const ok = await markCaveAsDone({ cave, userId: appStore.user.id })
  if (ok) {
    await caveStore.getList()
    showConfirmModal.value = false
    caveToMark.value = null
  } else {
    console.error('failed to save trip')
  }
}
</script>