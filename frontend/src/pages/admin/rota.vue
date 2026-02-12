<template>
    <v-container>
        <v-toolbar flat>
            <v-btn icon @click="$router.back()"><v-icon>mdi-arrow-left</v-icon></v-btn>
            <v-toolbar-title>On-Call Rota</v-toolbar-title>
            <v-spacer></v-spacer>
            <v-btn-toggle v-model="viewMode" mandatory class="mr-4">
                <v-btn value="list" icon><v-icon>mdi-view-list</v-icon></v-btn>
                <v-btn value="calendar" icon><v-icon>mdi-calendar</v-icon></v-btn>
            </v-btn-toggle>
        </v-toolbar>

        <v-row>
            <v-col cols="12" md="4">
                <v-card class="mt-4" outlined>
                    <v-card-title>
                        <v-icon left>mdi-calendar-plus</v-icon> Add Shift
                    </v-card-title>
                    <v-card-text>
                        <v-form @submit.prevent="addShift" ref="form" v-model="valid">
                            <v-select v-model="newShift.user_id" :items="users" item-title="name" item-value="id"
                                label="Officer" outlined dense :rules="[v => !!v || 'User is required']"
                                required></v-select>
                            
                            <v-text-field v-model="newShift.start_at" type="datetime-local" label="Start" outlined dense
                                :rules="[v => !!v || 'Start time is required']" required></v-text-field>

                            <v-text-field v-model="newShift.end_at" type="datetime-local" label="End" outlined dense
                                :rules="[v => !!v || 'End time is required']" required></v-text-field>

                            <v-btn block color="primary" type="submit" :loading="processing"
                                :disabled="!valid">Add Shift</v-btn>
                        </v-form>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="8">
                <!-- Calendar View -->
                <v-card v-if="viewMode === 'calendar'" class="mt-4" outlined>
                    <v-card-title class="d-flex align-center">
                        <v-btn icon small @click="prevMonth"><v-icon>mdi-chevron-left</v-icon></v-btn>
                        <span class="mx-4">{{ currentMonthName }}</span>
                        <v-btn icon small @click="nextMonth"><v-icon>mdi-chevron-right</v-icon></v-btn>
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <div class="calendar-grid">
                            <div v-for="day in weekDays" :key="day" class="calendar-header-day">{{ day }}</div>
                            <div v-for="(cell, i) in calendarCells" :key="i" class="calendar-day" 
                                :class="{ 'not-current': !cell.current, 'today': cell.today }">
                                <div class="day-number">{{ cell.date.date() }}</div>
                                <div class="day-events">
                                    <div v-for="shift in cell.shifts" :key="shift.id" 
                                        class="shift-event" 
                                        :style="{ background: getEventColor(shift.user_id) }"
                                        @click="editShift(shift)">
                                        {{ shift.user.name.split(' ')[0] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>

                <!-- List View -->
                <v-card v-else class="mt-4" outlined>
                    <v-card-title>Upcoming Shifts</v-card-title>
                    <v-data-table :headers="headers" :items="shifts" :loading="loading">
                        <template v-slot:item.start_at="{ value }">
                            {{ formatDateTime(value) }}
                        </template>
                        <template v-slot:item.end_at="{ value }">
                            {{ formatDateTime(value) }}
                        </template>
                        <template v-slot:item.actions="{ item }">
                            <v-btn icon x-small color="primary" @click="editShift(item.raw || item)" class="mr-2">
                                <v-icon>mdi-pencil</v-icon>
                            </v-btn>
                            <v-btn icon x-small color="red" @click="deleteShift(item.id || item.raw?.id || item.value)">
                                <v-icon>mdi-delete</v-icon>
                            </v-btn>
                        </template>
                    </v-data-table>
                </v-card>
            </v-col>
        </v-row>

        <!-- Edit Dialog -->
        <v-dialog v-model="editDialog" max-width="500">
            <v-card v-if="editingShift">
                <v-card-title>Edit Shift</v-card-title>
                <v-card-text>
                    <v-form ref="editForm">
                        <v-select v-model="editingShift.user_id" :items="users" item-title="name" item-value="id"
                            label="Officer" outlined dense required></v-select>
                        
                        <v-text-field v-model="editingShift.start_at" type="datetime-local" label="Start" outlined dense required></v-text-field>

                        <v-text-field v-model="editingShift.end_at" type="datetime-local" label="End" outlined dense required></v-text-field>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-btn color="error" text @click="deleteShift(editingShift.id)">Delete</v-btn>
                    <v-spacer></v-spacer>
                    <v-btn text @click="editDialog = false">Cancel</v-btn>
                    <v-btn color="primary" @click="saveShift" :loading="processing">Save</v-btn>
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
            loading: true,
            processing: false,
            valid: false,
            viewMode: 'calendar',
            shifts: [],
            users: [],
            currentDate: moment(),
            weekDays: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            newShift: {
                user_id: null,
                start_at: '',
                end_at: ''
            },
            editDialog: false,
            editingShift: null,
            headers: [
                { title: 'Officer', key: 'user.name' },
                { title: 'Start', key: 'start_at' },
                { title: 'End', key: 'end_at' },
                { title: 'Actions', key: 'actions', sortable: false }
            ]
        };
    },
    computed: {
        currentMonthName() {
            return this.currentDate.format('MMMM YYYY');
        },
        calendarCells() {
            const cells = [];
            const startOfMonth = moment(this.currentDate).startOf('month');
            const endOfMonth = moment(this.currentDate).endOf('month');

            // Start from the first Monday before or on the 1st
            const start = moment(startOfMonth).startOf('isoWeek');
            const end = moment(endOfMonth).endOf('isoWeek');

            const curr = moment(start);
            while (curr.isBefore(end)) {
                const dayShifts = this.shifts.filter(s => {
                    const sStart = moment(s.start_at);
                    const sEnd = moment(s.end_at);
                    const dayStart = moment(curr).startOf('day');
                    const dayEnd = moment(curr).endOf('day');

                    // A shift overlaps with this day if it starts before the end of the day 
                    // AND ends after the start of the day.
                    return sStart.isBefore(dayEnd) && sEnd.isAfter(dayStart);
                });

                cells.push({
                    date: moment(curr),
                    current: curr.month() === this.currentDate.month(),
                    today: curr.isSame(moment(), 'day'),
                    shifts: dayShifts
                });
                curr.add(1, 'day');
            }
            return cells;
        }
    },
    async mounted() {
        await Promise.all([
            this.fetchUsers(),
            this.fetchShifts()
        ]);
        this.loading = false;

        // Default new shift to 07:30 - 11:31 today
        this.newShift.start_at = moment().set({ hour: 7, minute: 30 }).format('YYYY-MM-DDTHH:mm');
        this.newShift.end_at = moment().set({ hour: 23, minute: 31 }).format('YYYY-MM-DDTHH:mm');
    },
    methods: {
        async fetchUsers() {
            try {
                const res = await axios.get('/api/admin/duty-officers');
                this.users = res.data.data;
            } catch (e) {
                console.error("Error fetching users", e);
            }
        },
        async fetchShifts() {
            this.loading = true;
            try {
                // Fetch a range for the calendar
                const start = moment(this.currentDate).startOf('month').subtract(7, 'days').format('YYYY-MM-DD');
                const end = moment(this.currentDate).endOf('month').add(7, 'days').format('YYYY-MM-DD');
                const res = await axios.get(`/api/admin/shifts?start=${start}&end=${end}`);
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
        prevMonth() {
            this.currentDate = moment(this.currentDate).subtract(1, 'month');
            this.fetchShifts();
        },
        nextMonth() {
            this.currentDate = moment(this.currentDate).add(1, 'month');
            this.fetchShifts();
        },
        getEventColor(userId) {
            // Simple deterministic color from ID
            const colors = ['#1976D2', '#388E3C', '#F57C00', '#7B1FA2', '#C2185B', '#0097A7', '#689F38'];
            return colors[userId % colors.length];
        },
        async addShift() {
            const { valid } = await this.$refs.form.validate();
            if (!valid) return;

            this.processing = true;
            try {
                await axios.post('/api/admin/shifts', this.newShift);
                this.$toast.success('Shift added successfully');
                await this.fetchShifts();
                this.newShift.user_id = null;
                this.$refs.form.resetValidation();
            } catch (e) {
                this.handleApiError(e, 'Failed to add shift');
            } finally {
                this.processing = false;
            }
        },
        editShift(shift) {
            this.editingShift = {
                id: shift.id,
                user_id: shift.user_id,
                start_at: moment(shift.start_at).format('YYYY-MM-DDTHH:mm'),
                end_at: moment(shift.end_at).format('YYYY-MM-DDTHH:mm')
            };
            this.editDialog = true;
        },
        async saveShift() {
            this.processing = true;
            try {
                await axios.put(`/api/admin/shifts/${this.editingShift.id}`, this.editingShift);
                this.$toast.success('Shift updated successfully');
                this.editDialog = false;
                await this.fetchShifts();
            } catch (e) {
                this.handleApiError(e, 'Failed to update shift');
            } finally {
                this.processing = false;
            }
        },
        async deleteShift(id) {
            if (!confirm("Remove this shift?")) return;

            try {
                await axios.delete(`/api/admin/shifts/${id}`);
                this.$toast.success('Shift removed');
                this.editDialog = false; // Close dialog if open
                await this.fetchShifts();
            } catch (e) {
                this.handleApiError(e, 'Failed to delete shift');
            }
        },
        handleApiError(e, defaultMsg) {
            console.error(e);
            let msg = defaultMsg;
            if (e.response && e.response.data) {
                if (e.response.data.errors) {
                    const firstKey = Object.keys(e.response.data.errors)[0];
                    msg = e.response.data.errors[firstKey][0];
                } else if (e.response.data.message) {
                    msg = e.response.data.message;
                }

                // If specialized "orphaned callouts" error
                if (e.response.status === 422 && e.response.data.affected_callouts) {
                    const callouts = e.response.data.affected_callouts;
                    const details = callouts.map(c => `• ${c.cave_name} (${c.user_name})`).join('\n');
                    alert(`${msg}\n\nExisting callouts require coverage:\n${details}`);
                }
            }
            this.$toast.error(msg);
        }
    }
};
</script>

<style scoped>
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-top: 1px solid #e0e0e0;
    border-left: 1px solid #e0e0e0;
}

.calendar-header-day {
    padding: 8px;
    text-align: center;
    font-weight: bold;
    background: #f5f5f5;
    border-right: 1px solid #e0e0e0;
    border-bottom: 1px solid #e0e0e0;
    font-size: 0.8rem;
}

.calendar-day {
    min-height: 100px;
    padding: 4px;
    border-right: 1px solid #e0e0e0;
    border-bottom: 1px solid #e0e0e0;
    position: relative;
    background: white;
}

.calendar-day.not-current {
    background: #fafafa;
    color: #bdbdbd;
}

.calendar-day.today {
    background: #e3f2fd;
}

.day-number {
    font-size: 0.8rem;
    font-weight: bold;
    margin-bottom: 4px;
}

.day-events {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.shift-event {
    font-size: 0.7rem;
    padding: 2px 4px;
    border-radius: 4px;
    color: white;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.shift-event:hover {
    filter: brightness(1.1);
}
</style>
