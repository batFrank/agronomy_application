import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import axiosInstance from '@/core/configs/axios.config.js'
import ApiService from './api.service.js'
import { ApiError, ConnectionError, ServerError } from '@/core/models/error.model.js'

// Spy on the real axios instance's `request` rather than vi.mock()-ing the module:
// module-factory mocks don't hoist under the bun runtime the project's test runner
// uses, but spying on a singleton method is runtime-agnostic. Error models and
// objToGetString stay real so URL building + error mapping are exercised for real.
let requestSpy

beforeEach(() => {
  requestSpy = vi.spyOn(axiosInstance, 'request')
})
afterEach(() => {
  vi.restoreAllMocks()
})

const resolveWith = (data) => requestSpy.mockResolvedValue({ data })
const rejectWith = (err) => requestSpy.mockRejectedValue(err)

describe('ApiService', () => {
  describe('request building', () => {
    it('GET without params keeps the bare url and sets method GET', async () => {
      resolveWith({ data: [] })
      await ApiService.get('/users')
      expect(requestSpy).toHaveBeenCalledWith({ url: '/users', method: 'GET' })
    })

    it('GET with params appends an encoded query string', async () => {
      resolveWith({ data: [] })
      await ApiService.get('/users', { role: 'admin user', page: 2 })
      expect(requestSpy).toHaveBeenCalledWith({
        url: '/users?role=admin%20user&page=2',
        method: 'GET',
      })
    })

    it('POST forwards the body and method', async () => {
      resolveWith({ data: { id: 'a' } })
      await ApiService.post('/users', { email: 'a@b.c' })
      expect(requestSpy).toHaveBeenCalledWith({
        url: '/users',
        method: 'POST',
        data: { email: 'a@b.c' },
      })
    })

    it('PUT and PATCH carry their body + method', async () => {
      resolveWith({})
      await ApiService.put('/x', { a: 1 })
      expect(requestSpy).toHaveBeenCalledWith({ url: '/x', method: 'PUT', data: { a: 1 } })
      await ApiService.patch('/y', { b: 2 })
      expect(requestSpy).toHaveBeenCalledWith({ url: '/y', method: 'PATCH', data: { b: 2 } })
    })

    it('DELETE sends no body', async () => {
      resolveWith({})
      await ApiService.delete('/users/1')
      expect(requestSpy).toHaveBeenCalledWith({ url: '/users/1', method: 'DELETE' })
    })

    it('merges an extra config into the request', async () => {
      resolveWith({ data: 1 })
      await ApiService.get('/x', {}, { headers: { 'X-Test': '1' } })
      expect(requestSpy).toHaveBeenCalledWith({
        headers: { 'X-Test': '1' },
        url: '/x',
        method: 'GET',
      })
    })
  })

  describe('response handling', () => {
    it('spreads response.data alongside error:false on success', async () => {
      resolveWith({ data: [{ id: 'u1' }], meta: { total: 1 } })
      const result = await ApiService.get('/users')
      expect(result).toEqual({ error: false, data: [{ id: 'u1' }], meta: { total: 1 } })
    })

    it('passes through a rejected ApiError as { error }', async () => {
      const err = new ApiError({ data: { message: 'bad request' } })
      rejectWith(err)
      const result = await ApiService.post('/x', {})
      expect(result).toEqual({ error: err })
      expect(result.error.message).toBe('bad request')
    })

    it('passes through ServerError and ConnectionError', async () => {
      const server = new ServerError('500')
      rejectWith(server)
      expect(await ApiService.get('/x')).toEqual({ error: server })

      const conn = new ConnectionError('offline')
      rejectWith(conn)
      expect(await ApiService.get('/x')).toEqual({ error: conn })
    })

    it('wraps an unknown rejection in a ConnectionError', async () => {
      rejectWith(new TypeError('kaboom'))
      const result = await ApiService.get('/x')
      expect(result.error).toBeInstanceOf(ConnectionError)
      expect(result.error.message).toBe('Connection error')
    })
  })
})
