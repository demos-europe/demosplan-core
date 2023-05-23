<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :boilerplate-group="boilerplateGroup"
      :set-boilerplate-group="setBoilerplateGroup" />
  </div>
</template>

<script>
export default {
  name: 'DpEditBoilerplate',

  props: {
    selectedBoilerplateGroup: {
      required: false,
      type: [String, Object],
      default: () => { return { id: '', title: '-' } }
    }
  },

  data () {
    return {
      boilerplateGroup: this.selectedBoilerplateGroup
    }
  },

  methods: {
    setBoilerplateGroup (val) {
      this.boilerplateGroup = val
    }
  }
}
</script>
