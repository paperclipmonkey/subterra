<template>
  <span>
    <v-btn class="float-right" icon @click="showConfirmModal = true">
      <v-icon>mdi-check</v-icon>
    </v-btn>
    <v-dialog v-model="showConfirmModal" max-width="500">
      <v-card>
        <v-card-title>Confirm</v-card-title>
        <v-card-text>Are you sure you want to mark this cave as done?</v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="showConfirmModal = false">Cancel</v-btn>
          <v-btn text color="primary" @click="markAsDone">Confirm</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </span>
</template>

<script setup>
import { ref } from 'vue'
import { useAppStore } from '@/stores/app'
import { markCaveAsDone } from '@/stores/markAsDone'

const props = defineProps({
  cave: { type: Object, required: true },
  onDone: { type: Function, required: false },
})

const showConfirmModal = ref(false)
const appStore = useAppStore()

const markAsDone = async () => {
  const ok = await markCaveAsDone({ cave: props.cave, userId: appStore.user.id })
  if (ok) {
    showConfirmModal.value = false
    if (props.onDone) props.onDone()
  } else {
    console.error('failed to save trip')
  }
}
</script>
