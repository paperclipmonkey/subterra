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
                  <v-list v-if="pendingMembers.length > 0" lines="one">
                    <v-list-item
                      v-for="pending in pendingMembers"
                      :key="pending.id"
                      :title="pending.name"
                      :subtitle="pending.email"
                    >
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
                          @click="rejectMemberRequest(pending)"
                        />
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
        <v-spacer />
        <v-btn color="blue darken-1" text @click="closeDialog">Cancel</v-btn>
        <v-btn color="blue darken-1" text :loading="saving" @click="saveClubAndMembers">Save</v-btn>
      </v-card-actions>
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
import { useToast } from "vue-toastification"
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
const router = useRouter()
const toast = useToast()

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
  const pendingApi = mande(`/api/admin/clubs/${props.clubSlug}/pending-members`)
  const response = await pendingApi.get()
  pendingMembers.value = (response.data || response).map(user => ({ ...user, loading: false }))
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
const rejectMemberRequest = async (pendingUser) => {
  if (!props.clubSlug) return
  pendingUser.loading = true
  try {
    const rejectApi = mande(`/api/admin/clubs/${props.clubSlug}/members/${pendingUser.id}/reject`)
    await rejectApi.put()
    await fetchPendingMembers()
  } finally { pendingUser.loading = false }
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
    toast.success('Club updated successfully')
  } catch (e) {
    console.error(e)
    toast.error('Failed to update club: ' + (e.message || 'Unknown error'))
  } finally {
    saving.value = false
  }
}
const closeDialog = () => { dialogVisible.value = false }

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
