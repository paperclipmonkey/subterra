<template>
  <v-card
    :disabled="loading"
    :loading="loading"
    class="mx-auto my-12"
  >
    <template v-slot:loader="{ isActive }">
      <v-progress-linear
        :active="isActive"
        color="deep-purple"
        height="4"
        indeterminate
      ></v-progress-linear>
    </template>

    <v-img
      height="250"
      src="https://upload.wikimedia.org/wikipedia/commons/d/d3/Gaping_Gill.jpg"
      cover
    ></v-img>

    <v-card-item>
      <v-card-title>Gaping Gill winch meet quick list</v-card-title>

      <v-card-subtitle>
        <span class="me-1">Local Favorite</span>

        <v-icon
          color="error"
          icon="mdi-fire-circle"
          size="small"
        ></v-icon>
      </v-card-subtitle>
    </v-card-item>

    <v-card-text>
      <v-row
        align="center"
        class="mx-0"
      >
        <v-rating
          :model-value="4.5"
          color="amber"
          density="compact"
          size="small"
          half-increments
          readonly
        ></v-rating>

        <div class="text-grey ms-4">
          4.5 (413)
        </div>
      </v-row>

      <div class="my-4 text-subtitle-1">
        $ • Italian, Cafe
      </div>

      <div>11 of the best entrances to Gaping Gill to do on a winch meet weekend. How many can you tick off?</div>

      <div>From (B)ar Pot to (W)ade's entrance</div>

      <br/>

    <v-divider class="mx-4 mb-1"></v-divider>

    <v-card-title>Tags</v-card-title>

    <div class="px-4 mb-2">
      <v-chip-group v-model="selection" selected-class="bg-deep-purple-lighten-2">
        <v-chip>Winch Meet</v-chip>
        <v-chip>Gaping Gill</v-chip>
        <v-chip>Yorkshire</v-chip>
      </v-chip-group>
    </div>

    <v-divider class="mx-4 mb-1"></v-divider>
    <v-card-title>Caves</v-card-title>
    Completed:
    <v-progress-linear
          color="orange-darken-3"
          height="12"
          model-value="75"
          rounded="lg"
        >
          <!-- <v-badge
            :style="`right: ${review}`"
            class="position-absolute"
            color="white"
            dot
            inline
          ></v-badge> -->
        </v-progress-linear>
    </v-card-text>

    <div class="px-4 mb-2">
      <v-row align="center" justify="center" dense>
        <v-col cols="12" md="6">
          <v-card
            class="mx-auto"
            color="deep-purple-lighten-2"
            subtitle="Gaping Gill"
            title="Main Shaft"
          >
            <v-card-text>First descended in 1895, this is the most direct entrance to the main chamber</v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card
            append-icon="mdi-check"
            class="mx-auto"
            subtitle="A recent connection to the system, only found in 2015"
            title="Small Mammal Pot"
          >
            <v-card-text>To the West of Bar Pot you'll find Small Mammal, a sporting entrance series</v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </div>
    <!-- <v-card-actions>
      <v-btn
        color="deep-purple-lighten-2"
        text="Reserve"
        block
        border
        @click="reserve"
      ></v-btn>
    </v-card-actions> -->
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
    { title: 'Length', key: 'length' },
    { title: 'Depth', key: 'depth' },
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
