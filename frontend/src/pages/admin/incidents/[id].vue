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

        <!-- User Safe Banner -->
        <v-alert v-if="incident.callout.status === 'cancelled'" type="success" prominent class="mb-4"
            icon="mdi-check-decagram">
            <div class="text-h6 font-weight-bold">USER MARKED SAFE</div>
            <div>The user has confirmed they are safe via the app. You may now stand down emergency services and resolve
                this incident.</div>
        </v-alert>

        <v-row>
            <!-- LEFT COLUMN: INCIDENT DETAILS & PROTOCOL -->
            <v-col cols="12" md="7">

                <!-- Police Log Number Display -->
                <v-card v-if="incident.police_log_number" class="mb-4">
                    <v-card-title class="green darken-2 white--text">
                        <v-icon left color="white">mdi-police-badge</v-icon>
                        Police Log Number
                    </v-card-title>
                    <v-card-text class="text-center pa-6">
                        <div class="text-h4 font-weight-bold mb-4">{{ incident.police_log_number }}</div>
                        <v-alert color="amber darken-1" variant="flat" icon="mdi-phone-in-talk"
                            class="text-left white--text">
                            <strong>Next Step:</strong> Stay with your phone and await a call from <strong>Cave
                                Rescue</strong>.
                            They will contact you for further information.
                        </v-alert>
                    </v-card-text>
                </v-card>

                <!-- Rescue Protocol (hidden once log number is recorded) -->
                <v-card class="mb-4" v-if="incident.status !== 'resolved' && !incident.police_log_number">
                    <v-card-title class="grey lighten-4">
                        <v-icon left color="blue">mdi-shield-check</v-icon>
                        Rescue Protocol
                    </v-card-title>
                    <v-card-text class="pt-4">
                        <v-timeline density="compact" align="start" truncate-line="start">

                            <!-- Step 1: Call 999 -->
                            <v-timeline-item dot-color="red" size="small" icon="mdi-phone">
                                <template v-slot:opposite>
                                    <div class="text-caption grey--text">Immediate</div>
                                </template>
                                <div class="mb-2 font-weight-bold">Initiate Call</div>
                                <div>Dial 999 and ask for <strong>POLICE</strong>.</div>
                                <v-checkbox v-model="script.calledPolice" label="Connected to Police Dispatch" dense
                                    hide-details color="success"></v-checkbox>
                            </v-timeline-item>

                            <!-- Step 2: State Nature -->
                            <v-timeline-item dot-color="orange" size="small" icon="mdi-alert">
                                <div class="mb-2 font-weight-bold">State Emergency</div>
                                <v-alert type="warning" variant="tonal" border="start" density="compact" class="mb-2">
                                    "I need to contact the <strong>CAVE RESCUE</strong> controller."
                                </v-alert>
                                <div>Location: <strong>{{ incident.callout.cave ? incident.callout.cave.name : 'Unknown'
                                        }}</strong></div>
                                <v-checkbox v-model="script.statedNature" label="Nature of emergency confirmed" dense
                                    hide-details color="success" :disabled="!script.calledPolice"></v-checkbox>
                            </v-timeline-item>

                            <!-- Step 3: Provide Info -->
                            <v-timeline-item dot-color="blue" size="small" icon="mdi-information">
                                <div class="mb-2 font-weight-bold">Provide Critical Info</div>
                                <div class="text-caption mb-2">
                                    Team: {{ incident.callout.participants.length }} people<br>
                                    Overdue: {{ formatTime(incident.callout.callout_time) }} ({{
                                        incident.callout.callout_time ? formatRelativeTime(incident.callout.callout_time) :
                                            '' }})<br>
                                    Medical: {{ incident.callout.medical_info || 'None known' }}
                                </div>
                                <v-checkbox v-model="script.providedInfo" label="Details passed to operator" dense
                                    hide-details color="success" :disabled="!script.statedNature"></v-checkbox>
                            </v-timeline-item>

                            <!-- Step 4: Log Number -->
                            <v-timeline-item dot-color="green" size="small" icon="mdi-file-document">
                                <div class="mb-2 font-weight-bold">Record Log Number</div>
                                <div class="d-flex align-center">
                                    <v-text-field v-model="policeLogInput" label="Police Log Number" dense outlined
                                        hide-details class="mr-2"></v-text-field>
                                    <v-btn color="success" icon @click="savePoliceLog"
                                        :disabled="!policeLogInput || !script.providedInfo">
                                        <v-icon>mdi-check</v-icon>
                                    </v-btn>
                                </div>
                            </v-timeline-item>

                        </v-timeline>
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
                                <div class="mt-1" v-if="incident.callout.car_registration || incident.callout.car_parking">
                                    <v-chip x-small v-if="incident.callout.car_registration" class="mr-1" label>
                                      Reg: {{ incident.callout.car_registration }}
                                    </v-chip>
                                    <span v-if="incident.callout.car_parking" class="text-caption grey--text text--darken-2">
                                      Parking: {{ incident.callout.car_parking }}
                                    </span>
                                </div>
                            </v-col>
                            <v-col cols="12">
                                <strong>Trip Plan:</strong>
                                <div class="grey lighten-4 pa-3 rounded mt-1">
                                    {{ incident.callout.trip_plan }}
                                </div>
                            </v-col>
                            <v-col cols="12">
                                <v-divider class="my-2"></v-divider>
                                <div class="text-subtitle-2 mb-2">Location Information</div>
                                
                                <div v-if="incident.callout.cave">
                                    <strong>Cave Location:</strong> 
                                    {{ incident.callout.cave.location_lat }}, {{ incident.callout.cave.location_lng }}
                                    <span v-if="incident.callout.cave.location_name">({{ incident.callout.cave.location_name }})</span>
                                </div>

                                <div v-if="incident.callout.location_data" class="mt-2">
                                    <strong>Callout Origin:</strong>
                                    <span v-if="incident.callout.location_data.coords">
                                        {{ incident.callout.location_data.coords.latitude.toFixed(5) }}, 
                                        {{ incident.callout.location_data.coords.longitude.toFixed(5) }}
                                    </span>
                                    <span v-else class="grey--text font-italic">No GPS data</span>
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
            script: {
                calledPolice: false,
                statedNature: false,
                providedInfo: false
            },
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
        // Restore script state from local storage or infer from incident notes?
        // For now, it resets on reload which is fine for MVP, or we can check police log number presence.
        if (this.incident && this.incident.police_log_number) {
            this.script.calledPolice = true;
            this.script.statedNature = true;
            this.script.providedInfo = true;
        }
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
                }
            } catch (e) {
                console.error(e);
            }
        },
        async acknowledge() {
            this.processing = true;
            try {
                await axios.post(`/api/admin/incidents/${this.incident.id}/acknowledge`);
                this.$toast.success('You have assumed control of this incident.');
                // Immediately refresh to update UI
                await this.fetchIncident();
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
                this.$router.push('/admin/callout');
            } catch (e) {
                this.$toast.error('Failed to resolve.');
            }
        },
        formatTime(d) {
            return moment(d).format('HH:mm');
        },
        formatRelativeTime(d) {
            return moment(d).fromNow();
        },
        formatDateTime(d) {
            return moment(d).format('HH:mm DD/MM');
        }
    }
};
</script>
