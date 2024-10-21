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
          <h2 class="text-h6 mb-2">Region</h2>

          <v-chip-group
            v-model="region"
            column
            multiple
          >
            <v-chip
              text="South Wales"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="North Wales"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Forest of Dean"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Devon"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Mendip"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Portland"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Peaks"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Yorkshire"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Assynt"
              variant="outlined"
              filter
            ></v-chip>
          </v-chip-group>


          <h2 class="text-h6 mb-2">Type</h2>

          <v-chip-group
            v-model="type"
            column
            multiple
          >
            <v-chip
              text="Cave"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Mine"
              variant="outlined"
              filter
            ></v-chip>
          </v-chip-group>

          <h2 class="text-h6 mb-2">Access</h2>

          <v-chip-group
            v-model="access"
            column
            multiple
          >
            <v-chip
              text="Gated"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Leader"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Keycode"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Open"
              variant="outlined"
              filter
            ></v-chip>
          </v-chip-group>

          <h2 class="text-h6 mb-2">Tackle</h2>

          <v-chip-group
            v-model="tackle"
            column
            multiple
          >
            <v-chip
              text="No tackle"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Ladder"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="SRT"
              variant="outlined"
              filter
            ></v-chip>

            <v-chip
              text="Confidence rope"
              variant="outlined"
              filter
            ></v-chip>
          </v-chip-group>
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
import { ref, defineProps } from 'vue';
const emit = defineEmits(['filter', 'close'])


const emitFilters = () => {
  const filters = {
    type: type.value,
    access: access.value,
    tackle: tackle.value,
  };

  emit('filter', filters);
};

const props = defineProps(['isActive'])
const type = ref([])
const access = ref([])
const tackle = ref([])
const region = ref([])
</script>