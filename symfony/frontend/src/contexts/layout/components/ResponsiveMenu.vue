<template>
  <div
    v-on-click-outside="closeMenu"
    class="ResponsiveMenu"
    :class="{ stacked: showResponsiveMenu }"
  >
    <Icon
      class="icon"
      :icon="openResponsiveMenu ? 'times' : 'bars'"
      @click="openResponsiveMenu = !openResponsiveMenu"
    />
    <div class="itemsContainer">
      <MenuItem
        v-for="item in MENU_ITEMS"
        v-show="!showResponsiveMenu || openResponsiveMenu"
        :key="item.key"
        :item="item"
        :show-toggle-icon="(showToggle || showResponsiveMenu) && !!item.subItems.length"
        :is-responsive="showResponsiveMenu"
        @click="(link) => openLink(router, link)"
      />
    </div>
    <div></div>
  </div>
</template>

<script setup>
import { inject, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import MenuItem from '@/contexts/layout/components/MenuItem.vue'
import { useRouter } from 'vue-router'
import { openLink } from '@/contexts/shared/utils/misc.utils.js'
const router = useRouter()

const showToggle = ref(false)
const openResponsiveMenu = ref(false)
const showResponsiveMenu = ref(null)
const parent = ref()
const itemsSize = ref(0)

const MENU_ITEMS = inject('menuItems')

watch(showResponsiveMenu, () => {
  if (!showResponsiveMenu.value) {
    nextTick(() => {
      const newValue = recalculateItemSize()
      if (itemsSize.value !== newValue) {
        itemsSize.value = newValue
        handlingResize()
      } else {
        openResponsiveMenu.value = false
      }
    })
  }
})

function handlingResize() {
  showResponsiveMenu.value = itemsSize.value > document.querySelector('.ResponsiveMenu').clientWidth
}
onMounted(() => {
  nextTick(() => {
    itemsSize.value = recalculateItemSize()
    showToggle.value = 'ontouchstart' in document.documentElement
    handlingResize()
  })
  window.addEventListener('resize', () => handlingResize())
})
onUnmounted(() => {
  window.removeEventListener('resize', () => handlingResize())
})
function recalculateItemSize() {
  return 50 + document.querySelector('.ResponsiveMenu .itemsContainer').clientWidth
}

function closeMenu() {
  if (openResponsiveMenu.value) openResponsiveMenu.value = false
}
</script>

<style scoped lang="scss">
.ResponsiveMenu {
  display: flex;
  height: 100%;
  width: 100%;

  .icon {
    display: none;
  }

  .itemsContainer {
    display: flex;
    flex-wrap: nowrap;
    width: fit-content;
    height: 100%;
  }

  &.stacked {
    .icon {
      display: initial;
      font-size: 2rem;
      margin: auto 0;
    }

    .itemsContainer {
      flex-direction: column;
      position: absolute;
      top: 100%;
      background-color: var(--header-bg-color);
      width: auto;
      height: fit-content;
      max-height: 70svh;
      overflow-y: auto;
      box-shadow: var(--header-shadow);

      .MenuItem {
        height: fit-content;
        border: solid var(--header-border-color);
        border-width: 0 0 1px;
      }
    }
  }
}
</style>
