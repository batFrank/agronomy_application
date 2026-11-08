import { Response } from 'miragejs'
import { REFRESH_TOKEN_URL } from '@/core/constants/general.constant.js'

function generateFakeToken(payload) {
  const header = btoa(JSON.stringify({ alg: 'HS256', typ: 'JWT' }))
  const body = btoa(JSON.stringify(payload))
  const signature = 'fake_signature'
  return `${header}.${body}.${signature}`
}

export default [
  {
    url: '/auth/login',
    callback: (_, request) => {
      const { email, password } = JSON.parse(request.requestBody)
      if (email === import.meta.env.VITE_TEST_USER && password === import.meta.env.VITE_TEST_PASS)
        return {
          status: 200,
          error: false,
          data: {
            token: generateFakeToken({
              id: 1,
              roles: import.meta.env.VITE_TEST_ROLES.split(','),
              username: 'username',
              exp: Math.floor(Date.now() / 1000) + 60 * 60,
            }),
            refresh_token: generateFakeToken({
              id: 1,
              type: 'refresh',
              exp: Math.floor(Date.now() / 1000) + 60 * 60 * 24 * 7,
            }),
          },
        }

      return new Response(
        400,
        {},
        {
          status: 401,
          data: 'Las credenciales introducidas no son correctas.',
          error: true,
        }
      )
    },
    method: 'post',
  },
  {
    url: REFRESH_TOKEN_URL,
    callback: () => ({
      status: 200,
      error: false,
      data: {
        token: 'token_sample',
        refresh_token: 'token_refresh_sample',
      },
    }),
    method: 'post',
  },
  {
    url: '/auth/logout',
    callback: () => ({
      status: 200,
      error: false,
      data: 'User successfully logout',
    }),
    method: 'get',
  },
]
