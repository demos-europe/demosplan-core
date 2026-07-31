<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :active-action-box-tab="activeActionBoxTab"
      :active-statement="activeStatement"
      :active-tab="activeTab"
      :dp-validate="dpValidate"
      :dp-validate-action="dpValidateAction"
      :is-submitting="isSubmitting"
      :open-statement-modal-from-list="openStatementModalFromList"
      :prefix-class="prefixClass"
      :set-ref="setRef"
      :submit-form="submitForm"
      :toggle-confirm-modal="toggleConfirmModal"
      :toggle-statement-modal="toggleStatementModal"
      :toggle-tabs="toggleTabs"
      :update-statement-and-open-modal="updateStatementAndOpenModal"
    />
  </div>
</template>

<script>
import { addFormHiddenField, removeFormHiddenField } from '../../lib/core/libs/FormActions'
import { dpValidateMixin, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { mapMutations, mapState } from 'vuex'
import { useSlotRefs } from '@DpJs/composables/useSlotRefs'

/*
 * The components the Twig markup uses are registered on the app by the bundle entrypoints, not here:
 * as scoped slot content it's compiled in the app's scope, not in this component's
 */
export default {
  name: 'DpPublicDetailNoMap',

  mixins: [dpValidateMixin, prefixClassMixin],

  props: {
    isMapEnabled: {
      type: Boolean,
      required: false,
      default: false,
    },

    userId: {
      type: String,
      required: true,
    },

    procedureId: {
      type: String,
      required: true,
    },
  },

  setup () {
    const { setRef, slotRefs } = useSlotRefs()

    return { setRef, slotRefs }
  },

  data () {
    return {
      activeTab: '#procedureDetailsDocumentlist',
      isSubmitting: false,
    }
  },

  computed: {
    ...mapState('PublicStatement', [
      'activeActionBoxTab',
      'initForm',
      'statement',
      'unsavedDrafts',
    ]),

    activeStatement () {
      return this.initForm !== JSON.stringify(this.statement)
    },
  },

  methods: {
    ...mapMutations('PublicStatement', ['initialiseStore', 'updateHighlighted', 'updateStatement', 'localStorageName']),

    submitForm (formId, hiddenFieldName) {
      const form = this.$el.querySelector(`[data-dp-validate="${formId}"]`)

      if (hiddenFieldName) {
        addFormHiddenField(form, hiddenFieldName)
      }

      if (this.dpValidate[formId]) {
        this.isSubmitting = true
        form.submit()
        this.isSubmitting = true
        removeFormHiddenField(form)
      }
    },

    toggleConfirmModal () {
      this.slotRefs.confirmModal.toggle()
    },

    toggleStatementModal (updateStatementPayload) {
      this.slotRefs.statementModal.toggleModal(true, updateStatementPayload)
    },

    toggleTabs (tabId) {
      this.activeTab = tabId
    },

    openStatementModalFromList (id, customFields) {
      /*
       * Only set custom fields from list if there are NO unsaved changes
       * If there are unsaved changes, localStorage will restore them
       */
      const hasUnsavedChanges = this.slotRefs.statementModal.unsavedDrafts.includes(id)

      if (!hasUnsavedChanges && customFields?.length > 0) {
        this.slotRefs.statementModal.setCustomFieldsForEditing(customFields)
      }

      this.slotRefs.statementModal.getDraftStatement(id, true, true)
    },

    updateStatementAndOpenModal (updateStatementPayload) {
      this.toggleStatementModal(updateStatementPayload)
      this.updateHighlighted({ key: 'documents', val: false })
      this.updateHighlighted({ key: 'documents', val: true })
    },
  },

  created () {
    this.initialiseStore({ procedureId: this.procedureId, userId: this.userId })
  },

  mounted () {
    const currentHash = window.document.location.hash.split('?')[0]

    if (['#openStatementForm'].includes(currentHash)) {
      this.toggleStatementModal(true, {})
    } else if (['#procedureDetailsMap', '#procedureDetailsDocumentlist', '#procedureDetailsStatementsPublic'].includes(currentHash)) {
      this.toggleTabs(currentHash)
    }
  },
}
</script>
