<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    This is a wrapper for the dp-sliding-pagination component that adds a itemsPerPage Box
  -->
  <usage>
    <dp-pager
      current-page="pagination.current_page"
      total-pages="pagination.total_pages"
      total-items="pagination.total"
      per-page="pagination.count"
      limits="pagination.limits"
      @page-change="handlePageChange"
      @size-change="handleSizeChange"
    ></dp-pager>
  </usage>
</documentation>

<template>
  <div class="c-pager__dropdown">
    <label
      class="c-pager__dropdown-label u-m-0 u-p-0 weight--normal display--inline-block"
      :aria-label="Translator.trans('pager.amount.multiple.label', { results: totalItems, items: Translator.trans('pager.amount.multiple.items') })">
      <span aria-hidden="true">
        {{ Translator.trans('pager.amount.multiple.show') }}
      </span>
      <div
        class="display--inline-block"
        v-if="totalItems > Math.min(...limits)">
        <dp-multiselect
          class="display--inline-block"
          v-model="itemsPerPage"
          @select="handleSizeChange"
          :searchable="false"
          selected-label=""
          :options="filteredLimits" />
      </div>
      <span v-else>{{ totalItems }}</span>
      <span aria-hidden="true">
        {{ Translator.trans('pager.amount.multiple.of') }}
        <span data-cy="totalItems">{{ totalItems }}</span>
        {{ Translator.trans('pager.amount.multiple.items') }}
      </span>
    </label>
    <dp-sliding-pagination
      v-if="totalItems > Math.min(...limits)"
      class="display--inline-block"
      :current="currentPage"
      :total="totalPages || 1"
      @page-change="handlePageChange" />
  </div>
</template>

<script>
import DpMultiselect from './form/DpMultiselect'
import DpSlidingPagination from './DpSlidingPagination'

export default {
  name: 'DpPager',

  components: {
    DpSlidingPagination,
    DpMultiselect
  },

  props: {
    currentPage: {
      required: false,
      type: Number,
      default: 1
    },

    totalItems: {
      required: false,
      type: Number,
      default: 1
    },

    totalPages: {
      required: false,
      type: Number,
      default: 1
    },

    perPage: {
      required: false,
      type: Number,
      default: 1
    },

    limits: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      itemsPerPage: this.perPage <= this.totalItems ? this.perPage : this.totalItems
    }
  },

  computed: {
    filteredLimits () {
      const filtered = this.limits.filter(limit => limit <= this.totalItems)

      if (filtered.length < this.limits.length && this.totalItems > filtered[filtered.length - 1]) {
        filtered.push(this.totalItems)
      }

      if (filtered.includes(this.itemsPerPage) === false) {
        filtered.push(this.itemsPerPage)
      }
      filtered.sort((a, b) => a - b)

      return filtered
    }
  },

  methods: {
    handlePageChange (newPage) {
      this.$emit('page-change', newPage)
    },

    handleSizeChange (selectedOption) {
      this.$emit('size-change', parseInt(selectedOption))
    }
  }
}
</script>
