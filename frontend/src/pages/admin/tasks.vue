<template>
  <v-container>
    <div class="mb-6">
      <h2 class="display-1 font-weight-bold mb-1">Data Quality Tasks</h2>
      <p class="subtitle-1 grey--text text--darken-1">Identify and fix gaps in cave and system data.</p>
    </div>

    <v-row>
      <!-- Caves Missing Photos -->
      <v-col cols="12" md="6" lg="4">
        <v-card height="100%" class="d-flex flex-column">
          <v-card-title class="primary--text">
            <v-icon left color="primary">mdi-image-off</v-icon>
            Missing Photos <v-chip small class="ml-2">{{ tasks.caves_no_photo.length }}</v-chip>
          </v-card-title>
          <v-divider></v-divider>
          <v-list v-if="tasks.caves_no_photo.length > 0" class="flex-grow-1 overflow-y-auto" style="max-height: 400px">
             <template v-for="(cave, i) in tasks.caves_no_photo" :key="cave.id">
              <v-list-item :to="`/caves/${cave.slug}`" link>
                <template v-slot:prepend>
                  <v-icon color="grey">mdi-cave</v-icon>
                </template>
                <v-list-item-title>{{ cave.name }}</v-list-item-title>
                <v-list-item-subtitle>{{ cave.location_name || 'Unknown Location' }}</v-list-item-subtitle>
                <template v-slot:append>
                  <v-icon small>mdi-open-in-new</v-icon>
                </template>
              </v-list-item>
              <v-divider v-if="i < tasks.caves_no_photo.length - 1"></v-divider>
            </template>
          </v-list>
          <v-card-text v-else class="text-center grey--text py-8">
            <v-icon size="48" color="grey lighten-2">mdi-check-all</v-icon>
            <div class="mt-2">Good</div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Caves Missing Descriptions -->
      <v-col cols="12" md="6" lg="4">
        <v-card height="100%" class="d-flex flex-column">
          <v-card-title class="info--text">
            <v-icon left color="info">mdi-text-box-missing</v-icon>
            Missing Descriptions <v-chip small class="ml-2">{{ tasks.caves_no_description.length }}</v-chip>
          </v-card-title>
          <v-divider></v-divider>
           <v-list v-if="tasks.caves_no_description.length > 0" class="flex-grow-1 overflow-y-auto" style="max-height: 400px">
             <template v-for="(cave, i) in tasks.caves_no_description" :key="cave.id">
              <v-list-item :to="`/caves/${cave.slug}`" link>
                <template v-slot:prepend>
                  <v-icon color="grey">mdi-cave</v-icon>
                </template>
                <v-list-item-title>{{ cave.name }}</v-list-item-title>
                <v-list-item-subtitle>{{ cave.location_name || 'Unknown Location' }}</v-list-item-subtitle>
                <template v-slot:append>
                   <v-icon small>mdi-open-in-new</v-icon>
                </template>
              </v-list-item>
              <v-divider v-if="i < tasks.caves_no_description.length - 1"></v-divider>
            </template>
          </v-list>
          <v-card-text v-else class="text-center grey--text py-8">
            <v-icon size="48" color="grey lighten-2">mdi-check-all</v-icon>
            <div class="mt-2">Good</div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Caves Low Tags -->
      <v-col cols="12" md="6" lg="4">
        <v-card height="100%" class="d-flex flex-column">
          <v-card-title class="purple--text">
            <v-icon left color="purple">mdi-tag-outline</v-icon>
            Low Tags (< 3) <v-chip small class="ml-2">{{ tasks.caves_low_tags.length }}</v-chip>
          </v-card-title>
          <v-divider></v-divider>
           <v-list v-if="tasks.caves_low_tags.length > 0" class="flex-grow-1 overflow-y-auto" style="max-height: 400px">
             <template v-for="(cave, i) in tasks.caves_low_tags" :key="cave.id">
              <v-list-item :to="`/caves/${cave.slug}`" link>
                <template v-slot:prepend>
                  <v-icon color="grey">mdi-cave</v-icon>
                </template>
                <v-list-item-title>{{ cave.name }}</v-list-item-title>
                <v-list-item-subtitle>Has {{ cave.tags_count }} tags</v-list-item-subtitle>
                <template v-slot:append>
                   <v-icon small>mdi-open-in-new</v-icon>
                </template>
              </v-list-item>
              <v-divider v-if="i < tasks.caves_low_tags.length - 1"></v-divider>
            </template>
          </v-list>
          <v-card-text v-else class="text-center grey--text py-8">
            <v-icon size="48" color="grey lighten-2">mdi-check-all</v-icon>
            <div class="mt-2">Good</div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Systems Missing References -->
      <v-col cols="12" md="6" lg="6">
        <v-card height="100%" class="d-flex flex-column">
          <v-card-title class="orange--text text--darken-2">
            <v-icon left color="orange darken-2">mdi-book-open-page-variant</v-icon>
            Systems No References <v-chip small class="ml-2">{{ tasks.systems_no_references.length }}</v-chip>
          </v-card-title>
          <v-divider></v-divider>
           <v-list v-if="tasks.systems_no_references.length > 0" class="flex-grow-1 overflow-y-auto" style="max-height: 400px">
             <template v-for="(system, i) in tasks.systems_no_references" :key="system.id">
              <v-list-item :to="`/cave-systems/${system.id}/edit`" link>
                <template v-slot:prepend>
                  <v-icon color="grey">mdi-family-tree</v-icon>
                </template>
                <v-list-item-title>{{ system.name }}</v-list-item-title>
                <template v-slot:append>
                   <v-icon small>mdi-open-in-new</v-icon>
                </template>
              </v-list-item>
              <v-divider v-if="i < tasks.systems_no_references.length - 1"></v-divider>
            </template>
          </v-list>
          <v-card-text v-else class="text-center grey--text py-8">
            <v-icon size="48" color="grey lighten-2">mdi-check-all</v-icon>
            <div class="mt-2">Good</div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Systems Missing Files/Surveys -->
      <v-col cols="12" md="6" lg="6">
        <v-card height="100%" class="d-flex flex-column">
          <v-card-title class="teal--text">
            <v-icon left color="teal">mdi-file-find</v-icon>
            Systems No Surveys <v-chip small class="ml-2">{{ tasks.systems_no_files.length }}</v-chip>
          </v-card-title>
          <v-divider></v-divider>
           <v-list v-if="tasks.systems_no_files.length > 0" class="flex-grow-1 overflow-y-auto" style="max-height: 400px">
             <template v-for="(system, i) in tasks.systems_no_files" :key="system.id">
              <v-list-item :to="`/cave-systems/${system.id}/edit`" link>
                <template v-slot:prepend>
                  <v-icon color="grey">mdi-family-tree</v-icon>
                </template>
                <v-list-item-title>{{ system.name }}</v-list-item-title>
                <template v-slot:append>
                   <v-icon small>mdi-open-in-new</v-icon>
                </template>
              </v-list-item>
              <v-divider v-if="i < tasks.systems_no_files.length - 1"></v-divider>
            </template>
          </v-list>
          <v-card-text v-else class="text-center grey--text py-8">
            <v-icon size="48" color="grey lighten-2">mdi-check-all</v-icon>
            <div class="mt-2">Good</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
    
    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="3000">
      {{ snackbarText }}
      <template v-slot:actions>
        <v-btn text @click="snackbar = false">Close</v-btn>
      </template>
    </v-snackbar>
  </v-container>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      tasks: {
        caves_no_photo: [],
        caves_no_description: [],
        caves_low_tags: [],
        systems_no_references: [],
        systems_no_files: []
      },
      loading: true,
      snackbar: false,
      snackbarText: '',
      snackbarColor: 'success'
    };
  },
  mounted() {
    this.fetchTasks();
  },
  methods: {
    async fetchTasks() {
      this.loading = true;
      try {
        const res = await axios.get('/api/admin/tasks');
        this.tasks = res.data;
      } catch (e) {
        console.error(e);
        this.showSnackbar('Failed to load tasks', 'error');
      } finally {
        this.loading = false;
      }
    },
    showSnackbar(text, color) {
      this.snackbarText = text;
      this.snackbarColor = color;
      this.snackbar = true;
    }
  }
};
</script>
