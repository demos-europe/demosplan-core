<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button class="c-data-table__wrap-trigger o-link--default btn--blank">
    <dp-icon
      aria-hidden="true"
      :aria-label="ariaLabel"
      :icon="icon"
      size="small" />
  </button>
</template>

<script>
import { DpIcon } from 'demosplan-ui/components'

export default {
  name: 'DpWrapTrigger',

  components: {
    DpIcon
  },

  props: {
    expanded: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  computed: {
    ariaLabel () {
      return Translator.trans(this.expanded ? 'aria.collapse' : 'aria.expand')
    },

    icon () {
      return this.expanded ? 'chevron-up' : 'chevron-down'
    }
  }
}
</script>
