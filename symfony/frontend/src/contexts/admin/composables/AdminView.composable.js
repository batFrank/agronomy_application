import { inject, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/contexts/shared/stores/auth.store.js'
import { useUsersService } from '@/contexts/admin/services/users.service.js'
import { useModal } from '@/core/composable/modal.composable.js'

export function useAdminView() {
  /** @type CurrentUserModel */
  const currentUser = inject('currentUser')
  const router = useRouter()
  const submittingLogout = ref(false)

  async function logout() {
    const { logout } = useAuthStore()
    if (submittingLogout.value) return
    submittingLogout.value = true
    await logout()
    if (!currentUser.isAuth) router?.push({ name: window.$settings.PAGES.ENTRY_PAGE }).catch()
    submittingLogout.value = false
  }

  const userModal = useModal()

  const { users, fetchUsers, setPage, saveUser } = useUsersService()
  async function submitData() {
    const result = await saveUser(userModal.data)
    if (result !== null) {
      userModal.close()
      await fetchUsers()
    }
  }

  onMounted(() => fetchUsers())

  return {
    username: currentUser.username,
    role: currentUser.role,
    roles: currentUser.roles,
    users,
    userModal,
    submitData,
    logout,
    setPage,
  }
}
