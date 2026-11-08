<template>
  <div class="PasswordRecoveryView">
    <div class="form">
      <img
        :src="logo"
        class="logo"
        alt="Logo"
      />

      <template v-if="stage === 'recover'">
        <p>Introduce tu email y te enviaremos un enlace para restablecer tu contraseña.</p>
        <BaseInput
          v-model="recoverData.payload.email"
          name="email"
          label="Email"
          :error="recoverData.errors?.email"
        />
        <BaseButton @click="recover">Enviar enlace</BaseButton>
        <RouterLink
          :to="{ name: 'login' }"
          class="link"
          >Volver al inicio de sesión</RouterLink
        >
      </template>

      <template v-else-if="stage === 'success'">
        <p>Si el email está registrado, recibirás en breve un enlace para restablecer tu contraseña.</p>
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
import { usePasswordRecoveryView } from '@/contexts/passwordRecovery/composables/PasswordRecoveryView.composable.js'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'
import BaseInput from '@/contexts/shared/components/BaseInput.vue'

const { logo, stage, recoverData, recover } = usePasswordRecoveryView()
</script>

<style scoped lang="scss">
.PasswordRecoveryView {
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
