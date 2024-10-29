<template>
  <v-card
    class="mx-auto"
    max-width="500"
    color="surface-variant"
  >
    <v-container fluid>
      <v-row dense>
        <v-col
          v-for="card in cards"
          :key="card.title"
          :cols="card.flex"
        >
          <v-card>
            <v-img
              :src="card.src"
              class="align-end"
              gradient="to bottom, rgba(0,0,0,.1), rgba(0,0,0,.5)"
              height="200px"
              cover
              link
            >
              <router-link :to="{name:'/collection/[id]',params:{id:card.id}}">
                <v-card-title class="text-white" v-text="card.title"></v-card-title>
              </router-link>
            </v-img>

            <v-card-actions>
              <v-spacer></v-spacer>

              <v-btn
                color="medium-emphasis"
                icon="mdi-heart"
                size="small"
              ></v-btn>

              <v-btn
                color="medium-emphasis"
                icon="mdi-bookmark"
                size="small"
              ></v-btn>

              <!-- <v-btn
                color="medium-emphasis"
                icon="mdi-share-variant"
                size="small"
              ></v-btn> -->
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </v-card>
</template>

<script setup>
  import { useAppStore } from '@/stores/app'
  import { useCaveStore } from '@/stores/caves';

  const store = useAppStore()  
  const caveStore = useCaveStore()

  const search = ref('')
  const headers = ref([
    { title: 'Name', key: 'name' },
    { title: 'Length', key: 'system.length' },
    { title: 'Depth', key: 'system.vertical_range' },
    { title: 'Location', key: 'location' },
    { title: 'Tags', key: 'tags' }
  ])

  const caves = ref([])
  onMounted(async () => {
    await caveStore.getList()
  })

  const tab = ref('list')

  const cards = ref([
    {
      id: 1,
      title: 'Top 10 hardest caves in the UK',
      src: 'https://cncc.org.uk/media/large/dd76cad6-5c8b-09a1-a1b6-58ae15d83e1b.jpg',
      flex: 12,
    },
    {
      id: 2,
      title: 'Gaping Gill winch meet quick list',
      src: 'https://upload.wikimedia.org/wikipedia/commons/d/d3/Gaping_Gill.jpg',
      flex: 6,
    },
    {
      id: 3,
      title: 'Mendip caves worth doing',
      src: 'https://live.staticflickr.com/8236/8437745500_837976d520_b.jpg',
      flex: 6,
    }
  ])
</script>
