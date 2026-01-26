<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :activate-interface="activateInterface"
      :auth-users-options="authUsersOptions"
      :handle-auto-switch-phase-update="handleAutoSwitchPhaseUpdate"
      :select-all-auth-users="selectAllAuthUsers"
      :set-interface-warning-modal-ref="setInterfaceWarningModalRef"
      :set-selected-internal-phase="setSelectedInternalPhase"
      :set-selected-public-phase="setSelectedPublicPhase"
      :sorted-agencies-options="sortedAgenciesOptions"
      :state="state"
      :submit="submit"
      :submit-without-interface-activation="submitWithoutInterfaceActivation"
      :unselect-all-auth-users="unselectAllAuthUsers"
      :update-addon-payload="updateAddonPayload"
    />
  </div>
</template>

<script>
import { computed, reactive, ref } from 'vue'
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
      default: () => [],
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

    const interfaceWarningModalRef = ref(null)

    const authUsersOptions = computed(() =>
      sortAlphabetically([...props.authorizedUsersOptions], 'name'),
    )

    const sortedAgenciesOptions = computed(() =>
      sortAlphabetically([...props.agenciesOptions], 'name'),
    )

    const setInterfaceWarningModalRef = (el) => {
      interfaceWarningModalRef.value = el
    }

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
      interfaceWarningModalRef,
      selectAllAuthUsers,
      setInterfaceWarningModalRef,
      setSelectedInternalPhase,
      setSelectedPublicPhase,
      sortedAgenciesOptions,
      state,
      unselectAllAuthUsers,
    }
  },

  data () {
    return {
      addonCheckAutoSwitchEnabled: false,
      addonCheckAutoSwitchPhase: '',
      addonPayload: { /** The payload required for addon requests. When a value is entered in the addon field, it emits data that must include the following fields */
        attributes: null,
        id: '',
        initValue: '',
        resourceType: '',
        url: '',
        value: '',
      },
      bypassAddonWarningModal: false,
      isLoadingPlisData: false,
    }
  },

  computed: {
    // Needed for the addon-modal on submit
    isAddonInterfaceActivated () {
      return this.addonPayload.attributes?.isInterfaceActivated ?? false
    },

    isAddonLoaded () {
      return !!globalThis.dplan.loadedAddons['interface.fields.to.transmit']
    },

    isPublicParticipationPhaseActive () {
      const currentPhaseIsPublic = this.publicParticipationPhases.includes(this.state.selectedPublicPhase)
      const autoSwitchPhaseIsPublic = this.addonCheckAutoSwitchEnabled &&
        this.publicParticipationPhases.includes(this.addonCheckAutoSwitchPhase)

      return currentPhaseIsPublic || autoSwitchPhaseIsPublic
    },

    publicParticipationPhases () {
      return ['earlyparticipation', 'participation', 'anotherparticipation']
    },

    shouldShowInterfaceWarningModal () {
      const checkbox = document.getElementById('interfaceFieldsToTransmit-checkbox')
      const isInterfaceCheckboxEnabled = !checkbox?.checked

      return this.isAddonLoaded &&
        !this.isAddonInterfaceActivated &&
        this.isPublicParticipationPhaseActive &&
        !this.bypassAddonWarningModal &&
        isInterfaceCheckboxEnabled
    },
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
          throw error
        })
    },

    submit (formElement) {
      if (this.shouldShowInterfaceWarningModal) {
        this.interfaceWarningModalRef?.toggle()

        return
      }

      const addonExists = this.isAddonLoaded
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

    // Needed for the addon-modal on submit
    activateInterface (formElement) {
        const checkbox = document.getElementById('interfaceFieldsToTransmit-checkbox')

        if (checkbox && !checkbox.disabled && !checkbox.checked) {
          checkbox.click()
        }

      this.interfaceWarningModalRef?.toggle()
      this.submit(formElement)
    },

    handleAutoSwitchPhaseUpdate (payload) {
      if (!payload.isInternal) {
        this.addonCheckAutoSwitchPhase = payload.phase
        this.addonCheckAutoSwitchEnabled = payload.enabled
      }
    },

    submitWithoutInterfaceActivation (formElement) {
      this.interfaceWarningModalRef?.toggle()
      this.bypassAddonWarningModal = true
      this.submit(formElement)
    },
  },
}
</script>
