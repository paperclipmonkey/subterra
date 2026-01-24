<template>
    <v-container>
        <v-toolbar flat>
            <v-btn icon @click="$router.back()"><v-icon>mdi-arrow-left</v-icon></v-btn>
            <v-toolbar-title>On-Call Rota</v-toolbar-title>
            <v-spacer></v-spacer>
            <p class="caption mb-0 mr-4">
                Ensure 24/7 coverage.
            </p>
        </v-toolbar>

        <v-card class="mt-4">
            <v-card-title>
                <v-icon left>mdi-calendar-plus</v-icon> Add Shift
            </v-card-title>
            <v-card-text>
                <v-form @submit.prevent="addShift" ref="form" v-model="valid">
                    <v-row>
                        <v-col cols="12" md="4">
                            <v-select v-model="newShift.user_id" :items="users" item-title="name" item-value="id"
                                label="Officer" outlined dense :rules="[v => !!v || 'User is required']"
                                required></v-select>
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-text-field v-model="newShift.start_at" type="datetime-local" label="Start" outlined dense
                                :rules="[v => !!v || 'Start time is required']" required></v-text-field>
                        </v-col>
                        <v-col cols="12" md="3">
                            <v-text-field v-model="newShift.end_at" type="datetime-local" label="End" outlined dense
                                :rules="[v => !!v || 'End time is required']" required></v-text-field>
                        </v-col>
                        <v-col cols="12" md="2">
                            <v-btn block color="primary" type="submit" :loading="processing"
                                :disabled="!valid">Add</v-btn>
                        </v-col>
                    </v-row>
                </v-form>
            </v-card-text>
        </v-card>

        <v-card class="mt-4">
            <v-card-title>Upcoming Shifts</v-card-title>
            <v-data-table :headers="headers" :items="shifts" :loading="loading">
                <template v-slot:item.start_at="{ value }">
                    {{ formatDateTime(value) }}
                </template>
                <template v-slot:item.end_at="{ value }">
                    {{ formatDateTime(value) }}
                </template>
                <template v-slot:item.actions="{ item }">
                    <v-btn icon x-small color="red" @click="deleteShift(item.id || item.raw?.id || item.value)">
                        <v-icon>mdi-delete</v-icon>
                    </v-btn>
                </template>
            </v-data-table>
        </v-card>
    </v-container>
</template>

<script>
import axios from 'axios';
import moment from 'moment';

export default {
    data() {
        return {
            loading: true,
            processing: false,
            valid: false,
            shifts: [],
            users: [],
            newShift: {
                user_id: null,
                start_at: '',
                end_at: ''
            },
            headers: [
                { title: 'Officer', key: 'user.name' },
                { title: 'Start', key: 'start_at' },
                { title: 'End', key: 'end_at' },
                { title: 'Actions', key: 'actions', sortable: false }
            ]
        };
    },
    async mounted() {
        await Promise.all([
            this.fetchUsers(),
            this.fetchShifts()
        ]);
        this.loading = false;

        // Default new shift to next 24h
        this.newShift.start_at = moment().format('YYYY-MM-DDTHH:mm');
        this.newShift.end_at = moment().add(24, 'hours').format('YYYY-MM-DDTHH:mm');
    },
    methods: {
        async fetchUsers() {
            try {
                const res = await axios.get('/api/admin/users');
                // Filter to only show approved admins
                this.users = res.data.data.filter(u => u.is_admin && u.is_approved);
            } catch (e) {
                console.error("Error fetching users", e);
            }
        },
        async fetchShifts() {
            this.loading = true;
            try {
                const res = await axios.get('/api/admin/shifts');
                this.shifts = res.data.data;
            } catch (e) {
                console.error("Error fetching shifts", e);
            } finally {
                this.loading = false;
            }
        },
        formatDateTime(d) {
            return moment(d).format('ddd Do MMM HH:mm');
        },
        async addShift() {
            const { valid } = await this.$refs.form.validate();
            if (!valid) return;

            this.processing = true;
            try {
                await axios.post('/api/admin/shifts', this.newShift);
                this.$toast.success('Shift added successfully');
                await this.fetchShifts();
                // Keep dates but clear user for easier consecutive entry
                this.newShift.user_id = null;
                this.$refs.form.resetValidation();
            } catch (e) {
                console.error(e);
                let msg = 'Failed to add shift';
                if (e.response && e.response.data) {
                    if (e.response.data.errors) {
                        const firstKey = Object.keys(e.response.data.errors)[0];
                        msg = e.response.data.errors[firstKey][0];
                    } else if (e.response.data.message) {
                        msg = e.response.data.message;
                    }
                }
                this.$toast.error(msg);
            } finally {
                this.processing = false;
            }
        },
        async deleteShift(id) {
            // Initial confirmation
            if (!confirm("Remove this shift?")) return;

            try {
                // Delete the shift and get information about affected callouts from backend
                const response = await axios.delete(`/api/admin/shifts/${id}`);

                // Check if there are affected callouts
                if (response.data.count > 0) {
                    const calloutsList = response.data.affected_callouts;
                    const calloutDetails = calloutsList.map(c =>
                        `  • ${c.cave_name} (${c.user_name}) at ${new Date(c.callout_time).toLocaleString()}`
                    ).join('\n');

                    const message = `⚠️ WARNING: This shift had ${response.data.count} open callout(s)!\n\n` +
                        `${calloutDetails}\n\n` +
                        `These callouts are now UNMONITORED.\n` +
                        `You should add another Duty Officer to cover these callouts immediately.`;

                    alert(message);
                    this.$toast.warning(`Shift removed - ${response.data.count} callout(s) now unmonitored!`);
                } else {
                    this.$toast.success('Shift removed');
                }

                await this.fetchShifts();
            } catch (e) {
                console.error(e);
                this.$toast.error('Failed to delete shift');
            }
        }
    }
};
</script>
