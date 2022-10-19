<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { addFormHiddenField, removeFormHiddenField } from '@DpJs/lib/FormActions'
import { mapMutations, mapState } from 'vuex'
import { DpButton } from 'demosplan-ui/components'
import DpModal from '@DpJs/components/core/DpModal'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import DpPublicSurvey from '@DemosPlanProcedureBundle/components/survey/DpPublicSurvey'
import dpValidateMixin from '@DpJs/lib/validation/dpValidateMixin'
import { prefixClassMixin } from 'demosplan-ui/mixins'
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal'

export default {
  name: 'DpPublicDetailNoMap',

  components: {
    StatementModal,
    DpButton,
    DpModal,
    DpPublicSurvey,
    DpPublicStatementList,
    DpMapModal: () => import('@DpJs/components/statement/assessmentTable/DpMapModal'),
    DpSelect: () => import('@DpJs/components/core/form/DpSelect'),
    DpVideoPlayer: () => import('@DpJs/components/core/DpVideoPlayer'),
    ElementsList: () => import('@DemosPlanDocumentBundle/components/ElementsList')
  },

  mixins: [dpValidateMixin, prefixClassMixin],

  props: {
    isMapEnabled: {
      type: Boolean,
      required: false,
      default: false
    },

    userId: {
      type: String,
      required: true
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      activeTab: '#procedureDetailsDocumentlist',
      consultationTokenInputField: ''
    }
  },

  computed: {
    ...mapState('publicStatement', [
      'activeActionBoxTab',
      'initForm',
      'statement',
      'unsavedDrafts'
    ]),

    activeStatement () {
      return this.initForm !== JSON.stringify(this.statement)
    }
  },

  methods: {
    ...mapMutations('publicStatement', ['initialiseStore', 'updateHighlighted', 'updateStatement', 'localStorageName']),

    submitForm (formId, hiddenFieldName) {
      const form = this.$el.querySelector(`[data-dp-validate="${formId}"]`)
      if (hiddenFieldName) {
        addFormHiddenField(form, hiddenFieldName)
      }

      if (this.dpValidate[formId]) {
        form.submit()
        removeFormHiddenField(form)
      }
    },

    toggleConfirmModal () {
      this.$refs.confirmModal.toggle()
    },

    toggleStatementModal (updateStatementPayload) {
      this.$refs.statementModal.toggleModal(true, updateStatementPayload)
    },

    toggleTabs (tabId) {
      this.activeTab = tabId
    },

    updateStatementAndOpenModal (updateStatementPayload) {
      this.toggleStatementModal(updateStatementPayload)
      this.updateHighlighted({ key: 'documents', val: false })
      this.updateHighlighted({ key: 'documents', val: true })
    }
  },

  created () {
    this.initialiseStore({ procedureId: this.procedureId, userId: this.userId })
  },

  mounted () {
    const currentHash = window.document.location.hash.split('?')[0]
    if (['#openStatementForm'].includes(currentHash)) {
      this.toggleStatementModal(true, {})
    } else if (['#procedureDetailsMap', '#procedureDetailsDocumentlist', '#procedureDetailsStatementsPublic', '#procedureDetailsSurvey'].includes(currentHash)) {
      this.toggleTabs(currentHash)
    }

    this.$on('open-statement-modal-from-list', (id) => {
      this.$refs.statementModal.getDraftStatement(id, true)
    })
  }
}
</script>
