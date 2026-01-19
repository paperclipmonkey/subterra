<template>
  <v-container>
    <v-row>
      <v-col>
        <v-card>
          <v-card-title>
            <span class="text-h5">{{ isEditing ? 'Edit Page' : 'Create Page' }}</span>
          </v-card-title>
          <v-card-text>
            <v-form @submit.prevent="savePage">
              <v-text-field
                v-model="page.title"
                label="Title"
                required
                @update:modelValue="generateSlug"
              ></v-text-field>
              <v-text-field
                v-model="page.slug"
                label="Slug"
                required
                hint="Unique URL identifier (e.g., my-page)"
                persistent-hint
              ></v-text-field>

              <div class="text-subtitle-1 mt-4 mb-2">Content</div>
              <MilkdownEditor 
                v-model="page.content" 
                @change="updateContent"
                placeholder="Write your page content here..." 
              />

              <div class="d-flex justify-end mt-4 ga-2">
                <v-btn variant="text" to="/admin/pages">Cancel</v-btn>
                <v-btn color="primary" type="submit" :loading="saving">Save</v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { mande } from 'mande';
import MilkdownEditor from '@/components/MilkdownEditor.vue';
import { useNotificationStore } from '@/stores/notifications';

const route = useRoute();
const router = useRouter();
const notificationStore = useNotificationStore();

const pagesApi = mande('/api/admin/pages');
const isEditing = ref(false);
const saving = ref(false);

const page = reactive({
    title: '',
    slug: '',
    content: '',
});

const generateSlug = (val) => {
    if (!isEditing.value && val) {
        page.slug = val.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }
};

const updateContent = (event) => {
    if (event && event.markdown) {
        page.content = event.markdown;
    }
}

const savePage = async () => {
    saving.value = true;
    try {
        if (isEditing.value) {
            await pagesApi.put(page.id, page);
            notificationStore.showSuccess('Page updated successfully');
        } else {
            await pagesApi.post(page);
            notificationStore.showSuccess('Page created successfully');
        }
        router.push('/admin/pages');
    } catch (error) {
        console.error(error);
        notificationStore.showError('Failed to save page');
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    if (route.query.id) {
        isEditing.value = true;
        try {
            // Fetch specific page via ID. 
            // Note: The Admin Controller resource usually supports GET /admin/pages/{id}
            // If mande works with `.get(id)`, it appends /id
            const data = await pagesApi.get(route.query.id);
            Object.assign(page, data);
        } catch (error) {
            console.error('Error loading page', error);
            notificationStore.showError('Failed to load page');
        }
    }
});
</script>
