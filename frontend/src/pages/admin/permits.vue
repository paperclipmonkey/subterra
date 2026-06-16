<template>
  <v-container>
    <div class="d-flex align-center mb-6">
      <v-btn icon variant="text" @click="$router.back()">
        <v-icon :icon="mdiArrowLeft" />
      </v-btn>
      <h2 class="text-h4 font-weight-bold ml-2">Permits</h2>
      <v-spacer />
      <v-btn color="primary" variant="text" to="/admin/bookings" class="mr-2">
        <v-icon :icon="mdiClipboardCheck" class="mr-1" />
        View and manage bookings
      </v-btn>
      <v-btn color="primary" @click="openCreateDialog">
        <v-icon :icon="mdiPlus" class="mr-1" />
        New Permit
      </v-btn>
    </div>

    <v-data-table
      :headers="headers"
      :items="permits"
      :loading="loading"
      :search="search"
      item-value="id"
      hover
    >
      <template #top>
        <v-text-field
          v-model="search"
          label="Search permits..."
          :prepend-inner-icon="mdiMagnify"
          variant="outlined"
          density="compact"
          class="mb-4"
          clearable
        />
      </template>

      <template #item.is_active="{ item }">
        <v-chip :color="item.is_active ? 'success' : 'grey'" size="small">
          {{ item.is_active ? 'Active' : 'Inactive' }}
        </v-chip>
      </template>

      <template #item.auto_approve="{ item }">
        <v-icon :icon="item.auto_approve ? mdiCheckCircle : mdiCloseCircle" :color="item.auto_approve ? 'success' : 'grey'" />
      </template>

      <template #item.caves="{ item }">
        <v-chip v-for="cave in (item.caves || []).slice(0, 3)" :key="cave.id" size="small" class="mr-1">
          {{ cave.name }}
        </v-chip>
        <v-chip v-if="(item.caves || []).length > 3" size="small" color="grey">
          +{{ item.caves.length - 3 }} more
        </v-chip>
      </template>

      <template #item.officers="{ item }">
        <v-chip v-for="officer in (item.officers || [])" :key="officer.id" size="small" class="mr-1" color="amber-darken-2">
          {{ officer.name }}
        </v-chip>
      </template>

      <template #item.actions="{ item }">
        <v-btn icon variant="text" size="small" @click.stop="openEditDialog(item)">
          <v-icon :icon="mdiPencil" size="small" />
        </v-btn>
        <v-btn icon variant="text" size="small" color="error" @click.stop="confirmDelete(item)">
          <v-icon :icon="mdiDelete" size="small" />
        </v-btn>
      </template>
    </v-data-table>

    <!-- Create / Edit Dialog -->
    <v-dialog v-model="dialog" max-width="700" persistent>
      <v-card>
        <v-card-title>{{ editing ? 'Edit' : 'Create' }} Permit</v-card-title>
        <v-card-text>
          <v-form ref="formRef" @submit.prevent="savePermit">
            <v-text-field v-model="form.name" label="Name" :rules="[v => !!v || 'Required']" class="mb-2" />
            <v-text-field v-model="form.slug" label="Slug" hint="Auto-generated from name if blank" persistent-hint class="mb-2" />
            <v-textarea v-model="form.description" label="Description" rows="3" class="mb-2" />

            <div class="mb-3">
              <div class="text-subtitle-2 mb-1">Photo</div>
              <v-img
                v-if="existingPhotoUrl"
                :src="existingPhotoUrl"
                height="150"
                cover
                class="rounded mb-2 bg-grey-lighten-3"
              />
              <v-file-input
                v-model="photoFile"
                accept="image/*"
                :label="existingPhotoUrl ? 'Replace photo' : 'Upload photo'"
                :prepend-icon="null"
                :prepend-inner-icon="mdiCamera"
                variant="outlined"
                density="compact"
                show-size
                hide-details
                clearable
              />
              <v-btn
                v-if="editing && existingPhotoUrl"
                size="small"
                variant="text"
                color="error"
                class="mt-1"
                :prepend-icon="mdiDelete"
                @click="removeExistingPhoto"
              >
                Remove current photo
              </v-btn>

              <v-row v-if="existingPhotoUrl || photoFile" dense class="mt-1">
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.photo_photographer"
                    label="Photographer"
                    density="compact"
                    hide-details="auto"
                    :prepend-inner-icon="mdiCamera"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.photo_copyright"
                    label="Licence / Copyright"
                    density="compact"
                    hide-details="auto"
                    :prepend-inner-icon="mdiCopyright"
                  />
                </v-col>
              </v-row>
            </div>

            <v-textarea v-model="form.conditions" label="Conditions" rows="3" hint="Applicants must accept these conditions" persistent-hint class="mb-2" />

            <v-switch v-model="form.has_max_groups_per_day" label="Limit groups per day" color="primary" class="mb-2" />
            <v-text-field
              v-if="form.has_max_groups_per_day"
              v-model.number="form.max_groups_per_day"
              label="Max groups per day"
              type="number"
              min="1"
              :rules="[v => v >= 1 || 'Must be at least 1']"
              class="mb-2"
            />

            <v-switch v-model="form.has_max_participants" label="Limit participants per booking" color="primary" class="mb-2" />
            <v-text-field
              v-if="form.has_max_participants"
              v-model.number="form.max_participants"
              label="Max participants per booking"
              type="number"
              min="1"
              :rules="[v => v >= 1 || 'Must be at least 1']"
              class="mb-2"
            />

            <v-switch v-model="form.has_season" label="Restrict to a season" color="primary" class="mb-2" />
            <v-row v-if="form.has_season" class="mb-2">
              <v-col cols="6">
                <v-text-field
                  v-model="form.season_start"
                  label="Season start (MM-DD)"
                  placeholder="04-01"
                  hint="e.g. 04-01 for 1st April"
                  persistent-hint
                  :rules="[v => !v || /^\d{2}-\d{2}$/.test(v) || 'Format: MM-DD']"
                />
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="form.season_end"
                  label="Season end (MM-DD)"
                  placeholder="03-10"
                  hint="e.g. 03-10 for 10th March (can cross year)"
                  persistent-hint
                  :rules="[v => !v || /^\d{2}-\d{2}$/.test(v) || 'Format: MM-DD']"
                />
              </v-col>
            </v-row>

            <v-switch v-model="form.auto_approve" label="Auto-approve bookings" color="primary" class="mb-2" />

            <v-textarea v-model="form.booking_info" label="Booking Information (sent on approval)" rows="3" hint="Access details, key codes, meeting points etc." persistent-hint class="mb-2" />

            <v-switch v-model="form.is_active" label="Active" color="success" class="mb-2" />

            <v-autocomplete
              v-model="form.cave_ids"
              :items="allCaves"
              item-title="name"
              item-value="id"
              label="Linked Caves"
              multiple
              chips
              closable-chips
              autocomplete="off"
              class="mb-2"
            />

            <v-autocomplete
              v-model="form.officer_ids"
              :items="allUsers"
              item-title="name"
              item-value="id"
              label="Access Officers"
              multiple
              chips
              closable-chips
              autocomplete="off"
              class="mb-2"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="savePermit">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Delete Permit</v-card-title>
        <v-card-text>Are you sure you want to delete <strong>{{ deletingPermit?.name }}</strong>? This will also remove all associated bookings.</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" :loading="deleting" @click="deletePermit">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { mdiArrowLeft, mdiCamera, mdiCheckCircle, mdiClipboardCheck, mdiCloseCircle, mdiCopyright, mdiDelete, mdiMagnify, mdiPencil, mdiPlus } from '@mdi/js'
import { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'

defineOptions({ name: 'AdminPermits' })

const route = useRoute()

const notificationStore = useNotificationStore()

const loading = ref(true)
const saving = ref(false)
const deleting = ref(false)
const search = ref('')
const permits = ref([])
const allCaves = ref([])
const allUsers = ref([])
const dialog = ref(false)
const deleteDialog = ref(false)
const editing = ref(false)
const editingId = ref(null)
const deletingPermit = ref(null)
const formRef = ref(null)
const photoFile = ref(null)
const existingPhotoUrl = ref(null)

const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Auto-approve', key: 'auto_approve', sortable: true },
  { title: 'Caves', key: 'caves', sortable: false },
  { title: 'Officers', key: 'officers', sortable: false },
  { title: 'Bookings', key: 'bookings_count', sortable: true },
  { title: '', key: 'actions', sortable: false, width: 100 },
]

const defaultForm = () => ({
  name: '',
  slug: '',
  description: '',
  photo_photographer: '',
  photo_copyright: '',
  conditions: '',
  has_max_groups_per_day: false,
  max_groups_per_day: 1,
  has_max_participants: false,
  max_participants: 1,
  has_season: false,
  season_start: '',
  season_end: '',
  auto_approve: false,
  booking_info: '',
  is_active: true,
  cave_ids: [],
  officer_ids: [],
})

const form = ref(defaultForm())

const fetchPermits = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/api/admin/permits')
    permits.value = data.data
  } catch (e) {
    // handled by interceptor
  } finally {
    loading.value = false
  }
}

const fetchCavesAndUsers = async () => {
  const [cavesRes, usersRes] = await Promise.allSettled([
    api.get('/api/caves'),
    api.get('/api/admin/users/officer-list'),
  ])
  if (cavesRes.status === 'fulfilled') {
    allCaves.value = cavesRes.value.data.data || cavesRes.value.data
  }
  if (usersRes.status === 'fulfilled') {
    allUsers.value = usersRes.value.data
  }
}

const openCreateDialog = () => {
  editing.value = false
  editingId.value = null
  form.value = defaultForm()
  photoFile.value = null
  existingPhotoUrl.value = null
  dialog.value = true
}

const openEditDialog = (permit) => {
  editing.value = true
  editingId.value = permit.slug
  form.value = {
    name: permit.name,
    slug: permit.slug,
    description: permit.description || '',
    photo_photographer: permit.photo?.photographer || '',
    photo_copyright: permit.photo?.copyright || '',
    conditions: permit.conditions || '',
    has_max_groups_per_day: permit.has_max_groups_per_day,
    max_groups_per_day: permit.max_groups_per_day || 1,
    has_max_participants: permit.has_max_participants,
    max_participants: permit.max_participants || 1,
    has_season: permit.has_season || false,
    season_start: permit.season_start || '',
    season_end: permit.season_end || '',
    auto_approve: permit.auto_approve,
    booking_info: permit.booking_info || '',
    is_active: permit.is_active,
    cave_ids: (permit.caves || []).map(c => c.id),
    officer_ids: (permit.officers || []).map(o => o.id),
  }
  photoFile.value = null
  existingPhotoUrl.value = permit.photo?.url || null
  dialog.value = true
}

const savePermit = async () => {
  saving.value = true
  try {
    let slug = editingId.value
    if (editing.value) {
      await api.put(`/api/admin/permits/${editingId.value}`, form.value)
    } else {
      const res = await api.post('/api/admin/permits', form.value)
      const created = res.data?.data || res.data
      slug = created?.slug
    }

    // A photo upload is a separate multipart request so the main form can stay JSON.
    const file = Array.isArray(photoFile.value) ? photoFile.value[0] : photoFile.value
    if (file && slug) {
      const fd = new FormData()
      fd.append('photo', file)
      await api.post(`/api/admin/permits/${slug}/photo`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    notificationStore.showSuccess(editing.value ? 'Permit updated' : 'Permit created')
    dialog.value = false
    fetchPermits()
  } catch (e) {
    if (e.response?.status === 422) {
      notificationStore.showError('Please check the form for errors.')
    }
  } finally {
    saving.value = false
  }
}

const removeExistingPhoto = async () => {
  if (!editing.value || !editingId.value) {
    photoFile.value = null
    existingPhotoUrl.value = null
    return
  }
  try {
    await api.delete(`/api/admin/permits/${editingId.value}/photo`)
    existingPhotoUrl.value = null
    photoFile.value = null
    notificationStore.showSuccess('Photo removed')
    fetchPermits()
  } catch (e) {
    // handled by interceptor
  }
}

const confirmDelete = (permit) => {
  deletingPermit.value = permit
  deleteDialog.value = true
}

const deletePermit = async () => {
  deleting.value = true
  try {
    await api.delete(`/api/admin/permits/${deletingPermit.value.slug}`)
    notificationStore.showSuccess('Permit deleted')
    deleteDialog.value = false
    fetchPermits()
  } catch (e) {
    // handled by interceptor
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  await fetchPermits()
  fetchCavesAndUsers()
  if (route.query.edit) {
    const target = permits.value.find(p => p.slug === route.query.edit)
    if (target) openEditDialog(target)
  }
})
</script>
