<template>
    <v-container>
        <v-row justify="center">
            <v-col cols="12" md="8">
                <v-card class="elevation-2">
                    <v-toolbar color="warning" dark>
                        <v-toolbar-title>Safety Callout</v-toolbar-title>
                    </v-toolbar>

                    <!-- Loading State -->
                    <v-card-text v-if="loading" class="text-center pa-5">
                        <v-progress-circular indeterminate color="warning"></v-progress-circular>
                        <p class="mt-3">Loading...</p>
                    </v-card-text>

                    <!-- Wizard Form -->
                    <v-card-text>
                        <!-- Duty Officer Banner -->
                        <v-alert v-if="onCallOfficer" color="info" variant="tonal" class="mb-4" border="start"
                            density="compact">
                            <div class="d-flex align-center">
                                <v-avatar color="primary" size="32" class="mr-3">
                                    <v-img v-if="onCallOfficer.photo" :src="onCallOfficer.photo"></v-img>
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

                        <div :class="{ 'disabled-content': officerError }">
                            <v-stepper v-model="step" vertical class="elevation-0">
                                <v-stepper-header class="elevation-0" style="box-shadow: none;">
                                    <v-stepper-step :complete="step > 1" step="1">Location</v-stepper-step>
                                    <v-divider></v-divider>
                                    <v-stepper-step :complete="step > 2" step="2">Team</v-stepper-step>
                                    <v-divider></v-divider>
                                    <v-stepper-step :complete="step > 3" step="3">Plan</v-stepper-step>
                                    <v-divider></v-divider>
                                    <v-stepper-step step="4">Safety</v-stepper-step>
                                </v-stepper-header>
                            </v-stepper>

                            <v-form ref="form" v-model="valid" @submit.prevent="submitCallout">
                                <v-window v-model="step">

                                    <!-- STEP 1: LOCATION -->
                                    <v-window-item :value="1">
                                        <div class="pa-4">
                                            <p class="text-body-1 mb-4">Where are you going and where are you parking?</p>

                                            <v-autocomplete v-model="form.cave_id" label="Cave Entrance" :items="caves"
                                                item-title="name" item-value="id" outlined
                                                placeholder="Search for a cave..."
                                                autocomplete="off"
                                                name="cave_search_no_autofill">
                                                <template v-slot:item="{ props, item }">
                                                    <v-list-item v-bind="props" :subtitle="item.raw.location_name"
                                                        :title="item.raw.name"></v-list-item>
                                                </template>
                                            </v-autocomplete>

                                            <!-- Through Trip Logic -->
                                            <v-checkbox v-if="systemEntrancesCount > 1" v-model="isThroughTrip"
                                                label="Through trip" class="mt-2"></v-checkbox>

                                            <v-expand-transition>
                                                <div v-if="isThroughTrip">
                                                    <v-autocomplete label="Exit Cave" :items="systemEntrances"
                                                        item-title="name" item-value="id" v-model="form.exit_cave_id"
                                                        outlined class="mt-2"
                                                        autocomplete="off"
                                                        name="exit_cave_search_no_autofill"></v-autocomplete>
                                                </div>
                                            </v-expand-transition>


                                            <v-row>
                                                <v-col cols="12" md="6">
                                                    <v-text-field v-model="form.car_registration" label="Car Registration"
                                                        hint="e.g. AB12 CDE" persistent-hint outlined required
                                                        :rules="[v => !!v || 'Registration is required']"></v-text-field>
                                                </v-col>
                                                <v-col cols="12" md="6">
                                                    <v-text-field v-model="form.car_parking" label="Where are you parking?"
                                                        hint="e.g. Bull Pot Farm" persistent-hint outlined required
                                                        :rules="[v => !!v || 'Parking location is required']"></v-text-field>
                                                </v-col>
                                            </v-row>

                                            <div class="mt-6 pa-3 grey lighten-4 rounded">
                                                <p class="text-subtitle-2 mb-2">Location & Security</p>
                                                <div class="test-body-2 mb-2">
                                                    To prevent abuse and assist rescue teams, we record your IP address, browser details, and current location when submitting a callout request.
                                                </div>
                                                
                                                <v-alert v-if="!form.location_data" :type="locationStatus === 'error' ? 'warning' : 'info'" variant="text" density="compact" class="mb-0 pa-0">
                                                    <div class="d-flex align-center">
                                                        <v-btn small color="primary" @click="getLocation" :loading="locationStatus === 'loading'" class="mr-3">
                                                            <v-icon left>mdi-crosshairs-gps</v-icon> Share Location
                                                        </v-btn>
                                                        <span class="text-caption red--text font-weight-bold" v-if="locationStatus === 'error'">Location access is required to proceed. Please enable it in your browser settings.</span>
                                                        <span class="text-caption" v-else>Please allow location access if prompted. Required for safety.</span>
                                                    </div>
                                                </v-alert>
                                                <v-alert v-else type="success" density="compact" variant="outlined" class="mb-0 mt-2">
                                                    <v-icon left color="success">mdi-check</v-icon>
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
                                            <v-autocomplete label="Add Subterra User" :items="availableUsers"
                                                item-title="name" item-value="id" outlined
                                                prepend-inner-icon="mdi-account-search"
                                                @update:model-value="addSubterraUser" v-model="userSelect" return-object
                                                clearable hint="Search for club members..."></v-autocomplete>

                                            <v-list class="mb-4">
                                                <v-list-item v-for="(p, i) in form.participants" :key="i">
                                                    <v-row align="center" dense>
                                                        <v-col cols="12" sm="5">
                                                            <v-text-field v-model="p.name" label="Name" dense outlined
                                                                hide-details :readonly="p.locked"
                                                                :prepend-icon="p.user_id ? 'mdi-account-check' : 'mdi-account'"></v-text-field>
                                                        </v-col>
                                                        <v-col class="flex-grow-1" cols="auto" sm="5">
                                                            <v-text-field v-model="p.phone" label="Phone (Mobile)" dense
                                                                outlined hide-details placeholder="07... or +44..."
                                                                :rules="[validateUKPhone]"></v-text-field>
                                                        </v-col>
                                                        <v-col cols="auto" sm="2" class="d-flex justify-center">
                                                            <v-btn icon color="error" x-small @click="removeParticipant(i)"
                                                                :disabled="p.locked" style="aspect-ratio: 1;">
                                                                <v-icon>mdi-delete</v-icon>
                                                            </v-btn>
                                                        </v-col>
                                                    </v-row>
                                                </v-list-item>
                                            </v-list>

                                            <v-btn text color="primary" @click="addManualParticipant">
                                                <v-icon left>mdi-plus</v-icon> Add Manual Guest
                                            </v-btn>

                                            <v-alert v-if="phoneError" type="error" dense class="mt-4">
                                                At least one participant must have a valid mobile phone number.
                                            </v-alert>

                                            <v-textarea v-model="form.medical_info" label="Additional Team Details"
                                                hint="Any relevant details (Medical info, experience levels, etc.) for the team."
                                                persistent-hint outlined rows="2" class="mt-6"></v-textarea>
                                        </div>
                                    </v-window-item>

                                    <!-- STEP 3: PLAN -->
                                    <v-window-item :value="3">
                                        <div class="pa-4">
                                            <p class="text-body-1 mb-4">What is the plan?</p>
                                            <v-textarea v-model="form.trip_plan" label="Trip Plan / Route"
                                                hint="Describe your intended route (e.g. 'Through trip from Top to Bottom, exiting via Wretched Rabbit')"
                                                persistent-hint outlined rows="5"></v-textarea>
                                        </div>
                                    </v-window-item>

                                    <!-- STEP 4: SAFETY -->
                                    <v-window-item :value="4">
                                        <div class="pa-4">
                                            <v-alert type="info" text dense class="mb-4">
                                                <strong>Callout Time:</strong> When we call 999. Give yourself a safety
                                                margin!
                                            </v-alert>

                                            <v-text-field v-model="form.callout_time"
                                                label="Callout Alarm Time (Panic Time)" type="datetime-local" outlined
                                                class="mt-4" required
                                                :hint="calloutDurationHint"
                                                persistent-hint></v-text-field>

                                            <!-- Emergency Contact Removed -->

                                            <v-alert type="warning" variant="outlined" density="compact" class="mt-6"
                                                icon="mdi-shield-check">
                                                <div class="text-caption">
                                                    <strong>Privacy Notice:</strong> Your information (including team
                                                    details) will be securely stored and
                                                    <strong>automatically deleted 7 days</strong> after your trip completion
                                                    for your privacy.
                                                </div>
                                            </v-alert>
                                        </div>
                                    </v-window-item>
                                </v-window>

                                <div class="d-flex justify-space-between pa-4">
                                    <v-btn text v-if="step > 1" @click="step--">Back</v-btn>
                                    <v-spacer v-else></v-spacer>

                                    <v-btn color="primary" v-if="step < 4" :disabled="!canProceed"
                                        @click="step++">Next</v-btn>
                                    <v-btn color="warning" v-if="step === 4" @click="submitCallout" :loading="processing"
                                        :disabled="!isFormValid">
                                        Activate Callout
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
                <v-card-title class="headline">Glad you're safe!</v-card-title>
                <v-card-text>
                    Callout has been cancelled. Would you like to turn this callout into a Trip Report?
                    We can pre-fill the details for you.
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn text @click="activeCallout = null; showSuccessDialog = false">No, thanks</v-btn>
                    <v-btn color="primary" @click="convertToTrip">Yes, Create Trip Report</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </v-container>
</template>

<script>
import axios from 'axios';
import moment from 'moment';
import { useAppStore } from '@/stores/app';

export default {
    name: 'CalloutView',
    data() {
        return {
            loading: true,
            processing: false,
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
                medical_info: '',
                trip_plan: '',
                callout_time: '',
            },
            locationStatus: null,
            onCallOfficer: null,
            officerError: false,
        };
    },
    computed: {
        availableUsers() {
            const addedIds = this.form.participants.map(p => p.user_id).filter(id => id);
            return this.users.filter(u => !addedIds.includes(u.id));
        },
        selectedCave() {
            return this.caves.find(c => c.id === this.form.cave_id);
        },
        systemEntrances() {
            if (!this.selectedCave) return [];
            if (!this.selectedCave.system) return [];
            return this.caves.filter(c => c.system && c.system.id === this.selectedCave.system.id);
        },
        systemEntrancesCount() {
            return this.systemEntrances.length;
        },
        phoneError() {
            const hasPhone = this.form.participants.some(p => p.phone && p.phone.trim().length > 0);
            return !hasPhone;
        },
        canProceed() {
            if (this.officerError) return false;
            // Step 1: Cave, Car Details AND Location are now required
            if (this.step === 1) return this.form.cave_id && this.form.car_registration && this.form.car_parking && this.form.location_data;
            if (this.step === 2) return !this.phoneError;
            if (this.step === 3) return this.form.trip_plan.length > 0;
            return true;
        },
        isFormValid() {
            return this.form.callout_time &&
                !this.phoneError;
        },
        calloutDurationHint() {
            if (!this.form.callout_time) return '';
            const end = moment(this.form.callout_time);
            const now = moment();

            if (!end.isValid()) return '';

            if (end.isBefore(now)) {
                return 'This time is in the past!';
            }

            const duration = moment.duration(end.diff(now));
            const hours = Math.floor(duration.asHours());
            const minutes = duration.minutes();

            let text = `That is ${hours} hours`;
            if (minutes > 0) text += ` and ${minutes} minutes`;
            text += ' from now.';

            return text;
        }
    },
    watch: {
        'form.cave_id': function () {
            this.isThroughTrip = false;
            this.form.exit_cave_id = null;
        }
    },
    async mounted() {
        await Promise.all([
            this.fetchCaves(),
            this.fetchUsers(),
            this.fetchDutyOfficer()
        ]);

        if (this.currentUser && this.currentUser.active_callout) {
            this.$router.push('/callout/active');
            return;
        }

        this.prefillForm();
        this.loading = false;

        // Auto-check location permission
        this.checkLocationPermission();
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            return moment(date).format('MMMM Do, h:mm a');
        },
        validateUKPhone(value) {
            if (!value) return true;
            const clean = value.replace(/\s+/g, '');
            if (clean.length === 0) return true;

            if (clean.startsWith('07')) {
                return clean.length === 11 || 'Mobile number must be 11 digits';
            }
            if (clean.startsWith('+44')) {
                return clean.length === 13 || 'Number must be 13 chars (+44...)';
            }
            return 'Must start with 07 or +44';
        },
        async checkLocationPermission() {
            if (!navigator.permissions || !navigator.permissions.query) return;
            try {
                const result = await navigator.permissions.query({ name: 'geolocation' });
                if (result.state === 'granted') {
                    this.getLocation();
                }
            } catch (e) {
                console.log("Permissions API not supported or error", e);
            }
        },
        getLocation() {
            if (!navigator.geolocation) {
                this.locationStatus = 'error';
                return;
            }
            this.locationStatus = 'loading';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.form.location_data = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: position.timestamp
                    };
                    this.locationStatus = 'success';
                },
                (error) => {
                    console.error("Geolocation error", error);
                    this.locationStatus = 'error';
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },
        getCaveName(id) {
            const c = this.caves.find(cave => cave.id === id);
            return c ? c.name : 'Unknown';
        },
        async fetchCaves() {
            try {
                const response = await axios.get('/api/caves');
                this.caves = response.data.data;
            } catch (e) {
                console.error(e);
            }
        },
        async fetchUsers() {
            try {
                const me = await axios.get('/api/users/me');
                this.currentUser = me.data.data;

                const response = await axios.get('/api/users');
                this.users = response.data.data;
            } catch (e) {
                console.error(e);
            }
        },
        async fetchDutyOfficer() {
            try {
                const response = await axios.get('/api/duty-officers/current');
                this.onCallOfficer = response.data.data;
            } catch (e) {
                console.error("Failed to fetch duty officer", e);
                if (e.response && e.response.status === 404) {
                    this.officerError = true;
                    this.onCallOfficer = null;
                }
            }
        },

        prefillForm() {
            const now = moment();
            this.form.callout_time = now.clone().add(5, 'hours').format('YYYY-MM-DDTHH:mm');

            if (this.currentUser) {
                this.form.participants.push({
                    user_id: this.currentUser.id,
                    name: this.currentUser.name,
                    phone: '', // Don't prefill phone
                    email: this.currentUser.email,
                    locked: true
                });
            }
        },
        addSubterraUser(user) {
            if (!user) return;
            this.form.participants.push({
                user_id: user.id,
                name: user.name,
                phone: '', // Don't prefill phone
                email: user.email,
                locked: false
            });
            this.userSelect = null;
        },
        addManualParticipant() {
            this.form.participants.push({ name: '', phone: '', user_id: null, locked: false });
        },
        removeParticipant(index) {
            this.form.participants.splice(index, 1);
        },
        async submitCallout() {
            if (this.phoneError) return;

            this.processing = true;
            try {
                const response = await axios.post('/api/callouts', this.form);
                this.activeCallout = response.data.callout;

                // Refresh user state to acknowledge the new open callout
                const appStore = useAppStore();
                await appStore.getUser();

                this.$toast.success('Callout activated. Stay safe!');

                // Redirect to the open callout dashboard
                this.$router.push('/callout/active');
            } catch (e) {
                console.error('Callout Error:', e);
                const errorMsg = e.response?.data?.message || e.message || 'Failed to activate callout.';
                this.$toast.error(errorMsg);
            } finally {
                this.processing = false;
            }
        },
        async cancelCallout() {
            if (!confirm('Are you definitely out and safe?')) return;

            this.processing = true;
            try {
                await axios.post(`/api/callouts/${this.activeCallout.id}/cancel`);
                this.showSuccessDialog = true;
                this.$toast.success('Callout cancelled.');
            } catch (e) {
                this.$toast.error('Failed to cancel.');
            } finally {
                this.processing = false;
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
            });
        }
    }
};
</script>

<style scoped>
.disabled-content {
    opacity: 0.5;
    pointer-events: none;
    user-select: none;
}
</style>
