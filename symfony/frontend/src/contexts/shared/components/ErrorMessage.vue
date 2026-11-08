<template>
  <div
    v-if="message"
    class="ErrorMessage"
  >
    {{ message }}
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: '' },
})
</script>

<style scoped lang="scss">
.ErrorMessage {
  color: var(--error-color);
  font-size: 0.8rem;
}
</style>
