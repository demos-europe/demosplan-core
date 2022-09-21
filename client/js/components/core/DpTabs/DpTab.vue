<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <section
    v-show="isActive"
    :id="id"
    role="tabpanel">
    <slot />
  </section>
</template>

<script>
export default {
  name: 'DpTab',

  props: {
    id: {
      type: String,
      required: true
    },

    /**
     * The label is passed to the DpTab child component, but then it is used
     * in the parent component DpTabs.
     */
    label: {
      type: String,
      required: true
    },

    /**
     * A html string may be passed here which is appended to the tab label.
     */
    suffix: {
      type: String,
      required: false,
      default: null
    }
  },

  data: () => ({
    isActive: false
  })
}
</script>
