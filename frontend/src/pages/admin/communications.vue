<template>
  <v-container>
    <h2 class="headline mb-4">Mass Communications</h2>
    
    <v-alert v-if="successMessage" type="success" closable class="mb-4">
      {{ successMessage }}
    </v-alert>

    <v-alert v-if="error" type="error" closable class="mb-4">
      {{ error }}
    </v-alert>

    <v-card :loading="loading">
      <v-card-title>
        <v-icon start>mdi-email-outline</v-icon>
        Draft New Email
      </v-card-title>
      
      <v-card-text>
        <p class="mb-4 text-body-2">
            Send emails to all users who have opted in to "Platform News". 
            Use the "Send Test" button to send a preview to yourself first.
        </p>

        <v-form ref="form" v-model="valid">
            <v-text-field
                v-model="subject"
                label="Subject Line"
                required
                :rules="[v => !!v || 'Subject is required']"
                variant="outlined"
                class="mb-2"
            ></v-text-field>

            <div class="mb-2">
                <label class="v-label mb-2 d-block">Message Body (Markdown)</label>
                <MilkdownEditor 
                    v-model="body" 
                    placeholder="Write your update here... Use markdown for formatting."
                />
                <div class="text-caption text-grey mt-1" v-pre>
                    Available variables: 
                    <code class="text-primary">{{ firstname }}</code>, 
                    <code class="text-primary">{{ fullname }}</code>, 
                    <code class="text-primary">{{ club }}</code>, 
                    <code class="text-primary">{{ id }}</code>
                </div>
            </div>
        </v-form>
      </v-card-text>
      
      <v-card-actions>
        <v-spacer></v-spacer>
        
        <v-btn
            color="secondary"
            variant="text"
            :loading="loading"
            :disabled="!valid || !subject || !body"
            @click="send(true)"
        >
            <v-icon start>mdi-test-tube</v-icon>
            Send Test to Me
        </v-btn>

        <v-btn
            color="primary"
            variant="elevated"
            :loading="loading"
            :disabled="!valid || !subject || !body"
            @click="confirmSend"
        >
            <v-icon start>mdi-send</v-icon>
            Send to All Subscribers
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Confirmation Dialog -->
    <v-dialog v-model="confirmDialog" max-width="500">
      <v-card>
        <v-card-title class="text-h5">Confirm Mass Send</v-card-title>
        <v-card-text>
          Are you sure you want to send this email to all subscribed users? 
          This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey-darken-1" variant="text" @click="confirmDialog = false">Cancel</v-btn>
          <v-btn color="red-darken-1" variant="text" @click="doSend">Yes, Send It</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

  </v-container>
</template>

<script setup>
import { ref } from 'vue'
import { api } from '@/plugins/api'
import MilkdownEditor from '@/components/MilkdownEditor.vue'

const loading = ref(false)
const error = ref(null)
const successMessage = ref(null)
const confirmDialog = ref(false)
const valid = ref(false)

const subject = ref('')
const body = ref('')

const confirmSend = () => {
  confirmDialog.value = true
}

const doSend = () => {
  confirmDialog.value = false
  send(false)
}

const send = async (testMode) => {
  loading.value = true
  error.value = null
  successMessage.value = null

  try {
    const response = await api.post('/api/admin/communications/send', {
      subject: subject.value,
      body: body.value,
      test_mode: testMode
    })

    successMessage.value = response.data.message || 'Email sent successfully'

    if (!testMode) {
      // clear form only on real send
      subject.value = ''
      body.value = ''
    } else {
      // For test mode, maybe keep the form filled so they can tweak it?
    }
  } catch (err) {
    error.value = err.response?.data?.message || err.message || 'Failed to send email'
    console.error('Error sending email:', err)
  } finally {
    loading.value = false
  }
}
</script>
