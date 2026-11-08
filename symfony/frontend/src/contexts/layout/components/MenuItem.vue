<template>
  <div
    v-on-click-outside="close"
    class="MenuItem"
    :class="{ responsive: isResponsive, toggle: showToggleIcon, selectedLink }"
    @click="emit('click', item.link)"
  >
    <div class="linkContent">
      <span @click.stop="emit('click', item.link)">
        <Icon
          v-if="!!item.icon"
          :icon="item.icon"
        />
        {{ item.title }}
      </span>
      <Icon
        v-if="showToggleIcon"
        class="icon"
        :icon="openSubMenu ? 'caret-up' : 'caret-down'"
        @click.stop="openSubMenu = !openSubMenu"
      />
    </div>
    <div
      v-if="item.subItems.length"
      class="subItemContainer"
      :class="{ open: openSubMenu }"
    >
      <div
        v-for="subItem in item.subItems"
        :key="subItem.key"
        class="subItems"
        @click.stop="emit('click', subItem.link)"
      >
        {{ subItem.title }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { MenuItemModel } from '@/contexts/layout/models/MenuItem.model.js'
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'

const emit = defineEmits(['click'])
const props = defineProps({
  item: {
    type: [MenuItemModel, Object],
    default: () => new MenuItemModel(),
  },
  showToggleIcon: {
    type: Boolean,
    default: false,
  },
  isResponsive: {
    type: Boolean,
    default: false,
  },
})

const openSubMenu = ref(false)

const route = useRoute()
const selectedLink = computed(() => {
  const link = encodeURI(props.item?.link || '')
  return link && `${route?.path || '--'}/`.includes(`${link}/`)
})

function close() {
  if (props.showToggleIcon && openSubMenu.value) openSubMenu.value = false
}
</script>

<style scoped lang="scss">
.MenuItem {
  display: grid;
  place-items: center;
  height: 100%;
  cursor: pointer;
  position: relative;
  min-width: fit-content;

  .linkContent {
    display: flex;
    align-items: center;

    span {
      display: flex;
      gap: 0.5rem;
    }

    .icon {
      padding: 0.5rem;
      margin-right: 0.5rem;
    }
  }

  span {
    display: grid;
    place-items: center;
    width: fit-content;
    height: 3rem;
    max-width: 250px;
    word-break: break-word;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-align: center;
    user-select: none;

    &::after {
      content: ' ';
      position: absolute;
      width: 0;
      transition: width 0.3s;
      height: 3px;
      top: calc(3rem - 3px);
      background-color: var(--header-hover-line-color);
    }
  }

  .subItemContainer {
    display: none;
    position: absolute;
    flex-direction: column;
    background-color: var(--header-bg-color);
    box-shadow: var(--header-shadow);
    top: 100%;
    width: 100%;
    left: 0;

    .subItems {
      padding: 0.5rem 1rem;
      border: solid var(--header-border-color);
      border-width: 0 0 1px;
      word-break: break-word;

      &:hover {
        background-color: var(--header-hover-bg-color);
      }
    }

    height: fit-content;
    max-height: 60vh;
    overflow-y: auto;
  }

  &:hover {
    &:not(.responsive) {
      background-color: var(--header-hover-bg-color);
    }

    &:not(.toggle) .subItemContainer {
      display: initial;
    }
  }

  &.toggle {
    .open {
      display: initial;
    }
  }

  &:hover:not(.responsive),
  &.selectedLink {
    span {
      &::after {
        width: 90%;
      }
    }
  }

  &.responsive {
    .subItemContainer {
      max-height: initial;
      position: initial;
      top: 0;

      .subItems {
        width: 100%;
      }

      .open {
        box-shadow: none;
      }
    }

    &.selectedLink {
      span {
        &::after {
          width: 100%;
        }
      }
    }
  }
}
</style>
