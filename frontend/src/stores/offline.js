import { defineStore } from 'pinia'

const DB_NAME = 'subterra-offline'
const DB_VERSION = 1

const STORES = {
  caves: 'caves',
  caveMedia: 'caveMedia',
  caveRoutes: 'caveRoutes',
  cachedImages: 'cachedImages',
  meta: 'meta',
}

function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)
    request.onerror = () => reject(request.error)
    request.onsuccess = () => resolve(request.result)
    request.onupgradeneeded = (event) => {
      const db = event.target.result
      if (!db.objectStoreNames.contains(STORES.caves)) {
        db.createObjectStore(STORES.caves, { keyPath: 'id' })
      }
      if (!db.objectStoreNames.contains(STORES.caveMedia)) {
        const mediaStore = db.createObjectStore(STORES.caveMedia, { keyPath: 'id' })
        mediaStore.createIndex('cave_id', 'cave_id', { unique: false })
      }
      if (!db.objectStoreNames.contains(STORES.caveRoutes)) {
        const routeStore = db.createObjectStore(STORES.caveRoutes, { keyPath: 'id' })
        routeStore.createIndex('cave_system_id', 'cave_system_id', { unique: false })
      }
      if (!db.objectStoreNames.contains(STORES.cachedImages)) {
        db.createObjectStore(STORES.cachedImages, { keyPath: 'url' })
      }
      if (!db.objectStoreNames.contains(STORES.meta)) {
        db.createObjectStore(STORES.meta, { keyPath: 'key' })
      }
    }
  })
}

async function dbPut(storeName, data) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite')
    const store = tx.objectStore(storeName)
    store.put(data)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function dbGet(storeName, key) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readonly')
    const store = tx.objectStore(storeName)
    const request = store.get(key)
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

async function dbGetAll(storeName) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readonly')
    const store = tx.objectStore(storeName)
    const request = store.getAll()
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

async function dbDelete(storeName, key) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite')
    const store = tx.objectStore(storeName)
    store.delete(key)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function dbGetByIndex(storeName, indexName, key) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readonly')
    const store = tx.objectStore(storeName)
    const index = store.index(indexName)
    const request = index.getAll(key)
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

async function dbClearStore(storeName) {
  const db = await openDB()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite')
    const store = tx.objectStore(storeName)
    store.clear()
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function cacheImageAsBlob(url) {
  try {
    const response = await fetch(url)
    if (!response.ok) return null
    const blob = await response.blob()
    return blob
  } catch {
    return null
  }
}

function detectPwa() {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: fullscreen)').matches ||
    window.navigator.standalone === true
  )
}

export const useOfflineStore = defineStore('offline', {
  state: () => ({
    isOnline: navigator.onLine,
    isPwa: detectPwa(),
    downloadedCaveIds: [],
    downloadingCaveId: null,
    downloadProgress: 0,
    swUpdateAvailable: false,
    swRegistration: null,
  }),

  getters: {
    isCaveDownloaded: (state) => (caveId) => {
      return state.downloadedCaveIds.includes(caveId)
    },
    downloadedCaveCount: (state) => state.downloadedCaveIds.length,
  },

  actions: {
    init() {
      window.addEventListener('online', () => { this.isOnline = true })
      window.addEventListener('offline', () => { this.isOnline = false })
      const mq = window.matchMedia('(display-mode: standalone)')
      mq.addEventListener('change', (e) => { this.isPwa = e.matches || window.navigator.standalone === true })
      this.loadDownloadedCaveIds()
    },

    async loadDownloadedCaveIds() {
      try {
        const caves = await dbGetAll(STORES.caves)
        this.downloadedCaveIds = caves.map(c => c.id)
      } catch {
        this.downloadedCaveIds = []
      }
    },

    async downloadCaveForOffline(caveId, api) {
      this.downloadingCaveId = caveId
      this.downloadProgress = 0

      try {
        // 1. Fetch full cave data
        this.downloadProgress = 10
        const caveRes = await api.get(`/api/caves/${caveId}`)
        const cave = caveRes.data.data
        this.downloadProgress = 25

        // 2. Fetch cave media
        let media = []
        if (cave.media && cave.media.length > 0) {
          media = cave.media
        }
        this.downloadProgress = 35

        // 3. Fetch routes for the cave system
        let routes = []
        if (cave.system && cave.system.id) {
          try {
            const routesRes = await api.get(`/api/cave-systems/${cave.system.id}/routes`)
            routes = routesRes.data.data || []
          } catch {
            // Routes may not exist
          }
        }
        this.downloadProgress = 50

        // 4. Cache images as blobs
        const imageUrls = []
        if (cave.hero_image) imageUrls.push(cave.hero_image)
        if (media.length > 0) {
          media.forEach(m => {
            if (m.url) imageUrls.push(m.url)
            if (m.thumbnail_url) imageUrls.push(m.thumbnail_url)
          })
        }
        if (routes.length > 0) {
          routes.forEach(r => {
            if (r.media && r.media.length > 0) {
              r.media.forEach(m => {
                if (m.url) imageUrls.push(m.url)
                if (m.thumbnail_url) imageUrls.push(m.thumbnail_url)
              })
            }
          })
        }

        const totalImages = imageUrls.length
        let cachedCount = 0
        for (const url of imageUrls) {
          const blob = await cacheImageAsBlob(url)
          if (blob) {
            await dbPut(STORES.cachedImages, { url, blob, cachedAt: Date.now() })
          }
          cachedCount++
          this.downloadProgress = 50 + Math.round((cachedCount / Math.max(totalImages, 1)) * 40)
        }

        // 5. Store cave data
        await dbPut(STORES.caves, {
          ...cave,
          _offlineAt: Date.now(),
          _offlineMedia: media,
        })

        // 6. Store media records
        for (const m of media) {
          await dbPut(STORES.caveMedia, { ...m, cave_id: caveId })
        }

        // 7. Store routes
        for (const r of routes) {
          await dbPut(STORES.caveRoutes, { ...r, cave_system_id: cave.system?.id })
        }

        // 8. Update meta
        await dbPut(STORES.meta, { key: `cave_${caveId}_downloaded`, value: Date.now() })

        this.downloadProgress = 100
        this.downloadedCaveIds.push(caveId)

        return { success: true }
      } catch (error) {
        console.error('Failed to download cave for offline:', error)
        return { success: false, error: error.message }
      } finally {
        this.downloadingCaveId = null
        this.downloadProgress = 0
      }
    },

    async removeCaveOfflineData(caveId) {
      try {
        // Remove cave
        await dbDelete(STORES.caves, caveId)

        // Remove media for this cave
        const media = await dbGetByIndex(STORES.caveMedia, 'cave_id', caveId)
        for (const m of media) {
          await dbDelete(STORES.caveMedia, m.id)
          // Remove cached images for this media
          if (m.url) await dbDelete(STORES.cachedImages, m.url)
          if (m.thumbnail_url) await dbDelete(STORES.cachedImages, m.thumbnail_url)
        }

        // Remove meta
        await dbDelete(STORES.meta, `cave_${caveId}_downloaded`)

        this.downloadedCaveIds = this.downloadedCaveIds.filter(id => id !== caveId)
      } catch (error) {
        console.error('Failed to remove offline cave data:', error)
      }
    },

    async getOfflineCave(caveIdOrSlug) {
      // Try by ID first
      const byId = await dbGet(STORES.caves, Number(caveIdOrSlug))
      if (byId) return byId
      // Fall back to slug search
      const allCaves = await dbGetAll(STORES.caves)
      return allCaves.find(c => c.slug === caveIdOrSlug || String(c.id) === String(caveIdOrSlug))
    },

    async getAllOfflineCaves() {
      return await dbGetAll(STORES.caves)
    },

    async getOfflineCaveRoutes(caveSystemId) {
      return await dbGetByIndex(STORES.caveRoutes, 'cave_system_id', caveSystemId)
    },

    async getOfflineCaveMedia(caveId) {
      return await dbGetByIndex(STORES.caveMedia, 'cave_id', caveId)
    },

    async getCachedImageUrl(originalUrl) {
      try {
        const cached = await dbGet(STORES.cachedImages, originalUrl)
        if (cached && cached.blob) {
          return URL.createObjectURL(cached.blob)
        }
      } catch {
        // Fall through to return original
      }
      return originalUrl
    },

    async getOfflineStorageSize() {
      try {
        if (navigator.storage && navigator.storage.estimate) {
          const estimate = await navigator.storage.estimate()
          return {
            used: estimate.usage || 0,
            quota: estimate.quota || 0,
            usedMB: Math.round((estimate.usage || 0) / 1024 / 1024),
            quotaMB: Math.round((estimate.quota || 0) / 1024 / 1024),
          }
        }
      } catch {
        // Storage estimate not available
      }
      return { used: 0, quota: 0, usedMB: 0, quotaMB: 0 }
    },

    async clearAllOfflineData() {
      await dbClearStore(STORES.caves)
      await dbClearStore(STORES.caveMedia)
      await dbClearStore(STORES.caveRoutes)
      await dbClearStore(STORES.cachedImages)
      await dbClearStore(STORES.meta)
      this.downloadedCaveIds = []
    },

    setSwRegistration(registration) {
      this.swRegistration = registration
    },

    setSwUpdateAvailable(available) {
      this.swUpdateAvailable = available
    },

    updateServiceWorker() {
      if (this.swRegistration && this.swRegistration.waiting) {
        this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' })
        this.swUpdateAvailable = false
        window.location.reload()
      }
    },
  },
})
