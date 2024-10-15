<template>
  <v-container class="fill-height">
    <v-responsive
      class="align-centerfill-height mx-auto"
      max-width="350"
    >
      <v-img
        class="mb-4"
        height="150"
        src="@/assets/logo.png"
      />

      <div class="text-center">
        <div class="text-body-2 font-weight-light mb-n1">Welcome to</div>

        <h1 class="text-h2 font-weight-bold">Subterra</h1>
      </div>

      <div class="py-4" />

      <v-row>
        <v-col cols="12">
          <v-card
            class="py-4"
            color="surface-variant"
            prepend-icon="mdi-earth"
            rounded="lg"
            variant="outlined"
          >
            <template #title>
              <h2 class="text-h5 font-weight-bold">Caves</h2>
            </template>

            <template #subtitle>
              <div class="text-subtitle-1">
                Browse caves and cave systems
              </div>
            </template>

            <v-overlay
              opacity=".12"
              scrim="primary"
              contained
              model-value
              persistent
            />
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12">
          <v-card
            class="py-4"
            color="surface-variant"
            prepend-icon="mdi-notebook-outline"
            rounded="lg"
            variant="outlined"
          >
            <template #title>
              <h2 class="text-h5 font-weight-bold">Trips</h2>
            </template>

            <template #subtitle>
              <div class="text-subtitle-1">
                Log your trips and view public trips
              </div>
            </template>

            <v-overlay
              opacity=".12"
              scrim="primary"
              contained
              model-value
              persistent
            />
          </v-card>
        </v-col>
      </v-row>
      <v-row>
        <v-col>
          <div id="loginWithGoogle"></div>
          <div id="g_id_onload"
            data-client_id="1062453848042-08ktfa8rv4pe786okrd2dc0k3ls4hksp.apps.googleusercontent.com"
            data-login_uri="http://localhost:3000/api/google/redirect"
            data-auto_prompt="true">
          </div>
          <div class="g_id_signin"
            data-type="standard"
            data-size="large"
            data-theme="outline"
            data-text="sign_in_with"
            data-shape="rectangular"
            data-logo_alignment="left">
          </div>
        </v-col>
      </v-row>
    </v-responsive>
  </v-container>
</template>

<script setup>
import { onMounted } from 'vue';

  onMounted(() => {
    google.accounts.id.initialize({
      client_id: "1062453848042-08ktfa8rv4pe786okrd2dc0k3ls4hksp.apps.googleusercontent.com"
      // callback: handleCredentialResponse
    });
    google.accounts.id.renderButton(
      document.getElementById("loginWithGoogle"),
      { theme: "outline", size: "large" }  // customization attributes
    );
    // google.accounts.id.prompt(); // also display the One Tap dialog
  });
</script>
