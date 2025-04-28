<template>
  <v-container class="pa-4">
    <v-card>
      <v-card-title>Confirm Your BCA Club Membership</v-card-title>
      <v-card-text>
        <div v-if="pendingClubs && pendingClubs.length">
          <h3 class="mb-2">Pending Club Memberships</h3>
          <v-list>
            <v-list-item v-for="club in pendingClubs" :key="club.id">
              <v-list-item-title>{{ club.name }}</v-list-item-title>
              <v-list-item-subtitle>
                <v-chip color="warning" size="small">Pending approval</v-chip>
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
          <v-divider class="my-4" />
        </div>
        <p class="mb-4">
          Please confirm which BCA club(s) you are a member of. Your request will be sent to the club administrators for approval. You will receive an email once your account has been approved.
        </p>
        <v-autocomplete
          v-model="selectedClub"
          :items="filteredAvailableClubs"
          item-title="name"
          item-value="id"
          label="Select Club(s)"
          multiple
          chips
          :loading="loadingClubs"
          clearable
        >
          <template v-slot:item="{ props, item }">
            <v-list-item v-bind="props" :title="item.raw.name"></v-list-item>
          </template>
          <template v-slot:no-data>
            <v-list-item>
              <v-list-item-title>No clubs available or matching your search.</v-list-item-title>
            </v-list-item>
          </template>
        </v-autocomplete>
        <v-alert v-if="success" type="success" class="mt-4">
          Club administrators have been notified. You will receive an email once your account has been approved.
        </v-alert>
        <v-alert v-if="error" type="error" class="mt-4">
          {{ error }}
        </v-alert>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn color="primary" :disabled="!selectedClub.length || loading" @click="submit">
          Confirm Membership
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
const props = defineProps({
  pendingClubs: {
    type: Array,
    default: () => []
  }
});
const emit = defineEmits(['membershipConfirmed']);

const availableClubs = ref([]);
const loadingClubs = ref(false);
const selectedClub = ref([]);
const loading = ref(false);
const success = ref(false);
const error = ref("");

const fetchAllClubs = async () => {
  loadingClubs.value = true;
  try {
    const response = await fetch('/api/clubs');
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    availableClubs.value = (await response.json()).data;
  } catch (e) {
    availableClubs.value = [];
  } finally {
    loadingClubs.value = false;
  }
};

const filteredAvailableClubs = computed(() => {
  // Exclude clubs already pending or approved
  const pendingIds = (props.pendingClubs || []).map(c => c.id);
  return availableClubs.value.filter(c => !pendingIds.includes(c.id));
});

const submit = async () => {
  if (!selectedClub.value.length) return;
  loading.value = true;
  error.value = "";
  success.value = false;
  try {
    // Send a join request for each selected club
    for (const clubId of selectedClub.value) {
      const club = availableClubs.value.find(c => c.id === clubId);
      if (!club) continue;
      const response = await fetch(`/api/clubs/${club.slug}/join`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ club_id: club.id }),
      });
      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
      }
    }
    success.value = true;
    emit('membershipConfirmed');
  } catch (e) {
    error.value = e.message || 'An error occurred.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchAllClubs();
});
</script>
