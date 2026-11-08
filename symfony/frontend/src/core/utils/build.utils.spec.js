import { describe, expect, it } from 'vitest'
import { parseBuildArgs, resolveFaviconSource, resolveLocaleFilePath } from '@/core/utils/build.utils.js'

const CLIENTS = ['default', 'easyLearning']

describe('[core/utils/build.utils] parseBuildArgs', () => {
  it('returns no options when no flags are given, so the caller can prompt', () => {
    expect(parseBuildArgs([], CLIENTS)).toEqual({})
  })

  it('parses client and mode flags in any order', () => {
    expect(parseBuildArgs(['--mode=production', '--client=easyLearning'], CLIENTS)).toEqual({
      client: 'easyLearning',
      mode: 'production',
    })
  })

  it('accepts one flag alone', () => {
    expect(parseBuildArgs(['--client=default'], CLIENTS)).toEqual({ client: 'default' })
  })

  it('rejects an unknown flag', () => {
    expect(() => parseBuildArgs(['--env=production'], CLIENTS)).toThrow(/Unknown build argument "--env=production"/)
  })

  it('rejects a flag without a value', () => {
    expect(() => parseBuildArgs(['--client='], CLIENTS)).toThrow(/Unknown build argument "--client="/)
  })

  it('rejects a mode vite does not have an env file for', () => {
    expect(() => parseBuildArgs(['--mode=staging'], CLIENTS)).toThrow(/Unknown build mode "staging"/)
  })

  it('rejects a client that has no folder', () => {
    expect(() => parseBuildArgs(['--client=acme'], CLIENTS)).toThrow(/Unknown client "acme"/)
  })
})

describe('[core/utils/build.utils] resolveFaviconSource', () => {
  const DEFAULT_FAVICON = 'src/contexts/shared/assets/images/favicon.ico'
  const CLIENT_FAVICON = 'src/clients/easyLearning/shared/assets/images/favicon.ico'

  it('uses the template favicon for the default client', () => {
    expect(resolveFaviconSource('default', () => true)).toBe(DEFAULT_FAVICON)
  })

  it('prefers the client favicon when it exists', () => {
    expect(resolveFaviconSource('easyLearning', () => true)).toBe(CLIENT_FAVICON)
  })

  it('falls back to the template favicon when the client has none', () => {
    expect(resolveFaviconSource('easyLearning', (path) => path === DEFAULT_FAVICON)).toBe(DEFAULT_FAVICON)
  })

  it('fails when no favicon exists anywhere instead of building without one', () => {
    expect(() => resolveFaviconSource('easyLearning', () => false)).toThrow(/No favicon found/)
  })
})

describe('[core/utils/build.utils] resolveLocaleFilePath', () => {
  it('stores a template locale at the top level', () => {
    expect(resolveLocaleFilePath('en', 'src/core/locales')).toBe('src/core/locales/en.json')
  })

  it('stores a client locale under its client folder', () => {
    expect(resolveLocaleFilePath('en_easyLearning', 'src/core/locales')).toBe(
      'src/core/locales/clients/easyLearning/en.json'
    )
  })

  it('drops a region suffix', () => {
    expect(resolveLocaleFilePath('es-ES', 'src/core/locales')).toBe('src/core/locales/es.json')
  })
})
