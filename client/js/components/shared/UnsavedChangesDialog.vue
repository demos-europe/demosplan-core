<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-confirm-dialog
    id="unsavedChangesDialog"
    ref="unsavedChangesDialog"
    :confirm-button-text="Translator.trans('save.leave')"
    content-header-classes="font-size-h2 border--none pb-4"
    :decline-button-text="Translator.trans('edit.continue')"
    :header="Translator.trans('unsaved.changes')"
    :message="Translator.trans('warning.unsaved.changes')"
    :tertiary-button-text="Translator.trans('change.discard')"
    secondary-btn-variant="outline"
    @confirmed="handleConfirm"
    @tertiary-action="handleTertiaryAction"
  />
</template>

<script>
import { DpConfirmDialog } from '@demos-europe/demosplan-ui'

export default {
  name: 'UnsavedChangesDialog',

  components: {
    DpConfirmDialog,
  },

  data () {
    return {
      currentResolver: null,
      handleUnsavedChangesDialogShow: null,
    }
  },

  methods: {
    show () {
      return new Promise(resolve => {
        this.currentResolver = resolve
        this.$refs.unsavedChangesDialog.open()
      })
    },

    resolveDialog (action) {
      if (!this.currentResolver) {
        return
      }

      this.currentResolver(action)
      this.currentResolver = null
    },

    handleConfirm (isConfirmed) {
      this.resolveDialog(isConfirmed ? 'save' : 'cancel')
    },

    handleTertiaryAction () {
      this.resolveDialog('discard')
    },
  },

  mounted () {
    this.handleUnsavedChangesDialogShow = async () => {
      const result = await this.show()

      document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
        detail: { action: result },
      }))
    }

    document.addEventListener('unsaved-changes-dialog:show', this.handleUnsavedChangesDialogShow)
  },

  beforeUnmount () {
    document.removeEventListener('unsaved-changes-dialog:show', this.handleUnsavedChangesDialogShow)
  },
}
</script>
