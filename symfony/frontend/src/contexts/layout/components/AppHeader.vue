<template>
  <div class="AppHeader">
    <div class="container">
      <img
        :src="logo"
        class="logo"
        alt="Vite logo"
      />
      <ResponsiveMenu />
      <div></div>
    </div>
  </div>
</template>

<script setup>
import { LoaderService } from '@/core/services/loader.service.js'
import ResponsiveMenu from '@/contexts/layout/components/ResponsiveMenu.vue'
const logo = LoaderService.importImage(`images/logo`)
</script>

<style scoped lang="scss">
.AppHeader {
  position: sticky;
  top: 0;
  width: 100svw;
  z-index: 5;
  padding: 0.5rem 1rem 0;
  background-color: var(--header-bg-color);
  height: var(--header-height);
  color: var(--header-text-color);
  box-shadow: var(--header-shadow);

  .container {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 1rem;
    width: 100%;
    height: calc(var(--header-height) - 1rem);
  }

  .logo {
    width: auto;
    max-width: 200px;
    height: calc(var(--header-height) - 1rem);
    object-fit: contain;
    object-position: center;
  }
}
</style>
