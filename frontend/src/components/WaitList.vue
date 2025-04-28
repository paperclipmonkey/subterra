<template>
  <v-container>
    <v-row>
      <v-col>
        <ClubMembershipConfirmation
          :pendingClubs="pendingClubs"
          @membershipConfirmed="fetchPendingClubs"
        />
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import ClubMembershipConfirmation from './ClubMembershipConfirmation.vue';

const pendingClubs = ref([]);

const fetchPendingClubs = async () => {
  try {
    const response = await fetch('/api/users/me');
    if (!response.ok) throw new Error('Failed to fetch user clubs');
    const user = (await response.json()).data;
    // Filter clubs with status 'pending'
    pendingClubs.value = (user.clubs || []).filter(c => c.status === 'pending');
  } catch (e) {
    pendingClubs.value = [];
  }
};

onMounted(() => {
  fetchPendingClubs();
});
</script>
