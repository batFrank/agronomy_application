<template>
  <div class="TableContainer">
    <p
      v-show="pagination.totalItems > pagination.pageSize"
      class="paginationInfo"
    >
      Mostrando {{ total }} registros de {{ pagination.totalItems }}
    </p>
    <div class="tableContainer">
      <slot />
    </div>
    <BasePagination
      v-if="pagination?.totalPages > 1"
      :disabled="disabled"
      :pagination="pagination"
      @page-change="(page) => emit('pageChange', page)"
    />
  </div>
</template>

<script setup>
import BasePagination from '@/shared/components/BasePagination.vue'
import { Pagination } from '@/shared/models/pagination.model.js'

const emit = defineEmits(['pageChange'])
defineProps({
  pagination: { type: [Pagination, Object], default: () => ({}) },
  total: { type: Number, default: 0 },
  disabled: { type: Boolean, default: false },
})
</script>

<style scoped lang="scss">
@use '@/contexts/shared/assets/styles/_breakpoints' as breakpoint;
.TableContainer {
  .paginationInfo {
    text-align: right;
    font-weight: bold;
  }

  .tableContainer {
    width: 100%;
    overflow-x: scroll;
    overflow-y: hidden;

    :deep(table) {
      width: 100%;
      border-collapse: collapse;
      * {
        min-width: 0;
      }

      td,
      th {
        border: solid var(--color-neutral-mid-light);
        border-width: 1px 0;
        padding: 0.5rem 1rem;
      }

      th {
        padding: 1rem;
        white-space: nowrap;
      }
    }

    @media #{breakpoint.$sm} {
      max-width: calc(100svw - 4rem);
    }
  }
}
</style>
