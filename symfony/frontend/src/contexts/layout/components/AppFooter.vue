<template>
  <div class="AppFooter">
    <div class="container">
      <span
        class="mainLink"
        @click="openLink(router, 'https://gestionet.net/')"
        >&#169; {{ currentYear }} Gestionet</span
      >
      <div class="itemContainer">
        <div
          v-for="item in FOOTER_ITEMS"
          :key="item.key"
          class="linkContainer"
        >
          <span
            class="link"
            @click="openLink(router, item.link)"
            >{{ item.title }}</span
          >
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import { openLink } from '@/contexts/shared/utils/misc.utils.js'
import { useRouter } from 'vue-router'
const router = useRouter()

const FOOTER_ITEMS = inject('footerItems')
const currentYear = computed(() => new Date().getFullYear())
</script>

<style scoped lang="scss">
.AppFooter {
  padding: 1rem;
  background-color: var(--footer-bg-color);
  color: var(--footer-text-color);

  .container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem 1rem;
  }

  .itemContainer {
    display: flex;
    flex-wrap: wrap;

    .linkContainer:not(:first-child):before {
      content: '|';
      padding-inline: 0.5rem;
    }
  }

  .mainLink,
  .link {
    cursor: pointer;
    user-select: none;
  }

  .link {
    color: var(--footer-link-color);
    word-break: break-word;
    white-space: nowrap;

    &:hover {
      text-decoration: underline;
    }
  }
}
</style>
