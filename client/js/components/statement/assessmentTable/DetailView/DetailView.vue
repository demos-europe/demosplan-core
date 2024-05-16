<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- This component is used as a wrapper for statement and cluster detail view -->
</documentation>

<script>
import {
  DpAccordion,
  dpApi,
  DpButton,
  DpContextualHelp,
  DpDatepicker,
  DpMultiselect,
  DpUploadFiles
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters } from 'vuex'
import DetailViewFinalEmailBody from '@DpJs/components/statement/assessmentTable/DetailView/DetailViewFinalEmailBody'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpStatementPublish from '@DpJs/components/statement/statement/DpStatementPublish'
import saveAndReturn from '@DpJs/directives/saveAndReturn'

export default {
  name: 'DpDetailView',

  components: {
    DetailViewFinalEmailBody,
    DpBoilerPlateModal,
    DpButton,
    DpContextualHelp,
    DpDatepicker,
    DpMapModal,
    DpMultiselect,
    DpStatementPublish,
    DpAccordion,
    DpUploadFiles,

    // Only needed in statement detail view
    DpSelectStatementCluster: () => import(/* webpackChunkName: "select-statement-cluster" */ '@DpJs/components/statement/statement/SelectStatementCluster'),

    DpSlidebar: async () => {
      const { DpSlidebar } = await import('@demos-europe/demosplan-ui')
      return DpSlidebar
    },
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    },
    DpVersionHistory: () => import(/* webpackChunkName: "version-history" */ '@DpJs/components/statement/statement/DpVersionHistory'),
    StatementReplySelect: () => import(/* webpackChunkName: "statement-reply-select" */ '@DpJs/components/statement/assessmentTable/StatementReplySelect'),
    StatementVoter: () => import(/* webpackChunkName: "statement-voter" */ '@DpJs/components/statement/voter/StatementVoter')
  },

  directives: {
    'save-and-return': saveAndReturn
  },

  props: {
    // Statement or cluster
    entity: {
      required: true,
      type: String
    },

    externId: {
      required: false,
      type: String,
      default: ''
    },

    initCounties: {
      required: false,
      type: Array,
      default: () => ([])
    },

    initMunicipalities: {
      required: false,
      type: Array,
      default: () => ([])
    },

    initPriorityAreas: {
      required: false,
      type: Array,
      default: () => ([])
    },

    initRecommendation: {
      required: false,
      type: String,
      default: ''
    },

    initTags: {
      required: false,
      type: Array,
      default: () => ([])
    },

    // Only needed in statement detail view
    isCopy: {
      required: false,
      type: Boolean,
      default: false
    },

    procedureId: {
      required: true,
      type: String
    },

    readonly: {
      required: true,
      type: Boolean
    },

    statementId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      busyCopyFromFragments: false,
      currentRecommendation: '',
      selectedCounties: [],
      selectedMunicipalities: [],
      selectedPriorityAreas: [],
      selectedTags: []
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', ['counties', 'municipalities', 'priorityAreas', 'tags'])
  },

  methods: {
    ...mapActions('AssessmentTable', ['applyBaseData']),

    addTagBoilerplate (value) {
      if (hasPermission('area_admin_boilerplates')) {
        const url = Routing.generate('dm_plan_assessment_get_boilerplates_ajax', { tag: value.id, procedure: this.procedureId })
        dpApi.get(url).then(response => {
          if (response.status === 200 && response.data.body !== '') {
            this.currentRecommendation = this.currentRecommendation + '<p>' + response.data.body + '</p>'
          }
        })
      }
    },

    copyRecommendationFromFragments () {
      if (hasPermission('feature_statements_fragment_consideration') && this.readonly === false) {
        const getConsiderationPath = Routing.generate('DemosPlan_statement_fragment_considerations_get_ajax', { procedure: this.procedureId, statementId: this.statementId })

        this.busyCopyFromFragments = true

        return dpApi.post(getConsiderationPath)
          .then(response => {
            if (response.data.code === 200 && response.data.success === true && response.data.body.considerations.length > 0) {
              const newText = response.data.body.considerations.join('')

              this.currentRecommendation += newText

              dplan.notify.notify('confirm', Translator.trans('confirm.statement.considerations.attached', { count: response.data.body.considerations.length }))
            }
          })
          .catch(() => {
            dplan.notify.notify('error', Translator.trans('error.results.loading'))
          })
          .finally(() => {
            this.busyCopyFromFragments = false
          })
      }
    },

    openStatementPublish () {
      this.$refs.statementPublish.toggle(true)
    },

    openStatementVoters () {
      this.$refs.statementVoters.toggle(true)
    },

    sortSelected (type) {
      const area = `selected${type}`
      this[area].sort((a, b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0))
    }
  },

  mounted () {
    this.selectedCounties = this.initCounties
    this.selectedMunicipalities = this.initMunicipalities
    this.selectedPriorityAreas = this.initPriorityAreas
    this.selectedTags = this.initTags
    this.currentRecommendation = this.initRecommendation

    this.applyBaseData([this.procedureId])

    const statementHistoryButton = document.getElementById('statementHistory')
    const clusterHistoryButton = document.getElementById('groupHistory')

    if (this.entity === 'statement' && hasPermission('feature_statement_content_changes_save') && statementHistoryButton) {
      statementHistoryButton.addEventListener('click', (event) => {
        event.preventDefault()
        const externalId = this.isCopy ? Translator.trans('copyof') + ' ' + this.externId : this.externId
        this.$root.$emit('version:history', this.statementId, 'statement', externalId)
        this.$root.$emit('show-slidebar')
      })
    }

    if (this.entity === 'cluster' && hasPermission('feature_statement_content_changes_save') && clusterHistoryButton) {
      clusterHistoryButton.addEventListener('click', (event) => {
        event.preventDefault()
        this.$root.$emit('version:history', this.statementId, 'statement', this.externId)
        this.$root.$emit('show-slidebar')
      })
    }
  }
}
</script>
