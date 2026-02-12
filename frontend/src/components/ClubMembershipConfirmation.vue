<template>
  <v-container class="pa-4">
    <v-card>
      <v-card-title>
        {{ step === 1 ? 'Confirm Your Identity' : 'BCA Club Membership' }}
      </v-card-title>
      <v-card-text>
        
        <!-- Step 1: Name Confirmation -->
        <div v-if="step === 1">
          <p class="mb-4">
            To help club administrators identify you, please provide your real full name.
            We operate a real-name policy to ensure accountability and safety within the community.
          </p>
          <v-text-field
            v-model="fullName"
            label="Full Name"
            variant="outlined"
            :rules="[v => !!v || 'Name is required', v => v.length >= 2 || 'Name must be at least 2 characters']"
            required
          />
          <v-alert type="info" variant="tonal" class="mt-4" icon="mdi-shield-check">
            This name will be visible to club admins when you request to join.
          </v-alert>
        </div>

        <!-- Step 2: Club Selection -->
        <div v-else>
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
            Please confirm which BCA club(s) you are a member of. Your request will be sent to the club administrators for approval.
          </p>
          
          <v-alert type="info" variant="tonal" class="mb-6" border="start">
            <div class="text-subtitle-1 font-weight-bold mb-2">Membership Benefits</div>
            <v-row dense>
              <v-col cols="12" sm="6">
                <div class="font-weight-medium mb-1">Available Now:</div>
                <ul class="ml-4 text-body-2">
                  <li>Log your trips & track progress</li>
                  <li>View public cave descriptions</li>
                  <li>Browse cave systems</li>
                </ul>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="font-weight-medium mb-1">After Approval:</div>
                <ul class="ml-4 text-body-2">
                  <li>View cave locations & maps</li>
                  <li>See detailed access information</li>
                  <li>Create safety callouts</li>
                </ul>
              </v-col>
            </v-row>
          </v-alert>
          <v-autocomplete
            ref="clubAutocomplete"
            v-model="selectedClub"
            :items="filteredAvailableClubs"
            item-title="name"
            item-value="id"
            label="Select Club(s)"
            multiple
            chips
            :loading="loadingClubs"
            clearable
            autocomplete="off"
            name="random_unique_club_confirm_field"
          >
            <template #item="{ props: slotProps, item }">
              <v-list-item v-bind="slotProps" :title="item.raw.name" />
            </template>
            <template #no-data>
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
        </div>

      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn v-if="step === 1" color="primary" :disabled="!fullName || fullName.length < 2 || savingName" :loading="savingName" @click="saveName">
          Next
        </v-btn>
        <v-btn v-else color="primary" :disabled="!selectedClub.length || loading" @click="submit">
          Confirm Membership
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
const props = defineProps({
  pendingClubs: {
    type: Array,
    default: () => []
  },
  user: {
    type: Object,
    default: () => ({})
  }
});
const emit = defineEmits(['membershipConfirmed']);

const step = ref(1);
const fullName = ref('');
const savingName = ref(false);

const availableClubs = ref([]);
const loadingClubs = ref(false);
const selectedClub = ref([]);
const loading = ref(false);
const success = ref(false);
const error = ref("");
const clubAutocomplete = ref(null);

// Pre-fill name if user prop changes
watch(() => props.user, (newUser) => {
  if (newUser && newUser.name) {
    fullName.value = newUser.name;
  }
}, { immediate: true });

const saveName = async () => {
  if (!fullName.value) return;
  savingName.value = true;
  try {
    const response = await fetch(`/api/users/${props.user.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ name: fullName.value })
    });
    if (!response.ok) throw new Error('Failed to update name');
    step.value = 2; // Proceed to next step
  } catch (e) {
    error.value = "Failed to save name. Please try again.";
  } finally {
    savingName.value = false;
  }
};

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

// Watch for first club selection to close the autocomplete (helps on mobile to dismiss keyboard)
watch(selectedClub, (newValue) => {
  if (newValue && newValue.length === 1 && clubAutocomplete.value) {
    // Blur the autocomplete after first selection to close the dropdown and dismiss mobile keyboard
    clubAutocomplete.value.blur();
  }
});
</script>
