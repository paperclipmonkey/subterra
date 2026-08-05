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
        <div class="d-flex align-center justify-space-between mb-2">
          <h3 class="mb-0">My Clubs:</h3>
          <v-btn
            color="primary"
            variant="tonal"
            size="small"
            icon
            aria-label="Confirm a club membership"
            @click="openJoinClubModal"
          >
            <v-icon :icon="mdiPlus" />
            <v-tooltip activator="parent" location="top">Confirm a club membership</v-tooltip>
          </v-btn>
        </div>
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
        <h3 class="d-flex align-center" style="gap: 8px;">
          Phone Number:
          <v-chip v-if="phoneVerified" size="small" color="success" variant="tonal" :prepend-icon="mdiCheckCircle">Verified</v-chip>
          <v-chip v-else-if="profile.phone" size="small" color="warning" variant="tonal" :prepend-icon="mdiAlertCircleOutline">Unverified</v-chip>
        </h3>
        <v-text-field
          v-model="profile.phone"
          label="Your mobile number"
          outlined
          :rules="phoneRules"
          :error-messages="errorMessages('phone')"
          hint="Must be exactly 11 digits (07...) or 13 characters (+44...)."
          persistent-hint
        />
        <p class="text-caption mt-2">Setting a phone number allows us to pre-fill it in safety callout forms, ensuring you're easily reachable in an emergency. <strong>You'll need to verify it before creating a callout.</strong></p>
        <v-btn
          v-if="profile.phone && !phoneVerified && !phoneDirty"
          class="mt-2 text-none"
          color="primary"
          variant="tonal"
          :prepend-icon="mdiCellphoneCheck"
          @click="showVerify = true"
        >
          Verify this number
        </v-btn>
        <p v-else-if="phoneDirty" class="text-caption text-medium-emphasis mt-1">Save your profile, then verify the new number.</p>

        <PhoneVerify v-model="showVerify" :phone="profile.phone" @verified="onVerified" />
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
          variant="text"
          class="visibility-toggle"
        >
          <v-btn value="public" class="text-none">
            <v-icon start :icon="mdiEarth" />
            Public
          </v-btn>
          <v-btn value="club" class="text-none">
            <v-icon start :icon="mdiAccountGroup" />
            Club Members Only
          </v-btn>
        </v-btn-toggle>
      </div>
      <v-card-actions class="pa-4">
        <v-btn color="error" variant="text" size="small" class="mr-auto" @click="openDeleteModal">Delete Account</v-btn>
        <v-spacer />
        <v-btn color="success" variant="flat" size="large" class="px-8" @click="save">Save Profile</v-btn>
      </v-card-actions>

    </v-card>

    <!-- Confirm Club Membership Modal -->
    <v-dialog v-model="showJoinClubModal" persistent max-width="600px">
      <v-card>
        <v-card-title>
          <span class="text-h5">Confirm Club Membership</span>
        </v-card-title>
        <v-card-text>
          <p class="mb-4">Pick a club you are <strong>already a member of</strong>. One of its administrators will confirm your membership, which unlocks full access.</p>
          <v-autocomplete
            v-model="selectedClubToJoinId"
            label="Select your club"
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
            Request Confirmation
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
import { mdiAccountGroup, mdiAlertCircleOutline, mdiCamera, mdiCellphoneCheck, mdiCheckCircle, mdiEarth, mdiPlus } from '@mdi/js'
import router from '@/router'
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useNotificationStore } from '@/stores/notifications'
import { api } from '@/plugins/api'
import { useFormErrors } from '@/composables/useFormErrors'
import PhoneVerify from '@/components/PhoneVerify.vue'

const { setErrors, clearErrors, errorMessages } = useFormErrors()

const route = useRoute()
const notifications = useNotificationStore()

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
const showVerify = ref(false)
const originalPhone = ref('')

// Editing the number (before saving) means it would need re-verifying, so don't show the
// "Verified" state or the Verify button until the change is saved.
const phoneDirty = computed(() => (profile.value.phone || '') !== originalPhone.value)
const phoneVerified = computed(() => !!profile.value.phone_verified && !phoneDirty.value)

const onVerified = () => {
  profile.value.phone_verified = true
}

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
    notifications.showError(nameError)
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
    profile.value.phone_verified = updatedProfile.phone_verified
    originalPhone.value = updatedProfile.phone || ''
    notifications.showSuccess('Profile updated successfully!')
    router.push({ name: '/profile/[id]', params: { id: route.params.id } }) // Redirect only if necessary
  } catch (error) {
    console.error("Error saving profile:", error)
    setErrors(error)
    if (error.response?.status !== 422) {
      notifications.showError('Failed to save profile: ' + (error.response?.data?.message || error.message || 'Unknown error'))
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
    originalPhone.value = profile.value.phone || ''
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
    notifications.showSuccess('Sent to the club — they will confirm your membership.')
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
    notifications.showSuccess('Your account has been deleted successfully')
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

// The router reuses this component when navigating between profile-edit pages,
// so onMounted won't re-fire — refetch when the id changes.
watch(() => route.params.id, (id, prev) => {
  if (id && id !== prev) fetchProfile()
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

/* Scale the photo to fill the circle (cover) instead of showing it at natural size,
   which would only reveal a crop of a large image. */
.v-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
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

/* Connected segmented control for the trip/callout visibility toggle. */
.visibility-toggle {
  width: 100%;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.22);
  border-radius: 10px;
  overflow: hidden;
}

.visibility-toggle :deep(.v-btn) {
  flex: 1 1 0;
  min-width: 0; /* allow the button to shrink so long labels can wrap instead of overflowing */
  min-height: 48px;
  height: auto;
  border-radius: 0;
}

/* Let "Club Members Only" wrap onto a second line on narrow screens rather than clip. */
.visibility-toggle :deep(.v-btn__content) {
  white-space: normal;
  line-height: 1.15;
}

.visibility-toggle :deep(.v-btn:not(:last-child)) {
  border-right: 1px solid rgba(var(--v-theme-on-surface), 0.22);
}

.visibility-toggle :deep(.v-btn--active) {
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

@media (max-width: 400px) {
  .visibility-toggle :deep(.v-btn) {
    font-size: 0.75rem;
    padding-inline: 8px;
  }
}

.v-card-actions {
  justify-content: flex-end;
}
</style>
