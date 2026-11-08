import useRestModel from '@/core/composable/restModel.composable.js'
import UserModel from '@/contexts/admin/models/user.model.js'
import { validateForm } from '@/core/utils/validator.utils.js'
import { ref } from 'vue'

export function useUsersService() {
  const { model: users, getAll, create, patch } = useRestModel('/admin/users', UserModel)

  async function fetchUsers() {
    users.pagination.active = false
    await getAll({ page: currentPage.value })
  }

  async function saveUser(model = new UserModel()) {
    if (model.inProcess || validateForm(model)) {
      return null
    }
    if (!model.id) {
      return await create({ model })
    } else {
      return await patch({ model, payload: model.getPayload() })
    }
  }

  const currentPage = ref(1)
  async function setPage(pageNumber = 1) {
    if (pageNumber !== currentPage.value) {
      await fetchUsers()
    }
  }

  return {
    users,
    setPage,
    fetchUsers,
    saveUser,
  }
}
