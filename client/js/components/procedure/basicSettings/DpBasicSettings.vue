<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import {
  DpButton,
  DpDateRangePicker,
  DpDatetimePicker,
  DpEditor,
  DpInlineNotification,
  DpInput,
  DpMultiselect,
  sortAlphabetically
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import DpEmailList from './DpEmailList'
import ExportSettings from './ExportSettings'

export default {
  name: 'DpBasicSettings',

  components: {
    AddonWrapper,
    AutoSwitchProcedurePhaseForm: () => import(/* webpackChunkName: "auto-switch-procedure-phase-form" */ '@DpJs/components/procedure/basicSettings/AutoSwitchProcedurePhaseForm'),
    DpButton,
    DpDateRangePicker,
    DpDatetimePicker,
    DpEditor,
    DpEmailList,
    DpInlineNotification,
    DpInput,
    DpMultiselect,
    DpProcedureCoordinate: () => import(/* webpackChunkName: "dp-procedure-coordinate" */ './DpProcedureCoordinate'),
    DpUploadFiles: async () => {
      const { DpUploadFiles } = await import('@demos-europe/demosplan-ui')
      return DpUploadFiles
    },
    ExportSettings
  },

  props: {
    authorizedUsersOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    initAgencies: {
      required: false,
      type: Array,
      default: () => []
    },

    initDataInputOrgas: {
      required: false,
      type: Array,
      default: () => []
    },

    initAuthUsers: {
      required: false,
      type: Array,
      default: () => []
    },

    initProcedureCategories: {
      required: false,
      type: Array,
      default: () => []
    },

    initProcedureName: {
      required: false,
      type: String,
      default: ''
    },

    initProcedurePhaseInternal: {
      required: false,
      type: String,
      default: ''
    },

    initProcedurePhasePublic: {
      required: false,
      type: String,
      default: ''
    },

    initSimilarRecommendationProcedures: {
      required: false,
      type: Array,
      default: () => []
    },

    participationPhases: {
      required: false,
      type: Array,
      default: () => []
    },

    procedureExternalDesc: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      selectedAgencies: this.initAgencies,
      selectedDataInputOrgas: this.initDataInputOrgas,
      selectedAuthUsers: this.initAuthUsers,
      selectedProcedureCategories: this.initProcedureCategories,
      selectedInternalPhase: this.initProcedurePhaseInternal,
      selectedPublicPhase: this.initProcedurePhasePublic,
      selectedSimilarRecommendationProcedures: this.initSimilarRecommendationProcedures,
      procedureDescription: this.procedureExternalDesc,
      procedureName: this.initProcedureName
    }
  },

  computed: {
    authUsersOptions () {
      const users = JSON.parse(JSON.stringify(this.authorizedUsersOptions))
      return sortAlphabetically(users, 'name')
    },

    publicPhaseIsInParticipation () {
      return this.participationPhases.includes(this.selectedPublicPhase)
    },

    internalPhaseIsInParticipation () {
      return this.participationPhases.includes(this.selectedInternalPhase)
    }
  },

  methods: {
    selectAllAuthUsers () {
      this.selectedAuthUsers = this.authorizedUsersOptions
    },

    unselectAllAuthUsers () {
      this.selectedAuthUsers = []
    }
  },

  mounted () {
    const users = JSON.parse(JSON.stringify(this.initAuthUsers))
    this.selectedAuthUsers = sortAlphabetically(users, 'name')
  }
}
</script>
