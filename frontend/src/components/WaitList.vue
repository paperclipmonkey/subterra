<template>
  <v-container>
    <v-row>
      <v-col>
        <ClubMembershipConfirmation
          :pendingClubs="pendingClubs"
          :user="user"
          @membershipConfirmed="fetchPendingClubs"
        />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import ClubMembershipConfirmation from './ClubMembershipConfirmation.vue';

const router = useRouter();
const pendingClubs = ref([]);
const user = ref({});

const fetchPendingClubs = async () => {
  try {
    const response = await fetch('/api/users/me');
    if (!response.ok) throw new Error('Failed to fetch user clubs');
    const userData = (await response.json()).data;
    user.value = userData;
    // Filter clubs with status 'pending'
    pendingClubs.value = (userData.clubs || []).filter(c => c.status === 'pending');
    let approvedClubs = (userData.clubs || []).filter(c => c.status === 'approved');

    if (userData.is_approved) {
      // If we've been approved, redirect to /caves
      router.push('/trips');
    }

    // If there are pending clubs, refresh the list every 5 seconds until a club is approved, then redirect
    if (pendingClubs.value.length) {
      setTimeout(() => {
        fetchPendingClubs();
      }, 5000);
    }
  } catch (e) {
    pendingClubs.value = [];
  }
};

onMounted(() => {
  fetchPendingClubs();
});
</script>
