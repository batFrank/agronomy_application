const modules = import.meta.glob('./*/index.js', { eager: true })

export const MODULE_REGISTRY = {}

Object.values(modules).forEach((module) => {
  const plugin = module.default
  if (plugin?.name) {
    MODULE_REGISTRY[plugin.name] = plugin
  }
})
