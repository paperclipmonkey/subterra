import { computed, watch, onUnmounted } from 'vue'
import { useAppStore } from '@/stores/app'

/**
 * While the signed-in user is waiting on a club to confirm them, check for the
 * approval in the background, on any page. Once it lands, refresh the user in
 * the store (which clears the "awaiting confirmation" banner straight away) and
 * reload the current page, so everything gated on membership (maps, locations,
 * callouts...) comes back fully enabled.
 */
export function useClubApprovalWatcher ({ intervalMs = 10000, reload = () => window.location.reload() } = {}) {
  const appStore = useAppStore()
  let timer = null

  const awaitingApproval = computed(() =>
    !!appStore.user?.id &&
    !appStore.canSuggest &&
    (appStore.user.clubs || []).some(c => c.status === 'pending')
  )

  const check = async () => {
    if (document.hidden) return
    const user = await appStore.getUser(true)
    if ((user?.clubs || []).some(c => c.status === 'approved')) {
      stop()
      reload()
    }
  }

  const onVisible = () => {
    if (!document.hidden) check()
  }

  const start = () => {
    if (timer) return
    timer = setInterval(check, intervalMs)
    document.addEventListener('visibilitychange', onVisible)
  }

  function stop () {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
    document.removeEventListener('visibilitychange', onVisible)
  }

  watch(awaitingApproval, (awaiting) => (awaiting ? start() : stop()), { immediate: true })
  onUnmounted(stop)

  return { awaitingApproval, check }
}
