<template>
  <v-container v-if="cave" class="pa-4" fluid>
    <!-- Top Navigation -->
    <v-row class="mb-2">
      <v-col cols="12" class="d-flex align-center">
        <v-btn :icon="mdiArrowLeft" variant="text" class="mr-2" @click="$router.push('/caves')" />
        <v-spacer />
        <v-btn
          v-if="appStore.user && appStore.canSuggest"
          variant="text"
          color="primary"
          :prepend-icon="mdiPencil"
          class="text-none mr-2"
          size="small"
          @click="$router.push('/caves/' + route.params.id + '/edit')"
        >
          {{ appStore.user?.is_admin ? 'Edit Cave' : 'Suggest Edit' }}
        </v-btn>
        <v-btn
          v-else-if="appStore.user"
          variant="text"
          color="grey"
          disabled
          :prepend-icon="mdiPencilOff"
          class="text-none mr-2"
          size="small"
        >
          <v-tooltip activator="parent" location="top">
            {{ !appStore.canSuggest ? 'Your account must be approved' : 'You must join a club' }} to suggest edits
          </v-tooltip>
          Suggest Edit
        </v-btn>
        <v-btn
          v-else
          variant="text"
          color="primary"
          to="/login"
          :prepend-icon="mdiPencil"
          class="text-none mr-2"
          size="small"
        >
          Log in to Suggest Edit
        </v-btn>
      </v-col>
    </v-row>

    <!-- Hero Section -->
    <v-card class="mb-6 rounded-lg position-relative cursor-pointer hero-img" elevation="2" @click="activeTab = 'media'; if(cave.hero_video) { openImage(cave.hero_video) } else if(cave.hero_image) { openImage(cave.hero_image) }">
      <div style="height: 300px; width: 100%; position: relative; overflow: hidden; border-radius: inherit;">
        <video
          v-if="cave.hero_video?.preview_url || cave.hero_video?.url"
          ref="heroVideoRef"
          :src="cave.hero_video?.preview_url || cave.hero_video?.url"
          autoplay muted loop playsinline
          class="position-absolute w-100 h-100"
          style="object-fit: cover; top: 0; left: 0; z-index: 1;"
        />
        <v-img v-else :src="cave.hero_image?.url || cave.entrance_image?.url || '/placeholder-cave.jpg'" height="300" cover
               class="align-end" style="z-index: 1;">
          <template #placeholder>
            <div class="d-flex align-center justify-center fill-height bg-grey-lighten-2">
              <v-icon color="grey" size="64" :icon="mdiImageOff" />
            </div>
          </template>
        </v-img>
        
        <div class="d-flex flex-column pa-6 text-white position-absolute w-100"
             style="background: linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0)); bottom: 0; z-index: 2;">
          <div class="d-flex justify-space-between align-end">
            <div>
              <div v-if="cave.system?.caves?.length > 1" class="text-overline mb-1">{{ cave.system?.name || 'Unknown System' }}</div>
              <h1 class="text-h3 font-weight-bold mb-2">{{ cave.name }}</h1>
              <div class="d-flex align-center">
                <v-icon size="small" class="mr-1" :icon="mdiMapMarker" />
                <span class="text-subtitle-1">{{ cave.location_name }}, {{ cave.location_country }}</span>
              </div>
            </div>
            <div v-if="cave.hero_video?.photographer || cave.hero_image?.photographer" class="text-caption text-right opacity-70">
              <v-icon size="x-small" class="mr-1" :icon="mdiCamera" />
              {{ cave.hero_video?.photographer || cave.hero_image?.photographer }}
            </div>
          </div>
        </div>
      </div>
    </v-card>

    <v-row>
      <!-- Main Column -->
      <v-col cols="12" md="8">
        <v-card class="fill-height rounded-lg" elevation="1">
          <v-tabs v-model="activeTab" color="primary">
            <v-tab value="overview">Overview</v-tab>
            <v-tab value="trips">Trips <v-badge v-if="visibleTripsCount > 0" :content="visibleTripsCount" inline
                                                color="grey-lighten-1" /></v-tab>
            <v-tab value="weather">Weather</v-tab>
            <v-tab value="system">System Info</v-tab>
            <v-tab v-if="smAndDown" value="map">Map</v-tab>
            <v-tab value="media">Media</v-tab>
            <v-tab value="routes">Routes <v-badge v-if="cave.system?.routes?.length > 0" :content="cave.system.routes.length" inline color="grey-lighten-1" /></v-tab>
            <v-tab v-if="appStore.user.is_admin || (linkedCollections && linkedCollections.length > 0)" value="collections">Collections</v-tab>
          </v-tabs>
          <v-divider />

          <v-window v-model="activeTab" class="pa-4">
            <!-- Overview Tab -->
            <v-window-item value="overview">
              <div class="text-h6 mb-3 font-weight-bold display-1">Description</div>
              <MarkdownRenderer :source="cave.description || '_No description provided._'" class="mb-6 text-body-1" />

              <v-alert v-if="isDescriptionStub" type="info" variant="tonal" class="mb-6">
                <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between w-100">
                  <div>
                    <div class="text-subtitle-1 font-weight-bold">Help improve this page</div>
                    <div class="text-body-2">This cave's description is a stub. Could you write an improved description?</div>
                  </div>
                  <div class="mt-3 mt-sm-0 ml-sm-4 shrink-0">
                    <v-btn
                      v-if="appStore.canSuggest"
                      color="primary"
                      variant="flat"
                      @click="$router.push('/caves/' + route.params.id + '/edit')"
                    >
                      {{ appStore.user.is_admin ? 'Edit Description' : 'Suggest Edit' }}
                    </v-btn>
                    <v-btn
                      v-else
                      color="primary"
                      variant="flat"
                      disabled
                    >
                      Suggest Edit
                      <v-tooltip activator="parent" location="top">
                        Your account must be approved to suggest edits
                      </v-tooltip>
                    </v-btn>
                  </div>
                </div>
              </v-alert>

              <v-divider class="mb-6" />

              <div class="text-h6 mb-3 font-weight-bold">Access Information</div>
              <div v-if="!appStore.canSuggest">
                <v-alert :icon="mdiLock" border="start" border-color="grey" elevation="0" color="grey-lighten-3"
                         class="mb-4">
                  <v-icon color="grey-darken-2" size="40" class="mr-4" :icon="mdiLock" />
                  <div>
                    <div class="text-body-2 text-grey-darken-2">
                      Access information is restricted to approved club members.
                      <router-link :to="`/profile/${appStore.user.id}`" class="text-decoration-none font-weight-bold">Join a club</router-link> to view details.
                    </div>
                  </div>
                </v-alert>
              </div>
              <div v-else-if="cave.access_info">
                <v-alert :icon="mdiLockAlert" border="start" border-color="warning" elevation="0" color="warning"
                         variant="tonal" class="mb-4">
                  <div class="text-high-emphasis text-body-1">
                    <MarkdownRenderer :source="cave.access_info" />
                  </div>
                </v-alert>
              </div>
              <p v-else class="text-grey text-body-2">No specific access information provided.</p>

              <!-- Permit Booking Link -->
              <v-alert
                v-if="cavePermit"
                :icon="mdiCalendarCheck"
                type="info"
                variant="tonal"
                class="mt-4"
              >
                <div class="d-flex align-center justify-space-between">
                  <div>
                    <div class="font-weight-bold">{{ cavePermit.name }}</div>
                    <div class="text-body-2">This cave requires a permit. View availability and apply online.</div>
                  </div>
                  <v-btn
                    color="primary"
                    variant="flat"
                    size="small"
                    :to="`/caves/${route.params.id}/bookings`"
                  >
                    View Availability
                  </v-btn>
                </div>
              </v-alert>
            </v-window-item>

            <!-- Trips Tab -->
            <v-window-item value="trips">
              <div class="d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between mb-4">
                <h3 class="text-h6 mb-2 mb-sm-0">Recent Trips</h3>
                <div class="d-flex align-center flex-wrap" style="gap: 8px;">
                  <v-btn v-if="!hasDone" variant="text" color="primary" :prepend-icon="mdiCheck" @click="markAsDone">
                    Mark Visited
                  </v-btn>
                  <v-btn color="primary" :prepend-icon="mdiPlus"
                         @click="$router.push({ name: '/create-trip', query: { cave_id: cave.id } })">
                    Log Trip
                  </v-btn>
                </div>
              </div>

              <div v-if="cave.trips && cave.trips.length > 0" class="mt-2">
                <template v-for="(trip, index) in cave.trips" :key="trip.datetime || index">
                  <CaveTripListItem v-if="trip.end_time || trip.participants.some(participant => participant.id === appStore.user.id)"
                                    :trip="trip" />
                </template>
              </div>
              <v-alert v-else type="info" variant="tonal" class="mt-2">
                No trips have been recorded for this cave yet. Be the first!
              </v-alert>

              <v-dialog v-model="showConfirmModal" max-width="400">
                <v-card>
                  <v-card-title class="text-h6 pa-4">Mark Cave as Done?</v-card-title>
                  <v-card-text class="pt-0 pb-4">
                    Are you sure you want to mark <strong>{{ cave.name }}</strong> as visited?
                  </v-card-text>
                  <v-card-actions class="pa-4 pt-0">
                    <v-spacer />
                    <v-btn variant="text" @click="showConfirmModal = false">Cancel</v-btn>
                    <v-btn color="primary" variant="flat" @click="confirmMarkAsDone">Confirm</v-btn>
                  </v-card-actions>
                </v-card>
              </v-dialog>
            </v-window-item>

            <!-- Weather Tab -->
            <v-window-item value="weather">
              <CaveWeather :cave-id="cave.slug || cave.id" />
            </v-window-item>

            <!-- Map Tab (Mobile only) -->
            <v-window-item v-if="smAndDown" value="map">
              <div class="cave-map-mobile">
                <template v-if="appStore.canSuggest && activeTab === 'map'">
                  <AppMap ref="mapRef" v-model="style" :center="lnglat" :zoom="zoom" :max-zoom="15" height="400px" @map:load="onMapLoad">
                    <mgl-marker :coordinates="lnglat" color="#cc0000" />
                  </AppMap>
                </template>
                <div v-else-if="!appStore.canSuggest" class="d-flex align-center justify-center bg-grey-lighten-3" style="height: 300px;">
                  <div class="text-center pa-4">
                    <v-icon size="48" color="grey" class="mb-2" :icon="mdiLock" />
                    <div class="text-h6 text-grey-darken-1">Location Locked</div>
                    <div class="text-caption text-grey-darken-1">Join a club to view cave locations and maps</div>
                  </div>
                </div>
              </div>
              <v-card-text v-if="appStore.canSuggest">
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <div class="text-caption text-grey">Coordinates</div>
                    <div v-if="cave.location_lat" class="font-weight-medium text-body-2">{{ cave.location_lat.toFixed(5) }}, {{
                      cave.location_lng.toFixed(5) }}</div>
                    <div v-else class="font-weight-medium text-body-2 text-grey">Hidden</div>
                  </div>
                  <div class="d-flex">
                    <v-tooltip text="Copy Coordinates" location="top">
                      <template #activator="{ props }">
                        <v-btn :icon="mdiContentCopy" size="small" variant="text" v-bind="props"
                               @click="copyLatLng" />
                      </template>
                    </v-tooltip>
                    <v-tooltip text="Open in Google Maps" location="top">
                      <template #activator="{ props }">
                        <v-btn :icon="mdiGoogleMaps" size="small" variant="text" v-bind="props"
                               :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`"
                               target="_blank" />
                      </template>
                    </v-tooltip>
                  </div>
                </div>
              </v-card-text>
            </v-window-item>

            <!-- System Tab -->
            <v-window-item value="system">
              <template v-if="cave.system">
                <div class="d-flex align-center justify-space-between mb-2">
                  <h3 class="text-h6">{{ cave.system.name }}</h3>
                  <div v-if="appStore.user.is_admin || (appStore.user.roles && appStore.user.roles.some(r => r.slug === 'data_admin'))">
                    <v-btn
                      size="small"
                      variant="text"
                      color="primary"
                      :prepend-icon="mdiPlus"
                      class="mr-2"
                      :to="`/caves/create?system_id=${cave.system.id}`"
                    >
                      Add Cave
                    </v-btn>
                    <v-btn size="small" variant="text" :icon="mdiPencil"
                           @click="$router.push('/cave-systems/' + cave.system.id + '/edit')" />
                  </div>
                </div>

                <div v-if="cave.system.catchment_name" class="mb-4">
                  <div class="text-subtitle-2 text-grey mb-1">Catchment</div>
                  <v-btn
                    variant="tonal"
                    color="primary"
                    size="small"
                    :to="{ path: '/caves', query: { catchment: cave.system.catchment_id } }"
                    :prepend-icon="mdiWater"
                  >
                    {{ cave.system.catchment_name }}
                  </v-btn>
                </div>

                <MarkdownRenderer v-if="cave.system.description" :source="cave.system.description"
                                  class="text-body-1 mb-4" />

                <v-chip-group class="mb-4">
                  <v-chip v-for="tag in cave.system.tags" :key="tag.tag" size="small" variant="outlined" disabled>{{ tag.tag
                  }}</v-chip>
                </v-chip-group>

                <v-divider class="mb-4" />

                <!-- References -->
                <div v-if="appStore.canSuggest && cave.system.references" class="mb-6">
                  <div class="text-subtitle-1 font-weight-bold mb-2">References</div>
                  <v-card variant="tonal" class="pa-4 bg-grey-lighten-5">
                    <MarkdownRenderer :source="cave.system.references" class="text-body-2" />
                  </v-card>
                </div>

                <!-- Files -->
                <div v-if="appStore.canSuggest && cave.system.files && cave.system.files.length > 0" class="mb-6">
                  <div class="text-subtitle-1 font-weight-bold mb-2">Surveys & Documents</div>
                  <v-row dense>
                    <v-col v-for="file in cave.system.files" :key="file.id" cols="12" sm="6">
                      <v-card variant="outlined" class="h-100 hover-card" :href="file.url" target="_blank">
                        <div class="d-flex align-center pa-3">
                          <v-avatar v-if="file.thumbnail_url" class="mr-3" rounded size="48">
                            <v-img :src="file.thumbnail_url" cover />
                          </v-avatar>
                          <v-avatar v-else color="primary-lighten-5" class="mr-3" rounded>
                            <v-icon color="primary" :icon="mdiFileDocumentOutline" />
                          </v-avatar>
                          <div class="flex-grow-1 overflow-hidden">
                            <div class="text-body-2 font-weight-bold text-truncate">{{ file.original_filename }}</div>
                            <div class="text-caption text-medium-emphasis">{{ file.details || 'No description' }}</div>
                          </div>
                          <v-icon size="small" color="grey" :icon="mdiDownload" />
                        </div>
                      </v-card>
                    </v-col>
                  </v-row>
                </div>

                <!-- Unapproved User Placeholder -->
                <div v-if="!appStore.canSuggest" class="text-center pa-8 bg-grey-lighten-5 rounded-lg border border-dashed">
                  <v-icon size="48" color="grey-lighten-1" class="mb-3" :icon="mdiShieldLockOutline" />
                  <div class="text-body-1 font-weight-medium text-grey-darken-2">Detailed System Data Restricted</div>
                  <div class="text-caption text-grey-darken-1 mb-4">
                    References, surveys, and technical documents are available to approved club members.
                  </div>
                </div>

                <!-- Statistics (Mobile only) -->
                <div v-if="smAndDown" class="mt-6">
                  <v-divider class="mb-6" />
                  <h3 class="text-h6 mb-4">Statistics</h3>
                  <v-row dense>
                    <v-col cols="6">
                      <div class="d-flex flex-column">
                        <span class="text-caption text-grey">Length</span>
                        <span class="text-h6">{{ cave.system?.length ? Math.round(cave.system.length) + ' m' : '-' }}</span>
                      </div>
                    </v-col>
                    <v-col cols="6">
                      <div class="d-flex flex-column">
                        <span class="text-caption text-grey">Vertical</span>
                        <span class="text-h6">{{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '-' }}</span>
                      </div>
                    </v-col>
                  </v-row>

                  <v-divider class="my-4" />

                  <v-chip-group>
                    <v-chip
                      v-for="tag in cave.tags"
                      :key="tag.tag"
                      size="small"
                      color="secondary"
                      variant="tonal"
                      style="cursor: pointer;"
                      @click="$router.push({ path: '/caves', query: { tags: tag.tag, view: 'list' } })"
                    >
                      {{ tag.tag }}
                    </v-chip>
                  </v-chip-group>

                  <template v-if="cave.system?.caves?.length > 1">
                    <v-divider class="my-4" />
                    <h3 class="text-h6 mb-2">System Entrances</h3>
                    <v-list density="compact" class="bg-transparent pa-0">
                      <v-list-item v-for="ent in cave.system.caves" :key="ent.id" :to="'/caves/' + ent.slug"
                                   :active="ent.id === cave.id" class="px-0">
                        <template #prepend>
                          <v-icon :icon="mdiTunnel" size="small" />
                        </template>
                        <v-list-item-title>{{ ent.name }}</v-list-item-title>
                      </v-list-item>
                    </v-list>
                  </template>
                </div>
              </template>
              <v-alert v-else type="warning" variant="tonal">No system information available.</v-alert>
            </v-window-item>

            <!-- Media Tab -->
            <v-window-item value="media">
              <v-row v-if="allMedia.length > 0">
                <v-col v-for="item in allMedia" :key="item.url || item.filename" cols="6" sm="4" md="3">
                  <v-card hover class="bg-black" :aspect-ratio="1" @click="openImage(item)">
                    <video
                      v-if="item.type === 'hero_video'"
                      :src="item.preview_url || item.url"
                      autoplay muted loop playsinline
                      style="width: 100%; height: 100%; object-fit: cover;"
                    />
                    <v-img v-else :src="item.url" aspect-ratio="1" cover class="rounded cursor-pointer" />
                  </v-card>
                </v-col>
              </v-row>
              <v-alert v-else type="info" variant="text">No photos available.</v-alert>
              
              <MediaViewModal v-model="showMediaModal" :media="selectedMedia" />
            </v-window-item>
            
            <!-- Routes Tab -->
            <v-window-item value="routes">
              <template v-if="cave.system && cave.system.routes && cave.system.routes.length > 0">
                <RouteList :routes="cave.system.routes" :cave-system-id="cave.system.id" />
              </template>
              <v-alert v-else-if="cave.system" type="info" variant="tonal">
                <div class="d-flex justify-space-between align-center">
                  <span>No specific routes defined for this system yet.</span>
                  <v-btn
                    v-if="appStore.user.is_admin"
                    color="primary"
                    size="small"
                    variant="text"
                    :prepend-icon="mdiPlus"
                    :to="`/cave-systems/${cave.system.id}/routes/new`"
                  >
                    Add Route
                  </v-btn>
                </div>
              </v-alert>
              <v-alert v-else type="warning" variant="tonal">System information not available, cannot show routes.</v-alert>
            </v-window-item>

            <!-- Collections Tab -->
            <v-window-item value="collections">
              <div v-if="linkedCollections && linkedCollections.length > 0">
                <v-row>
                  <v-col v-for="collection in linkedCollections" :key="collection.id" cols="12" md="6">
                    <v-card :to="`/collections/${collection.slug}`" link class="d-flex flex-row align-center rounded-lg"
                            elevation="1">
                      <v-avatar rounded="0" size="80">
                        <v-img
                          :src="collection.photo_path || 'https://images.unsplash.com/photo-1504386106331-3e4e71712b38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'"
                          cover />
                      </v-avatar>
                      <div class="pa-4 flex-grow-1">
                        <div class="text-subtitle-1 font-weight-bold">{{ collection.name }}</div>
                        <div class="text-caption text-grey">{{ collection.caves_count }} Caves</div>
                        <div v-if="collection.curr_cave_index !== undefined" class="text-caption text-primary">
                          Cave #{{ collection.curr_cave_index + 1 }} in list
                        </div>
                      </div>
                      <div class="pr-4">
                        <v-icon color="grey-lighten-1" :icon="mdiChevronRight" />
                      </div>
                    </v-card>
                  </v-col>
                </v-row>
              </div>
              <v-alert v-else type="info" variant="tonal">
                This cave does not appear in any collections yet.
              </v-alert>
            </v-window-item>

          </v-window>
        </v-card>
      </v-col>

      <!-- Sidebar Column -->
      <v-col v-if="!smAndDown" cols="12" md="4">
        <!-- Location Card -->
        <v-card class="mb-4 rounded-lg" elevation="1">
          <template v-if="appStore.canSuggest">
            <AppMap ref="mapRef" v-model="style" :center="lnglat" :zoom="zoom" :max-zoom="15" height="300px" @map:load="onMapLoad">
              <mgl-marker :coordinates="lnglat" color="#cc0000" />
              
              
            </AppMap>
          </template>
          <div v-else class="d-flex align-center justify-center bg-grey-lighten-3 rounded-t-lg" style="height: 300px;">
            <div class="text-center pa-4">
              <v-icon size="48" color="grey" class="mb-2" :icon="mdiLock" />
              <div class="text-h6 text-grey-darken-1">Location Locked</div>
              <div class="text-caption text-grey-darken-1">Join a club to view cave locations and maps</div>
            </div>
          </div>
          <v-card-text>
            <div class="d-flex justify-space-between align-center">
              <div>
                <div class="text-caption text-grey">Coordinates</div>
                <div v-if="appStore.canSuggest && cave.location_lat" class="font-weight-medium text-body-2">{{ cave.location_lat.toFixed(5) }}, {{
                  cave.location_lng.toFixed(5) }}</div>
                <div v-else class="font-weight-medium text-body-2 text-grey">Hidden</div>
              </div>
              <div v-if="appStore.canSuggest" class="d-flex">
                <v-tooltip text="Copy Coordinates" location="top">
                  <template #activator="{ props }">
                    <v-btn :icon="mdiContentCopy" size="small" variant="text" v-bind="props"
                           @click="copyLatLng" />
                  </template>
                </v-tooltip>
                <v-tooltip text="Open in Google Maps" location="top">
                  <template #activator="{ props }">
                    <v-btn :icon="mdiGoogleMaps" size="small" variant="text" v-bind="props"
                           :href="`https://www.google.com/maps?q=${cave.location_lat},${cave.location_lng}`"
                           target="_blank" />
                  </template>
                </v-tooltip>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Stats Card -->
        <v-card v-if="!smAndDown" class="mb-4 rounded-lg pa-4" elevation="1">
          <h3 class="text-h6 mb-4">Statistics</h3>
          <v-row dense>
            <v-col cols="6">
              <div class="d-flex flex-column">
                <span class="text-caption text-grey">Length</span>
                <span class="text-h6">{{ cave.system?.length ? Math.round(cave.system.length) + ' m' : '-' }}</span>
              </div>
            </v-col>
            <v-col cols="6">
              <div class="d-flex flex-column">
                <span class="text-caption text-grey">Vertical</span>
                <span class="text-h6">{{ cave.system?.vertical_range ? cave.system.vertical_range + ' m' : '-' }}</span>
              </div>
            </v-col>
          </v-row>

          <v-divider class="my-4" />

          <div class="text-caption text-grey mb-2">Tags</div>
          <v-chip-group>
            <v-chip
              v-for="tag in cave.tags"
              :key="tag.tag"
              size="small"
              color="secondary"
              variant="tonal"
              style="cursor: pointer;"
              @click="$router.push({ path: '/caves', query: { tags: tag.tag, view: 'list' } })"
            >
              {{ tag.tag }}
            </v-chip>
          </v-chip-group>
        </v-card>

        <!-- Entrances Card (if multiple) -->
        <v-card v-if="cave.system?.caves?.length > 1" class="mb-4 rounded-lg" elevation="1">
          <v-card-title class="text-subtitle-1">System Entrances</v-card-title>
          <v-list density="compact">
            <v-list-item v-for="ent in cave.system.caves" :key="ent.id" :to="'/caves/' + ent.slug"
                         :active="ent.id === cave.id">
              <template #prepend>
                <v-icon :icon="mdiTunnel" size="small" />
              </template>
              <v-list-item-title>{{ ent.name }}</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card>

      </v-col>
    </v-row>
  </v-container>
  <v-container v-else-if="loading" class="fill-height d-flex justify-center align-center">
    <v-progress-circular indeterminate color="primary" />
  </v-container>
  
  <v-container v-else-if="error" class="fill-height d-flex flex-column justify-center align-center text-center">
    <v-icon :icon="mdiAlertCircleOutline" size="64" color="grey" class="mb-4" />
    <h2 class="text-h5 text-grey-darken-1 mb-2">Oops!</h2>
    <p class="text-body-1 text-grey mb-6">{{ error }}</p>
    <v-btn color="primary" variant="flat" to="/caves" :prepend-icon="mdiArrowLeft">
      Back to Caves
    </v-btn>
  </v-container>
</template>


<script setup>
import AppMap from '@/components/AppMap.vue'
import { api } from '@/plugins/api'
import { mdiAlertCircleOutline, mdiArrowLeft, mdiCalendarCheck, mdiCamera, mdiTunnel, mdiCheck, mdiChevronRight, mdiContentCopy, mdiDownload, mdiFileDocumentOutline, mdiGoogleMaps, mdiImageOff, mdiLock, mdiLockAlert, mdiMapMarker, mdiPencil, mdiPencilOff, mdiPlus, mdiShieldLockOutline, mdiWater } from '@mdi/js'
import { useAppStore } from '@/stores/app'
import { useNotificationStore } from '@/stores/notifications'
import { useDisplay } from 'vuetify'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'
import { useRoute, useRouter } from 'vue-router'
import { markCaveAsDone } from '@/stores/markAsDone'
import { useCollectionStore } from '@/stores/collections'
import CaveWeather from '@/components/CaveWeather.vue'
import MediaViewModal from '@/components/MediaViewModal.vue'
import { usePageTitle } from '@/composables/usePageTitle'
import { MglMarker } from '@indoorequal/vue-maplibre-gl'
import maplibregl from 'maplibre-gl'

const style = ref('https://api.maptiler.com/maps/hybrid/style.json?key=0gGMv4po9Mjrpd64A528')
const zoom = 14



const appStore = useAppStore()
const notificationStore = useNotificationStore()
const { smAndDown } = useDisplay()
const collectionStore = useCollectionStore()

const route = useRoute()
const router = useRouter()
const cave = ref(null)
const cavePermit = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref(route.query.tab || 'overview')

const isDescriptionStub = computed(() => {
  if (!cave.value) return false
  if (!cave.value.description) return true
  return cave.value.description.length < 50
})

const pageTitle = computed(() => cave.value?.name)
usePageTitle(pageTitle)

// Sync tab changes to URL without adding history
watch(activeTab, (newTab) => {
  router.replace({ query: { ...route.query, tab: newTab } })
})

// Sync URL changes to tab (e.g. back/forward navigation or direct link)
watch(() => route.query.tab, (newTab) => {
  if (newTab && newTab !== activeTab.value) {
    activeTab.value = newTab
  }
})
const showConfirmModal = ref(false)

// Media Modal State
const showMediaModal = ref(false)
const selectedMedia = ref({})
const heroVideoRef = ref(null)

watch(showMediaModal, (isShowing) => {
  if (heroVideoRef.value) {
    if (isShowing) {
      heroVideoRef.value.pause()
    } else {
      heroVideoRef.value.play().catch(e => console.error("Could not resume hero video", e))
    }
  }
})

const hasDone = computed(() => {
  return cave.value?.trips?.some(trip => trip.participants.some(participant => participant.id === appStore.user.id))
})

// Count only visible trips (those with end_time or where current user is a participant)
const visibleTripsCount = computed(() => {
  if (!cave.value?.trips) return 0
  return cave.value.trips.filter(trip =>
    trip.end_time || trip.participants.some(participant => participant.id === appStore.user.id)
  ).length
})

const allMedia = computed(() => {
  const mediaList = []

  // Add Cave-specific media (Hero, Entrance, etc.)
  if (cave.value?.media) {
    mediaList.push(...cave.value.media)
  }

  // Add Trip-related media
  if (cave.value?.trips) {
    const tripMedia = cave.value.trips.reduce((acc, trip) => {
      if (trip.media && trip.media.length > 0) {
        const enrichedMedia = trip.media.map(m => ({
          ...m,
          trip_id: trip.id,
          trip_name: trip.name,
          photographer: m.photographer || (m.user_id ? trip.participants.find(p => p.id === m.user_id)?.name : null)
        }))
        acc.push(...enrichedMedia)
      }
      return acc
    }, [])
    mediaList.push(...tripMedia)
  }

  return mediaList
})

// Compute collections that this cave belongs to
const linkedCollections = computed(() => {
  return cave.value?.collections || []
})

const fetchCave = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get(`/api/caves/${route.params.id}`)
    cave.value = response.data.data

    if (cave.value && cave.value.system) {
      cave.value.system.files = cave.value.system.files || []
      cave.value.system.caves = cave.value.system.caves || []
    } else if (cave.value) {
      cave.value.system = { files: [], caves: [] }
    }
    if (!cave.value.trips) {
      cave.value.trips = []
    }
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = "Cave not found. It may have been deleted or you may have the wrong link."
    } else {
      console.error("Failed to fetch cave data:", e)
      error.value = "Failed to load cave. Please try again later."
    }
  } finally {
    loading.value = false
  }
}

const lnglat = computed(() => {
  return [cave.value.location_lng, cave.value.location_lat]
})

const copyLatLng = async () => {
  if (cave.value && navigator.clipboard) {
    const textToCopy = `${cave.value.location_lat}, ${cave.value.location_lng}`
    try {
      await navigator.clipboard.writeText(textToCopy)
      notificationStore.showSuccess('Coordinates copied to clipboard')
    } catch (err) {
      console.error('Failed to copy coordinates: ', err)
    }
  }
}

const markAsDone = () => {
  showConfirmModal.value = true
}

const confirmMarkAsDone = async () => {
  const ok = await markCaveAsDone({ cave: cave.value, userId: appStore.user.id })
  if (ok) {
    await fetchCave()
    showConfirmModal.value = false
  } else {
    console.error('failed to save trip')
  }
}

const openImage = (item) => {
  selectedMedia.value = item
  showMediaModal.value = true
}

const fetchCavePermit = async () => {
  try {
    const response = await api.get(`/api/caves/${route.params.id}/permit`)
    cavePermit.value = response.data.data
  } catch (e) {
    // permit info is optional
  }
}

onMounted(() => {
  fetchCave()
  fetchCavePermit()
})

watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId !== oldId) {
      fetchCave()
    }
  }
)
const onMapLoad = (event) => {
  const map = event.map

  // Re-render annotation overlays whenever the map style changes
  map.on('style.load', () => {
    renderAnnotationOverlays(map)
  })

  renderAnnotationOverlays(map)
}

function renderAnnotationOverlays (map) {
  if (!map || !cave.value?.system?.annotation?.geojson?.features?.length) return

  // Clean up any existing annotation layers/sources/images
  const layerIds = ['annotation-lines-layer', 'annotation-lines-hit', 'annotation-parking-layer', 'annotation-houses-layer']
  const sourceIds = ['annotation-lines', 'annotation-parking', 'annotation-houses']
  const imageIds = ['parking-icon', 'house-icon']
  layerIds.forEach(id => { if (map.getLayer(id)) map.removeLayer(id) })
  sourceIds.forEach(id => { if (map.getSource(id)) map.removeSource(id) })
  imageIds.forEach(id => { if (map.hasImage(id)) map.removeImage(id) })

  // Single popup management
  let activePopup = null
  function showPopup (lngLat, html, offset = 16) {
    if (activePopup) { activePopup.remove(); activePopup = null }
    activePopup = new maplibregl.Popup({ offset, className: 'annotation-popup', maxWidth: '280px' })
      .setLngLat(lngLat)
      .setHTML(html)
      .addTo(map)
    activePopup.on('close', () => { activePopup = null })
  }

  const geojson = cave.value.system.annotation.geojson
  const parkingFeatures = []
  const houseFeatures = []
  const lineFeatures = []

  geojson.features.forEach(feature => {
    const type = feature.properties?.annotation_type
    if (feature.geometry.type === 'Point' && type === 'parking') {
      parkingFeatures.push(feature)
    } else if (feature.geometry.type === 'Point' && type === 'house') {
      houseFeatures.push(feature)
    } else if (feature.geometry.type === 'LineString') {
      lineFeatures.push(feature)
    }
  })

  // Walking routes
  if (lineFeatures.length > 0) {
    map.addSource('annotation-lines', {
      type: 'geojson',
      data: { type: 'FeatureCollection', features: lineFeatures },
    })
    map.addLayer({
      id: 'annotation-lines-layer',
      type: 'line',
      source: 'annotation-lines',
      paint: { 'line-color': '#ff9800', 'line-width': 4, 'line-dasharray': [2, 2] },
    })
    // Invisible wider hit area for easier clicking
    map.addLayer({
      id: 'annotation-lines-hit',
      type: 'line',
      source: 'annotation-lines',
      paint: { 'line-color': 'transparent', 'line-width': 16 },
    })
    map.on('click', 'annotation-lines-hit', (e) => {
      const feature = e.features[0]
      const desc = feature.properties?.description
      showPopup(e.lngLat, `<div style="font-family:sans-serif;max-width:240px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
          <span style="display:inline-block;width:24px;height:3px;background:#ff9800;border-radius:2px;border:1px dashed #ff9800;"></span>
          <strong>Walking Route</strong>
        </div>
        ${desc ? `<p style="margin:0;color:#555;">${escHtml(desc)}</p>` : '<p style="margin:0;color:#888;font-style:italic;">No description</p>'}
      </div>`, 8)
    })
    map.on('mouseenter', 'annotation-lines-hit', () => { map.getCanvas().style.cursor = 'pointer' })
    map.on('mouseleave', 'annotation-lines-hit', () => { map.getCanvas().style.cursor = '' })
  }

  const canvasToImageData = (canvas) => {
    const ctx = canvas.getContext('2d')
    return { width: canvas.width, height: canvas.height, data: new Uint8Array(ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer) }
  }

  const addIconLayer = (iconData, imageId, features, sourceId, layerId, popupBuilder) => {
    if (!map.hasImage(imageId)) map.addImage(imageId, iconData)
    if (features.length > 0) {
      map.addSource(sourceId, {
        type: 'geojson',
        data: { type: 'FeatureCollection', features },
      })
      map.addLayer({
        id: layerId,
        type: 'symbol',
        source: sourceId,
        layout: { 'icon-image': imageId, 'icon-size': 0.5, 'icon-allow-overlap': true },
      })
      map.on('click', layerId, (e) => {
        const f = e.features[0]
        const coords = f.geometry.coordinates.slice()
        showPopup(coords, popupBuilder(f, coords))
      })
      map.on('mouseenter', layerId, () => { map.getCanvas().style.cursor = 'pointer' })
      map.on('mouseleave', layerId, () => { map.getCanvas().style.cursor = '' })
    }
  }

  const escHtml = (str) => { const d = document.createElement('div'); d.textContent = str; return d.innerHTML }

  // Parking icon
  const parkingCanvas = document.createElement('canvas')
  parkingCanvas.width = parkingCanvas.height = 64
  const pCtx = parkingCanvas.getContext('2d')
  pCtx.beginPath(); pCtx.arc(32, 32, 30, 0, Math.PI * 2); pCtx.fillStyle = '#1976d2'; pCtx.fill()
  pCtx.strokeStyle = '#fff'; pCtx.lineWidth = 3; pCtx.stroke()
  pCtx.fillStyle = '#fff'; pCtx.font = 'bold 36px Arial'; pCtx.textAlign = 'center'; pCtx.textBaseline = 'middle'
  pCtx.fillText('P', 32, 32)

  addIconLayer(canvasToImageData(parkingCanvas), 'parking-icon', parkingFeatures, 'annotation-parking', 'annotation-parking-layer', (f, coords) => {
    const desc = f.properties?.description || 'Parking'
    const [lng, lat] = coords
    return `<div style="font-family:sans-serif;max-width:220px;">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#1976d2;border-radius:50%;color:#fff;font-weight:bold;font-size:13px;">P</span>
        <strong>Parking</strong>
      </div>
      <p style="margin:0 0 8px;color:#555;">${escHtml(desc)}</p>
      <div style="display:flex;gap:8px;">
        <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#f5f5f5;border-radius:4px;text-decoration:none;color:#333;font-size:12px;">
          <img src="https://www.google.com/favicon.ico" width="14" height="14" alt="" style="border-radius:2px;" />Google Maps
        </a>
        <a href="https://maps.apple.com/?daddr=${lat},${lng}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#f5f5f5;border-radius:4px;text-decoration:none;color:#333;font-size:12px;">
          Apple Maps
        </a>
      </div>
    </div>`
  })

  // House icon
  const houseCanvas = document.createElement('canvas')
  houseCanvas.width = houseCanvas.height = 64
  const hCtx = houseCanvas.getContext('2d')
  hCtx.beginPath(); hCtx.arc(32, 32, 30, 0, Math.PI * 2); hCtx.fillStyle = '#e65100'; hCtx.fill()
  hCtx.strokeStyle = '#fff'; hCtx.lineWidth = 3; hCtx.stroke()
  hCtx.fillStyle = '#fff'; hCtx.beginPath(); hCtx.moveTo(32, 14); hCtx.lineTo(16, 34); hCtx.lineTo(48, 34); hCtx.closePath(); hCtx.fill()
  hCtx.fillRect(20, 32, 24, 20)

  addIconLayer(canvasToImageData(houseCanvas), 'house-icon', houseFeatures, 'annotation-houses', 'annotation-houses-layer', (f) => {
    const desc = f.properties?.description || 'Permission required'
    return `<div style="font-family:sans-serif;max-width:220px;">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:#e65100;border-radius:50%;font-size:14px;">🏠</span>
        <strong style="color:#e65100;">Permission Required</strong>
      </div>
      <p style="margin:0;color:#555;">${escHtml(desc)}</p>
    </div>`
  })

  // Fit bounds to include annotations
  const bounds = new maplibregl.LngLatBounds()
  if (cave.value.location_lng && cave.value.location_lat) {
    bounds.extend([cave.value.location_lng, cave.value.location_lat])
  }
  geojson.features.forEach(f => {
    if (f.geometry.type === 'Point') bounds.extend(f.geometry.coordinates)
    else if (f.geometry.type === 'LineString') f.geometry.coordinates.forEach(c => bounds.extend(c))
  })
  if (!bounds.isEmpty()) {
    map.fitBounds(bounds, { padding: 40, maxZoom: 15 })
  }
}
</script>

<style lang="scss">
@import "maplibre-gl/dist/maplibre-gl.css";

.cursor-pointer {
  cursor: pointer;
}

.hero-img {
  transition: filter 0.2s ease;

  &:hover {
    filter: brightness(0.9);
  }
}

.file-list {
  background-color: transparent;
}

.file-item {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding-left: 0 !important;
  padding-right: 0 !important;
}

.file-item:last-child {
  border-bottom: none;
}

.cave-map-mobile {
  margin-left: -16px;
  margin-right: -16px;
}

.annotation-popup .maplibregl-popup-content {
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.annotation-popup .maplibregl-popup-tip {
  border-top-color: #fff;
}
</style>