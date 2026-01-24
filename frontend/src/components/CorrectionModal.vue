<template>
  <v-dialog v-model="dialog" max-width="600">
    <template v-slot:activator="{ props }">
      <v-btn
        variant="text"
        color="warning"
        prepend-icon="mdi-flag"
        v-bind="props"
        class="text-none"
        size="small"
      >
        Report Issue
      </v-btn>
    </template>
    <v-card>
      <v-card-title class="text-h5">
        Suggest Factual Correction
      </v-card-title>
      <v-card-text>
        <p class="mb-4 text-body-1">
          Found a mistake on the <strong>{{ entityName }}</strong> page? Please let us know.
        </p>
        
        <v-form ref="form" v-model="valid" @submit.prevent="submit">
          <v-textarea
            v-model="correction"
            label="Correction Details"
            placeholder="e.g., The coordinates seem off, or the description mentions..."
            rows="5"
            auto-grow
            :rules="[v => !!v || 'Correction details are required', v => v.length > 10 || 'Please provide more detail']"
            required
            variant="outlined"
          ></v-textarea>
        </v-form>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="dialog = false" :disabled="loading">Cancel</v-btn>
        <v-btn
          color="primary"
          variant="flat"
          @click="submit"
          :loading="loading"
          :disabled="!valid"
        >
          Submit Report
        </v-btn>
      </v-card-actions>
    </v-card>
    
    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const form = ref(null);

const props = defineProps({
  entityType: {
    type: String,
    required: true
  },
  entityId: {
    type: [Number, String],
    required: true
  },
  entityName: {
    type: String,
    required: true
  }
});

const dialog = ref(false);
const correction = ref('');
const loading = ref(false);
const valid = ref(false);
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const submit = async () => {
  if (!valid.value) return;

  loading.value = true;

  try {
    const response = await fetch('/api/corrections', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // 'X-XSRF-TOKEN': ... handled by browser cookies usually with Sanctum
      },
      body: JSON.stringify({
        correction: correction.value,
        entity_name: props.entityName,
        entity_type: props.entityType,
        entity_id: props.entityId,
        url: window.location.href
      })
    });

    if (!response.ok) {
      throw new Error('Failed to submit correction');
    }

    snackbarText.value = 'Thank you! Your correction has been submitted.';
    snackbarColor.value = 'success';
    snackbar.value = true;

    // Reset form and validation state
    if (form.value) {
      form.value.reset();
    }
    correction.value = ''; // Ensure data is cleared

    setTimeout(() => {
      dialog.value = false;
    }, 1500);

  } catch (error) {
    console.error(error);
    snackbarText.value = 'Error submitting report. Please try again.';
    snackbarColor.value = 'error';
    snackbar.value = true;
  } finally {
    loading.value = false;
  }
};
</script>
