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
    :description="description"
    :set-assignee="setAssignee"
    :set-attachmode="setAttachmode"
    :set-boilerplate="setBoilerplate"
    :handle-submit="handleSubmit"
    :set-description="setDescription"
  />

  <dp-confirm-dialog
    ref="confirmDialog"
    :confirm-button-text="Translator.trans('tag.description.change.confirm.button')"
    content-header-classes="text-h2"
    :decline-button-text="Translator.trans('abort')"
    :header="Translator.trans('tag.description.change.confirm.header')"
    icon="warning"
    :message="Translator.trans('tag.description.change.confirm.message')"
  />
</template>

<script>
import { DpConfirmDialog } from '@demos-europe/demosplan-ui'

export default {
  name: 'EditTag',

  components: {
    DpConfirmDialog,
  },

  props: {
    isProcedureTemplate: {
      required: false,
      type: Boolean,
      default: false,
    },

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

    selectedDescription: {
      required: false,
      type: String,
      default: '',
    },
  },

  data () {
    return {
      assignee: this.selectedAssignee,
      attachmode: this.selectedAttachmode,
      boilerplate: this.selectedBoilerplate,
      description: this.selectedDescription,
    }
  },

  computed: {
    // Changing or deleting an existing description needs confirmation, except in the procedure template
    needsConfirmation () {
      if (this.isProcedureTemplate || this.selectedDescription === '') {
        return false
      }

      return this.description !== this.selectedDescription
    },
  },

  methods: {
    async handleSubmit (event) {
      // The event target is gone after the await, so the form has to be read first
      const form = event.currentTarget.closest('form')

      if (this.needsConfirmation && !(await this.$refs.confirmDialog.open())) {
        return
      }

      // Unlike submit(), requestSubmit() still runs the form validation
      form.requestSubmit()
    },

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

    setDescription (val) {
      this.description = val
    },
  },
}
</script>
