<template>
  <div class="PasswordResetView">
    <div class="form">
      <img
        :src="logo"
        class="logo"
        alt="Logo"
      />

      <template v-if="stage === 'reset'">
        <p>Introduce tu nueva contraseña.</p>
        <BaseInput
          v-model="resetData.payload.new_password"
          name="new_password"
          label="Nueva contraseña"
          type="password"
          :error="resetData.errors?.new_password"
        />
        <BaseButton @click="reset">Restablecer contraseña</BaseButton>
      </template>

      <template v-else-if="stage === 'invalid'">
        <p>El enlace no es válido o ha expirado.</p>
        <RouterLink
          :to="{ name: 'login' }"
          class="link"
          >Volver al inicio de sesión</RouterLink
        >
      </template>
    </div>
  </div>
</template>

<script setup>
import { usePasswordResetView } from '@/contexts/passwordReset/composables/PasswordResetView.composable.js'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'
import BaseInput from '@/contexts/shared/components/BaseInput.vue'

const { logo, stage, resetData, reset } = usePasswordResetView()
</script>

<style scoped lang="scss">
.PasswordResetView {
  display: grid;
  place-content: center;
  width: 100svw;
  min-height: calc(100svh - var(--header-height) * 2);
  background-color: var(--color-neutral-darker);

  .form {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    width: 300px;
    margin: auto;
    color: var(--color-neutral-darkest);
    border: 1px solid var(--color-neutral-light);
    background-color: var(--color-neutral-lightest);
    border-radius: 3px;
    padding: 2rem 1rem;

    .logo {
      width: auto;
      max-width: 200px;
      height: calc(var(--header-height) - 1rem);
      object-fit: contain;
      object-position: center;
    }

    p {
      margin: 0;
      text-align: center;
      font-family: var(--font-primary);
      color: var(--text-color);
    }

    .BaseInput {
      width: 100%;

      :deep(input) {
        color: var(--color-neutral-darkest);
        background-color: var(--color-neutral-lightest);
      }
    }

    .BaseButton {
      margin-top: 0.5rem;
    }

    .link {
      font-family: var(--font-primary);
      font-size: 0.85rem;
      color: var(--link-color);
      text-decoration: none;

      &:hover {
        text-decoration: underline;
      }
    }
  }
}
</style>
