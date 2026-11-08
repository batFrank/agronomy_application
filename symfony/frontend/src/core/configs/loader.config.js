export const VIEWS = {
  default: import.meta.glob('@/contexts/**/views/*.vue', { eager: true }),
  clients: import.meta.glob('@/clients/**/views/*.vue', { eager: true }),
}

// PARCHE LOCAL — ver issue #88 del repositorio symfony-base-project.
// El patrón de extensión debe ir con llaves «{a,b}»: el extglob «(a|b)» no lo
// expande el matcher de import.meta.glob, así que IMAGES quedaba vacío y ningún
// logo ni favicon llegaba al build de producción.
// Este fichero pertenece al subtree de vue-base-project: si «make frontend-sync»
// revierte el cambio, hay que volver a aplicarlo hasta que se corrija en origen.
export const IMAGES = {
  default: import.meta.glob('@/contexts/**/shared/assets/images/*.{png,jpg,jpeg,svg,ico,webp}', {
    import: 'default',
    eager: true,
  }),
  clients: import.meta.glob('@/clients/**/shared/assets/images/*.{png,jpg,jpeg,svg,ico,webp}', {
    import: 'default',
    eager: true,
  }),
}

export const APP_COMPONENT = {
  AppHeader: import.meta.glob('@/contexts/layout/components/AppHeader.vue', {
    import: 'default',
    eager: true,
  }),
  AppFooter: import.meta.glob('@/contexts/layout/components/AppFooter.vue', {
    import: 'default',
    eager: true,
  }),
}
