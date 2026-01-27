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
                <v-btn v-if="!incident.controller" color="warning" class="black--text" light large @click="acknowledge" :loading="processing">
                    ACKNOWLEDGE & TAKE CONTROL
                </v-btn>
                <v-btn v-else-if="incident.status !== 'resolved'" color="success"
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



                <!-- Rescue Protocol -->
                <v-card class="mb-4" v-if="incident.status !== 'resolved' && !dismissedProtocol">
                    <v-card-title class="grey lighten-4">
                        <div class="d-flex align-center">
                            <v-icon left color="blue">mdi-shield-check</v-icon>
                            Rescue Protocol
                        </div>
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
                                <div class="mt-1" v-if="policeRegion">
                                    Request Police for <strong>{{ policeRegion }}</strong> region.
                                </div>
                                <div class="mt-1 red--text font-weight-bold" v-else>
                                    Ask caller for the correct Police region.
                                </div>
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
                                            '' }})
                                </div>
                                <v-checkbox v-model="script.providedInfo" label="Details passed to operator" dense
                                    hide-details color="success" :disabled="!script.statedNature"></v-checkbox>
                            </v-timeline-item>



                        </v-timeline>
                        
                        <div class="d-flex justify-end mt-4">
                            <v-btn color="success" variant="tonal" @click="dismissProtocol">
                                <v-icon left>mdi-check-circle</v-icon> Done
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- Callout Data Card -->
                <v-card>
                    <v-card-title>Callout Details</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="6">
                                <strong>Cave:</strong> 
                                <router-link v-if="incident.callout.cave" :to="'/caves/' + incident.callout.cave.slug">
                                    {{ incident.callout.cave.name }}
                                </router-link>
                            </v-col>
                            <v-col cols="6">
                                <strong>Exit Cave:</strong>
                                <template v-if="incident.callout.exit_cave">
                                    <router-link :to="'/caves/' + incident.callout.exit_cave.slug">
                                        {{ incident.callout.exit_cave.name }}
                                    </router-link>
                                </template>
                                <span v-else>Same</span>
                            </v-col>
                            <v-col cols="12">
                                <strong v-if="incident.callout.car_details">Car Details:</strong> {{ incident.callout.car_details }}
                                <div class="mt-1" v-if="incident.callout.car_registration || incident.callout.car_parking">
                                    <v-chip x-small v-if="incident.callout.car_registration" class="mr-1" label>
                                      Reg: {{ incident.callout.car_registration }}
                                    </v-chip>
                                    <span v-if="incident.callout.car_parking" class="text-caption grey--text text--darken-2">
                                      Parking: {{ incident.callout.car_parking }}
                                    </span>
                                </div>
                            </v-col>
                            <v-col cols="12" v-if="incident.callout.team_details">
                                <strong>Party Info:</strong>
                                <div class="grey lighten-4 pa-3 rounded mt-1">
                                    {{ incident.callout.team_details }}
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
                                    <div class="mt-1">
                                        <v-btn x-small variant="tonal" color="primary" class="mr-1" @click="copyToClipboard(`${incident.callout.cave.location_lat},${incident.callout.cave.location_lng}`)">
                                            <v-icon size="12" class="mr-1">mdi-content-copy</v-icon> Lat,Lng
                                        </v-btn>
                                        <v-btn x-small variant="tonal" class="mr-1" :href="`https://gridreferencefinder.com/index.php?lt=${formatCoord(incident.callout.cave.location_lat)}&lg=${formatCoord(incident.callout.cave.location_lng)}`" target="_blank">
                                            <v-icon size="12" class="mr-1">mdi-map-marker-path</v-icon> OS Grid
                                        </v-btn>
                                        <v-btn x-small variant="tonal" :href="`https://what3words.com/${formatCoord(incident.callout.cave.location_lat)},${formatCoord(incident.callout.cave.location_lng)}`" target="_blank">
                                            <v-icon size="12" class="mr-1">mdi-map-marker-radius</v-icon> W3W
                                        </v-btn>
                                    </div>
                                </div>

                                <div v-if="incident.callout.exit_cave" class="mt-4">
                                    <strong>Exit Cave Location:</strong> 
                                    {{ incident.callout.exit_cave.location_lat }}, {{ incident.callout.exit_cave.location_lng }}
                                    <div class="mt-1">
                                        <v-btn x-small variant="tonal" color="primary" class="mr-1" @click="copyToClipboard(`${incident.callout.exit_cave.location_lat},${incident.callout.exit_cave.location_lng}`)">
                                            <v-icon size="12" class="mr-1">mdi-content-copy</v-icon> Lat,Lng
                                        </v-btn>
                                        <v-btn x-small variant="tonal" class="mr-1" :href="`https://gridreferencefinder.com/index.php?lt=${formatCoord(incident.callout.exit_cave.location_lat)}&lg=${formatCoord(incident.callout.exit_cave.location_lng)}`" target="_blank">
                                            <v-icon size="12" class="mr-1">mdi-map-marker-path</v-icon> OS Grid
                                        </v-btn>
                                        <v-btn x-small variant="tonal" :href="`https://what3words.com/${formatCoord(incident.callout.exit_cave.location_lat)},${formatCoord(incident.callout.exit_cave.location_lng)}`" target="_blank">
                                            <v-icon size="12" class="mr-1">mdi-map-marker-radius</v-icon> W3W
                                        </v-btn>
                                    </div>
                                </div>

                                <div v-if="incident.callout.location_data" class="mt-4">
                                    <strong>Callout opened at location:</strong>
                                    <span v-if="incident.callout.location_data.latitude">
                                        {{ incident.callout.location_data.latitude.toFixed(5) }}, 
                                        {{ incident.callout.location_data.longitude.toFixed(5) }}
                                    </span>
                                    <span v-else class="grey--text font-italic">No GPS data</span>

                                    <div class="mt-1" v-if="incident.callout.location_data.latitude">
                                        <v-btn x-small variant="tonal" color="primary" class="mr-1" @click="copyToClipboard(`${incident.callout.location_data.latitude.toFixed(5)},${incident.callout.location_data.longitude.toFixed(5)}`)">
                                            <v-icon size="12" class="mr-1">mdi-content-copy</v-icon> Lat,Lng
                                        </v-btn>
                                        <v-btn x-small variant="tonal" class="mr-1" :href="`https://gridreferencefinder.com/index.php?lt=${formatCoord(incident.callout.location_data.latitude)}&lg=${formatCoord(incident.callout.location_data.longitude)}`" target="_blank">
                                            <v-icon size="12" class="mr-1">mdi-map-marker-path</v-icon> OS Grid
                                        </v-btn>
                                        <v-btn x-small variant="tonal" :href="`https://what3words.com/${formatCoord(incident.callout.location_data.latitude)},${formatCoord(incident.callout.location_data.longitude)}`" target="_blank">
                                            <v-icon size="12" class="mr-1">mdi-map-marker-radius</v-icon> W3W
                                        </v-btn>
                                    </div>
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
                                    <v-list-item-title>
                                        <router-link v-if="p.user_id" :to="'/profile/' + p.user_id">{{ p.name }}</router-link>
                                        <span v-else>{{ p.name }}</span>
                                    </v-list-item-title>
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

                <!-- Incident Map -->
                <v-card class="mt-4 overflow-hidden">
                    <v-card-title class="grey lighten-4 py-2 text-subtitle-2">
                        <v-icon left size="20">mdi-map</v-icon>
                        Incident Locations
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <mgl-map
                            ref="mapRef"
                            :map-style="mapStyle"
                            :center="[-2, 53]"
                            :zoom="5"
                            height="400px"
                            @load="onMapLoad"
                        >
                            <!-- Cave Entrance -->
                            <mgl-marker 
                                v-if="incident.callout.cave" 
                                :coordinates="[incident.callout.cave.location_lng, incident.callout.cave.location_lat]" 
                                :anchor="'bottom'"
                            >
                                <template v-slot:marker>
                                    <div class="marker-container">
                                        <div class="marker-label">Entrance</div>
                                        <v-icon size="24" color="#ff0000">mdi-map-marker</v-icon>
                                    </div>
                                </template>
                            </mgl-marker>

                            <!-- Cave Exit (if different) -->
                            <mgl-marker 
                                v-if="incident.callout.exit_cave" 
                                :coordinates="[incident.callout.exit_cave.location_lng, incident.callout.exit_cave.location_lat]" 
                                :anchor="'bottom'"
                            >
                                <template v-slot:marker>
                                    <div class="marker-container">
                                        <div class="marker-label">Exit</div>
                                        <v-icon size="24" color="#008000">mdi-map-marker</v-icon>
                                    </div>
                                </template>
                            </mgl-marker>

                            <!-- Callout Origin -->
                            <mgl-marker 
                                v-if="incident.callout.location_data && incident.callout.location_data.latitude" 
                                :coordinates="[incident.callout.location_data.longitude, incident.callout.location_data.latitude]" 
                                :anchor="'bottom'"
                            >
                                <template v-slot:marker>
                                    <div class="marker-container">
                                        <div class="marker-label">Origin</div>
                                        <v-icon size="24" color="#0000ff">mdi-map-marker</v-icon>
                                    </div>
                                </template>
                            </mgl-marker>
                            
                            <mgl-navigation-control />
                        </mgl-map>
                    </v-card-text>
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
import {
    MglMap,
    MglNavigationControl,
    MglMarker,
    MglPopup,
} from '@indoorequal/vue-maplibre-gl';
import maplibregl from 'maplibre-gl';

export default {
    components: {
        MglMap,
        MglNavigationControl,
        MglMarker,
        MglPopup
    },
    data() {
        return {
            incident: null,
            mapInstance: null,
            mapStyle: 'https://api.os.uk/maps/vector/v1/vts/resources/styles?srs=3857&key=1uHtffJAZux4RBSVyOhOOGVmt3ASocge',
            script: {
                calledPolice: false,
                statedNature: false,
                providedInfo: false
            },
            dismissedProtocol: false,
            newNote: '',
            processing: false,
            showResolveDialog: false,
            resolveNotes: 'Safe and well.',
            hasFittedBounds: false
        };
    },
    computed: {
        bannerColor() {
            if (!this.incident) return 'grey';
            if (this.incident.status === 'open') return 'red';
            if (this.incident.status === 'managed') return 'orange darken-3';
            return 'green';
        },
        policeRegion() {
            if (!this.incident || !this.incident.callout || !this.incident.callout.cave || !this.incident.callout.cave.tags) return null;
            const regionTag = this.incident.callout.cave.tags.find(t => t.category === 'region');
            return regionTag ? regionTag.tag : null;
        }
    },
    watch: {
        incident: {
            handler(newVal) {
                if (newVal) {
                    this.fitMapToBounds();
                }
            },
            immediate: false // fetchIncident calls it manually or via onMapLoad
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

                // Check if protocol has been dismissed via note
                const dismissNote = "Police have been contacted and they're waiting to hear from cave rescue.";
                if (this.incident.notes.some(n => n.content.includes(dismissNote))) {
                    this.dismissedProtocol = true;
                }
            } catch (e) {
                console.error(e);
            }
        },
        onMapLoad(event) {
            this.mapInstance = event.map;
            this.mapInstance.resize();
            this.fitMapToBounds();
        },
        fitMapToBounds() {
            if (!this.mapInstance || !this.incident || !this.incident.callout || this.hasFittedBounds) return;
            const points = [];
            const c = this.incident.callout;

            const isValid = (lat, lng) =>
                lat !== null && lat !== undefined && !isNaN(parseFloat(lat)) && parseFloat(lat) !== 0 &&
                lng !== null && lng !== undefined && !isNaN(parseFloat(lng)) && parseFloat(lng) !== 0;

            if (c.cave && isValid(c.cave.location_lat, c.cave.location_lng))
                points.push([parseFloat(c.cave.location_lng), parseFloat(c.cave.location_lat)]);

            if (c.exit_cave && isValid(c.exit_cave.location_lat, c.exit_cave.location_lng))
                points.push([parseFloat(c.exit_cave.location_lng), parseFloat(c.exit_cave.location_lat)]);

            if (c.location_data?.latitude && isValid(c.location_data.latitude, c.location_data.longitude))
                points.push([parseFloat(c.location_data.longitude), parseFloat(c.location_data.latitude)]);

            if (points.length > 0) {
                const bounds = new maplibregl.LngLatBounds();
                points.forEach(p => bounds.extend(p));

                if (points.length > 1) {
                    this.mapInstance.fitBounds(bounds, {
                        padding: 50,
                        maxZoom: 15,
                        duration: 1000
                    });
                    this.hasFittedBounds = true;
                } else {
                    this.mapInstance.setCenter(points[0]);
                    this.mapInstance.setZoom(15);
                    this.hasFittedBounds = true;
                }
            } else {
                // Default view if no valid points are found
                this.mapInstance.setCenter([-3.29, 54.46]); // Approximate center of UK caving regions
                this.mapInstance.setZoom(7);
                this.hasFittedBounds = true;
            }
        },
        async dismissProtocol() {
            try {
                const note = "Police have been contacted and they're waiting to hear from cave rescue.";
                await axios.post(`/api/admin/incidents/${this.incident.id}/notes`, { content: note });
                this.$toast.success('Protocol dismissed and logged.');
                this.dismissedProtocol = true;
                this.fetchIncident();
            } catch (e) {
                this.$toast.error('Failed to log dismissal.');
            }
        },
        copyToClipboard(text) {
            if (!navigator.clipboard) {
                this.$toast.error('Clipboard access not available');
                return;
            }
            navigator.clipboard.writeText(text).then(() => {
                this.$toast.success('Copied to clipboard!');
            }).catch(err => {
                this.$toast.error('Failed to copy');
            });
        },
        formatCoord(val) {
            if (val === null || val === undefined || isNaN(val)) return '';
            const num = parseFloat(val);
            const str = num.toString();
            const parts = str.split('.');
            // Ensure at least 5 decimal places for reliability, but preserve more if present
            if (parts.length < 2 || parts[1].length < 5) {
                return num.toFixed(5);
            }
            return str;
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

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.marker-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    // No transform here, using MapLibre's anchor="bottom"
}

.marker-label {
    background: white;
    padding: 1px 4px;
    border-radius: 3px;
    border: 1px solid #666;
    font-size: 10px;
    font-weight: bold;
    white-space: nowrap;
    margin-bottom: 2px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    color: #333;
}
</style>
