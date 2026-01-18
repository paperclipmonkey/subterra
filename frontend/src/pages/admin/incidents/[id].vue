<template>
    <v-container v-if="incident">
        <!-- Top Banner -->
        <v-banner sticky app :color="bannerColor" dark two-line>
            <div class="d-flex align-center w-100">
                <v-icon size="32" left class="mr-4">mdi-alert-decagram</v-icon>
                <div>
                    <div class="text-h6 font-weight-bold">
                        INCIDENT #{{ incident.id }} - {{ incident.status.toUpperCase() }}
                    </div>
                    <div class="text-subtitle-2">
                        Controller:
                        <span v-if="incident.controller" class="font-weight-bold">{{ incident.controller.name }}</span>
                        <span v-else class="font-italic">UNASSIGNED</span>
                    </div>
                </div>
                <v-spacer></v-spacer>
                <v-btn v-if="!incident.controller" color="white" light large @click="acknowledge" :loading="processing">
                    ACKNOWLEDGE & TAKE CONTROL
                </v-btn>
                <v-btn v-else-if="incident.status !== 'resolved'" color="white" outlined
                    @click="showResolveDialog = true">
                    RESOLVE INCIDENT
                </v-btn>
            </div>
        </v-banner>

        <div class="mt-4"></div>

        <v-row>
            <!-- LEFT COLUMN: INCIDENT DETAILS & SCRIPT -->
            <v-col cols="12" md="7">

                <!-- Police Script Stepper -->
                <v-card class="mb-4" v-if="incident.status !== 'resolved'">
                    <v-card-title class="grey lighten-4">
                        <v-icon left color="blue">mdi-police-badge</v-icon>
                        Police Liaison Script
                    </v-card-title>
                    <v-card-text class="pt-4">
                        <v-stepper v-model="scriptStep" vertical class="elevation-0">
                            <v-stepper-step :complete="scriptStep > 1" step="1">Call 999</v-stepper-step>
                            <v-stepper-content step="1">
                                <v-alert type="info" text border="left">
                                    Dial 999 and ask for <strong>POLICE</strong>.
                                </v-alert>
                                <v-btn color="primary" @click="scriptStep = 2">Connected to Police</v-btn>
                            </v-stepper-content>

                            <v-stepper-step :complete="scriptStep > 2" step="2">State Nature of
                                Emergency</v-stepper-step>
                            <v-stepper-content step="2">
                                <v-alert type="warning" prominent text>
                                    "I need to report a <strong>CAVE RESCUE</strong> incident."
                                </v-alert>
                                <p>Give Location: <strong>{{ incident.callout.cave ? incident.callout.cave.name :
                                        'Unknown' }}</strong></p>
                                <v-btn color="primary" @click="scriptStep = 3">Next</v-btn>
                                <v-btn text @click="scriptStep = 1">Back</v-btn>
                            </v-stepper-content>

                            <v-stepper-step :complete="scriptStep > 3" step="3">Provide Details</v-stepper-step>
                            <v-stepper-content step="3">
                                <v-list dense>
                                    <v-list-item>
                                        <v-list-item-content>
                                            <v-list-item-title>Participants</v-list-item-title>
                                            <v-list-item-subtitle>{{ incident.callout.participants.length }}
                                                people</v-list-item-subtitle>
                                        </v-list-item-content>
                                    </v-list-item>
                                    <v-list-item>
                                        <v-list-item-content>
                                            <v-list-item-title>Overdue Since</v-list-item-title>
                                            <v-list-item-subtitle>{{ formatTime(incident.callout.expected_exit_time)
                                                }}</v-list-item-subtitle>
                                        </v-list-item-content>
                                    </v-list-item>
                                    <v-list-item>
                                        <v-list-item-content>
                                            <v-list-item-title>Medical Info</v-list-item-title>
                                            <v-list-item-subtitle>{{ incident.callout.medical_info || 'None known'
                                                }}</v-list-item-subtitle>
                                        </v-list-item-content>
                                    </v-list-item>
                                </v-list>
                                <v-btn color="primary" @click="scriptStep = 4">Next</v-btn>
                                <v-btn text @click="scriptStep = 2">Back</v-btn>
                            </v-stepper-content>

                            <v-stepper-step step="4">Record Police Log Number</v-stepper-step>
                            <v-stepper-content step="4">
                                <p>Ask the Operator for the <strong>Police Log Number</strong>.</p>
                                <v-text-field v-model="policeLogInput" label="Log Number (e.g. 1234 of 18/01)"
                                    outlined></v-text-field>
                                <v-btn color="success" @click="savePoliceLog" :disabled="!policeLogInput">Save Log
                                    Number</v-btn>
                            </v-stepper-content>
                        </v-stepper>
                    </v-card-text>
                </v-card>

                <!-- Callout Data Card -->
                <v-card>
                    <v-card-title>Callout Details</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="6">
                                <strong>Cave:</strong> {{ incident.callout.cave?.name }}
                            </v-col>
                            <v-col cols="6">
                                <strong>Exit Cave:</strong> {{ incident.callout.exit_cave?.name || 'Same' }}
                            </v-col>
                            <v-col cols="12">
                                <strong>Car Details:</strong> {{ incident.callout.car_details || 'N/A' }}
                            </v-col>
                            <v-col cols="12">
                                <strong>Trip Plan:</strong>
                                <div class="grey lighten-4 pa-3 rounded mt-1">
                                    {{ incident.callout.trip_plan }}
                                </div>
                            </v-col>
                        </v-row>

                        <v-divider class="my-4"></v-divider>

                        <h3>Participants</h3>
                        <v-list>
                            <v-list-item v-for="p in incident.callout.participants" :key="p.id">
                                <v-list-item-avatar>
                                    <v-icon>mdi-account</v-icon>
                                </v-list-item-avatar>
                                <v-list-item-content>
                                    <v-list-item-title>{{ p.name }}</v-list-item-title>
                                    <v-list-item-subtitle>{{ p.phone || 'No phone' }}</v-list-item-subtitle>
                                </v-list-item-content>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- RIGHT COLUMN: INCIDENT LOG -->
            <v-col cols="12" md="5">
                <v-card class="fill-height d-flex flex-column" style="max-height: 80vh;">
                    <v-card-title>Incident Log</v-card-title>

                    <v-card-text class="flex-grow-1 overflow-y-auto" style="min-height: 400px;" ref="logContainer">
                        <div v-for="note in incident.notes" :key="note.id" class="mb-3">
                            <div class="caption grey--text">
                                {{ formatDateTime(note.created_at) }} - {{ note.user ? note.user.name : 'System' }}
                            </div>
                            <div class="body-2 black--text pa-2 rounded" style="background-color: #f5f5f5;">
                                {{ note.content }}
                            </div>
                        </div>
                    </v-card-text>

                    <v-divider></v-divider>

                    <v-card-actions class="pa-3">
                        <v-textarea v-model="newNote" outlined dense rows="2" label="Add entry..." hide-details
                            @keydown.enter.prevent="addNote"></v-textarea>
                        <v-btn icon color="primary" @click="addNote">
                            <v-icon>mdi-send</v-icon>
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>

        <!-- Resolve Dialog -->
        <v-dialog v-model="showResolveDialog" max-width="500">
            <v-card>
                <v-card-title>Resolve Incident?</v-card-title>
                <v-card-text>
                    Has everyone been accounted for? This will close the callout and incident.
                    <v-textarea v-model="resolveNotes" label="Reason/Outcome" outlined class="mt-4"></v-textarea>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn text @click="showResolveDialog = false">Cancel</v-btn>
                    <v-btn color="success" @click="resolveIncident">Confirm Resolution</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import axios from 'axios';
import moment from 'moment';

export default {
    data() {
        return {
            incident: null,
            scriptStep: 1,
            policeLogInput: '',
            newNote: '',
            processing: false,
            showResolveDialog: false,
            resolveNotes: 'Safe and well.'
        };
    },
    computed: {
        bannerColor() {
            if (!this.incident) return 'grey';
            if (this.incident.status === 'open') return 'red';
            if (this.incident.status === 'managed') return 'orange darken-3';
            return 'green';
        }
    },
    async mounted() {
        await this.fetchIncident();
        this.poll = setInterval(this.fetchIncident, 10000); // 10s poll for log updates
    },
    beforeUnmount() {
        clearInterval(this.poll);
    },
    methods: {
        async fetchIncident() {
            try {
                const res = await axios.get(`/api/admin/incidents/${this.$route.params.id}`);
                this.incident = res.data.data;
                if (this.incident.police_log_number && this.policeLogInput === '') {
                    this.policeLogInput = this.incident.police_log_number;
                    // Auto advance if log exists? Maybe.
                }
            } catch (e) {
                console.error(e);
            }
        },
        async acknowledge() {
            this.processing = true;
            try {
                await axios.post(`/api/admin/incidents/${this.incident.id}/acknowledge`);
                this.fetchIncident();
                this.$toast.success('You have assumed control of this incident.');
            } catch (e) {
                this.$toast.error(e.response?.data?.message || 'Failed to acknowledge');
            } finally {
                this.processing = false;
            }
        },
        async addNote() {
            if (!this.newNote.trim()) return;
            try {
                await axios.post(`/api/admin/incidents/${this.incident.id}/notes`, { content: this.newNote });
                this.newNote = '';
                this.fetchIncident();
            } catch (e) {
                console.error(e);
            }
        },
        async savePoliceLog() {
            try {
                await axios.post(`/api/admin/incidents/${this.incident.id}/notes`, {
                    content: `Police Log Number recorded: ${this.policeLogInput}`,
                    police_log_number: this.policeLogInput
                });
                this.$toast.success('Log number saved.');
                this.fetchIncident();
            } catch (e) {
                console.error(e);
            }
        },
        async resolveIncident() {
            try {
                await axios.post(`/api/admin/incidents/${this.incident.id}/resolve`, { notes: this.resolveNotes });
                this.showResolveDialog = false;
                this.$toast.success('Incident Resolved.');
                this.$router.push('/admin/dashboard');
            } catch (e) {
                this.$toast.error('Failed to resolve.');
            }
        },
        formatTime(d) {
            return moment(d).format('HH:mm');
        },
        formatDateTime(d) {
            return moment(d).format('HH:mm DD/MM');
        }
    }
};
</script>
