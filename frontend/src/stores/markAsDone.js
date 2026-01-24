import { mande } from 'mande'
import { useToast } from "vue-toastification"

export async function markCaveAsDone({ cave, userId }) {
  const toast = useToast()

  if (!cave || !userId) return false;
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
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(trip)
    })

    if (response.ok) {
      toast.success('Cave marked as done!')
      return true
    } else {
      toast.error('Failed to mark cave as done')
      return false
    }
  } catch (error) {
    toast.error('Failed to mark cave as done: ' + (error.message || 'Unknown error'))
    return false
  }
}
