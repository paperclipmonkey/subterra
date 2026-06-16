<template>
  <div>
    <v-container v-if="loading" class="py-12">
      <v-skeleton-loader type="image, article" />
    </v-container>

    <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center py-12">
      <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
      <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
      <p class="text-body-1 text-grey mb-6">{{ error }}</p>
      <v-btn color="primary" variant="flat" :prepend-icon="mdiArrowLeft" to="/bookings">
        Back to Permits
      </v-btn>
    </v-container>

    <template v-else-if="permit">
      <!-- Hero -->
      <v-img
        :src="permit.photo?.url || '/placeholder-cave.jpg'"
        :srcset="permit.photo?.srcset || undefined"
        height="300"
        cover
        class="align-end"
        gradient="to top, rgba(0,0,0,0.85), rgba(0,0,0,0.1) 60%"
      >
        <div class="position-absolute top-0 left-0 pa-4 d-flex w-100" style="z-index: 1;">
          <v-btn :icon="mdiArrowLeft" variant="tonal" color="white" class="backdrop-blur" @click="$router.push('/bookings')" />
          <v-spacer />
          <v-btn
            v-if="canEdit"
            variant="elevated"
            color="white"
            class="text-primary"
            :prepend-icon="mdiPencil"
            :to="`/admin/permits?edit=${permit.slug}`"
          >
            Edit
          </v-btn>
        </div>
        <v-container class="pb-6">
          <div class="d-flex flex-wrap ga-2 mb-2">
            <v-chip v-if="permit.auto_approve" color="success" size="small" variant="flat">
              <v-icon start :icon="mdiCheckDecagram" /> Auto-approved
            </v-chip>
            <v-chip v-else color="warning" size="small" variant="flat">
              <v-icon start :icon="mdiAccountClock" /> Reviewed by officer
            </v-chip>
            <v-chip v-if="permit.has_max_groups_per_day" size="small" variant="flat" color="white" class="text-grey-darken-3">
              Max {{ permit.max_groups_per_day }} group{{ permit.max_groups_per_day !== 1 ? 's' : '' }}/day
            </v-chip>
          </div>
          <h1 class="text-h3 text-white font-weight-bold">{{ permit.name }}</h1>
          <div v-if="permit.caves?.length" class="text-subtitle-1 text-white mt-1 d-flex align-center">
            <v-icon :icon="mdiMapMarker" size="small" class="mr-1" />
            {{ permit.caves.map(c => c.name).join(', ') }}
          </div>
        </v-container>
        <div v-if="permit.photo?.photographer || permit.photo?.copyright" class="photo-credit text-caption">
          <v-icon size="x-small" :icon="mdiCamera" class="mr-1" />
          <span v-if="permit.photo.photographer">{{ permit.photo.photographer }}</span>
          <span v-if="permit.photo.photographer && permit.photo.copyright"> · </span>
          <span v-if="permit.photo.copyright">{{ permit.photo.copyright }}</span>
        </div>
      </v-img>

      <v-container class="py-6">
        <v-row>
          <!-- Main -->
          <v-col cols="12" md="8">
            <v-card class="rounded-lg mb-4" elevation="2">
              <v-card-title class="d-flex align-center py-4">
                <v-icon :icon="mdiInformationOutline" class="mr-2 text-primary" />
                About this permit
              </v-card-title>
              <v-divider />
              <v-card-text class="pa-5">
                <MarkdownRenderer v-if="permit.description" :source="permit.description" />
                <p v-else class="text-grey font-italic mb-0">No description provided.</p>
              </v-card-text>
            </v-card>

            <v-card v-if="permit.conditions" class="rounded-lg mb-4" elevation="2">
              <v-card-title class="d-flex align-center py-4">
                <v-icon :icon="mdiClipboardCheckOutline" class="mr-2 text-primary" />
                Conditions
              </v-card-title>
              <v-divider />
              <v-card-text class="pa-5">
                <MarkdownRenderer :source="permit.conditions" />
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Sidebar -->
          <v-col cols="12" md="4">
            <v-card class="rounded-lg mb-4" elevation="2">
              <v-list density="comfortable">
                <v-list-item v-if="permit.has_max_participants" :prepend-icon="mdiAccountGroup">
                  <v-list-item-title>Up to {{ permit.max_participants }} per booking</v-list-item-title>
                </v-list-item>
                <v-list-item v-if="permit.has_season" :prepend-icon="mdiCalendarRange">
                  <v-list-item-title>Season: {{ permit.season_start }} → {{ permit.season_end }}</v-list-item-title>
                </v-list-item>
                <v-list-item v-if="permit.officers?.length" :prepend-icon="mdiShieldAccount">
                  <v-list-item-title>Access officers</v-list-item-title>
                  <v-list-item-subtitle>{{ permit.officers.map(o => o.name).join(', ') }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
              <v-divider />
              <v-card-text>
                <v-btn
                  v-if="applyTarget"
                  block
                  color="primary"
                  size="large"
                  :prepend-icon="mdiCalendarCheck"
                  :to="applyTarget"
                >
                  View availability &amp; apply
                </v-btn>
                <p v-else class="text-caption text-grey mb-0">No cave is linked to this permit yet.</p>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import {
  mdiAccountClock,
  mdiAccountGroup,
  mdiAlertCircleOutline,
  mdiArrowLeft,
  mdiCalendarCheck,
  mdiCalendarRange,
  mdiCamera,
  mdiCheckDecagram,
  mdiClipboardCheckOutline,
  mdiInformationOutline,
  mdiMapMarker,
  mdiPencil,
  mdiShieldAccount,
} from '@mdi/js'
import { api } from '@/plugins/api'
import { useAppStore } from '@/stores/app'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

const route = useRoute()
const appStore = useAppStore()

const permit = ref(null)
const loading = ref(true)
const error = ref(null)

const applyTarget = computed(() => {
  const slug = permit.value?.caves?.[0]?.slug
  return slug ? `/caves/${slug}/bookings` : null
})

const canEdit = computed(() => {
  const user = appStore.user
  if (!user?.id) return false
  if (user.roles?.some(r => r.slug === 'platform_admin')) return true
  return (permit.value?.officers || []).some(o => o.id === user.id)
})

const loadPermit = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/api/permits/${route.params.slug}`, { suppressErrorNotification: true })
    permit.value = data.data || data
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'Permit not found. It may have been removed or is no longer active.'
    } else {
      error.value = 'Failed to load this permit. Please try again later.'
    }
  } finally {
    loading.value = false
  }
}

onMounted(loadPermit)
</script>

<style scoped>
.backdrop-blur {
  backdrop-filter: blur(4px);
}

.photo-credit {
  position: absolute;
  right: 8px;
  bottom: 4px;
  z-index: 1;
  color: rgba(255, 255, 255, 0.75);
}
</style>
