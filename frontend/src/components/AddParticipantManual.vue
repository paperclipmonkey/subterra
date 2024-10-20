<template>
  <!-- <div class="pa-4 text-center"> -->
    <!-- <v-btn-group
      color="#b2d7ef"
      density="comfortable"
      rounded="pill"
      divided
    >
      <v-btn
        class="pe-2"
        prepend-icon="mdi-account-multiple-outline"
        variant="flat"
      >
        <div class="text-none font-weight-regular">
          Add participant manually
        </div> -->

        <v-dialog v-model="props.isActive" max-width="500">
          <template v-slot:default="{ isActive }">
            <v-card rounded="lg">
              <v-card-title class="d-flex justify-space-between align-center">
                <div class="text-h5 text-medium-emphasis ps-2">
                  Add trip participants
                </div>

                <v-btn
                  icon="mdi-close"
                  variant="text"
                  @click="isActive.value = false"
                ></v-btn>
              </v-card-title>

              <v-divider class="mb-4"></v-divider>

              <v-card-text>
                <div class="text-medium-emphasis mb-4">
                  Add the trip participant by email addresses
                </div>

                <v-text-field
                  label="Email address"
                  placeholder="johndoe@gmail.com"
                  type="email"
                  v-model="email"
                ></v-text-field>
              </v-card-text>

              <v-divider class="mt-2"></v-divider>

              <v-card-actions class="my-2 d-flex justify-end">
                <v-btn
                  class="text-none"
                  rounded="xl"
                  text="Cancel"
                  @click="isActive.value = false"
                ></v-btn>

                <v-btn
                  class="text-none"
                  color="primary"
                  rounded="xl"
                  text="Add"
                  variant="flat"
                  @click="$emit('add', email); email = '';"
                ></v-btn>
              </v-card-actions>
            </v-card>
          </template>
        </v-dialog>
      <!-- </v-btn>
    </v-btn-group> -->
  <!-- </div> -->
</template>

<script setup>
const props = defineProps(['isActive'])
const email = ref('')
</script>