<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentaion>
  <!--
    This component provides the spacing and toggle logic for a single action to be used
    within the first step of the ActionStepper component.
  -->
</documentaion>

<template>
  <div class="u-pv space-stack-s">
    <dp-checkbox
      :id="id"
      :checked="checked"
      :label="{
        bold: checked,
        text: label
      }"
      @change="isChecked => $emit('change', isChecked)" />
    <div
      v-if="checked"
      class="u-ml">
      <slot />
    </div>
  </div>
</template>

<script>
import { DpCheckbox } from '@demos-europe/demosplan-ui'

export default {
  name: 'ActionStepperAction',

  components: {
    DpCheckbox
  },

  model: {
    prop: 'checked',
    event: 'change'
  },

  props: {
    checked: {
      type: Boolean,
      required: false,
      default: false
    },

    id: {
      required: true,
      type: String
    },

    label: {
      required: true,
      type: String
    }
  },

  emits: [
    'change'
  ]
}
</script>
