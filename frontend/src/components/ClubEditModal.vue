<template>
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
            <v-badge v-if="pendingMembers.length > 0" color="info" :content="pendingMembers.length" inline />
          </v-tab>
        </v-tabs>
        <v-window v-model="tab">
          <v-window-item value="details">
            <v-container>
              <v-row>
                <v-col cols="12">
                  <v-text-field v-model="editedClub.name" label="Club Name*" required :rules="[rules.required]" />
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="editedClub.slug"
                    label="Club slug*"
                    required
                    :rules="[rules.required, rules.slug]"
                    :disabled="editMode"
                  />
                </v-col>
                <v-col cols="12">
                  <div class="text-subtitle-2 mb-1">Description (Markdown supported)</div>
                  <MilkdownEditor v-model="editedClub.description" placeholder="Write about your club..." />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="editedClub.website" label="Website URL" :rules="[rules.url]" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="editedClub.location" label="Location" />
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
                  <v-autocomplete
                    v-if="appStore.user?.is_admin"
                    v-model="selectedUserToAdd"
                    :items="availableUsers"
                    item-title="name"
                    item-value="id"
                    label="Add Member"
                    return-object
                    clearable
                    @update:model-value="addUserToClub"
                  >
                    <template #item="{ props: slotProps, item }">
                      <v-list-item v-bind="slotProps" :subtitle="item.raw.email" />
                    </template>
                  </v-autocomplete>
                  <v-list v-if="clubMembers.length > 0" lines="one">
                    <v-list-item
                      v-for="member in clubMembers"
                      :key="member.id"
                      :title="member.name"
                      :subtitle="member.email"
                    >
                      <template #append>
                        <v-switch
                          v-model="member.is_club_admin"
                          label="Admin"
                          color="primary"
                          hide-details
                          inset
                          @change="markMemberDataChanged"
                        />
                        <v-btn :icon="mdiDelete" variant="text" color="red" size="small" @click="removeUserFromClub(member)" />
                      </template>
                    </v-list-item>
                  </v-list>
                  <p v-else class="text-grey mt-4">No approved members yet.</p>
                </v-col>
              </v-row>
            </v-container>
          </v-window-item>

          <v-window-item value="pending">
            <v-container>
              <v-row>
                <v-col>
                  <p class="mb-2">Review requests from users wanting to join the club.</p>
                  <div v-if="loadingPending" class="d-flex justify-center align-center py-6">
                    <v-progress-circular indeterminate color="primary" />
                  </div>
                  <v-list v-else-if="pendingMembers.length > 0" lines="one">
                    <v-list-item
                      v-for="pending in pendingMembers"
                      :key="pending.id"
                      :subtitle="pending.email"
                    >
                      <template #title>
                        <span
                          v-if="appStore.user?.is_admin"
                          class="text-primary"
                          style="cursor: pointer; text-decoration: underline;"
                          role="button"
                          tabindex="0"
                          @click="navigateToUserAdmin(pending)"
                          @keydown.enter="navigateToUserAdmin(pending)"
                        >{{ pending.name }}</span>
                        <span v-else>{{ pending.name }}</span>
                      </template>
                      <template #append>
                        <v-btn
                          color="green"
                          variant="text"
                          :icon="mdiCheck"
                          :loading="pending.loading"
                          :disabled="pending.loading"
                          @click="approveMemberRequest(pending)"
                        />
                        <v-btn
                          color="red"
                          variant="text"
                          :icon="mdiClose"
                          :loading="pending.loading"
                          :disabled="pending.loading"
                          @click="openRejectDialog(pending)"
                        />
                      </template>
                    </v-list-item>
                  </v-list>
                  <p v-else-if="!loadingPending" class="text-grey mt-4">No pending join requests.</p>
                </v-col>
              </v-row>
            </v-container>
          </v-window-item>
        </v-window>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <template v-if="tab === 'pending'">
          <v-btn color="blue darken-1" text @click="closeDialog">Close</v-btn>
        </template>
        <template v-else>
          <v-btn color="blue darken-1" text @click="closeDialog">Cancel</v-btn>
          <v-btn color="blue darken-1" text :loading="saving" @click="saveClubAndMembers">Save</v-btn>
        </template>
      </v-card-actions>

      <!-- Reject Member Reason Dialog -->
      <v-dialog v-model="rejectDialog" max-width="500px">
        <v-card>
          <v-card-title class="headline">Reject Membership Request</v-card-title>
          <v-card-text>
            <p>Please select a reason for rejecting <strong>{{ selectedRejectUser?.name }}</strong>:</p>
            <v-radio-group v-model="rejectReason">
              <v-radio label="Incorrect Name (Requires full legal name)" value="incorrect_name" />
              <v-radio label="Other / No reason specified" value="" />
            </v-radio-group>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn color="grey" variant="text" @click="closeRejectDialog">Cancel</v-btn>
            <v-btn color="error" variant="text" @click="confirmReject">Reject</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { mdiCheck, mdiClose, mdiDelete } from '@mdi/js'

// This script is adapted from the admin/clubs.vue modal logic, but expects props for clubSlug and visibility
import { ref, computed, watch, onMounted } from 'vue'
import { mande } from 'mande'
import { useRouter } from 'vue-router'
import MilkdownEditor from '@/components/MilkdownEditor.vue'
import { useNotificationStore } from '@/stores/notifications'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
const props = defineProps({
  clubSlug: { type: String, default: '' },
  modelValue: Boolean,
  initialTab: { type: String, default: 'details' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const dialogVisible = ref(props.modelValue)
watch(() => props.modelValue, v => dialogVisible.value = v)
watch(dialogVisible, v => emit('update:modelValue', v))

const tab = ref(props.initialTab)
watch(() => props.initialTab, v => { if (v) tab.value = v })

const editMode = ref(true) // Always edit mode for club details page
const dialogTitle = computed(() => 'Edit Club')
const rules = {
  required: value => !!value || 'Required.',
  slug: value => /^[a-z0-9-]+$/.test(value || '') || 'Only lowercase letters, numbers, and dashes allowed.',
  url: value => {
    if (!value) return true
    try { new URL(value); return true } catch (_) { return 'Must be a valid URL (e.g., https://example.com)' }
  },
}
const editedClub = ref({})
const clubMembers = ref([])
const pendingMembers = ref([])
const availableUsers = ref([])
const selectedUserToAdd = ref(null)
const memberDataChanged = ref(false)
const saving = ref(false)
const loadingPending = ref(false)
const router = useRouter()
const notifications = useNotificationStore()

const rejectDialog = ref(false)
const selectedRejectUser = ref(null)
const rejectReason = ref('incorrect_name')

const fetchClub = async () => {
  if (!props.clubSlug) return
  const clubApi = mande(`/api/clubs/${props.clubSlug}`)
  const clubResponse = await clubApi.get()
  editedClub.value = clubResponse.data || clubResponse
}
const fetchAvailableUsers = async () => {
  try {
    const usersApi = mande('/api/admin/users')
    const response = await usersApi.get()
    availableUsers.value = response.data || response
  } catch (e) {
    // If not super-admin, this might fail, but we shouldn't block the modal
    console.warn('Could not fetch all users (likely not platform admin)')
    availableUsers.value = []
  }
}
const fetchClubMembers = async () => {
  if (!props.clubSlug) return
  // Use the public (but auth-guarded) endpoint which now includes is_club_admin info
  // This allows Club Admins to see members without needing full platform admin rights
  const membersApi = mande(`/api/clubs/${props.clubSlug}/members`)
  const response = await membersApi.get()

  // Map the response to the format expected by the template
  const members = response.data || response
  clubMembers.value = members.map(m => ({
    id: m.id,
    name: m.name,
    email: m.email,
    is_club_admin: m.is_club_admin || false
  }))

  memberDataChanged.value = false
}
const fetchPendingMembers = async () => {
  if (!props.clubSlug) return
  loadingPending.value = true
  try {
    const pendingApi = mande(`/api/admin/clubs/${props.clubSlug}/pending-members`)
    const response = await pendingApi.get()
    pendingMembers.value = (response.data || response).map(user => ({ ...user, loading: false }))
  } finally {
    loadingPending.value = false
  }
}
const addUserToClub = (user) => {
  if (user && !clubMembers.value.some(m => m.id === user.id)) {
    clubMembers.value.push({ id: user.id, name: user.name, email: user.email, is_club_admin: false })
    markMemberDataChanged()
  }
  selectedUserToAdd.value = null
}
const removeUserFromClub = (memberToRemove) => {
  clubMembers.value = clubMembers.value.filter(m => m.id !== memberToRemove.id)
  markMemberDataChanged()
}
const markMemberDataChanged = () => { memberDataChanged.value = true }
const approveMemberRequest = async (pendingUser) => {
  if (!props.clubSlug) return
  pendingUser.loading = true
  try {
    const approveApi = mande(`/api/admin/clubs/${props.clubSlug}/members/${pendingUser.id}/approve`)
    await approveApi.put()
    await fetchPendingMembers()
    await fetchClubMembers()
    await fetchClub()
  } finally { pendingUser.loading = false }
}
const openRejectDialog = (pendingUser) => {
  selectedRejectUser.value = pendingUser
  rejectReason.value = 'incorrect_name' // Default to name error as it's the focus
  rejectDialog.value = true
}

const closeRejectDialog = () => {
  rejectDialog.value = false
  selectedRejectUser.value = null
  rejectReason.value = ''
}

const confirmReject = async () => {
  if (!selectedRejectUser.value) return
  const user = selectedRejectUser.value
  user.loading = true
  closeRejectDialog()
  try {
    const rejectApi = mande(`/api/admin/clubs/${props.clubSlug}/members/${user.id}/reject`)
    await rejectApi.put({}, { query: { reason: rejectReason.value } })
    await fetchPendingMembers()
  } catch (e) {
    notifications.showError('Failed to reject member: ' + (e.message || 'Unknown error'))
  } finally {
    user.loading = false
  }
}

const rejectMemberRequest = async (pendingUser) => {
  // Legacy or simplified method if needed, but we use dialog now
  openRejectDialog(pendingUser)
}
const saveClubAndMembers = async () => {
  if (!editedClub.value.name) return
  // Fix: validation returns a string error message if invalid, or true if valid.
  // We need to check if it does NOT return true.
  if (editedClub.value.website && rules.url(editedClub.value.website) !== true) return

  saving.value = true
  try {
    // Save club details
    const updateApi = mande(`/api/admin/clubs/${props.clubSlug}`)
    await updateApi.put({
      name: editedClub.value.name,
      slug: editedClub.value.slug,
      description: editedClub.value.description,
      website: editedClub.value.website,
      location: editedClub.value.location,
      is_active: editedClub.value.is_active,
    })
    // Save members if changed
    if (memberDataChanged.value) {
      const membersPayload = {
        members: clubMembers.value.map(m => ({ id: m.id, is_admin: m.is_club_admin, status: 'approved' }))
      }
      const membersApi = mande(`/api/admin/clubs/${props.clubSlug}/members`)
      await membersApi.put(membersPayload)
      memberDataChanged.value = false
    }
    dialogVisible.value = false
    emit('saved')
    notifications.showSuccess('Club updated successfully')
  } catch (e) {
    console.error(e)
    notifications.showError('Failed to update club: ' + (e.message || 'Unknown error'))
  } finally {
    saving.value = false
  }
}
const closeDialog = () => { dialogVisible.value = false }

const navigateToUserAdmin = (user) => {
  dialogVisible.value = false
  router.push({ path: '/admin/users', query: { search: user.email } })
}

onMounted(async () => {
  await fetchClub()
  if (appStore.user?.is_admin) {
    await fetchAvailableUsers()
  }
  await fetchClubMembers()
  await fetchPendingMembers()
  if (props.initialTab) tab.value = props.initialTab
})
</script>
