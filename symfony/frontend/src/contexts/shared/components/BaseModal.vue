<template>
  <div class="BaseModal">
    <section
      class="modalContent"
      :class="[`size--${size}`]"
    >
      <header>
        <h1>{{ title }}</h1>
        <Icon
          v-if="showCloseButton"
          class="icon"
          :icon="['fas', 'times']"
          @click="emit('close')"
        />
      </header>
      <main>
        <slot />
      </main>
    </section>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'

const emit = defineEmits(['close'])
defineProps({
  title: { type: String, default: '' },
  showCloseButton: { type: Boolean, default: true },
  size: {
    type: String,
    default: 'm',
    validator: (value) => ['s', 'm', 'l'].includes(value),
  },
})

function handleEsc(event) {
  if (event.key === 'Escape') {
    emit('close')
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleEsc)
})
onUnmounted(() => {
  window.removeEventListener('keydown', handleEsc)
})
</script>

<style scoped lang="scss">
@use '@/contexts/shared/assets/styles/_breakpoints' as b;
.BaseModal {
  position: fixed;
  width: 100svw;
  height: 100svh;
  z-index: 9999;
  display: grid;
  place-content: center;
  top: 0;
  left: 0;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(3px);

  .modalContent {
    display: grid;
    grid-template-rows: 4rem auto;
    background-color: var(--color-neutral-lightest);
    width: clamp(300px, 100svw, 800px);
    margin: auto;
    padding: 0 0 1rem;
    overflow: hidden;
    border-radius: 3px;

    header {
      display: grid;
      grid-template-columns: auto 2rem;
      background-color: var(--color-neutral-darker);
      color: var(--color-neutral-lightest);
      padding: 0.5rem 1rem;
      font-size: 0.9rem;

      h1 {
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        margin: auto 0;
      }

      .icon {
        font-size: 1.5rem;
        cursor: pointer;
        margin: auto 0 auto auto;
      }
    }

    main {
      min-height: 100px;
      height: fit-content;
      max-height: min(600px, 70svh);
      overflow-x: auto;
      padding: 1rem 1rem 0;
    }

    &.size--s {
      width: clamp(300px, 100svw, 400px);
    }

    &.size--l {
      width: clamp(300px, 100svw, 1300px);
    }

    @media #{b.$breakpoint-xs} {
      height: 100svh;

      main {
        height: 100%;
      }
    }
  }
}
</style>
