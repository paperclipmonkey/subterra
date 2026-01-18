<template>
    <v-container>
        <v-row justify="center">
            <v-col cols="12" md="8" lg="6">
                <v-card class="mb-6 elevation-10" color="red darken-4" dark>
                    <v-card-text class="text-center pa-6">
                        <div class="text-h6 mb-2">RESCUE WILL BE ACTIVATED IN</div>
                        <div class="text-h2 font-weight-black mb-2 white--text">
                            {{ timeRemaining }}
                        </div>
                        <div class="subtitle-1">
                            {{ formatTime(callout.callout_time) }} - {{ formatDate(callout.callout_time) }}
                        </div>
                    </v-card-text>
                </v-card>

                <v-card class="mb-6">
                    <v-card-title class="headline">
                        <v-icon left color="primary">mdi-map-marker</v-icon>
                        {{ callout.cave ? callout.cave.name : callout.description }}
                    </v-card-title>
                    <v-card-text>
                        <v-list dense>
                            <v-list-item>
                                <v-list-item-title>Start Time</v-list-item-title>
                                <v-list-item-subtitle>{{ formatTime(callout.created_at) }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item v-if="callout.emergency_contact_phone">
                                <v-list-item-title>Emergency Contact</v-list-item-title>
                                <v-list-item-subtitle>{{ callout.emergency_contact_name }} ({{
                                    callout.emergency_contact_phone }})</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item v-if="callout.trip_plan">
                                <v-list-item-title>Trip Plan</v-list-item-title>
                                <div class="text-body-2 mt-1">{{ callout.trip_plan }}</div>
                            </v-list-item>
                            <v-list-item v-if="callout.car_details">
                                <v-list-item-title>Vehicle</v-list-item-title>
                                <v-list-item-subtitle>{{ callout.car_details }}</v-list-item-subtitle>
                            </v-list-item>
                            <v-list-item v-if="callout.team_details">
                                <v-list-item-title>Additional Team Details</v-list-item-title>
                                <div class="text-body-2 mt-1">{{ callout.team_details }}</div>
                            </v-list-item>
                            <v-list-item v-if="callout.participants && callout.participants.length > 0">
                                <v-list-item-title>The Team</v-list-item-title>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <v-chip v-for="participant in callout.participants" :key="participant.id"
                                        size="small" class="mr-1 mb-1">
                                        {{ participant.name }}
                                    </v-chip>
                                </div>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <v-btn block x-large color="success" size="x-large" class="py-6 font-weight-black text-h5 mb-4"
                    @click="confirmSafe = true">
                    <v-icon left size="large">mdi-check-circle</v-icon>
                    I AM SAFE
                </v-btn>

                <div class="text-center caption grey--text">
                    Clicking this will cancel the safety watchdog immediately.
                </div>
            </v-col>
        </v-row>

        <!-- Confirmation Dialog -->
        <v-dialog v-model="confirmSafe" max-width="400">
            <v-card>
                <v-card-title class="headline">Verify Safety</v-card-title>
                <v-card-text>
                    Are you out of the cave and safe? This will cancel the callout for all participants.
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="grey darken-1" text @click="confirmSafe = false">Cancel</v-btn>
                    <v-btn color="green darken-1" text @click="cancelCallout">Yes, I'm Safe</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Convert to Trip Dialog -->
        <v-dialog v-model="convertToTrip" persistent max-width="400">
            <v-card>
                <v-card-title class="headline text-center">Glad you're safe!</v-card-title>
                <v-card-text class="text-center">
                    <v-icon size="64" color="green" class="mb-4">mdi-party-popper</v-icon>
                    <p>Would you like to save this callout as a trip report?</p>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="grey" text @click="finish">No Thanks</v-btn>
                    <v-btn color="primary" @click="createTrip">Create Trip Report</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

    </v-container>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAppStore } from '@/stores/app';
import { useRouter } from 'vue-router';
import axios from 'axios';
import moment from 'moment';
import { useToast } from "vue-toastification";

const appStore = useAppStore();
const router = useRouter();
const toast = useToast();

const confirmSafe = ref(false);
const convertToTrip = ref(false);
const now = ref(moment());
let timer = null;

const callout = computed(() => appStore.user.active_callout || {});

const timeRemaining = computed(() => {
    if (!callout.value.callout_time) return '--:--:--';
    const end = moment(callout.value.callout_time);
    const diff = end.diff(now.value); // ms
    if (diff <= 0) return 'OVERDUE';

    const duration = moment.duration(diff);
    const hours = Math.floor(duration.asHours());
    const mins = duration.minutes();
    const secs = duration.seconds();

    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const formatTime = (t) => moment(t).format('HH:mm');
const formatDate = (t) => moment(t).format('ddd Do MMM');

const cancelCallout = async () => {
    confirmSafe.value = false;
    try {
        await axios.post(`/api/callouts/${callout.value.id}/cancel`);
        toast.success("Callout Cancelled");

        // Update user state to remove active callout
        await appStore.getUser();

        // Show convert dialog
        convertToTrip.value = true;
    } catch (e) {
        toast.error("Failed to cancel callout: " + (e.response?.data?.message || e.message));
    }
};

const finish = () => {
    convertToTrip.value = false;
    router.push('/');
};

const createTrip = () => {
    router.push({
        path: '/create-trip',
        query: {
            callout_id: callout.value.id,
            cave_id: callout.value.cave_id,
            exit_cave_id: callout.value.exit_cave_id,
            date: moment(callout.value.created_at).format('YYYY-MM-DD'),
        }
    });
};

onMounted(() => {
    if (!callout.value.id) {
        // Fallback if accessed via URL directly but no data in store yet
        // Could force refetch user or redirect
        if (!appStore.user.id) {
            appStore.getUser();
        } else {
            router.push('/callout'); // Redirect back if really no callout?
        }
    }

    timer = setInterval(() => {
        now.value = moment();
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>
