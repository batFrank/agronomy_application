<template>
  <div class="UserTable">
    <div
      v-if="loading"
      class="state"
    >
      Cargando...
    </div>

    <div
      v-else-if="!users.length"
      class="state"
    >
      No hay usuarios registrados.
    </div>

    <template v-else>
      <table>
        <thead>
          <tr>
            <th>Email</th>
            <th>Rol</th>
            <th>Creado</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
          >
            <td>{{ user.email }}</td>
            <td>{{ primaryRole(user.roles) }}</td>
            <td>{{ formatDate(user.createdAt) }}</td>
            <td class="actions">
              <BaseButton @click="emit('edit', user)">Editar</BaseButton>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="pagination.totalPages > 1"
        class="pagination"
      >
        <BaseButton
          :disabled="pagination.currentPage <= 1"
          @click="emit('page-change', pagination.getPrevPage())"
        >
          ← Anterior
        </BaseButton>
        <span>{{ pagination.currentPage }} / {{ pagination.totalPages }}</span>
        <BaseButton
          :disabled="pagination.currentPage >= pagination.totalPages"
          @click="emit('page-change', pagination.getNextPage())"
        >
          Siguiente →
        </BaseButton>
      </div>
    </template>
  </div>
</template>

<script setup>
import BaseButton from '@/contexts/shared/components/BaseButton.vue'
import { ROLE_HIERARCHY } from '@/core/constants/roles.constants.js'
import { Pagination } from '@/core/models/pagination.model.js'

defineProps({
  users: { type: Array, default: () => [] },
  pagination: { type: [Pagination, Object], default: () => ({}) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['edit', 'page-change'])

function primaryRole(roles = []) {
  return ROLE_HIERARCHY.find((r) => roles.includes(r)) ?? '-'
}

function formatDate(iso) {
  if (!iso) return '-'
  return new Date(iso).toLocaleDateString('es-ES')
}
</script>

<style scoped lang="scss">
.UserTable {
  .state {
    padding: 2rem 0;
    text-align: center;
    color: var(--color-neutral-darker);
    font-family: var(--font-primary);
  }

  table {
    width: 100%;
    border-collapse: collapse;

    th,
    td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid var(--color-neutral-light);
      font-family: var(--font-primary);
      font-size: 0.9rem;
    }

    th {
      font-weight: 600;
      color: var(--color-neutral-darker);
      white-space: nowrap;
    }

    td {
      color: var(--text-color);
    }

    .actions {
      text-align: right;
      width: 1%;
      white-space: nowrap;
    }
  }

  .pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;

    span {
      font-family: var(--font-primary);
      font-size: 0.9rem;
      color: var(--text-color);
    }
  }
}
</style>
