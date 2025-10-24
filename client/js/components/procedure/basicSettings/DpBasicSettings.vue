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
import { defineAsyncComponent } from 'vue'
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
    DpProcedureCoordinate: defineAsyncComponent(() => import(/* webpackChunkName: "dp-procedure-coordinate" */ './DpProcedureCoordinate')),
    DpUploadFiles: defineAsyncComponent(async () => {
      const { DpUploadFiles } = await import('@demos-europe/demosplan-ui')
      return DpUploadFiles
    }),
    ExportSettings,
    ParticipationPhases
  },

  mixins: [dpValidateMixin],

  props: {
    agenciesOptions: {
      type: Array,
      required: false,
      default: () => []
    },

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

    initAuthUsers: {
      required: false,
      type: Array,
      default: () => []
    },

    initDataInputOrgas: {
      required: false,
      type: Array,
      default: () => []
    },

    initPictogramAltText: {
      required: false,
      type: String,
      default: ''
    },

    initPictogramCopyright: {
      required: false,
      type: String,
      default: ''
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
      addonPayload: { /** The payload required for addon requests. When a value is entered in the addon field, it emits data that must include the following fields */
        attributes: null,
        id: '',
        initValue: '',
        resourceType: '',
        url: '',
        value: ''
      },
      isLoadingPlisData: false,
      pictogramAltText: this.initPictogramAltText,
      pictogramCopyright: this.initPictogramCopyright,
      procedureDescription: this.procedureExternalDesc,
      procedureName: this.initProcedureName,
      selectedAgencies: this.initAgencies,
      selectedAuthUsers: this.initAuthUsers,
      selectedDataInputOrgas: this.initDataInputOrgas,
      selectedInternalPhase: this.initProcedurePhaseInternal,
      selectedProcedureCategories: this.initProcedureCategories,
      selectedPublicPhase: this.initProcedurePhasePublic,
      selectedSimilarRecommendationProcedures: this.initSimilarRecommendationProcedures
    }
  },

  computed: {
    sortedAgenciesOptions () {
      const agencies = JSON.parse(JSON.stringify(this.agenciesOptions))
      return sortAlphabetically(agencies, 'name')
    },

    authUsersOptions () {
      const users = JSON.parse(JSON.stringify(this.authorizedUsersOptions))
      return sortAlphabetically(users, 'name')
    }
  },

  methods: {
    createAddonPayload () {
      const { attributes, id, resourceType, url } = this.addonPayload
      return {
        type: resourceType,
        attributes,
        relationships: url === 'api_resource_update'
          ? undefined
          : {
              procedure: {
                data: {
                  type: 'Procedure',
                  id: this.procedureId
                }
              }
            },
        ...(url === 'api_resource_update' ? { id } : {})
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

      return addonRequest
        .then(checkResponse)
        .catch(error => {
          /** The 'is-invalid' class would be added to the addon field in case of an error */
          const input = document.getElementById('addonAdditionalField')
          input.classList.add('is-invalid')

          throw error
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
      const addonExists = !!window.dplan.loadedAddons['addon.additional.field']
      const addonHasValue = !!this.addonPayload.value || !!this.addonPayload.initValue

      this.dpValidateAction('configForm', () => {
        if (addonExists && addonHasValue) {
          this.handleAddonRequest().then(() => {
            this.submitConfigForm()
          })
        } else {
          this.submitConfigForm()
        }
      }, false)
    },

    submitConfigForm () {
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
