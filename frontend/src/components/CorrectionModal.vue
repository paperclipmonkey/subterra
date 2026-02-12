<template>
  <v-dialog v-model="dialog" max-width="600">
    <template #activator="{ props }">
      <v-btn
        v-if="userStore.canSuggest"
        variant="text"
        color="warning"
        prepend-icon="mdi-flag"
        v-bind="props"
        class="text-none"
        size="small"
      >
        Report Issue
      </v-btn>
      <v-btn
        v-else
        variant="text"
        color="grey"
        disabled
        prepend-icon="mdi-flag-off"
        class="text-none"
        size="small"
      >
        <v-tooltip activator="parent" location="top">
          {{ userStore.user?.id ? (!userStore.canSuggest ? 'Account must be approved' : 'You must join a club') : 'Log in' }} to report issues
        </v-tooltip>
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
          />
        </v-form>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" :disabled="loading" @click="dialog = false">Cancel</v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :loading="loading"
          :disabled="!valid"
          @click="submit"
        >
          Submit Report
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAppStore } from '@/stores/app';
import { useToast } from "vue-toastification";

const toast = useToast();
const route = useRoute();
const userStore = useAppStore();
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

    toast.success('Thank you! Your suggestion has been submitted for review.');

    // Reset form and validation state
    if (form.value) {
      form.value.reset();
    }
    correction.value = ''; // Ensure data is cleared

    setTimeout(() => {
      dialog.value = false;
    }, 500);

  } catch (error) {
    console.error(error);
    toast.error('Error submitting report. Please try again.');
  } finally {
    loading.value = false;
  }
};
</script>
