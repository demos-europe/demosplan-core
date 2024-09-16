<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { addFormHiddenField, removeFormHiddenField } from '../../lib/core/libs/FormActions'
import { DpButton, DpContextualHelp, DpModal, dpValidateMixin, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { mapMutations, mapState } from 'vuex'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import DpPublicSurvey from '@DpJs/components/procedure/survey/DpPublicSurvey'
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal'

export default {
  name: 'DpPublicDetailNoMap',

  components: {
    StatementModal,
    DpButton,
    DpContextualHelp,
    DpModal,
    DpPublicSurvey,
    DpPublicStatementList,
    DpMapModal: () => import('@DpJs/components/statement/assessmentTable/DpMapModal'),
    DpSelect: async () => {
      const { DpSelect } = await import('@demos-europe/demosplan-ui')
      return DpSelect
    },
    DpVideoPlayer: async () => {
      const { DpVideoPlayer } = await import('@demos-europe/demosplan-ui')
      return DpVideoPlayer
    },
    ElementsList: () => import('@DpJs/components/document/ElementsList')
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
      consultationTokenInputField: '',
      isSubmitting: false
    }
  },

  computed: {
    ...mapState('PublicStatement', [
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
