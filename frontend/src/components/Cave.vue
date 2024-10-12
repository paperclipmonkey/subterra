<template>
  <v-container>
    <v-row>
      <v-col cols="12">
        <v-btn icon @click="$router.go(-1)">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <v-toolbar-title>{{ cave.name }}</v-toolbar-title>
        <v-subheader>{{ cave.system.name }}</v-subheader>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>{{ cave.name }}</v-card-title>
          <v-card-subtitle>{{ cave.system.name }}</v-card-subtitle>
          <v-card-text>
            <vue-markdown :source="cave.description" />
            <p><strong>Length:</strong> {{ cave.length }}m</p>
            <p><strong>Depth:</strong> {{ cave.depth }}m</p>
            <p><strong>Location:</strong> <a :href='googleMapsUrl'>{{ cave.location.name }}, {{ cave.location.country }}</a></p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>System</v-card-title>
          <v-card-text>
            <v-list>
              <v-list-item v-for="system_cave in cave.system.caves" :key="id">
                <v-list-item-content>
                  <v-list-item-title>{{ system_cave.name }}</v-list-item-title>
                  <v-list-item-subtitle>{{ system_cave.description }}</v-list-item-subtitle>
                </v-list-item-content>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>Recent Trips</v-card-title>
          <v-card-text>
            <v-list>
              <v-list-item v-for="trip in cave.trips" :key="trip.datetime">
                <v-list-item-content>
                  <v-list-item-title>{{ new Date(trip.datetime).toLocaleString() }}</v-list-item-title>
                  <v-list-item-subtitle>{{ trip.description }}</v-list-item-subtitle>
                  <v-list-item-subtitle>Duration: {{ trip.duration }} minutes</v-list-item-subtitle>
                    <v-list-item-subtitle>Party:</v-list-item-subtitle>
                    <v-row>
                      <v-col v-for="member in trip.party" :key="member.id" cols="auto">
                        <v-list-item-avatar>
                        <v-img :src="member.photo"></v-img>
                        </v-list-item-avatar>
                        <v-list-item-content>
                        <v-list-item-title>{{ member.name }}</v-list-item-title>
                        </v-list-item-content>
                      </v-col>
                    </v-row>
                </v-list-item-content>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { computed } from 'vue'
import VueMarkdown from 'vue-markdown-render'

const route = useRoute()

  const googleMapsUrl = computed(() => {
    return `https://www.google.com/maps/search/?api=1&query=${cave.value.location.lng}%2C${ cave.value.location.lat }`
  })
  // const cave = ref(
  //   {
  //     name: 'Carlsbad Caverns',
  //     description: "Carlsbad Caverns is a famous cave system located in Carlsbad, New Mexico, USA. It is known for its vast underground chambers and stunning rock formations. The cave system includes the Big Room, which is one of the largest underground chambers in North America.",
  //     system: 'Carlsbad Caverns',
  //     length: '33.5 km',
  //     depth: '1,597 metres',
  //     location: {
  //       name: 'Carlsbad, New Mexico',
  //       country: 'USA',
  //       lat: 4.4,
  //       lng: 3.3,
  //     },
  //     trips: [
  //       {
  //           description: "An exhilarating sport caving trip exploring the depths of Carlsbad Caverns. The team navigated through narrow passages and climbed steep rock formations, discovering stunning underground landscapes and unique geological features.",
  //           datetime: new Date(new Date().setDate(new Date().getDate() - 7)).toISOString(),
  //           duration: 240,
  //           party: [
  //             {
  //               "name": "Alice",
  //               "id": 3211,
  //               "photo": "/users/3211.png",
  //             },
  //             {
  //               "name": "Bob",
  //               "id": 3212,
  //               "photo": "/users/3212.png",
  //             }
  //           ]
  //       }
  //     ]
  //   }
  // )

  const cave = ref({
    name: '',
    description: '',
    system: {
      name: '',
      description: '',
    },
    length: '',
    depth: '',
    location: {
      name: '',
      country: '',
      lat: 0,
      lng: 0,
    },
    trips: []
  })
  onMounted(async () => {
    const response = await fetch(`/api/caves/${route.params.id}`)
    cave.value = (await response.json()).data
  })
</script>
