export function initSiteData() {
  window.document.title = window.$settings.TITLE
  const tags = window.$settings?.META?.TAGS || []
  if (tags.length) setMetaTags('keywords', tags.join(', '))
  if (window.$settings?.META?.DESCRIPTION) setMetaTags('description', $settings.META.DESCRIPTION)
}

function setMetaTags(name, content) {
  let meta = document.querySelector(`meta[name="${name}"]`)
  if (!meta) {
    meta = document.createElement('meta')
    meta.name = name
    document.getElementsByTagName('head')[0].appendChild(meta)
  }
  meta.content = content
}
