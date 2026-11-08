export function bytesToText(bytes = 0) {
  if (!bytes) return '-'
  const sizes = ['B', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb']
  const index = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${parseFloat((bytes / Math.pow(1024, index)).toFixed(2))} ${sizes[index]}`
}

export function openLink(router, link) {
  if (/http(s)?:/gi.test(link)) return window.open(link, '_blank', 'noopener noreferrer')
  router.push({ path: link }).catch((e) => console.log(e))
}
