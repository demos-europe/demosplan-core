<template>
  <dp-badge
    class="inline-block whitespace-nowrap"
    :color="color"
    :text="Translator.trans(status)" />
</template>

<script>
import { DpBadge } from '@demos-europe/demosplan-ui'

const statusMap = {
  new: 'info',
  processing: 'warning',
  completed: 'confirm'
}
export default {
  name: 'StatusBadge',

  components: {
    DpBadge
  },

  props: {
    status: {
      type: String,
      required: true,
      validator: (prop) => ['new', 'processing', 'completed'].includes(prop)
    }
  },

  computed: {
    color () {
      return statusMap[this.status]
    }
  }
}
</script>

