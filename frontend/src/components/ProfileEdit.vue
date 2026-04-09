<template>
  <v-container class="pa-4">
    <v-card class="profile">
      <v-card-title>
        <v-avatar size="64" class="cursor-pointer" @click="triggerPhotoUpload">
          <img :src="photoPreview || profile.photo" alt="Profile Photo">
          <div class="avatar-overlay d-flex align-center justify-center">
            <v-icon color="white" size="small" :icon="mdiCamera" />
          </div>
        </v-avatar>
        <div class="profile-info">
          <h2>{{ profile.name || 'Please set your name' }}</h2>
          <div class="text-caption text-grey mt-1">Tap photo to change</div>
        </div>
      </v-card-title>
      <input ref="photoInput" type="file" accept="image/*" class="d-none" @change="onPhotoSelected">
      <v-divider />
      
      <!-- Name editing section -->
      <div class="name-edit pa-4">
        <h3>Full Name:</h3>
        <v-text-field
          v-model="profile.name"
          label="Your full name (first and last)"
          outlined
          :rules="nameRules"
          :error-messages="errorMessages('name')"
          hint="Your name may be passed to cave rescue as an emergency point of contact and should be your legal first and last name"
          persistent-hint
          required
        />
      </div>
      <v-divider />
      
      <!-- Display User's Clubs -->

      <!-- Display User's Clubs -->
      <div class="clubs pa-4">
        <h3>My Clubs:</h3>
        <v-list v-if="profile.clubs && profile.clubs.length" lines="one">
          <v-list-item
            v-for="club in profile.clubs"
            :key="club.id"
            :title="club.name"
          >
            <template #append>
              <v-chip :color="getClubStatusColor(club.status)" size="small">
                {{ club.status }}
              </v-chip>
            </template>
          </v-list-item>
        </v-list>
        <p v-else>You are not a member of any clubs yet.</p>
        <v-btn color="primary" class="mt-2" @click="openJoinClubModal">Request to Join Club</v-btn>
      </div>

      <v-divider />
      <div class="bio pa-4">
        <h3>Bio:</h3>
        <v-textarea
          v-model="profile.bio"
          label="Bio"
          outlined
          :error-messages="errorMessages('bio')"
          rows="4"
        />
      </div>
      <v-divider />

      <!-- Phone Number section -->
      <div class="phone-edit pa-4">
        <h3>Phone Number:</h3>
        <v-text-field
          v-model="profile.phone"
          label="Your mobile number"
          outlined
          :rules="phoneRules"
          :error-messages="errorMessages('phone')"
          hint="Must be exactly 11 digits (07...) or 13 characters (+44...)."
          persistent-hint
        />
        <p class="text-caption mt-2">Setting a phone number allows us to pre-fill it in safety callout forms, ensuring you're easily reachable in an emergency.</p>
      </div>
      <v-divider />

      <!-- Email Preferences section -->
      <div class="email-prefs pa-4">
        <h3>Email Communications:</h3>
        <v-switch
          v-model="profile.email_trophies"
          label="Trophies"
          hint="Get notified when you earn a new medal"
          persistent-hint
          color="primary"
        />
        <v-switch
          v-model="profile.email_tagged"
          label="Tagged in Trips"
          hint="Get notified when someone tags you in a trip report"
          persistent-hint
          color="primary"
        />
        <v-switch
          v-model="profile.email_platform_news"
          label="Platform News"
          hint="Stay up to date with new features and announcements"
          persistent-hint
          color="primary"
        />
      </div>
      <v-divider />

      <!-- Trip & Callout Visibility section -->
      <div class="trip-visibility pa-4">
        <h3>Addable to Trips & Callouts:</h3>
        <p class="text-body-2 mb-4">Control who can add you to their trip reports and safety callouts. People can always add you if they know your email address.</p>
        <v-btn-toggle
          v-model="profile.visibility_addable"
          mandatory
          color="primary"
          variant="outlined"
        >
          <v-btn value="public">
            <v-icon left :icon="mdiEarth" />
            Public
          </v-btn>
          <v-btn value="club">
            <v-icon left :icon="mdiAccountGroup" />
            Club Members Only
          </v-btn>
        </v-btn-toggle>
      </div>
      <v-card-actions class="pa-4">
        <v-btn color="error" variant="outlined" class="mr-auto" @click="openDeleteModal">Delete Account</v-btn>
        <v-spacer />
        <v-btn color="success" @click="save">Save Profile</v-btn>
      </v-card-actions>

    </v-card>

    <!-- Join Club Modal -->
    <v-dialog v-model="showJoinClubModal" persistent max-width="600px">
      <v-card>
        <v-card-title>
          <span class="text-h5">Request to Join Club</span>
        </v-card-title>
        <v-card-text>
          <p class="mb-4">Please note: You should already be an official member of the club you are requesting to join online. A club administrator will need to approve your request before you gain full access.</p>
          <v-autocomplete
            v-model="selectedClubToJoinId"
            label="Select Club to Join"
            :items="availableClubs"
            item-title="name" 
            item-value="id"
            return-object
            :loading="loadingClubs"
            clearable
            autocomplete="off"
            name="random_unique_club_search_field"
          >
            <template #item="{ props, item }">
              <v-list-item
                v-bind="props"
                :title="item.raw.name"
              />
            </template>
            <template #no-data>
              <v-list-item>
                <v-list-item-title>
                  No clubs available or matching your search.
                </v-list-item-title>
              </v-list-item>
            </template>
          </v-autocomplete>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="blue-darken-1" variant="text" @click="closeJoinClubModal">
            Cancel
          </v-btn>
          <v-btn color="blue-darken-1" variant="text" :disabled="!selectedClubToJoinId" @click="requestToJoinClub">
            Submit Request
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Account Confirmation Modal -->
    <v-dialog v-model="showDeleteModal" persistent max-width="500px">
      <v-card>
        <v-card-title class="text-h5">Confirm Account Deletion</v-card-title>
        <v-card-text>
          <p>Are you sure you want to delete your account? This action cannot be undone.</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="grey" variant="text" @click="closeDeleteModal">Cancel</v-btn>
          <v-btn color="error" variant="text" :loading="deletingAccount" @click="deleteAccount">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirm Legal Name Modal -->
    <v-dialog v-model="showConfirmNameModal" persistent max-width="500px">
      <v-card>
        <v-card-title class="text-h5">Confirm Legal Name</v-card-title>
        <v-card-text>
          <p>Please confirm that <strong>{{ profile.name }}</strong> is your full legal name.</p>
          <p class="text-caption text-grey mt-2">We require full legal names to ensure accurate tagging in safety callouts and emergency situations.</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="grey" variant="text" @click="showConfirmNameModal = false">Cancel</v-btn>
          <v-btn color="success" variant="text" @click="confirmAndSave">Confirm & Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import { mdiAccountGroup, mdiAlert, mdiCamera, mdiEarth } from '@mdi/js'
import router from '@/router'
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from "vue-toastification"
import { api } from '@/plugins/api'
import { useFormErrors } from '@/composables/useFormErrors'

const { setErrors, clearErrors, errorMessages } = useFormErrors()

const route = useRoute()
const toast = useToast()

const profile = ref({
  "name": "",
  "id": 0,
  "photo": "",
  "stats": {},
  "clubs": [],
  "bio": "",
  "phone": "",
  "email_trophies": true,
  "email_tagged": true,
  "email_platform_news": true,
  "visibility_addable": "public",
})

const allClubs = ref([]) // To store all clubs fetched from API
const loadingClubs = ref(false)
const showJoinClubModal = ref(false)
const selectedClubToJoinId = ref(null)
const showDeleteModal = ref(false)
const deletingAccount = ref(false)
const showConfirmNameModal = ref(false)
const isNameConfirmed = ref(false)
const photoInput = ref(null)
const photoPreview = ref(null)
const photoFile = ref(null)

const triggerPhotoUpload = () => {
  photoInput.value?.click()
}

const onPhotoSelected = (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  photoFile.value = file
  photoPreview.value = URL.createObjectURL(file)
}

// Name validation rules
const nameRules = [
  v => !!v || 'Name is required',
  v => {
    const parts = (v || '').trim().split(/\s+/)
    if (parts.length < 2) return 'Please enter both your first and last name'
    for (const part of parts) {
      if (part.length < 2) return 'Each part of your name must be at least 2 characters'
    }
    return true
  },
  v => (v && v.length <= 100) || 'Name must be less than 100 characters',
]

const phoneRules = [
  v => !v || /^(07[0-9]{9}|\+44[0-9]{10})$/.test(v) || 'Must be exactly 11 digits (07...) or 13 characters (+44...) long',
]

// Filter clubs the user can request to join (not already a member or pending)
const availableClubs = computed(() => {
  if (!profile.value.clubs || !allClubs.value) return []
  const userClubIds = profile.value.clubs.map(c => c.slug)
  return allClubs.value.filter(club => !userClubIds.includes(club.slug))
})

const save = async () => {
  // Validate name with rules first
  const nameError = nameRules.map(r => r(profile.value.name)).find(r => r !== true)
  if (nameError) {
    toast.error(nameError)
    return
  }

  if (!isNameConfirmed.value) {
    showConfirmNameModal.value = true
    return
  }

  clearErrors()
  try {
    const formData = new FormData()
    formData.append('name', profile.value.name || '')
    formData.append('bio', profile.value.bio || '')
    formData.append('phone', profile.value.phone || '')
    formData.append('email_trophies', profile.value.email_trophies ? '1' : '0')
    formData.append('email_tagged', profile.value.email_tagged ? '1' : '0')
    formData.append('email_platform_news', profile.value.email_platform_news ? '1' : '0')
    formData.append('visibility_addable', profile.value.visibility_addable || 'public')
    formData.append('_method', 'PUT')
    if (photoFile.value) {
      formData.append('photo', photoFile.value)
    }
    const response = await api.post(`/api/users/me`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const updatedProfile = response.data.data
    // Merge updated data carefully, especially if API doesn't return full profile
    profile.value.name = updatedProfile.name
    profile.value.bio = updatedProfile.bio
    profile.value.phone = updatedProfile.phone
    profile.value.email_trophies = updatedProfile.email_trophies
    profile.value.email_tagged = updatedProfile.email_tagged
    profile.value.email_platform_news = updatedProfile.email_platform_news
    profile.value.visibility_addable = updatedProfile.visibility_addable
    toast.success('Profile updated successfully!')
    router.push({ name: '/profile/[id]', params: { id: route.params.id } }) // Redirect only if necessary
  } catch (error) {
    console.error("Error saving profile:", error)
    setErrors(error)
    if (error.response?.status !== 422) {
      toast.error('Failed to save profile: ' + (error.response?.data?.message || error.message || 'Unknown error'))
    }
  }
}

const confirmAndSave = () => {
  isNameConfirmed.value = true
  showConfirmNameModal.value = false
  save()
}

const fetchProfile = async () => {
  try {
    const response = await api.get(`/api/users/me`)
    profile.value = response.data.data
  } catch (error) {
    console.error("Error fetching profile:", error)
    // Global interceptor handles the toast
  }
}

const fetchAllClubs = async () => {
  loadingClubs.value = true
  try {
    const response = await api.get('/api/clubs') // Assuming this endpoint exists
    allClubs.value = response.data.data // Assuming API returns { data: [...] }
  } catch (error) {
    console.error("Error fetching clubs:", error)
    allClubs.value = [] // Reset on error
  } finally {
    loadingClubs.value = false
  }
}

const openJoinClubModal = () => {
  selectedClubToJoinId.value = null // Reset selection
  showJoinClubModal.value = true
  // Fetch clubs if not already loaded or needs refresh
  if (allClubs.value.length === 0) {
    fetchAllClubs()
  }
}

const closeJoinClubModal = () => {
  showJoinClubModal.value = false
}

const requestToJoinClub = async () => {
  if (!selectedClubToJoinId.value) return

  try {
    const response = await api.post(`/api/clubs/${selectedClubToJoinId.value.slug}/join`, {
      club_id: selectedClubToJoinId.value.id
    })

    // Success!
    closeJoinClubModal()
    toast.success('Club join request submitted! Awaiting approval.')
    // Re-fetch profile data to show the new pending request
    await fetchProfile()

  } catch (error) {
    console.error("Error requesting to join club:", error)
    // Global interceptor handles the toast
  }
}

// Helper to get chip color based on club membership status
const getClubStatusColor = (status) => {
  switch (status) {
    case 'approved': return 'success'
    case 'pending': return 'warning'
    case 'rejected': return 'error'
    default: return 'grey'
  }
}

const openDeleteModal = () => {
  showDeleteModal.value = true
}
const closeDeleteModal = () => {
  showDeleteModal.value = false
}
const deleteAccount = async () => {
  deletingAccount.value = true
  try {
    await api.delete(`/api/users/me`)
    toast.success('Your account has been deleted successfully')
    window.location.href = '/'
  } catch (error) {
    console.error('Error deleting account:', error)
  } finally {
    deletingAccount.value = false
    closeDeleteModal()
  }
}

onMounted(async () => {
  await fetchProfile()
  // Fetch all clubs needed for the join request modal
  // We don't necessarily need to wait for this for the initial profile display
  fetchAllClubs()
})
</script>

<style scoped>
.profile {
  max-width: 800px;
  margin: auto;
}

.profile-info {
  margin-left: 16px;
}

.clubs,
.bio,
.name-edit {
  padding: 16px;
}

h3 {
  margin-bottom: 8px;
}

.v-chip {
  margin: 4px;
}

.v-avatar {
  position: relative;
}

.avatar-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  border-radius: 50%;
  opacity: 0;
  transition: opacity 0.2s;
}

.v-avatar:hover .avatar-overlay {
  opacity: 1;
}

.v-card-actions {
  justify-content: flex-end;
}
</style>
