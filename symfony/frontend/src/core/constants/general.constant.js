export const BASE_LOCATION = (() => {
  try {
    const baseURL = import.meta.env.VITE_BASE_URL ?? window?.location?.origin ?? ''
    return baseURL[baseURL.length - 1] === '/' ? baseURL : `${baseURL}/`
  } catch (e) {
    return ''
  }
})()

export const API_URL = (() => {
  try {
    return import.meta.env?.VITE_API_URL ?? `${BASE_LOCATION}api`
  } catch (e) {
    return ''
  }
})()

export const REFRESH_TOKEN_URL = '/auth/refresh'
export const PUBLIC_ENDPOINTS = ['/auth/login', '/auth/logout', REFRESH_TOKEN_URL]

export const ONE_DAY_IN_MS = 86400

export const HTACCESS_TEMPLATE = (BASE_DIR = '') => `DirectoryIndex index.html index.html
<IfModule mod_rewrite.c>
    RewriteEngine On
    Header Set Cache-Control "max-age=0, no-store"
    RewriteCond %{REQUEST_URI} ^/404/$
    RewriteRule ^(.*)$ /index.html [L]

    RewriteBase /${BASE_DIR}
    RewriteRule ^${BASE_DIR}/index\\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /${BASE_DIR}/index.html [L]
    RewriteRule ^ /${BASE_DIR}/index.html [L]
</IfModule>
`
