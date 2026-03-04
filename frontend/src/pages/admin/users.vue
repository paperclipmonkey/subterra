<template>
  <v-container>
    <v-row>
      <v-col>
        <h1>User Administration</h1>
        <v-text-field
          v-model="search"
          append-inner-icon="mdi-magnify"
          label="Search Users (Name, Email)"
          single-line
          hide-details
          class="mb-4"
        />
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
          <template #item.clubs="{ item }">
            <div class="d-flex ga-2 mt-2">
              <v-chip
                v-for="club in item.clubs"
                :key="club.id"
                :color="club.status === 'approved' ? 'green' : 'orange'"
                size="small"
                :style="club.status === 'pending' ? 'cursor: pointer' : ''"
                @click.stop="club.status === 'pending' ? approveMembership(item, club) : null"
              >
                {{ club.name }}
                <v-tooltip v-if="club.status === 'pending'" activator="parent" location="top">
                  Click to approve membership
                </v-tooltip>
              </v-chip>
            </div>
          </template>
          <template #item.roles="{ item }">
            <div class="d-flex ga-1 flex-wrap py-1" @click.stop>
              <v-chip
                v-for="role in allRoles"
                :key="role.slug"
                :color="hasRole(item, role.slug) ? role.color : 'grey-lighten-2'"
                :variant="hasRole(item, role.slug) ? 'flat' : 'outlined'"
                size="small"
                :loading="item.loadingRole === role.slug"
                :style="isSelf(item) ? 'opacity: 0.5; cursor: not-allowed' : 'cursor: pointer'"
                :disabled="isSelf(item)"
                @click.stop="isSelf(item) ? null : toggleRole(item, role.slug)"
              >
                <v-icon start size="x-small">{{ role.icon }}</v-icon>
                {{ role.label }}
                <v-tooltip activator="parent" location="top">
                  {{ isSelf(item) ? 'Cannot modify your own roles' : (hasRole(item, role.slug) ? `Remove ${role.label}` : `Assign ${role.label}`) }}
                </v-tooltip>
              </v-chip>
            </div>
          </template>
          <template #item.created_at="{ item }">
            {{ moment(item.created_at).format('DD/MM/YYYY') }}
          </template>
          <template #item.actions="{ item }">
            <v-btn
              icon
              variant="text"
              size="small"
              color="error"
              :loading="item.loadingDelete"
              @click.stop="confirmDelete(item)"
            >
              <v-icon>mdi-delete</v-icon>
              <v-tooltip activator="parent" location="top">Delete User</v-tooltip>
            </v-btn>
          </template>
        </v-data-table>
      </v-col>
    </v-row>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title class="text-h5 bg-error text-white">
          Delete User?
        </v-card-title>
        <v-card-text class="pt-4">
          Are you sure you want to delete <strong>{{ userToDelete?.name || userToDelete?.email }}</strong>?
          <br><br>
          This will permanently remove their profile, trips, and sensitive safety data. This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="cancelDelete">Cancel</v-btn>
          <v-btn color="error" variant="flat" @click="executeDelete">Delete Permanently</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>

</template>

<script setup>
import moment from 'moment'
import { ref, onMounted } from 'vue'
import { mande } from 'mande'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useNotificationStore } from '@/stores/notifications'

const usersApi = mande('/api/admin/users')
const users = ref([])
const loading = ref(false)
const search = ref('')
const router = useRouter()
const appStore = useAppStore()
const notificationStore = useNotificationStore()

const allRoles = [
  { slug: 'platform_admin', label: 'Platform Admin', color: 'purple', icon: 'mdi-shield-crown' },
  { slug: 'duty_officer', label: 'Duty Officer', color: 'blue', icon: 'mdi-phone-in-talk' },
  { slug: 'data_admin', label: 'Data Admin', color: 'teal', icon: 'mdi-database-edit' },
]

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Roles', key: 'roles', sortable: false, align: 'start' },
  { title: 'Clubs', key: 'clubs', sortable: true },
  { title: 'Joined', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
]

const isSelf = (user) => {
  return appStore.user?.id === user.id
}

const hasRole = (user, slug) => {
  return user.roles && user.roles.some(r => r.slug === slug)
}

const fetchUsers = async () => {
  loading.value = true
  try {
    const response = await usersApi.get()
    users.value = (response.data || response).map(user => ({
      ...user,
      loadingRole: null,
      loadingDelete: false,
    }))
  } catch (error) {
    console.error('Error fetching users:', error)
  } finally {
    loading.value = false
  }
}

// Deletion State
const deleteDialog = ref(false)
const userToDelete = ref(null)

const confirmDelete = (user) => {
  userToDelete.value = user
  deleteDialog.value = true
}

const cancelDelete = () => {
  deleteDialog.value = false
  userToDelete.value = null
}

const executeDelete = async () => {
  if (!userToDelete.value) return

  const user = userToDelete.value
  user.loadingDelete = true
  deleteDialog.value = false

  try {
    const deleteApi = mande(`/api/users/${user.id}`)
    await deleteApi.delete()
    users.value = users.value.filter(u => u.id !== user.id)
  } catch (error) {
    console.error(`Error deleting user ${user.id}:`, error)
    user.loadingDelete = false
  } finally {
    userToDelete.value = null
  }
}

const updateUserInList = (updatedUser) => {
  const index = users.value.findIndex(u => u.id === updatedUser.id)
  if (index !== -1) {
    users.value[index] = {
      ...updatedUser,
      loadingRole: null,
      loadingDelete: false
    }
  }
}

const toggleRole = async (user, roleSlug) => {
  if (isSelf(user)) return
  user.loadingRole = roleSlug
  try {
    const toggleApi = mande(`/api/admin/users/${user.id}/toggle-role/${roleSlug}`)
    const updatedUser = await toggleApi.put()
    updateUserInList(updatedUser.data || updatedUser)
  } catch (error) {
    console.error(`Error toggling role ${roleSlug} for user ${user.id}:`, error)

    let message = `Error toggling role ${roleSlug}`
    if (error.body && error.body.message) {
      message = error.body.message
    } else if (error.body && error.body.errors) {
      // Handle Laravel validation errors structure
      const firstError = Object.values(error.body.errors)[0]
      if (Array.isArray(firstError) && firstError.length > 0) {
        message = firstError[0]
      }
    }

    notificationStore.showError(message)
    user.loadingRole = null
  }
}

const approveMembership = async (user, club) => {
  loading.value = true
  try {
    const approveApi = mande(`/api/admin/clubs/${club.slug}/members/${user.id}/approve`)
    const updatedUser = await approveApi.put()
    updateUserInList(updatedUser.data || updatedUser)
  } catch (error) {
    console.error(`Error approving membership for user ${user.id} in club ${club.slug}:`, error)

    let message = `Error approving membership`
    if (error.body && error.body.message) {
      message = error.body.message
    }

    notificationStore.showError(message)
  } finally {
    loading.value = false
  }
}


const handleRowClick = (event, { item }) => {
  const targetUser = item

  let target = event.target
  while (target && target !== event.currentTarget) {
    if (target.tagName === 'BUTTON' || target.classList.contains('v-icon') || target.classList.contains('v-chip')) {
      return
    }
    target = target.parentNode
  }

  router.push(`/profile/${targetUser.id}`)
}

onMounted(() => {
  fetchUsers()
})
</script>

<style scoped>
.v-data-table :deep(tbody tr) {
  cursor: pointer;
}
</style>
