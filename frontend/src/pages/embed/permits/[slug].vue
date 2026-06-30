<template>
  <div class="embed-root pa-3">
    <v-progress-linear v-if="loading" indeterminate />

    <template v-if="permit">
      <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
        <div>
          <h1 class="text-h6 font-weight-bold mb-0">{{ permit.name }}</h1>
          <div v-if="permit.caves?.length" class="text-caption text-grey-darken-1 d-flex align-center">
            <v-icon :icon="mdiMapMarker" size="x-small" class="mr-1" />
            {{ permit.caves.map(c => c.name).join(', ') }}
          </div>
        </div>
        <v-btn
          color="primary"
          size="small"
          :append-icon="mdiOpenInNew"
          :href="bookUrl"
          target="_blank"
          rel="noopener"
        >
          Book on Subterra
        </v-btn>
      </div>

      <PermitCalendar
        v-model:current-month="currentMonth"
        :calendar-data="calendarData"
        :permit-info="calendarPermitInfo"
        readonly
      />

      <div class="text-caption text-grey mt-2 text-center">
        To request a booking,
        <a :href="bookUrl" target="_blank" rel="noopener">sign in to Subterra</a>
        and use the online booking form.
      </div>
    </template>

    <v-alert v-else-if="!loading" type="info" variant="tonal" density="compact">
      This booking calendar is not currently available.
    </v-alert>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { mdiMapMarker, mdiOpenInNew } from '@mdi/js'
import { api } from '@/plugins/api'
import PermitCalendar from '@/components/PermitCalendar.vue'

defineOptions({ name: 'EmbedPermitCalendar' })

const route = useRoute()

const loading = ref(true)
const permit = ref(null)
const calendarData = ref({})
const calendarPermitInfo = ref({})
const currentMonth = ref(new Date())

const slug = computed(() => route.params.slug)

// Deep-link back to the (login-gated) online booking flow on the Subterra app.
const bookUrl = computed(() => `${window.location.origin}/permits/${slug.value}`)

const fetchCalendar = async () => {
  const year = currentMonth.value.getFullYear()
  const month = String(currentMonth.value.getMonth() + 1).padStart(2, '0')
  try {
    const { data } = await api.get(`/api/embed/permits/${slug.value}/calendar`, {
      params: { month: `${year}-${month}` },
      // Public embed: show inline state rather than the app's toast notifications.
      suppressErrorNotification: true,
    })
    permit.value = data.permit || null
    calendarData.value = data.data || {}
    calendarPermitInfo.value = data.permit || {}
  } catch (e) {
    permit.value = null
  } finally {
    loading.value = false
  }
}

watch(currentMonth, fetchCalendar)

onMounted(fetchCalendar)
</script>

<style scoped>
.embed-root {
  max-width: 760px;
  margin: 0 auto;
}
</style>
