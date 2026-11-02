<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :boilerplate-group="boilerplateGroup"
      :set-boilerplate-group="setBoilerplateGroup"
    />
    <boilerplate-save-confirm-modal
      ref="saveConfirmModal"
      :count="usageCount"
      :title="boilerplateTitle"
      @confirm="onConfirm"
    />
  </div>
</template>

<script>
import BoilerplateSaveConfirmModal from '@DpJs/components/procedure/admin/BoilerplateSaveConfirmModal'
import { validateForm } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpEditBoilerplate',

  components: {
    BoilerplateSaveConfirmModal,
  },

  props: {
    // Saved title, shown in the confirm modal
    boilerplateTitle: {
      required: false,
      type: String,
      default: '',
    },

    // Saved text, used to detect whether the user changed the text
    originalText: {
      required: false,
      type: String,
      default: '',
    },

    selectedBoilerplateGroup: {
      required: false,
      type: [String, Object],
      default: () => {
        return { id: '', title: '-' }
      },
    },

    // Number of segments that use this boilerplate
    usageCount: {
      required: false,
      type: Number,
      default: 0,
    },
  },

  data () {
    return {
      boilerplateGroup: this.selectedBoilerplateGroup,
      // Lets the click through that is re-triggered after the user confirmed in the modal
      isSaveConfirmed: false,
    }
  },

  methods: {
    getSaveButton () {
      return this.$el.querySelector('[name="saveBoilerplate"]')
    },

    needsConfirmation (form) {
      // Cheap checks first; an invalid form is left to dpValidate, which shows the errors
      return this.usageCount > 0 &&
        this.isTextChanged(document.getElementById('r_text')?.value ?? '') &&
        validateForm(form).valid
    },

    onConfirm () {
      // Click again so dpValidate validates and submits as usual
      this.isSaveConfirmed = true
      this.getSaveButton()?.click()
      this.isSaveConfirmed = false
    },

    onSaveClick (event) {
      const button = event.target.closest('[name="saveBoilerplate"]')

      if (!button || this.isSaveConfirmed || !this.needsConfirmation(button.closest('form'))) {
        return
      }

      event.stopPropagation()
      event.preventDefault()
      this.$refs.saveConfirmModal.toggle()
    },

    isTextChanged (currentText) {
      return this.normalizeText(currentText) !== this.normalizeText(this.originalText)
    },

    // The editor may turn normal spaces into non-breaking spaces; they must not count as a change
    normalizeText (text) {
      return text.replace(/&nbsp;|\u00a0/g, ' ')
    },

    setBoilerplateGroup (val) {
      this.boilerplateGroup = val
    },
  },

  mounted () {
    // Capture phase, so this runs before the click handler dpValidate attaches to the save button
    this.$el.addEventListener('click', this.onSaveClick, true)
  },

  beforeUnmount () {
    this.$el.removeEventListener('click', this.onSaveClick, true)
  },
}
</script>
