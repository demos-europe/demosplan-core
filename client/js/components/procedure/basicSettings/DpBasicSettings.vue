<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import {
  checkResponse,
  dpApi,
  DpButton,
  DpContextualHelp,
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
import ParticipationPhases from './ParticipationPhases'

export default {
  name: 'DpBasicSettings',

  components: {
    AddonWrapper,
    AutoSwitchProcedurePhaseForm: () => import(/* webpackChunkName: "auto-switch-procedure-phase-form" */ '@DpJs/components/procedure/basicSettings/AutoSwitchProcedurePhaseForm'),
    DpButton,
    DpContextualHelp,
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
    ExportSettings,
    ParticipationPhases
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

    plisId: {
      required: false,
      type: String,
      default: ''
    },

    procedureExternalDesc: {
      required: false,
      type: String,
      default: ''
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      addonPayload: null,
      isLoadingPlisData: false,
      selectedAgencies: this.initAgencies,
      selectedDataInputOrgas: this.initDataInputOrgas,
      selectedAuthUsers: this.initAuthUsers,
      selectedInternalPhase: this.initProcedurePhaseInternal,
      selectedPublicPhase: this.initProcedurePhasePublic,
      selectedProcedureCategories: this.initProcedureCategories,
      selectedSimilarRecommendationProcedures: this.initSimilarRecommendationProcedures,
      procedureDescription: this.procedureExternalDesc,
      procedureName: this.initProcedureName
    }
  },

  computed: {
    authUsersOptions () {
      const users = JSON.parse(JSON.stringify(this.authorizedUsersOptions))
      return sortAlphabetically(users, 'name')
    }
  },

  methods: {
    addonRequest () {
      const payload = this.createAddonPayload()

      const apiCall = this.addonPayload.request === 'PATCH'
        ? dpApi.patch(Routing.generate('api_resource_update', { resourceType: this.addonPayload.resourceType, resourceId: this.addonPayload.id }), {}, { data: payload })
        : dpApi.post(Routing.generate('api_resource_create', { resourceType: this.addonPayload.resourceType }), {}, { data: payload })

      apiCall
        .then(checkResponse)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
    },

    createAddonPayload () {
      return {
        type: this.addonPayload.resourceType,
        attributes: this.addonPayload.attributes,
        relationships: this.addonPayload.request === 'PATCH' ? undefined : {
          procedure: {
            data: {
              type: 'Procedure',
              id: this.procedureId
            }
          }
        },
        ...(this.addonPayload.request === 'PATCH' ? { id: this.addonPayload.id } : {}),
      }
    },

    getDataPlis (plisId, routeName) {
      return dpApi({
        method: 'GET',
        url: Routing.generate(routeName, { uuid: plisId })
      })
        .then(data => {
          return data.data
        })
    },

    selectAllAuthUsers () {
      this.selectedAuthUsers = this.authorizedUsersOptions
    },

    setSelectedInternalPhase (phase) {
      this.selectedInternalPhase = phase
    },

    setSelectedPublicPhase (phase) {
      this.selectedPublicPhase = phase
    },

    submit () {
      this.addonRequest()
      this.$refs.configForm.submit()
    },

    unselectAllAuthUsers () {
      this.selectedAuthUsers = []
    },

    updateAddonPayload (payload) {
      this.addonPayload = payload
    }
  },

  mounted () {
    const users = JSON.parse(JSON.stringify(this.initAuthUsers))
    this.selectedAuthUsers = sortAlphabetically(users, 'name')
  }
}
</script>
