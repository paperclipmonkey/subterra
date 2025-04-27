<template>
  <v-container>
    <v-row>
      <v-col>
        <h1>User Administration</h1>
        <v-text-field
          v-model="search"
          append-inner-icon="mdi-magnify"
          label="Search Users (Name, Email, Club)"
          single-line
          hide-details
          class="mb-4"
        ></v-text-field>
        <v-data-table
          :headers="headers"
          :items="users"
          :loading="loading"
          :search="search"
          :items-per-page="1000"
          :sort-by="[{ key: 'created_at', order: 'desc' }]"
          hide-default-footer  
          class="elevation-1"
          item-value="id"
          @click:row="handleRowClick"
        >
          <template v-slot:item.clubs="{ item }">
            <div class="d-flex ga-2 mt-2">
              <v-chip
                v-for="club in item.clubs"
                :key="club.id"
                :color="club.status === 'approved' ? 'green' : 'orange'"
                size="small"
              >
                {{ club.name }}
              </v-chip>
            </div>
          </template>
          <template v-slot:item.is_approved="{ item }">
            <v-btn 
              icon 
              variant="text" 
              size="small" 
              @click.stop="toggleApproval(item)" 
              :loading="item.loadingApproval"
              :color="item.is_approved ? 'green' : 'red'"
            >
              <v-icon>
                {{ item.is_approved ? 'mdi-check-circle' : 'mdi-close-circle' }}
              </v-icon>
              <v-tooltip activator="parent" location="top">{{ item.is_approved ? 'Revoke Approval' : 'Approve User' }}</v-tooltip>
            </v-btn>
          </template>
          <template v-slot:item.is_admin="{ item }">
             <v-btn 
              icon 
              variant="text" 
              size="small" 
              @click.stop="toggleAdmin(item)" 
              :loading="item.loadingAdmin"
              :color="item.is_admin ? 'green' : 'red'"
             >
              <v-icon>
                {{ item.is_admin ? 'mdi-check-circle' : 'mdi-close-circle' }}
              </v-icon>
               <v-tooltip activator="parent" location="top">{{ item.is_admin ? 'Revoke Admin' : 'Make Admin' }}</v-tooltip>
            </v-btn>
          </template>
           <template v-slot:item.created_at="{ item }">
            {{ moment(item.created_at).format('DD/MM/YYYY') }}
          </template>
        </v-data-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import moment from 'moment';
import { ref, onMounted } from 'vue';
import { mande } from 'mande';
import { useRouter } from 'vue-router';

const usersApi = mande('/api/admin/users');
const users = ref([]);
const loading = ref(false);
const search = ref(''); // Added search ref
const router = useRouter();

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Approved', key: 'is_approved', sortable: true, align: 'center' }, // Centered icons
  { title: 'Admin', key: 'is_admin', sortable: true, align: 'center' }, // Centered icons
  { title: 'Clubs', key: 'clubs', sortable: false },
  { title: 'Joined', key: 'created_at', sortable: true }, // Added created_at header
];

const fetchUsers = async () => {
  loading.value = true;
  try {
    const response = await usersApi.get();
    // Add loading flags to each user object
    users.value = (response.data || response).map(user => ({
      ...user,
      loadingApproval: false,
      loadingAdmin: false,
    })); 
  } catch (error) {
    console.error('Error fetching users:', error);
    // Handle error display if needed (e.g., snackbar)
  } finally {
    loading.value = false;
  }
};

// Function to update user in the local array
const updateUserInList = (updatedUser) => {
  const index = users.value.findIndex(u => u.id === updatedUser.id);
  if (index !== -1) {
    // Preserve loading state if needed, or reset it
    users.value[index] = { 
      ...updatedUser, 
      loadingApproval: false, // Reset loading flags
      loadingAdmin: false 
    };
  }
};

const toggleApproval = async (user) => {
  user.loadingApproval = true;
  try {
    const toggleApi = mande(`/api/admin/users/${user.id}/toggle-approval`);
    const updatedUser = await toggleApi.put();
    updateUserInList(updatedUser.data || updatedUser);
  } catch (error) {
    console.error(`Error toggling approval for user ${user.id}:`, error);
    // Handle error display
    user.loadingApproval = false; // Reset loading on error
  } finally {
     // Ensure loading state is always reset
     if (user) user.loadingApproval = false;
  }
};

const toggleAdmin = async (user) => {
  user.loadingAdmin = true;
  try {
    const toggleApi = mande(`/api/admin/users/${user.id}/toggle-admin`);
    const updatedUser = await toggleApi.put();
     updateUserInList(updatedUser.data || updatedUser);
  } catch (error) {
    console.error(`Error toggling admin for user ${user.id}:`, error);
     // Handle error display
    user.loadingAdmin = false; // Reset loading on error
  } finally {
    // Ensure loading state is always reset
    if (user) user.loadingAdmin = false;
  }
};


const handleRowClick = (event, { item }) => {
  // Check if the click target is one of the action buttons to prevent navigation
  // Ensure item is reactive if passed directly, otherwise use item.value if it's a ref
  const targetUser = item; // Assuming item is the raw user object from the array

  // More robust check to ensure we don't navigate when clicking buttons/icons within the row
  let target = event.target;
  while (target && target !== event.currentTarget) {
    if (target.tagName === 'BUTTON' || target.classList.contains('v-icon')) {
      return; // Click was on a button or icon, do nothing
    }
    target = target.parentNode;
  }

  router.push(`/profile/${targetUser.id}`);
};

onMounted(() => {
  fetchUsers();
});
</script>

<style scoped>
.v-data-table :deep(tbody tr) {
  cursor: pointer;
}
/* Optional: Add specific styling for the action buttons if needed */
.v-data-table :deep(td .v-btn) {
  /* Example: Adjust margin if needed */
  /* margin: -8px 0; */
}
</style>
