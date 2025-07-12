import { mande } from 'mande'

export async function markCaveAsDone({ cave, userId }) {
  if (!cave || !userId) return false;
  const trip = {
    name: 'Marked as Done',
    entrance_cave_id: cave.id,
    exit_cave_id: cave.id,
    participants: [userId],
    cave_system_id: cave.system.id,
    visibility: 'private',
  }

  const response = await fetch('/api/trips', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(trip)
  })
  return response.ok;
}
