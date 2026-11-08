<template>
  <div class="AdminView">
    <div class="header">
      <h1>Panel de administración</h1>
      <BaseButton
        type="danger"
        @click="logout"
        >Cerrar sesión</BaseButton
      >
    </div>

    <div class="card">
      <h2>Información de sesión</h2>
      <dl>
        <dt>Usuario</dt>
        <dd>{{ username }}</dd>
        <dt>Rol principal</dt>
        <dd>{{ role }}</dd>
        <dt>Roles asignados</dt>
        <dd>{{ roles.join(', ') }}</dd>
      </dl>
    </div>

    <div class="card">
      <div class="cardHeader">
        <h2>Gestión de usuarios</h2>
        <BaseButton @click="() => userModal.open(new UserModel())">Nuevo usuario</BaseButton>
      </div>
      <UserTable
        :users="users.list"
        :pagination="users.pagination"
        :loading="users.isLoading"
        @edit="(user) => userModal.open(user)"
        @page-change="setPage"
      />
    </div>

    <UserFormModal
      v-if="userModal.isOpen"
      v-model="userModal.data"
      @close="userModal.close"
      @save="submitData"
    />
  </div>
</template>

<script setup>
import { useAdminView } from '@/contexts/admin/composables/AdminView.composable.js'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'
import UserTable from '@/contexts/admin/components/UserTable.vue'
import UserFormModal from '@/contexts/admin/components/UserFormModal.vue'
import UserModel from '@/contexts/admin/models/user.model.js'

const { username, role, roles, users, userModal, submitData, logout, setPage } = useAdminView()
</script>

<style scoped lang="scss">
.AdminView {
  max-width: 960px;
  margin: 0 auto;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;

  .header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    h1 {
      font-family: var(--font-primary);
      color: var(--text-color);
      margin: 0;
    }
  }

  .card {
    background-color: var(--color-neutral-lightest);
    border: 1px solid var(--color-neutral-light);
    border-radius: 3px;
    padding: 1.5rem;

    h2 {
      font-family: var(--font-primary);
      color: var(--color-neutral-darkest);
      margin: 0 0 1rem;
      font-size: 1rem;
    }

    .cardHeader {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;

      h2 {
        margin: 0;
      }
    }

    dl {
      display: grid;
      grid-template-columns: max-content 1fr;
      gap: 0.5rem 1.5rem;
      margin: 0;

      dt {
        font-family: var(--font-primary);
        font-weight: 600;
        color: var(--color-neutral-darker);
      }

      dd {
        font-family: var(--font-primary);
        color: var(--text-color);
        margin: 0;
      }
    }
  }
}
</style>
