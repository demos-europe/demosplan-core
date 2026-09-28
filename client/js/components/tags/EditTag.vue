<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <slot
    :assignee="assignee"
    :attachmode="attachmode"
    :boilerplate="boilerplate"
    :set-assignee="setAssignee"
    :set-attachmode="setAttachmode"
    :set-boilerplate="setBoilerplate"
  />
</template>

<script>
export default {
  name: 'EditTag',

  props: {
    selectedAssignee: {
      required: false,
      type: Object,
      default: () => ({ id: '', label: '' }),
    },

    selectedAttachmode: {
      required: false,
      type: String,
      default: 'none',
    },

    selectedBoilerplate: {
      required: false,
      type: Object,
      default: () => ({ id: '', label: '' }),
    },
  },

  data () {
    return {
      assignee: this.selectedAssignee,
      attachmode: this.selectedAttachmode,
      boilerplate: this.selectedBoilerplate,
    }
  },

  methods: {
    setAssignee (val) {
      this.assignee = val
    },

    setAttachmode (val) {
      this.attachmode = val
    },

    setBoilerplate (val) {
      this.boilerplate = val
      // Choosing a boilerplate implies the "existing" mode.
      this.attachmode = 'existing'
    },
  },
}
</script>
