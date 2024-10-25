<template>
  <v-dialog v-model="props.isActive" max-width="500">
    <template v-slot:default="{ isActive }">
      <v-card rounded="lg">
        <v-card-title class="d-flex justify-space-between align-center">
          <div class="text-h5 text-medium-emphasis ps-2">
            Filter
          </div>
          <v-btn
            icon="mdi-close"
            variant="text"
            @click="$emit('close')"
          ></v-btn>
        </v-card-title>

        <v-divider class="mb-4"></v-divider>

        <v-card-text>
          <template v-for="(groupItems, groupName) in tagsAvailable">

          <h2 class="text-h6 mb-2 tagGroupTitle">{{groupName}}</h2>

          <!-- multiple -->
          <v-chip-group
            v-model="selectedTags[groupName]"
            column
          >
            <v-chip
              v-for="tag in groupItems"
              :text="tag.tag"
              variant="outlined"
              :value="tag.tag"
              filter
            ></v-chip>
          </v-chip-group>
          </template>
        </v-card-text>
        <v-divider class="mt-2"></v-divider>

        <v-card-actions class="my-2 d-flex justify-end">
          <v-btn
            class="text-none"
            rounded="xl"
            text="Cancel"
            @click="$emit('close')"
          ></v-btn>

          <v-btn
            class="text-none"
            color="primary"
            rounded="xl"
            text="Filter"
            variant="flat"
            @click="emitFilters"
          ></v-btn>
        </v-card-actions>
      </v-card>
    </template>
  </v-dialog>
</template>

<script setup>
import { ref, defineProps, onMounted } from 'vue';
const emit = defineEmits(['filter', 'close'])

const tagsAvailable = ref({});
const selectedTags = ref({});

onMounted(async () => {
  const response = await fetch('/api/tags');
  tagsAvailable.value = await response.json();
});

const emitFilters = () => {
  const filters = Object.values(selectedTags.value).flat();
  emit('filter', filters);
};

const props = defineProps(['isActive'])
</script>

<style scoped>
  .tagGroupTitle {
    text-transform: capitalize !important;
  }
</style>