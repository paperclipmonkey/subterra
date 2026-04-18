import { computed } from 'vue'
import { useOfflineStore } from '@/stores/offline'

export function useOffline() {
  const offlineStore = useOfflineStore()

  const isOnline = computed(() => offlineStore.isOnline)
  const isOffline = computed(() => !offlineStore.isOnline)

  function isCaveAvailableOffline(caveId) {
    return offlineStore.isCaveDownloaded(caveId)
  }

  return {
    isOnline,
    isOffline,
    isCaveAvailableOffline,
    offlineStore,
  }
}
