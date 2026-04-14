import { useNotificationStore } from '@/stores/notifications'
import { api } from '@/plugins/api'

export async function markCaveAsDone({ cave, userId }) {
  const notifications = useNotificationStore()

  if (!cave || !userId) return false
  const trip = {
    name: 'Marked as Done',
    entrance_cave_id: cave.id,
    exit_cave_id: cave.id,
    participants: [userId],
    cave_system_id: cave.system.id,
    visibility: 'private',
  }

  try {
    await api.post('/api/trips', trip)
    notifications.showSuccess('Cave marked as done!')
    return true
  } catch (error) {
    notifications.showError('Failed to mark cave as done: ' + (error.response?.data?.message || error.message || 'Unknown error'))
    return false
  }
}
