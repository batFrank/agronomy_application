export function BASE_URL() {
  let apiUrl = import.meta.env.VITE_API_URL || ''
  apiUrl = apiUrl.replace(/\/+$/, '')
  if (/^https?:\/\//i.test(apiUrl)) {
    return apiUrl
  }
  if (apiUrl.startsWith('/')) {
    return `${window.location.origin}${apiUrl}`
  }
  return `${window.location.origin}/${apiUrl}`
}
