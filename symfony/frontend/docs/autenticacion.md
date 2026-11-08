# Autenticación

## Visión general

El sistema de autenticación usa **JWT** con **refresh token**. Los tokens se almacenan en **cookies** (no en localStorage) para mejorar la seguridad. El estado del usuario se lee directamente del payload del JWT sin necesidad de llamadas adicionales al servidor.

---

## Flujo de login

```
Usuario rellena formulario
        │
        ▼
useLoginView.login()
        │
        ├─ Valida con loginSchema (Joi)
        │   → email: requerido, formato válido
        │   → password: mínimo 5 caracteres
        │
        ▼
authStore.login({ email, password })
        │
        ├─ POST /auth/login
        │   ← { data: { token, refresh_token } }
        │
        ├─ StorageService.setToken(token)
        ├─ StorageService.setRefreshToken(refresh_token)
        ├─ currentUser.initData()  ← re-lee el JWT
        │
        └─ router.push(currentUser.authDefault)
```

---

## Almacenamiento de tokens

Los tokens se guardan en **cookies** con la clave `_tk` (token) y `_rtk` (refresh token):

```js
StorageService.setToken(value)       // Cookie _tk, expira en 1 día
StorageService.setRefreshToken(value) // Cookie _rtk, expira en 1 día
StorageService.setToken()            // Sin valor → borra la cookie
```

El locale del usuario se guarda en `localStorage` con clave `_locale`.

---

## CurrentUserModel — lectura del JWT

Al llamar `currentUser.initData()`, el modelo decodifica el payload Base64 del JWT almacenado en cookie:

```js
getJwtPayloadData() {
  const [_, payload] = (StorageService.getToken() || '').split('.')
  return stringToJson(window.atob(payload), {})
}
```

Campos esperados en el payload:
- `id` — identificador del usuario
- `username` — nombre de usuario
- `roles` — array de roles (`ROLE_ADMIN`, `ROLE_MANAGER`, `ROLE_USER`)
- `exp` — timestamp de expiración (Unix seconds)

`isAuth` es `true` si hay `username` y el token no ha expirado.

---

## Refresh token automático

El interceptor de respuesta de Axios maneja la renovación automática:

```
Respuesta 401 (no en /auth/)
        │
        ├─ ¿Es el primer intento? (_retry === false)
        │   ├─ POST /auth/refresh { refresh_token }
        │   │   ← { token, refresh_token }
        │   ├─ Guarda nuevos tokens
        │   ├─ currentUser.initData()
        │   └─ Reintenta la petición original
        │
        └─ ¿Ya se reintentó? (_retry === true)
            └─ authStore.logout()
```

Si no hay refresh token almacenado, `refreshToken()` retorna `undefined` y la sesión termina.

---

## Logout

```js
authStore.logout()
// 1. Lee el refresh_token de la cookie
// 2. Borra ambas cookies (_tk, _rtk) inmediatamente
// 3. currentUser.initData() → isAuth = false
// 4. POST /auth/logout { refresh_token }  (invalida en el servidor)
// 5. Si hay error en el POST → muestra toast de error
```

El logout es local primero (cookies borradas antes de la petición) para garantizar que el usuario quede deslogueado aunque falle la red.

---

## Recuperación de contraseña

### Paso 1 — Solicitar enlace (`PasswordRecoveryView`)
- Valida email con `emailSchema`.
- `POST /auth/password-recover { email }`.
- Al éxito, cambia `stage` a `'success'` y muestra confirmación.

### Paso 2 — Verificación del token (`PasswordResetView`)
- Al montar la vista, valida el token del parámetro de ruta:
  `POST /auth/password-check { reset_token }`.
- Si el token no es válido → `stage = 'invalid'`.

### Paso 3 — Nueva contraseña (`PasswordResetView`)
- Valida con `passwordResetSchema` (mínimo 6 caracteres).
- `POST /auth/password-reset { reset_token, new_password }`.
- Al éxito → redirige a `/login`.

---

## Endpoints de autenticación

| Método | URL | Descripción |
|---|---|---|
| POST | `/auth/login` | Obtiene token y refresh token |
| POST | `/auth/logout` | Invalida refresh token en servidor |
| POST | `/auth/refresh` | Renueva ambos tokens |
| POST | `/auth/password-recover` | Envía email con enlace de recuperación |
| POST | `/auth/password-check` | Verifica validez de un reset token |
| POST | `/auth/password-reset` | Establece nueva contraseña |

---

## Control de acceso en la UI

`currentUser.isGranted(roles)` es un **guard de UI únicamente**:

```js
// Siempre retorna true para ROLE_ADMIN
// Para otros roles: comprueba intersección con roles requeridos
currentUser.isGranted([ROLES.MANAGER])  // true si es MANAGER o ADMIN
```

> ⚠️ La autorización real se aplica en el backend en cada petición. Este método solo sirve para mostrar/ocultar elementos en la interfaz.

---

## Stores relacionados

- **`useAuthStore`** (`src/contexts/shared/stores/auth.store.js`) — acciones de login, logout y refresh.
- **`useLocalStore`** (`src/core/stores/locale.store.js`) — gestión del idioma activo, se inicializa detectando el idioma del navegador o las preferencias guardadas.
