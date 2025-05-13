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
import { useRouter } from "vue-router";

const pendingClubs = ref([]);

const fetchPendingClubs = async () => {
  try {
    const response = await fetch('/api/users/me');
    if (!response.ok) throw new Error('Failed to fetch user clubs');
    const user = (await response.json()).data;
    // Filter clubs with status 'pending'
    pendingClubs.value = (user.clubs || []).filter(c => c.status === 'pending');
    let approvedClubs = (user.clubs || []).filter(c => c.status === 'approved');

    console.log(approvedClubs);
    if(approvedClubs.length > 0) {
      console.log("Approved clubs found");
      // If we've been approved, redirect to /caves
      window.location.href = '/trips';
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
