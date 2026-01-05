<template>
  <dp-flyout
    ref="exportFlyout"
    align="left"
    data-cy="exportFlyout"
    :padded="false"
    @close="handleClose"
    @open="handleOpen"
  >
    <template v-slot:trigger>
      <span class="pr-1">
        {{ Translator.trans('export.verb') }}
      </span>
      <i
        class="fa"
        :class="isExpanded ? 'fa-angle-up' : 'fa-angle-down'"
        aria-hidden="true"
      />
    </template>
    <ul class="!py-1">
      <li v-if="docx">
        <button
          class="btn btn--blank o-link--default"
          type="button"
          @click.prevent.stop="handleExport('docx')"
        >
          DOCX
        </button>
      </li>
      <li v-if="csv">
        <button
          class="btn btn--blank o-link--default"
          type="button"
          @click.prevent.stop="handleExport('csv')"
        >
          CSV
        </button>
      </li>
    </ul>
  </dp-flyout>
</template>

<script>
import {
  DpFlyout,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'ExportFlyout',

  components: {
    DpFlyout,
  },

  props: {
    docx: {
      type: Boolean,
      default: false,
    },

    csv: {
      type: Boolean,
      default: false,
    },
  },

  emits: [
    'export',
  ],

  data () {
    return {
      isExpanded: false,
    }
  },

  methods: {
    handleClose () {
      this.isExpanded = false
    },

    handleExport (type) {
      this.$emit('export', type)
      this.handleClose()
      this.$refs.exportFlyout.close()
    },

    handleOpen () {
      this.isExpanded = true
    },
  },
}
</script>
