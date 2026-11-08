<template>
  <div class="HomeView">
    <h1>Hello world!</h1>
    <BaseButton
      type="danger"
      @click="logout"
    >
      Cerrar sesión
    </BaseButton>
    <BaseButton
      type="warning"
      @click="throwError"
    >
      Forzar error (Sentry)
    </BaseButton>
  </div>
</template>

<script setup>
import { useHomeView } from '@/contexts/home/composables/HomeView.composable.js'
import BaseButton from '@/contexts/shared/components/BaseButton.vue'

const { logout } = useHomeView()

function throwError() {
  throw new Error('Sentry Test Error: Manual trigger from easyLearning client')
}
</script>

<style scoped lang="scss">
.HomeView {
  max-width: 1280px;
  margin: 0 auto;
  padding: 2rem;
  text-align: center;
}
</style>
