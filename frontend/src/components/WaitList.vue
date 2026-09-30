<template>
  <v-container>
    <v-row justify="center">
      <v-col cols="12" md="8">
        <div class="text-center mb-6">
          <h1 class="text-h4 font-weight-bold mb-2">Membership Status</h1>
          <p class="text-medium-emphasis">
            Track your club membership confirmations and learn about the features they unlock.
          </p>
        </div>
        <ClubMembershipConfirmation
          :pending-clubs="pendingClubs"
          :user="user"
          @membership-confirmed="fetchPendingClubs"
        />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ClubMembershipConfirmation from './ClubMembershipConfirmation.vue'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const appStore = useAppStore()
const pendingClubs = ref([])
const user = ref({})

// Refresh the user in the shared store, not a local copy: the header banner
// reads the store, and a new pending request there is what starts the app-wide
// approval watcher (useClubApprovalWatcher), which takes over from the old
// 5-second polling loop that lived here and never stopped.
const fetchPendingClubs = async () => {
  const userData = await appStore.getUser(true)
  user.value = userData || {}
  pendingClubs.value = (userData?.clubs || []).filter(c => c.status === 'pending')

  if ((userData?.clubs || []).some(c => c.status === 'approved')) {
    router.push('/trips')
  }
}

onMounted(() => {
  fetchPendingClubs()
})
</script>
