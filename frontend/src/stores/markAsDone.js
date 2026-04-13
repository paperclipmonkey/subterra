import { useNotificationStore } from '@/stores/notifications'

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
    const response = await fetch('/api/trips', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(trip)
    })

    if (response.ok) {
      notifications.showSuccess('Cave marked as done!')
      return true
    } else {
      notifications.showError('Failed to mark cave as done')
      return false
    }
  } catch (error) {
    notifications.showError('Failed to mark cave as done: ' + (error.message || 'Unknown error'))
    return false
  }
}
