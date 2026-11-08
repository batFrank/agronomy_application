<template>
  <BaseModal
    :title="model.id ? 'Editar usuario' : 'Nuevo usuario'"
    @close="emit('close')"
  >
    <form
      class="UserFormModal"
      @submit.prevent="emit('save')"
    >
      <BaseInput
        v-model="model.email"
        name="email"
        label="Email"
        :disabled="model.inProcess"
        :error="model.errors?.email"
        required
      />

      <template v-if="!model.id">
        <BaseInput
          v-model="model.password"
          name="password"
          label="Contraseña"
          type="password"
          :disabled="model.inProcess"
          :error="model.errors?.password"
          required
        />
        <BaseInput
          v-model="model.password_confirmation"
          name="password_confirmation"
          label="Confirmar contraseña"
          type="password"
          :disabled="model.inProcess"
          :error="model.errors?.password_confirmation"
          required
        />
      </template>

      <BaseSelect
        v-model="model.roles"
        name="roles"
        label="Roles"
        :options="roleOptions"
        :multiselect="true"
        :disabled="model.inProcess"
        :error="model.errors?.roles"
        required
      />

      <div class="actions">
        <BaseButton
          :disabled="model.inProcess"
          @click="emit('close')"
        >
          Cancelar
        </BaseButton>
        <BaseButton
          :disabled="model.inProcess"
          type="primary"
          @click="emit('save')"
        >
          {{ model.id ? 'Guardar cambios' : 'Crear usuario' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import UserModel from '@/contexts/admin/models/user.model.js'
import { ROLES } from '@/core/constants/roles.constants.js'
import BaseModal from '@/contexts/shared/components/BaseModal.vue'
import BaseInput from '@/contexts/shared/components/BaseInput.vue'
import BaseSelect from '@/contexts/shared/components/BaseSelect.vue'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'

const emit = defineEmits(['close', 'save'])
const model = defineModel({ type: UserModel, default: new UserModel() })

const roleOptions = [
  { value: ROLES.ADMIN, label: 'Admin' },
  { value: ROLES.MANAGER, label: 'Manager' },
  { value: ROLES.USER, label: 'Usuario' },
]
</script>

<style scoped lang="scss">
.UserFormModal {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding-bottom: 1rem;

  .actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 0.5rem;
  }
}
</style>
