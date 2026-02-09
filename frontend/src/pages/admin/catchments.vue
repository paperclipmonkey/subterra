<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12" class="d-flex align-center">
        <h1 class="text-h4">Catchments</h1>
        <v-spacer></v-spacer>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">
          New Catchment
        </v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-data-table
        :headers="headers"
        :items="catchments"
        :loading="loading"
        :hide-default-footer="true"
        :items-per-page="-1"
        class="elevation-1"
      >
        <template v-slot:item.gauges="{ item }">
          {{ item.gauges ? item.gauges.length : 0 }} gauges
        </template>
        
        <template v-slot:item.cave_systems_count="{ item }">
          <router-link :to="`/caves?catchment=${item.id}`" class="text-decoration-none">
            <v-chip size="small" :color="item.cave_systems_count > 0 ? 'info' : 'default'" class="cursor-pointer">
              {{ item.cave_systems_count }}
            </v-chip>
          </router-link>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-icon size="small" class="me-2" @click="editItem(item)">
            mdi-pencil
          </v-icon>
          <v-icon size="small" color="error" @click="deleteItem(item)" :disabled="item.cave_systems_count > 0">
            mdi-delete
          </v-icon>
        </template>
      </v-data-table>
    </v-card>

    <!-- Dialog -->
    <v-dialog v-model="dialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="text-h5">{{ editedIndex === -1 ? 'New Catchment' : 'Edit Catchment' }}</span>
        </v-card-title>

        <v-card-text>
          <v-container>
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="editedItem.name"
                  label="Name"
                  required
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="editedItem.reference_id"
                  label="Reference ID"
                  hint="Unique alphanumeric ID"
                  persistent-hint
                  required
                ></v-text-field>
              </v-col>
              
              <v-col cols="12">
                <div class="d-flex align-center mb-2">
                    <h3 class="text-subtitle-1">Gauges</h3>
                    <v-spacer></v-spacer>
                    <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="addGauge">Add Gauge</v-btn>
                </div>
                
                <div v-for="(gauge, index) in editedItem.gauges" :key="index" class="d-flex align-center gap-4 mb-2">
                    <v-text-field
                        v-model="gauge.name"
                        label="Gauge Name"
                        density="compact"
                        hide-details
                        class="mr-2"
                        style="max-width: 200px;"
                    ></v-text-field>
                    <v-select
                        v-model="gauge.type"
                        :items="['river', 'rain']"
                        label="Type"
                        density="compact"
                        hide-details
                        class="mr-2"
                        style="max-width: 120px;"
                    ></v-select>
                     <v-text-field
                        v-if="!gauge.type || gauge.type === 'river'"
                        v-model="gauge.rloi_id"
                        label="RLOI ID"
                        density="compact"
                        hide-details
                        class="mr-2"
                        type="number"
                    ></v-text-field>
                    <v-text-field
                        v-if="gauge.type === 'rain'"
                        v-model="gauge.station_id"
                        label="Station ID"
                        density="compact"
                        hide-details
                        class="mr-2"
                    ></v-text-field>
                    <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="removeGauge(index)"></v-btn>
                </div>
                <div v-if="!editedItem.gauges || editedItem.gauges.length === 0" class="text-caption text-grey font-italic">
                    No gauges added yet.
                </div>
              </v-col>
            </v-row>
          </v-container>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="blue-darken-1" variant="text" @click="close">Cancel</v-btn>
          <v-btn color="blue-darken-1" variant="text" @click="save">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    
    <!-- Delete Confirmation -->
    <v-dialog v-model="dialogDelete" max-width="500px">
        <v-card>
            <v-card-title class="text-h5">Delete Catchment?</v-card-title>
             <v-card-text>Are you sure you want to delete this catchment? This action cannot be undone.</v-card-text>
            <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn color="blue-darken-1" variant="text" @click="closeDelete">Cancel</v-btn>
                <v-btn color="error" variant="text" @click="deleteItemConfirm">OK</v-btn>
                <v-spacer></v-spacer>
            </v-card-actions>
        </v-card>
    </v-dialog>
    
    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="3000">
        {{ snackbarText }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const loading = ref(false);
const catchments = ref([]);
const dialog = ref(false);
const dialogDelete = ref(false);
const editedIndex = ref(-1);

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const headers = [
  { title: 'Name', align: 'start', key: 'name' },
  { title: 'Reference ID', key: 'reference_id' },
  { title: 'Gauges', key: 'gauges', sortable: false },
  { title: 'Cave Systems', key: 'cave_systems_count' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
];

const defaultItem = {
  name: '',
  reference_id: '',
  gauges: []
};

const editedItem = ref({ ...defaultItem });

onMounted(() => {
  fetchCatchments();
});

const generateReferenceId = () => {
  return Math.random().toString(36).substring(2, 8).toUpperCase();
}

const fetchCatchments = async () => {
  loading.value = true;
  try {
    const response = await fetch('/api/admin/catchments');
    if (response.ok) {
      const json = await response.json();
      catchments.value = json.data;
    } else {
      showSnackbar('Failed to fetch catchments', 'error');
    }
  } catch (error) {
    console.error(error);
    showSnackbar('Error fetching catchments', 'error');
  } finally {
    loading.value = false;
  }
};

const openCreateDialog = () => {
  editedIndex.value = -1;
  editedItem.value = JSON.parse(JSON.stringify(defaultItem));
  editedItem.value.reference_id = generateReferenceId();
  dialog.value = true;
}

const editItem = (item) => {
  editedIndex.value = catchments.value.indexOf(item);
  editedItem.value = JSON.parse(JSON.stringify(item));
  // Ensure gauges is array
  if (!editedItem.value.gauges) editedItem.value.gauges = [];
  dialog.value = true;
};

const deleteItem = (item) => {
  editedIndex.value = catchments.value.indexOf(item);
  editedItem.value = { ...item };
  dialogDelete.value = true;
};

const deleteItemConfirm = async () => {
  try {
    const response = await fetch(`/api/admin/catchments/${editedItem.value.id}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      }
    });

    if (response.ok) {
      catchments.value.splice(editedIndex.value, 1);
      showSnackbar('Catchment deleted successfully');
    } else {
      const data = await response.json();
      showSnackbar(data.message || 'Failed to delete catchment', 'error');
    }
  } catch (e) {
    showSnackbar('Error deleting catchment', 'error');
  }
  closeDelete();
};

const close = () => {
  dialog.value = false;
  editedItem.value = { ...defaultItem };
  editedIndex.value = -1;
};

const closeDelete = () => {
  dialogDelete.value = false;
  editedItem.value = { ...defaultItem };
  editedIndex.value = -1;
};

const save = async () => {
  const method = editedIndex.value > -1 ? 'PUT' : 'POST';
  const url = editedIndex.value > -1
    ? `/api/admin/catchments/${editedItem.value.id}`
    : '/api/admin/catchments';

  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(editedItem.value)
    });

    if (response.ok) {
      const json = await response.json();
      if (editedIndex.value > -1) {
        Object.assign(catchments.value[editedIndex.value], json.data);
      } else {
        // For new items, we might need a refresh to get the count or just push 
        json.data.cave_systems_count = 0;
        catchments.value.push(json.data);
      }
      showSnackbar('Catchment saved successfully');
      close();
    } else {
      const data = await response.json();
      showSnackbar(data.message || 'Failed to save', 'error');
    }
  } catch (e) {
    showSnackbar('Error saving catchment', 'error');
  }
};

const addGauge = () => {
  editedItem.value.gauges.push({ name: '', rloi_id: '', type: 'river' });
}

const removeGauge = (index) => {
  editedItem.value.gauges.splice(index, 1);
}

const showSnackbar = (text, color = 'success') => {
  snackbarText.value = text;
  snackbarColor.value = color;
  snackbar.value = true;
}
</script>
