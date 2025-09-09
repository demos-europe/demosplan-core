<template>
  <dp-accordion
    ref="accordion"
    data-cy="toggleForm:toggle"
    :title="title">
    <!-- this is where the form fields go -->
    <slot />

    <slot name="buttons">
      <dp-button-row
        primary
        secondary
        data-cy="toggleForm"
        @primary-action="dpValidateAction(formId, save, false)"
        @secondary-action="abort" />
    </slot>
  </dp-accordion>
</template>

<script>
import { DpAccordion, DpButtonRow, dpValidateMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'ToggleForm',

  components: {
    DpAccordion,
    DpButtonRow
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
      }

      this.$refs.accordion.toggle()
    },

    save () {
      if (this.formId !== '') {
        document.querySelector(`form#${this.formId}`).submit()
      }
    }
  }
}
</script>
