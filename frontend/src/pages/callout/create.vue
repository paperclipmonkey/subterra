<template>
  <v-container>
    <v-row justify="center">
      <v-col cols="12" md="8">
        <v-card class="elevation-2">
          <v-toolbar color="warning">
            <v-toolbar-title>Safety Callout</v-toolbar-title>
          </v-toolbar>

          <!-- Loading State -->
          <v-card-text v-if="loading" class="text-center pa-5">
            <v-progress-circular indeterminate color="warning" />
            <p class="mt-3">Loading...</p>
          </v-card-text>

          <!-- Wizard Form -->
          <v-card-text>
            <!-- Duty Officer Banner -->
            <v-alert v-if="onCallOfficer" color="info" variant="tonal" class="mb-4" border="start"
                     density="compact">
              <div class="d-flex align-center">
                <v-avatar color="primary" size="32" class="mr-3">
                  <v-img v-if="onCallOfficer.photo" :src="onCallOfficer.photo" />
                  <span v-else class="text-h6 white--text">{{ onCallOfficer.name.charAt(0) }}</span>
                </v-avatar>
                <div>
                  <div class="text-caption font-weight-bold">On Call Duty Officer</div>
                  <div class="text-body-2">{{ onCallOfficer.name }} is monitoring open callouts.
                  </div>
                </div>
              </div>
            </v-alert>

            <v-alert v-if="officerError" type="error" border="start" variant="tonal" class="mb-4">
              <div class="text-h6">No Duty Officer On Call</div>
              <div>There is currently no one monitoring callouts. Please leave your callout with a trusted friend.</div>
            </v-alert>

            <v-alert v-if="generalError" type="error" border="start" variant="tonal" class="mb-4" closable @click:close="clearErrors">
              {{ generalError }}
            </v-alert>

            <div v-else-if="!isApproved" class="mb-6 pa-4 bg-grey-lighten-4 rounded border text-center">
              <v-icon size="48" color="grey" class="mb-2" :icon="mdiShieldLock" />
              <h3 class="text-h6 mb-2">Member Access Only</h3>
              <p class="text-body-1 mb-4">Callouts are a safety feature available only to approved club members.</p>
              <v-btn color="primary" :to="`/profile/${currentUser?.id}`">Join a Club</v-btn>
            </div>

            <div v-else :class="{ 'disabled-content': officerError }">
              <v-stepper v-model="step" class="elevation-0">
                <v-stepper-header class="elevation-0" style="box-shadow: none;">
                  <v-stepper-item :complete="step > 1" :value="1" title="Location" />
                  <v-divider />
                  <v-stepper-item :complete="step > 2" :value="2" title="Team" :disabled="step < 2" />
                  <v-divider />
                  <v-stepper-item :complete="step > 3" :value="3" title="Plan" :disabled="step < 3" />
                  <v-divider />
                  <v-stepper-item :value="4" title="Safety" :disabled="step < 4" />
                </v-stepper-header>
              </v-stepper>

              <v-form ref="form" v-model="valid" @submit.prevent="submitCallout">
                <v-window v-model="step" :touch="false">

                  <!-- STEP 1: LOCATION -->
                  <v-window-item :value="1">
                    <div class="pa-4">
                      <p class="text-body-1 mb-4">Where are you going and where are you parking?</p>

                      <v-autocomplete v-model="form.cave_id" label="Cave Entrance" :items="caves"
                                      item-title="name" item-value="id" variant="outlined"
                                      placeholder="Search for a cave..."
                                      autocomplete="off"
                                      :error-messages="errorMessages('cave_id')"
                                      name="cave_search_no_autofill">
                        <template #item="{ props, item }">
                          <v-list-item v-bind="props" :subtitle="item.raw.location_name"
                                       :title="item.raw.name" />
                        </template>
                      </v-autocomplete>

                      <!-- Through Trip Logic -->
                      <v-checkbox v-if="systemEntrancesCount > 1" v-model="isThroughTrip"
                                  label="Through trip" class="mt-2" />

                      <v-expand-transition>
                        <div v-if="isThroughTrip">
                          <v-autocomplete v-model="form.exit_cave_id" label="Exit Cave"
                                          :items="systemEntrances" item-title="name" item-value="id"
                                          variant="outlined" class="mt-2"
                                          autocomplete="off"
                                          name="exit_cave_search_no_autofill" />
                        </div>
                      </v-expand-transition>


                      <v-row>
                        <v-col cols="12" md="6">
                          <v-text-field v-model="form.car_registration" label="Car Registration"
                                        hint="e.g. AB12 CDE" persistent-hint variant="outlined" required
                                        :rules="[v => !!v || 'Registration is required']"
                                        :error-messages="errorMessages('car_registration')"
                                        autocomplete="off" />
                        </v-col>
                        <v-col cols="12" md="6">
                          <v-text-field v-model="form.car_parking" label="Where are you parking?"
                                        hint="e.g. Bull Pot Farm" persistent-hint variant="outlined" required
                                        :rules="[v => !!v || 'Parking location is required']"
                                        :error-messages="errorMessages('car_parking')"
                                        autocomplete="off" />
                        </v-col>
                      </v-row>

                      <div class="mt-6 pa-3 grey lighten-4 rounded">
                        <p class="text-subtitle-2 mb-2">Location & Security</p>
                        <div class="test-body-2 mb-2">
                          To prevent abuse and assist rescue teams, we record your IP address, browser details, and current location when submitting a callout request.
                        </div>
                                                
                        <v-alert v-if="!form.location_data" :type="locationStatus === 'error' ? 'warning' : 'info'" variant="text" density="compact" class="mb-0 pa-0">
                          <div class="d-flex align-center">
                            <v-btn size="small" color="primary" :loading="locationStatus === 'loading'" class="mr-3" :prepend-icon="mdiCrosshairsGps" @click="getLocation">
                              Share Location
                            </v-btn>
                            <span v-if="locationStatus === 'error'" class="text-caption red--text font-weight-bold">Location access is required to proceed. Please enable it in your browser settings.</span>
                            <span v-else class="text-caption">Please allow location access if prompted. Required for safety.</span>
                          </div>
                        </v-alert>
                        <v-alert v-else type="success" density="compact" variant="outlined" class="mb-0 mt-2">
                          <v-icon left color="success" :icon="mdiCheck" />
                          Location captured (Accuracy: {{ Math.round(form.location_data.accuracy) }}m)
                        </v-alert>
                      </div>
                    </div>
                  </v-window-item>

                  <!-- STEP 2: TEAM -->
                  <v-window-item :value="2">
                    <div class="pa-4">
                      <p class="text-body-1 mb-4">Who is on the trip?</p>

                      <!-- Add User Autocomplete -->
                      <v-autocomplete v-model="userSelect" v-model:search="userSearchInput"
                                      label="Add Subterra User" :items="availableUsers" item-title="name"
                                      item-value="id"
                                      variant="outlined" :prepend-inner-icon="mdiAccountSearch" return-object
                                      clearable hint="Type to search for users..."
                                      :loading="isSearching"
                                      autocomplete="off"
                                      @update:model-value="addSubterraUser"
                                      @update:search="onUserSearch"
                                      @focus="onUserSearch('')">
                        <template #item="{ props, item }">
                          <v-list-item v-bind="props">
                            <template #prepend>
                              <v-avatar v-if="item.raw.photo" :image="item.raw.photo" class="mr-3" size="40" />
                              <v-avatar v-else color="primary" class="mr-3" size="40">
                                <span class="text-white">{{ item.raw.name.charAt(0) }}</span>
                              </v-avatar>
                            </template>
                            <template #title>
                              <div class="d-flex align-center">
                                <span class="text-body-1">{{ item.raw.name }}</span>
                                <v-icon v-if="item.raw.has_phone" size="small" color="success" class="ml-2" title="Phone number saved" :icon="mdiPhone" />
                              </div>
                            </template>
                            <template v-if="item.raw.clubs && item.raw.clubs.length > 0" #subtitle>
                              <span class="text-caption text-medium-emphasis">{{ item.raw.clubs.map(c => c.name).join(', ') }}</span>
                            </template>
                          </v-list-item>
                        </template>
                      </v-autocomplete>


                      <div class="mb-4">
                        <div v-for="(p, i) in form.participants" :key="p.local_id" class="mb-3">
                          <v-card variant="outlined" class="pa-3">
                            <div class="d-flex align-center w-100">
                              <v-avatar v-if="p.photo" :image="p.photo" class="mr-4" size="48" />
                              <v-avatar v-else color="primary" class="mr-4" size="48">
                                <v-icon v-if="!p.name" :icon="mdiAccount" />
                                <span v-else class="text-white">{{ p.name.charAt(0) }}</span>
                              </v-avatar>

                              <v-row dense class="flex-grow-1 align-center">
                                <v-col cols="12" :sm="p.hasPhone ? 12 : 6">
                                  <div v-if="p.user_id !== null">
                                    <div class="d-flex align-center">
                                      <v-icon size="small" color="primary" class="mr-2" :icon="mdiAccountCheck" />
                                      <span class="text-subtitle-1 font-weight-bold">{{ p.name }}</span>
                                    </div>
                                    <div v-if="p.clubs && p.clubs.length > 0" class="mt-1 d-flex flex-wrap">
                                      <v-chip v-for="c in p.clubs" :key="c.slug" size="small" color="blue-grey" variant="tonal" class="mr-1 mb-1">
                                        {{ c.name }}
                                      </v-chip>
                                    </div>
                                  </div>
                                  <v-text-field v-else v-model="p.name" label="Guest Name" density="compact" variant="outlined" autocomplete="off"
                                                hide-details :prepend-inner-icon="mdiAccount" class="mr-2" />
                                </v-col>
                                <v-col v-if="!p.hasPhone" cols="12" sm="6">
                                  <v-text-field :model-value="p.phone" label="Phone (Mobile)" density="compact" variant="outlined"
                                                hide-details="auto" placeholder="07... or +44..."
                                                :autocomplete="p.isCurrentUser ? 'tel' : 'off'"
                                                :rules="[validateUKPhone]"
                                                @update:model-value="updatePhone(i, $event)" />
                                  <v-expand-transition>
                                    <div v-if="p.isCurrentUser && p.phone && validateUKPhone(p.phone) === true" class="mt-2 text-right">
                                      <v-btn size="small" color="success" variant="tonal" :prepend-icon="mdiContentSave"
                                             :loading="savingPhone" @click="savePhoneToProfile(p.phone, i)">
                                        Save to Profile
                                      </v-btn>
                                    </div>
                                  </v-expand-transition>
                                </v-col>
                              </v-row>

                              <div class="d-flex flex-column align-center justify-center ml-3" style="min-width: 40px">
                                <v-btn v-if="p.hasPhone" icon color="success" variant="text" size="small" class="mb-1"
                                       @click="$toast.info('This user has a valid phone number saved on their profile.')">
                                  <v-icon size="large" :icon="mdiPhoneCheck" />
                                </v-btn>
                                <v-btn v-if="!p.isCurrentUser" icon color="error" size="small" variant="text"
                                       @click="removeParticipant(i)">
                                  <v-icon :icon="mdiDelete" />
                                </v-btn>
                              </div>
                            </div>
                          </v-card>
                        </div>
                      </div>


                      <v-btn variant="text" color="primary" :prepend-icon="mdiPlus" @click="addManualParticipant">
                        Add Manual Guest
                      </v-btn>

                      <v-alert v-if="phoneError" type="error" density="compact" class="mt-4">
                        You must provide a valid UK mobile phone number (07... or +44...).
                      </v-alert>

                      <v-textarea v-model="form.team_details" label="Additional Team Details"
                                  hint="Any relevant details for the team."
                                  persistent-hint variant="outlined" rows="2" class="mt-6"
                                  :error-messages="errorMessages('team_details')" />
                    </div>
                  </v-window-item>

                  <!-- STEP 3: PLAN -->
                  <v-window-item :value="3">
                    <div class="pa-4">
                      <p class="text-body-1 mb-4">What is the plan?</p>
                      <v-textarea v-model="form.trip_plan" label="Trip Plan / Route"
                                  hint="Describe your intended route (e.g. 'Through trip from Top to Bottom, exiting via Wretched Rabbit')"
                                  persistent-hint variant="outlined" rows="5"
                                  :error-messages="errorMessages('trip_plan')" />
                    </div>
                  </v-window-item>

                  <!-- STEP 4: SAFETY -->
                  <v-window-item :value="4">
                    <div class="pa-4">
                      <p class="text-body-1 mb-4">When should we call 999?</p>

                      <CalloutTimePicker v-model="form.callout_time" />

                      <!-- Third-Party Consent Notice -->
                      <v-alert type="warning" variant="outlined" density="compact" class="mt-6"
                               :icon="mdiShieldCheck">
                        <div class="text-caption">
                          <strong>Third-Party Consent:</strong> By providing emergency contact details, you confirm that you have their explicit permission to share their personal data with Subterra for rescue purposes.
                        </div>
                      </v-alert>

                      <!-- Privacy Notice -->
                      <v-alert type="info" variant="outlined" density="compact" class="mt-4"
                               :icon="mdiClockOutline">
                        <div class="text-caption">
                          <strong>Privacy Notice:</strong> Your information (including team
                          details) will be securely stored and
                          <strong>automatically deleted 30 days</strong> after your trip completion
                          for your privacy.
                        </div>
                      </v-alert>
                    </div>
                  </v-window-item>
                </v-window>

                <div class="d-flex justify-space-between pa-4">
                  <v-btn v-if="step > 1" variant="text" @click="step--">Back</v-btn>
                  <v-spacer v-else />

                  <v-btn v-if="step < 4" color="primary" :disabled="!canProceed"
                         @click="step++">Next</v-btn>
                  <v-btn v-if="step === 4" color="warning" :loading="processing" :disabled="!isFormValid"
                         @click="submitCallout">
                    Open Callout
                  </v-btn>
                </div>
              </v-form>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Success / Conversion Dialog -->
    <v-dialog v-model="showSuccessDialog" max-width="500">
      <v-card>
        <v-card-title class="headline">Callout Cancelled</v-card-title>
        <v-card-text>
          You've marked yourself safe. Would you like to log this trip?
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="grey" method text @click="showSuccessDialog = false">Close</v-btn>
          <v-btn color="primary" @click="convertToTrip">Log Trip</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Leave Confirmation Dialog -->
    <v-dialog v-model="showLeaveDialog" max-width="500" persistent>
      <v-card>
        <v-card-title class="headline">
          <v-icon color="warning" class="mr-2" :icon="mdiAlert" />
          Leave Without Creating Callout?
        </v-card-title>
        <v-card-text>
          <p class="mb-4">You haven't finished creating your callout. If you leave now, your progress will be lost.</p>
          <v-alert v-if="step < 4" type="info" variant="tonal" density="compact" class="mb-2">
            <strong>Incomplete steps:</strong>
            <ul class="mt-2 ml-4">
              <li v-if="step < 1 || !form.cave_id || !form.car_registration || !form.car_parking || !form.location_data">Location & Vehicle Details</li>
              <li v-if="step < 2 || phoneError">Team & Contact Information</li>
              <li v-if="step < 3 || !form.trip_plan">Trip Plan</li>
              <li v-if="step < 4">Callout Time</li>
            </ul>
          </v-alert>
          <p class="text-body-2 text-grey-darken-1">For your safety, we recommend completing the callout before heading underground.</p>
        </v-card-text>
        <v-card-actions>
          <v-btn color="grey" variant="text" @click="showLeaveDialog = false">Stay & Complete</v-btn>
          <v-spacer />
          <v-btn color="warning" variant="text" @click="confirmLeave">Leave Anyway</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
import { mdiAccount, mdiAccountCheck, mdiAccountSearch, mdiAlert, mdiCheck, mdiClockOutline, mdiContentSave, mdiCrosshairsGps, mdiDelete, mdiPhone, mdiPhoneCheck, mdiPlus, mdiShieldCheck, mdiShieldLock } from '@mdi/js'
import moment from 'moment'
import { useAppStore } from '@/stores/app'
import { api } from '@/plugins/api'
import { useFormErrors } from '@/composables/useFormErrors'
import CalloutTimePicker from '@/components/CalloutTimePicker.vue'

export default {
  name: 'CalloutView',
  components: {
    CalloutTimePicker
  },
  beforeRouteLeave(to, from, next) {
    // Allow navigation if user clicked "Leave Anyway"
    if (this.allowLeave) {
      this.allowLeave = false // Reset for future navigations
      return next()
    }

    // Allow navigation if they already completed the callout or have an active one
    if (this.activeCallout || !this.form.participants.length) {
      return next()
    }

    // If the form is incomplete, show a warning dialog
    const isFormComplete = this.step === 4 && this.isFormValid
    if (!isFormComplete) {
      this.showLeaveDialog = true
      this.pendingRoute = to // Store where they want to go
      next(false) // Cancel navigation
    } else {
      next() // Allow navigation
    }
  },
  setup() {
    const { setErrors, clearErrors, errorMessages, generalError } = useFormErrors()
    return {
      setErrors,
      clearErrors,
      errorMessages,
      generalError,
      mdiAccount,
      mdiAccountCheck,
      mdiAccountSearch,
      mdiAlert,
      mdiCheck,
      mdiClockOutline,
      mdiContentSave,
      mdiCrosshairsGps,
      mdiDelete,
      mdiPhone,
      mdiPhoneCheck,
      mdiPlus,
      mdiShieldCheck,
      mdiShieldLock
    }
  },
  data() {
    return {
      loading: true,
      processing: false,
      savingPhone: false,
      step: 1,
      valid: false,
      activeCallout: null,
      showSuccessDialog: false,
      isThroughTrip: false,
      caves: [],
      users: [],
      currentUser: null,
      userSelect: null,
      form: {
        cave_id: null,
        exit_cave_id: null,
        car_registration: '',
        car_parking: '',
        location_data: null,
        participants: [],
        team_details: '',
        trip_plan: '',
        callout_time: '',
      },
      locationStatus: null,
      onCallOfficer: null,
      officerError: false,
      userSearchInput: '',
      searchTimeout: null,
      isSearching: false,
      showLeaveDialog: false,
      pendingRoute: null, // Store the destination route instead of callback
      allowLeave: false,
    }
  },
  computed: {
    availableUsers() {
      const addedIds = this.form.participants.map(p => p.user_id).filter(id => id)
      return this.users.filter(u => !addedIds.includes(u.id))
    },
    selectedCave() {
      return this.caves.find(c => c.id === this.form.cave_id)
    },
    systemEntrances() {
      if (!this.selectedCave) return []
      if (!this.selectedCave.system) return []
      return this.caves.filter(c => c.system && c.system.id === this.selectedCave.system.id)
    },
    systemEntrancesCount() {
      return this.systemEntrances.length
    },
    phoneError() {
      // The current user *must* have a phone number
      const currentUserParticipant = this.form.participants.find(p => p.user_id === this.currentUser?.id)
      if (!currentUserParticipant || !currentUserParticipant.phone || currentUserParticipant.phone.trim().length === 0) {
        return true
      }

      // Check all participants' phone numbers for valid format (if they are provided)
      for (const p of this.form.participants) {
        if (!p.phone || p.phone === '🔒 Hidden') continue

        const validation = this.validateUKPhone(p.phone)
        if (validation !== true) {
          return true // Found an invalid format
        }
      }

      return false // All checks passed
    },
    canProceed() {
      if (this.officerError) return false
      // Step 1: Cave, Car Details AND Location are now required
      if (this.step === 1) {
        return !!(this.form.cave_id && this.form.car_registration && this.form.car_parking && this.form.location_data)
      }
      if (this.step === 2) return !this.phoneError
      if (this.step === 3) return this.form.trip_plan.length > 0
      return true
    },
    isFormValid() {
      if (!this.form.callout_time || this.phoneError) return false

      const end = moment(this.form.callout_time)
      const now = moment()
      if (end.isValid() && end.isBefore(now)) return false

      return true
    },
    calloutDurationHint() {
      if (!this.form.callout_time) return ''
      const end = moment(this.form.callout_time)
      const now = moment()

      if (!end.isValid()) return ''

      if (end.isBefore(now)) {
        return 'This time is in the past!'
      }

      const duration = moment.duration(end.diff(now))
      const hours = Math.floor(duration.asHours())
      const minutes = duration.minutes()

      let text = `That is ${hours} hours`
      if (minutes > 0) text += ` and ${minutes} minutes`
      text += ' from now.'

      return text
    },
    isApproved() {
      // Check store first then local user object
      const appStore = useAppStore()
      if (appStore.canSuggest) return true
      if (this.currentUser && this.currentUser.clubs && this.currentUser.clubs.some(c => c.status === 'approved')) return true
      return false
    }
  },
  watch: {
    'form.cave_id': function () {
      this.isThroughTrip = false
      this.form.exit_cave_id = null
    }
  },
  async mounted() {
    await Promise.all([
      this.fetchCaves(),
      this.fetchUsers(),
      this.fetchDutyOfficer()
    ])

    if (this.currentUser && this.currentUser.active_callout) {
      this.$router.push('/callout/active')
      return
    }

    this.prefillForm()
    this.loading = false

    // Auto-check location permission
    this.checkLocationPermission()
  },
  methods: {
    formatDate(date) {
      if (!date) return null
      return moment(date).format('MMMM Do, h:mm a')
    },
    validateUKPhone(value) {
      if (!value) return true
      const clean = value.replace(/\s+/g, '')
      if (clean.length === 0) return true

      if (clean.startsWith('07')) {
        return clean.length === 11 || 'Mobile number must be 11 digits'
      }
      if (clean.startsWith('+44')) {
        return clean.length === 13 || 'Number must be 13 chars (+44...)'
      }
      return 'Must start with 07 or +44'
    },
    async checkLocationPermission() {
      if (!navigator.permissions || !navigator.permissions.query) return
      try {
        const result = await navigator.permissions.query({ name: 'geolocation' })
        if (result.state === 'granted') {
          this.getLocation()
        }
      } catch (e) {
        console.log("Permissions API not supported or error", e)
      }
    },
    getLocation() {
      if (!navigator.geolocation) {
        this.locationStatus = 'error'
        return
      }
      this.locationStatus = 'loading'
      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.form.location_data = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            timestamp: position.timestamp
          }
          this.locationStatus = 'success'
        },
        (error) => {
          console.error("Geolocation error", error)
          this.locationStatus = 'error'
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      )
    },
    getCaveName(id) {
      const c = this.caves.find(cave => cave.id === id)
      return c ? c.name : 'Unknown'
    },
    async fetchCaves() {
      try {
        const response = await api.get('/api/caves')
        this.caves = response.data.data
      } catch (e) {
        console.error(e)
      }
    },
    async fetchUsers() {
      try {
        const me = await api.get('/api/users/me')
        this.currentUser = me.data.data
        // Add me to the users list instantly
        this.users = [this.currentUser]

      } catch (e) {
        console.error(e)
      }
    },
    async fetchDutyOfficer() {
      try {
        const response = await api.get('/api/duty-officers/current')
        const data = response.data.data

        if (data.is_covered) {
          this.onCallOfficer = data
          this.officerError = false
        } else {
          this.onCallOfficer = null
          this.officerError = true
        }
      } catch (e) {
        console.error("Failed to fetch duty officer", e)
        this.officerError = true
        this.onCallOfficer = null
      }
    },

    generateId() {
      return Date.now().toString(36) + Math.random().toString(36).substr(2)
    },
    prefillForm() {
      const now = moment()
      this.form.callout_time = now.clone().add(5, 'hours').format('YYYY-MM-DDTHH:mm')

      if (this.currentUser) {
        this.form.participants.push({
          local_id: this.generateId(),
          user_id: this.currentUser.id,
          name: this.currentUser.name,
          phone: this.currentUser.phone ? '🔒 Hidden' : '',
          email: this.currentUser.email,
          locked: !!this.currentUser.phone,
          photo: this.currentUser.photo,
          clubs: this.currentUser.clubs || [],
          hasPhone: !!this.currentUser.phone,
          isCurrentUser: true,
        })
      }
    },
    addSubterraUser(user) {
      if (!user) return
      this.form.participants.push({
        local_id: this.generateId(),
        user_id: user.id,
        name: user.name,
        phone: (user.has_phone || user.phone) ? '🔒 Hidden' : '',
        email: user.email,
        locked: !!(user.has_phone || user.phone),
        photo: user.photo,
        clubs: user.clubs || [],
        hasPhone: user.has_phone || !!user.phone,
        isCurrentUser: false,
      })
      this.userSelect = null
      this.userSearchInput = ''
    },
    addManualParticipant() {
      this.form.participants.push({
        local_id: this.generateId(),
        name: '', phone: '', user_id: null, locked: false,
        photo: null, clubs: [], hasPhone: false, isCurrentUser: false
      })
    },
    removeParticipant(index) {
      this.form.participants.splice(index, 1)
    },
    updatePhone(index, value) {
      this.form.participants[index].phone = value
    },
    async savePhoneToProfile(phone, index) {
      if (!this.currentUser) return
      this.savingPhone = true
      try {
        const payload = {
          name: this.currentUser.name,
          bio: this.currentUser.bio,
          phone: phone,
          email_trophies: this.currentUser.email_trophies ?? true,
          email_tagged: this.currentUser.email_tagged ?? true,
          email_platform_news: this.currentUser.email_platform_news ?? true,
          visibility_addable: this.currentUser.visibility_addable ?? 'public',
        }
        const res = await api.put('/api/users/me', payload)
        this.currentUser = res.data.data

        // Update the form participant to lock it visually
        this.form.participants[index].locked = true
        this.form.participants[index].hasPhone = true
        this.form.participants[index].phone = '🔒 Hidden'

        // Refresh app store user
        const appStore = useAppStore()
        await appStore.getUser()

        this.$toast.success('Phone number saved to your profile.')
      } catch (e) {
        console.error("Failed to save phone:", e)
        const errorMsg = e.response?.data?.message || 'Failed to save phone number.'
        this.$toast.error(errorMsg)
      } finally {
        this.savingPhone = false
      }
    },
    async submitCallout() {
      if (!this.isFormValid) {
        if (!this.form.callout_time || moment(this.form.callout_time).isBefore(moment())) {
          this.setErrors(new Error('Callout time must be in the future.'))
        }
        return
      }

      this.processing = true
      this.clearErrors()
      try {
        const payload = JSON.parse(JSON.stringify(this.form))
        payload.participants.forEach(p => {
          if (p.phone === '🔒 Hidden') {
            p.phone = null
          }
        })

        const response = await api.post('/api/callouts', payload)
        this.activeCallout = response.data.callout

        // Refresh user state to acknowledge the new open callout
        const appStore = useAppStore()
        await appStore.getUser()

        this.$toast.success('Callout activated. Stay safe!')

        // Redirect to the open callout dashboard
        this.$router.push('/callout/active')
      } catch (e) {
        console.error('Callout Error:', e)
        this.setErrors(e)
      } finally {
        this.processing = false
      }
    },
    async cancelCallout() {
      if (!confirm('Are you definitely out and safe?')) return

      this.processing = true
      try {
        await api.post(`/api/callouts/${this.activeCallout.id}/cancel`)
        this.showSuccessDialog = true
        this.$toast.success('Callout cancelled.')
      } catch (e) {
        // Global interceptor handles this
      } finally {
        this.processing = false
      }
    },
    async convertToTrip() {
      this.$router.push({
        name: 'create-trip',
        query: {
          cave_id: this.activeCallout.cave_id,
          exit_cave_id: this.activeCallout.exit_cave_id,
          date: moment().format('YYYY-MM-DD'), // Default to today
        }
      })
    },
    onUserSearch(val) {
      if (this.searchTimeout) clearTimeout(this.searchTimeout)
      if (val === null) return

      this.isSearching = true
      this.searchTimeout = setTimeout(async () => {
        try {
          const response = await api.get(`/api/users?search=${encodeURIComponent(val)}`)
          const matches = response.data.data

          // Merge matches, avoiding duplicates
          const existingIds = this.users.map(u => u.id)
          matches.forEach(match => {
            if (!existingIds.includes(match.id)) {
              this.users.push(match)
            }
          })
        } catch (e) {
          console.error("Search failed", e)
        } finally {
          this.isSearching = false
        }
      }, 300)
    },
    confirmLeave() {
      this.showLeaveDialog = false
      this.allowLeave = true
      // Navigate to the pending route
      if (this.pendingRoute) {
        const route = this.pendingRoute
        this.pendingRoute = null
        this.$router.push(route)
      }
    }
  }
}
</script>

<style scoped>
.disabled-content {
  opacity: 0.5;
  pointer-events: none;
  user-select: none;
}
</style>
