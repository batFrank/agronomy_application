import { reactive } from 'vue'
import { Pagination } from '@/core/models/pagination.model.js'
import FormModel from '@/core/models/form.model.js'
import { toast } from 'vue3-toastify'
import ApiService from '@/core/services/api.service.js'

export default function useRestModel(BASE_URL, DTO) {
  /** @type {{
   *  isLoading: Boolean
   *  list: []
   *  pagination: Pagination
   }} */
  const model = reactive({
    isLoading: false,
    list: [],
    pagination: new Pagination({ active: false }),
  })

  function getItemsAsOptions(key = '', value = '') {
    return model.list.map((item) => ({ key: item[key], name: item[value] }))
  }

  function isValidDTO(DTO_VAR) {
    return typeof DTO_VAR === 'function' && /^\s*class\s+/.test(DTO_VAR.toString())
  }

  function setPagination(val) {
    if (val !== model.pagination.currentPage) {
      model.pagination.setCurrentPage(val)
    }
  }

  async function getAll(payload = {}, errorMessage = '', url = '') {
    if (model.isLoading) {
      return null
    }
    model.isLoading = true
    let newPayload = { ...payload }
    if (model.pagination.active) {
      newPayload = { ...newPayload, ...model.pagination.getPayloadData() }
    }
    const { error, data, metadata } = await ApiService.get(url || BASE_URL, newPayload)
    if (error) {
      model.isLoading = false
      toast.error(errorMessage || 'Error cargando el listado')
      return null
    }
    model.list = isValidDTO(DTO) ? data.map((item) => new DTO(item)) : data
    if (metadata) {
      model.pagination = new Pagination({ ...metadata, active: true })
    }
    model.isLoading = false
  }

  /** @type {{
   *  inProcess: Boolean
   *  error: Boolean
   *  data: FormModel
   }} */
  const current = reactive({
    inProcess: false,
    error: false,
    data: {},
  })
  async function getById({ id, params = {}, config = {}, model, errorMessage = '', url = '' } = {}) {
    if (current.inProcess) {
      return null
    }
    current.inProcess = true
    const { error, data } = await ApiService.get(url || `${BASE_URL}/${id}`, params, config)
    current.error = error
    if (error) {
      toast.error(errorMessage || 'Error cargando la información')
      return null
    }
    current.data = isValidDTO(model) ? new model(data) : data
    current.inProcess = false
  }
  async function save(model = new FormModel()) {
    if (model.inProcess) {
      return null
    }
    if (!model.id) {
      return await create({ model })
    } else {
      return await update({ model })
    }
  }
  async function create({ model = new FormModel(), config = {}, errorMessage = '' } = {}) {
    if (model.inProcess) {
      return null
    }
    model.inProcess = true
    const { error, data } = await ApiService.post(BASE_URL, model.getPayload(), config)
    if (error) {
      model.inProcess = false
      toast.error(errorMessage || 'Error intentando guardar')
      return null
    }
    model.inProcess = false
    return { error, data }
  }
  async function update({ model = new FormModel(), config = {}, errorMessage = '' } = {}) {
    if (model.inProcess) {
      return null
    }
    model.inProcess = true
    const { error, data } = await ApiService.put(`${BASE_URL}/${model.id}`, model.getPayload(), config)
    if (error) {
      model.inProcess = false
      toast.error(errorMessage || 'Error intentando actualizar')
      return null
    }
    model.inProcess = false
    return { error, data }
  }
  async function patch({ model = new FormModel(), payload, config = {}, errorMessage = '' } = {}) {
    if (model.inProcess) {
      return null
    }
    model.inProcess = true
    const { error, data } = await ApiService.patch(`${BASE_URL}/${model.id}`, payload, config)
    if (error) {
      toast.error(errorMessage || 'Error intentando actualizar')
    }
    model.inProcess = false
    return { error, data }
  }
  async function remove({ model = new FormModel(), config = {}, errorMessage = '' } = {}) {
    if (current.inProcess) {
      return null
    }
    model.inProcess = true
    const { error, data } = await ApiService.delete(`${BASE_URL}/${model.id}`, config)
    if (error) {
      toast.error(errorMessage || 'Error intentando eliminar')
      return null
    }
    model.inProcess = false
    return { error, data }
  }

  return {
    model,
    current,
    getAll,
    getById,
    save,
    create,
    update,
    remove,
    patch,
    setPagination,
    getItemsAsOptions,
  }
}
