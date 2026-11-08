<template>
  <div class="BaseTabs">
    <div class="tabList">
      <div
        v-for="tab in tabs"
        :key="tab.key"
        class="tab"
        :class="{ active: tab.key === current.key }"
        @click="emitChange(tab)"
      >
        <Icon
          v-if="tab.icon"
          :icon="tab.icon"
        />
        <small>
          <b>{{ tab.title }}</b>
        </small>
        <span :class="['notification', notifications[tab.key]?.style]">
          {{ notifications[tab.key]?.value }}
        </span>
      </div>
    </div>
    <div class="tabsContent">
      <slot />
    </div>
  </div>
</template>

<script setup>
import TabModel from '@/core/models/tab.model.js'

const emit = defineEmits(['change'])
const props = defineProps({
  tabs: { type: Array, default: () => [] },
  notifications: { type: Object, default: () => ({}) },
  notificationColor: { type: String, default: 'danger', validator: (value) => ['danger', 'success'].includes(value) },
  current: { type: [Object, TabModel], default: () => new TabModel() },
  disabled: { type: Boolean, default: false },
})

function emitChange(tab) {
  if (props.disabled || props.current.key === tab.key) {
    return null
  }
  emit('change', tab)
}
</script>

<style scoped lang="scss">
@use '@/shared/assets/styles/_breakpoints' as breakpoint;
.BaseTabs {
  display: grid;
  grid-template-rows: auto 1fr;
  width: 100%;

  .tabList {
    display: flex;
    gap: 3px;
    padding-inline: 1rem;
    user-select: none;
    overflow-y: hidden;
    overflow-x: auto;
    scrollbar-width: thin;
    margin-bottom: -1px;
    width: 100%;
    max-width: calc(100svw - 13rem);

    @media #{breakpoint.$sm} {
      max-width: calc(100svw - 4rem);
    }

    .tab {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.35rem 0.75rem;
      border: 1px solid var(--color-neutral-mid-dark);
      margin-bottom: -1px;
      border-radius: 7px 7px 0 0;
      cursor: pointer;
      text-wrap: nowrap;
      z-index: 0;
      background-color: var(--color-neutral-lighter);

      &.active {
        border-width: 1px 1px 0;
      }

      .notification {
        padding: 0.15rem 0.45rem;
        font-size: 0.75rem;
        border-radius: 0.5rem;
        z-index: 1;

        &.danger {
          color: var(--color-neutral-lightest);
          background-color: var(--color-danger);
          &:hover {
            background-color: var(--color-danger-hover);
          }
        }

        &.success {
          color: var(--color-neutral-darkest);
          background-color: var(--color-success);
          &:hover {
            background-color: var(--color-success-hover);
          }
        }

        &:empty {
          display: none;
        }
      }
    }
  }

  .tabsContent,
  .tab.active {
    background-color: var(--color-neutral-lightest);
  }

  .tabsContent {
    border: solid var(--color-neutral-mid);
    border-width: 1px 0 0;
  }
}
</style>
