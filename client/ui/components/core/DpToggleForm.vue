<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-accordion
    ref="accordion"
    :title="title">
    <!-- this is where the form fields go -->
    <slot />

    <slot name="buttons">
      <dp-button-row
        primary
        secondary
        @primary-action="dpValidateAction(formId, save, false)"
        @secondary-action="abort" />
    </slot>
  </dp-accordion>
</template>

<script>
import DpAccordion from './DpAccordion'
import { dpValidateMixin } from 'demosplan-utils/mixins'

export default {
  name: 'DpToggleForm',

  components: {
    DpAccordion,
    DpButtonRow: () => import('./DpButtonRow')
  },

  mixins: [dpValidateMixin],

  props: {
    formId: {
      type: String,
      default: ''
    },

    title: {
      type: String,
      default: ''
    }
  },

  methods: {
    abort () {
      if (this.formId !== '') {
        document.querySelector(`form#${this.formId}`).reset()
      } else {
        this.$emit('form-abort')
      }
      this.$refs.accordion.toggle()
    },

    save () {
      if (this.formId !== '') {
        document.querySelector(`form#${this.formId}`).submit()
      } else {
        this.$emit('form-save')
      }
    }
  }
}
</script>
