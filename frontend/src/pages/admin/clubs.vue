<template>
  <v-container>
    <v-row>
      <v-col>
        <h1>Club Administration</h1>
        <v-row justify="space-between" class="mb-4">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              append-inner-icon="mdi-magnify"
              label="Search Clubs (Name, Location)"
              single-line
              hide-details
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="auto" class="d-flex align-center justify-end">
             <v-btn color="primary" @click="openCreateDialog">
               <v-icon start>mdi-plus</v-icon> Create Club
             </v-btn>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="clubs"
          :loading="loading"
          :search="search"
          :items-per-page="1000"
          :sort-by="[{ key: 'name', order: 'asc' }]"
          hide-default-footer
          class="elevation-1"
          item-value="id"
          @click:row="handleRowClick"
        >
          <template v-slot:item.is_enabled="{ item }">
            <v-btn
              icon
              variant="text"
              size="small"
              @click.stop="toggleEnabled(item)"
              :loading="item.loadingEnabled"
              :color="item.is_enabled ? 'green' : 'red'"
            >
              <v-icon>
                {{ item.is_enabled ? 'mdi-check-circle' : 'mdi-close-circle' }}
              </v-icon>
              <v-tooltip activator="parent" location="top">{{ item.is_enabled ? 'Disable Club' : 'Enable Club' }}</v-tooltip>
            </v-btn>
          </template>
           <template v-slot:item.website="{ item }">
             <a :href="item.website" target="_blank" @click.stop>{{ item.website }}</a>
           </template>
           <template v-slot:item.description="{ item }">
             <div class="text-truncate" style="max-width: 200px;" :title="item.description">{{ item.description }}</div>
           </template>
           <!-- Add actions column for explicit edit button -->
           <template v-slot:item.actions="{ item }">
             <v-btn icon variant="text" size="small" @click.stop="openEditDialog(item)">
               <v-icon>mdi-pencil</v-icon>
               <v-tooltip activator="parent" location="top">Edit Club</v-tooltip>
             </v-btn>
           </template>
        </v-data-table>
      </v-col>
    </v-row>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialogVisible" persistent max-width="800px">
      <v-card>
        <v-card-title>
          <span class="headline">{{ dialogTitle }}</span>
        </v-card-title>
        <v-card-text>
          <v-tabs v-model="tab" grow>
             <v-tab value="details">Details</v-tab>
             <v-tab value="members" :disabled="!editMode">Members</v-tab>
             <v-tab value="pending" :disabled="!editMode">
               Pending Requests
               <v-badge color="info" :content="pendingMembers.length" inline v-if="pendingMembers.length > 0"></v-badge>
             </v-tab>
           </v-tabs>
           <v-window v-model="tab">
             <v-window-item value="details">
               <v-container>
                 <v-row>
                   <v-col cols="12">
                     <v-text-field v-model="editedClub.name" label="Club Name*" required :rules="[rules.required]"></v-text-field>
                   </v-col>
                   <v-col cols="12">
                     <v-textarea v-model="editedClub.description" label="Description (Markdown supported)" rows="3"></v-textarea>
                   </v-col>
                   <v-col cols="12" sm="6">
                     <v-text-field v-model="editedClub.website" label="Website URL" :rules="[rules.url]"></v-text-field>
                   </v-col>
                   <v-col cols="12" sm="6">
                     <v-text-field v-model="editedClub.location" label="Location"></v-text-field>
                   </v-col>
                    <v-col cols="12">
                      <v-switch v-model="editedClub.is_enabled" :label="editedClub.is_enabled ? 'Enabled' : 'Disabled'" color="primary"></v-switch>
                    </v-col>
                 </v-row>
               </v-container>
               <small>*indicates required field</small>
             </v-window-item>

             <v-window-item value="members">
                <v-container>
                  <v-row>
                    <v-col>
                      <p class="mb-2">Manage approved club members and designate administrators.</p>
                       <!-- Add User Autocomplete/Search -->
                       <v-autocomplete
                         v-model="selectedUserToAdd"
                         :items="availableUsers"
                         item-title="name"
                         item-value="id"
                         label="Add Member"
                         return-object
                         clearable
                         @update:modelValue="addUserToClub"
                       >
                         <template v-slot:item="{ props, item }">
                           <v-list-item v-bind="props" :subtitle="item.raw.email"></v-list-item>
                         </template>
                       </v-autocomplete>

                      <v-list lines="one" v-if="clubMembers.length > 0">
                        <v-list-item
                          v-for="member in clubMembers"
                          :key="member.id"
                          :title="member.name"
                          :subtitle="member.email"
                        >
                          <template v-slot:append>
                             <v-switch
                               v-model="member.is_club_admin"
                               label="Admin"
                               color="primary"
                               hide-details
                               inset
                               @change="markMemberDataChanged"
                             ></v-switch>
                             <v-btn icon="mdi-delete" variant="text" color="red" @click="removeUserFromClub(member)" size="small"></v-btn>
                          </template>
                        </v-list-item>
                      </v-list>
                      <p v-else class="text-grey mt-4">No approved members yet.</p>
                    </v-col>
                  </v-row>
                </v-container>
             </v-window-item>

             <!-- New Pending Requests Tab -->
             <v-window-item value="pending">
               <v-container>
                 <v-row>
                   <v-col>
                     <p class="mb-2">Review requests from users wanting to join the club.</p>
                     <v-list lines="one" v-if="pendingMembers.length > 0">
                       <v-list-item
                         v-for="pending in pendingMembers"
                         :key="pending.id"
                         :title="pending.name"
                         :subtitle="pending.email"
                       >
                         <template v-slot:append>
                           <v-btn
                             color="green"
                             variant="text"
                             icon="mdi-check"
                             @click="approveMemberRequest(pending)"
                             :loading="pending.loading"
                             :disabled="pending.loading"
                           ></v-btn>
                           <v-btn
                             color="red"
                             variant="text"
                             icon="mdi-close"
                             @click="rejectMemberRequest(pending)"
                             :loading="pending.loading"
                             :disabled="pending.loading"
                           ></v-btn>
                         </template>
                       </v-list-item>
                     </v-list>
                     <p v-else class="text-grey mt-4">No pending join requests.</p>
                   </v-col>
                 </v-row>
               </v-container>
             </v-window-item>

           </v-window>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue darken-1" text @click="closeDialog">Cancel</v-btn>
          <v-btn color="blue darken-1" text @click="saveClubAndMembers" :loading="saving">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'; // Added watch
import { mande } from 'mande'; // Ensure mande is imported
import { useRouter } from 'vue-router';

// --- API Setup ---
const clubsApi = mande('/api/admin/clubs');
const usersApi = mande('/api/admin/users'); // API to fetch all users for autocomplete

// --- Dialog State ---
const dialogVisible = ref(false);
const saving = ref(false);
const editMode = ref(false);
const tab = ref('details'); // For tabs in dialog
const defaultClub = {
  id: null,
  name: '',
  description: '',
  website: '',
  location: '',
  member_count: 0, // Not editable here, managed by user association
  is_enabled: true,
  loadingEnabled: false, // Keep internal state consistent
};
const editedClub = ref({ ...defaultClub });
const clubMembers = ref([]); // Now represents *approved* members
const pendingMembers = ref([]); // State for pending members
const availableUsers = ref([]); // All users for the autocomplete
const selectedUserToAdd = ref(null);
const memberDataChanged = ref(false); // Flag to track if member list/status changed

const dialogTitle = computed(() => (editMode.value ? 'Edit Club' : 'Create Club'));

// --- Validation Rules ---
const rules = {
  required: value => !!value || 'Required.',
  url: value => {
    if (!value) return true; // Allow empty
    try {
      new URL(value);
      return true;
    } catch (_) {
      return 'Must be a valid URL (e.g., https://example.com)';
    }
  },
};


// --- Data Fetching ---
const clubs = ref([]);
const loading = ref(false);
const search = ref('');
const router = useRouter();

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Description', key: 'description', sortable: false, width: '250px' }, // Give description more space
  { title: 'Website', key: 'website', sortable: false },
  { title: 'Location', key: 'location', sortable: true },
  { title: 'Members', key: 'member_count', sortable: true, align: 'center' },
  { title: 'Enabled', key: 'is_enabled', sortable: true, align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }, // Added Actions column header
];

const fetchClubs = async () => {
  loading.value = true;
  try {
    // Use actual API
    const response = await clubsApi.get();
    // Adjust based on actual API response structure (e.g., response.data if nested)
    clubs.value = (response.data || response).map(club => ({
      ...club,
      loadingEnabled: false, // Initialize loading flag for UI
    }));
  } catch (error) {
    console.error('Error fetching clubs:', error);
    // Handle error display (e.g., snackbar)
  } finally {
    loading.value = false;
  }
};


// Fetch all users for the member add dropdown
const fetchAvailableUsers = async () => {
  try {
    const response = await usersApi.get(); // Assuming admin/users returns all users
    availableUsers.value = response.data || response;
  } catch (error) {
    console.error("Error fetching available users:", error);
    availableUsers.value = []; // Reset on error
  }
};

// Fetch *approved* members for the specific club being edited
const fetchClubMembers = async (clubId) => { // Renamed internally, still fetches approved
  if (!clubId) {
    clubMembers.value = [];
    return;
  }
  try {
    // Use the endpoint that gets approved members
    const membersApi = mande(`/api/admin/clubs/${clubId}/members`);
    const response = await membersApi.get();
    clubMembers.value = response.data || response;
    memberDataChanged.value = false; // Reset change flag after fetching
  } catch (error) {
    console.error(`Error fetching approved members for club ${clubId}:`, error);
    clubMembers.value = [];
  }
};

// Fetch *pending* members for the specific club being edited
const fetchPendingMembers = async (clubId) => {
  if (!clubId) {
    pendingMembers.value = [];
    return;
  }
  try {
    const pendingApi = mande(`/api/admin/clubs/${clubId}/pending-members`);
    const response = await pendingApi.get();
    // Add loading flag for UI feedback during approve/reject
    pendingMembers.value = (response.data || response).map(user => ({ ...user, loading: false }));
  } catch (error) {
    console.error(`Error fetching pending members for club ${clubId}:`, error);
    pendingMembers.value = [];
  }
};

const updateClubInList = (updatedClub) => {
  // Ensure the updatedClub from the API response is used
  const clubData = updatedClub.data || updatedClub;
  const index = clubs.value.findIndex(c => c.id === clubData.id);
  if (index !== -1) {
    clubs.value[index] = {
      ...clubData,
      loadingEnabled: false, // Reset loading flag
    };
  } else {
     clubs.value.push({
       ...clubData,
       loadingEnabled: false,
     });
  }
};

const toggleEnabled = async (club) => {
  club.loadingEnabled = true;
  try {
    // Use actual API endpoint
    const toggleApi = mande(`/api/admin/clubs/${club.id}/toggle-enabled`);
    const updatedClub = await toggleApi.put();
    updateClubInList(updatedClub); // Update list with response data
  } catch (error) {
    console.error(`Error toggling enabled status for club ${club.id}:`, error);
    // Handle error display
    club.loadingEnabled = false; // Reset loading on error
  }
  // No finally needed if updateClubInList handles the flag on success
};

const handleRowClick = (event, { item }) => {
  // Prevent navigation if clicking interactive elements
  let target = event.target;
  while (target && target !== event.currentTarget) {
    // Include check for the edit button's parent elements if necessary
    if (target.tagName === 'BUTTON' || target.closest('button') || target.classList.contains('v-icon') || target.tagName === 'A') {
      return;
    }
    target = target.parentNode;
  }
  // Instead of navigating, open the edit dialog
  openEditDialog(item);
};

// --- Dialog Actions ---
const openCreateDialog = () => {
  editMode.value = false;
  editedClub.value = { ...defaultClub };
  clubMembers.value = []; // Clear members for new club
  pendingMembers.value = []; // Clear pending members
  memberDataChanged.value = false;
  tab.value = 'details'; // Reset to details tab
  dialogVisible.value = true;
  fetchAvailableUsers(); // Fetch users for potential adding (though can't add until saved)
};

const openEditDialog = (club) => {
  editMode.value = true;
  editedClub.value = JSON.parse(JSON.stringify(club));
  tab.value = 'details'; // Reset to details tab
  dialogVisible.value = true;
  fetchClubMembers(club.id); // Fetch approved members
  fetchPendingMembers(club.id); // Fetch pending members
  fetchAvailableUsers(); // Fetch users for adding members
};

const closeDialog = () => {
  dialogVisible.value = false;
  saving.value = false;
  // Reset editedClub after dialog transition finishes (optional, good practice)
  setTimeout(() => {
    editedClub.value = { ...defaultClub };
    clubMembers.value = [];
    pendingMembers.value = [];
    memberDataChanged.value = false;
    editMode.value = false;
    tab.value = 'details';
    selectedUserToAdd.value = null;
  }, 300);
};

// --- Member Management ---
const addUserToClub = (user) => {
  if (user && !clubMembers.value.some(m => m.id === user.id)) {
    clubMembers.value.push({
      id: user.id,
      name: user.name,
      email: user.email,
      is_club_admin: false // Default to not admin
    });
    markMemberDataChanged();
  }
  // Clear selection after adding
  selectedUserToAdd.value = null;
};

const removeUserFromClub = (memberToRemove) => {
  clubMembers.value = clubMembers.value.filter(m => m.id !== memberToRemove.id);
  markMemberDataChanged();
};

const markMemberDataChanged = () => {
  memberDataChanged.value = true;
}

// --- Pending Member Actions ---
const approveMemberRequest = async (pendingUser) => {
  if (!editedClub.value.id) return;
  pendingUser.loading = true;
  try {
    const approveApi = mande(`/api/admin/clubs/${editedClub.value.id}/members/${pendingUser.id}/approve`);
    await approveApi.put();
    // Refresh both lists after approval
    await fetchPendingMembers(editedClub.value.id);
    await fetchClubMembers(editedClub.value.id);
    // Optionally update club count
    const refreshApi = mande(`/api/clubs/${editedClub.value.id}`);
    const refreshedClubResponse = await refreshApi.get();
    updateClubInList(refreshedClubResponse.data || refreshedClubResponse);
  } catch (error) {
    console.error(`Error approving member ${pendingUser.id}:`, error);
  } finally {
    pendingUser.loading = false;
  }
};

const rejectMemberRequest = async (pendingUser) => {
  if (!editedClub.value.id) return;
  pendingUser.loading = true;
  try {
    const rejectApi = mande(`/api/admin/clubs/${editedClub.value.id}/members/${pendingUser.id}/reject`);
    await rejectApi.put();
    // Refresh pending list after rejection
    await fetchPendingMembers(editedClub.value.id);
  } catch (error) {
    console.error(`Error rejecting member ${pendingUser.id}:`, error);
  } finally {
    pendingUser.loading = false;
  }
};


// Combined save function - only saves details and *approved* members list
const saveClubAndMembers = async () => {
  // Basic validation check
  if (!editedClub.value.name) {
     console.error("Club name is required.");
     // Optionally show a snackbar error
     return;
  }
   if (editedClub.value.website && !rules.url(editedClub.value.website)) {
      console.error("Invalid website URL.");
      // Optionally show a snackbar error
      return;
   }


  saving.value = true;
  try {
    // 1. Save Club Details
    let savedClubData;
    const clubPayload = {
      name: editedClub.value.name,
      description: editedClub.value.description,
      website: editedClub.value.website,
      location: editedClub.value.location,
      is_enabled: editedClub.value.is_enabled,
    };

    if (editMode.value) {
      const updateApi = mande(`/api/admin/clubs/${editedClub.value.id}`);
      const response = await updateApi.put(clubPayload);
      savedClubData = response.data || response;
    } else {
      const response = await clubsApi.post(clubPayload);
      savedClubData = response.data || response;
      editedClub.value.id = savedClubData.id;
      editMode.value = true;
    }

    // 2. Save *Approved* Members (Sync Endpoint)
    if (memberDataChanged.value && editedClub.value.id) {
       const membersPayload = {
         members: clubMembers.value.map(m => ({
           id: m.id,
           is_admin: m.is_club_admin,
           status: 'approved' // Ensure status is set for backend sync
         }))
       };
       // Use the endpoint that syncs approved members
       const membersApi = mande(`/api/admin/clubs/${editedClub.value.id}/members`);
       await membersApi.put(membersPayload);
       memberDataChanged.value = false;

       // Refetch club data for updated member_count
       const refreshApi = mande(`/api/clubs/${editedClub.value.id}`);
       const refreshedClubResponse = await refreshApi.get();
       updateClubInList(refreshedClubResponse.data || refreshedClubResponse);

    } else {
       // If members didn't change, still update list with the saved club details
       updateClubInList(savedClubData);
    }

    closeDialog(); // This closes the dialog after all saves are successful

  } catch (error) {
    console.error(`Error saving club or members:`, error);
    // Handle error display (e.g., using a snackbar component)
    // Consider showing specific validation errors if status is 422
    // The dialog will remain open if an error occurs here.
  } finally {
    saving.value = false;
  }
};


onMounted(() => {
  fetchClubs();
});

// Watch for dialog visibility to reset tab
watch(dialogVisible, (newVal) => {
  if (!newVal) {
    tab.value = 'details';
  }
});

</script>

<style scoped>
.v-data-table :deep(tbody tr) {
  cursor: pointer; /* Keep pointer cursor for row click to edit */
}
</style>
