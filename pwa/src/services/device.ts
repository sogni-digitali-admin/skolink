/** Stable per-device id required by the backend login endpoints ("one device = one token"). */
const KEY = 'gego.device_id'

export function getDeviceId(): string {
  let id = localStorage.getItem(KEY)
  if (!id) {
    id = crypto.randomUUID()
    localStorage.setItem(KEY, id)
  }
  return id
}
