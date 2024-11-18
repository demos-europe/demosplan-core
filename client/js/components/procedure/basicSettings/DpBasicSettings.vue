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
  dpValidateMixin,
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

  mixins: [dpValidateMixin],

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
      addonPayload: {
        id: '',
        resourceType: '',
        attributes: null,
        url: '',
        value: '',
        initValue: ''
      },
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
    createAddonPayload () {
      return {
        type: this.addonPayload.resourceType,
        attributes: this.addonPayload.attributes,
        relationships: this.addonPayload.url === 'api_resource_update' ? undefined : {
          procedure: {
            data: {
              type: 'Procedure',
              id: this.procedureId
            }
          }
        },
        ...(this.addonPayload.url === 'api_resource_update' ? { id: this.addonPayload.id } : {}),
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

    handleAddonRequest () {
      const payload = this.createAddonPayload()

      const addonRequest = dpApi({
        method: this.addonPayload.url === 'api_resource_update' ? 'PATCH' : 'POST',
        url: Routing.generate(this.addonPayload.url, {
          resourceType: this.addonPayload.resourceType,
          ...(this.addonPayload.url === 'api_resource_update' && { resourceId: this.addonPayload.id })
        }),
        data: {
          data: payload
        }
      })

      return addonRequest.then(checkResponse)
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
      const addonExists = Boolean(window['AddonAdditionalField']) // have to check if addon is presented (another option to check it?)
      const addonHasValue = this.addonPayload.value || this.addonPayload.initValue

      if (addonExists && addonHasValue) {
        this.handleAddonRequest().then(() => {
          this.submitConfigForm()
        })
      } else {
        this.submitConfigForm()
      }
    },

    submitConfigForm() {
      this.dpValidateAction('configForm', () => {
        this.$refs.configForm.submit()
      }, false)
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
