export const LOCALES = {
  default: import.meta.glob(`../locales/*.json`, {
    import: 'default',
    eager: true,
  }),
  clients: import.meta.glob(`../locales/*/*.json`, {
    import: 'default',
    eager: true,
  }),
}
