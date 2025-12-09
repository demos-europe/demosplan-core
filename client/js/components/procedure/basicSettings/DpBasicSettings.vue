<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :auth-users-options="authUsersOptions"
      :select-all-auth-users="selectAllAuthUsers"
      :set-selected-internal-phase="setSelectedInternalPhase"
      :set-selected-public-phase="setSelectedPublicPhase"
      :sorted-agencies-options="sortedAgenciesOptions"
      :state="state"
      :submit="submit"
      :unselect-all-auth-users="unselectAllAuthUsers"
      :update-addon-payload="updateAddonPayload"
    />
  </div>
</template>

<script>
import { computed, reactive } from 'vue'
import {
  dpApi,
  dpValidateMixin,
  sortAlphabetically,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'DpBasicSettings',

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
      default: () => [],
    },

    initAgencies: {
      required: false,
      type: Array,
      default: () => [],
    },

    initAuthUsers: {
      required: false,
      type: Array,
      default: () => [],
    },

    initDataInputOrgas: {
      required: false,
      type: Array,
      default: () => [],
    },

    initPictogramAltText: {
      required: false,
      type: String,
      default: '',
    },

    initPictogramCopyright: {
      required: false,
      type: String,
      default: '',
    },

    initProcedureCategories: {
      required: false,
      type: Array,
      default: () => [],
    },

    initProcedureName: {
      required: false,
      type: String,
      default: '',
    },

    initProcedurePhaseInternal: {
      required: false,
      type: String,
      default: '',
    },

    initProcedurePhasePublic: {
      required: false,
      type: String,
      default: '',
    },

    initPublicParticipationFeedbackEnabled: {
      required: false,
      type: Boolean,
      default: false,
    },

    initSimilarRecommendationProcedures: {
      required: false,
      type: Array,
      default: () => [],
    },

    participationPhases: {
      required: false,
      type: Array,
      default: () => [],
    },

    procedureExternalDesc: {
      required: false,
      type: String,
      default: '',
    },

    procedureId: {
      required: true,
      type: String,
    },
  },

  setup (props) {
    const state = reactive({
      pictogramAltText: props.initPictogramAltText,
      pictogramCopyright: props.initPictogramCopyright,
      procedureDescription: props.procedureExternalDesc,
      procedureName: props.initProcedureName,
      publicParticipationFeedbackEnabled: props.initPublicParticipationFeedbackEnabled,
      selectedAgencies: props.initAgencies,
      selectedAuthUsers: sortAlphabetically(structuredClone(props.initAuthUsers), 'name'),
      selectedDataInputOrgas: props.initDataInputOrgas,
      selectedInternalPhase: props.initProcedurePhaseInternal,
      selectedProcedureCategories: props.initProcedureCategories,
      selectedPublicPhase: props.initProcedurePhasePublic,
      selectedSimilarRecommendationProcedures: props.initSimilarRecommendationProcedures,
    })

    const authUsersOptions = computed(() =>
      sortAlphabetically([...props.authorizedUsersOptions], 'name'),
    )

    const sortedAgenciesOptions = computed(() =>
      sortAlphabetically([...props.agenciesOptions], 'name'),
    )

    const setSelectedInternalPhase = phase => {
      state.selectedInternalPhase = phase
    }
    const setSelectedPublicPhase = phase => {
      state.selectedPublicPhase = phase
    }
    const selectAllAuthUsers = () => {
      state.selectedAuthUsers = props.authorizedUsersOptions
    }
    const unselectAllAuthUsers = () => {
      state.selectedAuthUsers = []
    }

    return {
      authUsersOptions,
      selectAllAuthUsers,
      setSelectedInternalPhase,
      setSelectedPublicPhase,
      sortedAgenciesOptions,
      state,
      unselectAllAuthUsers,
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
        value: '',
      },
      isLoadingPlisData: false,
    }
  },

  methods: {
    createAddonPayload () {
      const { attributes, id, resourceType, url } = this.addonPayload
      return {
        type: resourceType,
        attributes,
        relationships: url === 'api_resource_update' ?
          undefined :
          {
            procedure: {
              data: {
                type: 'Procedure',
                id: this.procedureId,
              },
            },
          },
        ...(url === 'api_resource_update' ? { id } : {}),
      }
    },

    handleAddonRequest () {
      const payload = this.createAddonPayload()

      const addonRequest = dpApi({
        method: this.addonPayload.url === 'api_resource_update' ? 'PATCH' : 'POST',
        url: Routing.generate(this.addonPayload.url, {
          resourceType: this.addonPayload.resourceType,
          ...(this.addonPayload.url === 'api_resource_update' && { resourceId: this.addonPayload.id }),
        }),
        data: {
          data: payload,
        },
      })

      return addonRequest
        .catch(error => {
          /** The 'is-invalid' class would be added to the addon field in case of an error */
          const input = document.getElementById('addonAdditionalField')
          input.classList.add('is-invalid')

          throw error
        })
    },

    submit (formElement) {
      const addonExists = !!window.dplan.loadedAddons['addon.additional.field']
      const addonHasValue = !!this.addonPayload.value || !!this.addonPayload.initValue

      this.dpValidateAction('configForm', () => {
        if (addonExists && addonHasValue) {
          this.handleAddonRequest().then(() => {
            this.submitConfigForm(formElement)
          })
        } else {
          this.submitConfigForm(formElement)
        }
      }, false)
    },

    submitConfigForm (formElement) {
      formElement.submit()
    },

    updateAddonPayload (payload) {
      this.addonPayload = payload
    },
  },
}
</script>
