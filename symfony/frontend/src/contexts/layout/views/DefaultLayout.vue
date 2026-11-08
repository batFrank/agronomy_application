<template>
  <div id="App">
    <RouterView
      name="header"
      class="header"
    />
    <RouterView class="main" />
    <RouterView
      name="footer"
      class="footer"
    />
  </div>
</template>

<script setup>
import useModalObserver from '@/core/composable/bodyLock.composable.js'

useModalObserver('BaseModal')
</script>

<style lang="scss" scoped>
#App {
  display: flex;
  flex-direction: column;
  min-height: 100dvh;
  position: relative;

  .header {
    position: fixed;
    top: 0;

    & + .main {
      margin-top: 4rem;
    }
  }

  .main {
    flex-grow: 1;
  }
}
</style>
