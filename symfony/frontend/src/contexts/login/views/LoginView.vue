<template>
  <div class="LoginView">
    <div class="form">
      <img
        :src="logo"
        class="logo"
        alt="Vite logo"
      />
      <p>Iniciar sesión</p>
      <BaseInput
        v-model="loginData.payload.email"
        name="email"
        label="Usuario"
        :error="loginData.errors?.email"
      />
      <BaseInput
        v-model="loginData.payload.password"
        name="pass"
        label="Contraseña"
        type="password"
        :error="loginData.errors?.password"
      />
      <BaseButton @click="login">Iniciar sesión</BaseButton>
      <RouterLink
        v-if="showPasswordRecovery"
        :to="{ name: 'password-recovery' }"
        class="link"
        >¿Olvidaste tu contraseña?</RouterLink
      >
    </div>
  </div>
</template>

<script setup>
import { useLoginView } from '@/contexts/login/composables/LoginView.composable.js'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'
import BaseInput from '@/contexts/shared/components/BaseInput.vue'

const { logo, login, loginData } = useLoginView()
const showPasswordRecovery = window.$settings.AUTH?.PASSWORD_RECOVERY ?? false
</script>

<style scoped lang="scss">
.LoginView {
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

    .BaseButton {
      margin-top: 1rem;
    }

    .BaseInput {
      :deep(input) {
        color: var(--color-neutral-darkest);
        background-color: var(--color-neutral-lightest);
      }
    }

    p {
      margin: auto;
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
