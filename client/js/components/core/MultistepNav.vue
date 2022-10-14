<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--

  The MultistepNav generates a horizontal Bar with n steps
  the steps can be decorated with an fa-icon.

  on click of an "old" step the event change-step emits the new step index.

  -->
  <usage>
    <multistep-nav
      @change-step="val => step = val"
      :active-step="step"
      :steps="[{
           label: 'my text here',
           icon: 'fa-some-icon',
           title: 'In this step you can do this'
          }, {
           label: 'this is the second step',
           icon: 'fa-some-other-icon',
           title: 'In this step you can do that'
          }, {
           label: 'and so on'
          }]" />
  </usage>
</documentation>

<template>
  <nav :class="prefixClass('c-multistep')">
    <button
      v-for="(step, idx) in steps"
      :key="`step_${idx}`"
      :disabled="idx > activeStep"
      @click="changeStep(idx)"
      class="btn--blank"
      :aria-label="step.title ? Translator.trans(step.title) : Translator.trans(step.label)"
      :class="[
        prefixClass('c-multistep__step'),
        idx === activeStep ? prefixClass('is-active') : '',
        idx > activeStep ? prefixClass('is-disabled') : ''
      ]">
      <span>
        <i
          v-if="step.icon"
          aria-hidden="true"
          :class="[prefixClass(step.icon), prefixClass('fa u-mr-0_25')]" />
        {{ Translator.trans(step.label) }}
      </span>
    </button>
  </nav>
</template>

<script>
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'MultistepNav',

  mixins: [prefixClassMixin],

  props: {
    activeStep: {
      type: Number,
      required: false,
      default: 0
    },

    steps: {
      type: Array,
      required: true
    }
  },

  methods: {
    changeStep (val) {
      this.$emit('change-step', val)
    }
  }
}
</script>
