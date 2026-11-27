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
      :interface-char-limits="interfaceCharLimits"
      :select-all-auth-users="selectAllAuthUsers"
      :set-interface-addon-loaded="setInterfaceAddonLoaded"
      :set-interface-warning-modal-ref="setInterfaceWarningModalRef"
      :set-selected-internal-phase="setSelectedInternalPhase"
      :set-selected-public-phase="setSelectedPublicPhase"
      :sorted-agencies-options="sortedAgenciesOptions"
      :state="state"
      :submit="submit"
      :submit-without-interface-activation="submitWithoutInterfaceActivation"
      :unselect-all-auth-users="unselectAllAuthUsers"
      :update-addon-payload="updateAddonPayload"
      :update-extra-fields-payload="updateExtraFieldsPayload"
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

    externalPhaseOptions: {
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

    initProcedureExternalName: {
      required: false,
      type: String,
      default: '',
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
      procedureExternalName: props.initProcedureExternalName,
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
      /*
       * Payload emitted by addons hooked into `administration.edit.extra.fields`, sent when the form is saved.
       * It has the same shape as `addonPayload`: `attributes` (the data to save), `id` (id of the resource to update),
       * `method` (e.g. 'PATCH'), `resourceType` (the JSON:API type) and `url` (a route name or a full path).
       */
      extraFieldsPayload: null,
      isInterfaceAddonLoaded: false,
      isLoadingPlisData: false,
    }
  },

  computed: {
    interfaceCharLimits () {
      return this.isInterfaceAddonLoaded ?
        { externalDesc: 250, externalName: 120 } :
        { externalDesc: 10000, externalName: 200 }
    },

    // Needed for the addon-modal on submit
    isAddonInterfaceActivated () {
      return this.addonPayload.attributes?.isInterfaceActivated ?? false
    },

    isAddonLoaded () {
      return !!globalThis.dplan.loadedAddons['interface.fields.to.transmit']
    },

    isPublicParticipationPhaseActive () {
      const isParticipationPhase = (id) => this.externalPhaseOptions.some(phase => phase.value === id && phase.permissionset === 'write')

      return isParticipationPhase(this.state.selectedPublicPhase) ||
        (this.addonCheckAutoSwitchEnabled && isParticipationPhase(this.addonCheckAutoSwitchPhase))
    },

    shouldShowInterfaceWarningModal () {
      const checkbox = document.getElementById('interfaceFieldsToTransmit-checkbox')

      if (!checkbox) {
        return false
      }

      const isInterfaceCheckboxEnabled = !checkbox?.checked

      return this.isAddonLoaded &&
        !this.isAddonInterfaceActivated &&
        this.isPublicParticipationPhaseActive &&
        !this.bypassAddonWarningModal &&
        isInterfaceCheckboxEnabled
    },
  },

  methods: {
    createAddonPayload (addonPayload = this.addonPayload) {
      const { attributes, id, method, resourceType, url } = addonPayload
      const isUpdate = url === 'api_resource_update' || method === 'PATCH'

      return {
        type: resourceType,
        attributes,
        relationships: isUpdate ?
          undefined :
          {
            procedure: {
              data: {
                type: 'Procedure',
                id: this.procedureId,
              },
            },
          },
        ...(isUpdate ? { id } : {}),
      }
    },

    handleAddonRequest (addonPayload = this.addonPayload) {
      const payload = this.createAddonPayload(addonPayload)

      // An addon sends either a route name or a root-relative path (e.g. `/api/3.0/...`) plus the method to use.
      const isPath = addonPayload.url.startsWith('/')
      const method = addonPayload.method ?? (addonPayload.url === 'api_resource_update' ? 'PATCH' : 'POST')
      const url = isPath ?
        addonPayload.url :
        Routing.generate(addonPayload.url, {
          resourceType: addonPayload.resourceType,
          ...(addonPayload.url === 'api_resource_update' && { resourceId: addonPayload.id }),
        })

      const addonRequest = dpApi({
        method,
        url,
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
        const addonRequests = []

        // The extra fields are updates which can be repeated safely, so they go first. A create must not run again after it succeeded
        if (this.extraFieldsPayload) {
          addonRequests.push(() => this.handleAddonRequest(this.extraFieldsPayload))
        }

        if (addonExists && addonHasValue) {
          addonRequests.push(() => this.handleAddonRequest())
        }

        if (addonRequests.length > 0) {
          addonRequests
            .reduce((previousRequest, sendRequest) => previousRequest.then(sendRequest), Promise.resolve())
            .then(() => {
              this.submitConfigForm(formElement)
            })
            .catch(error => {
              // The form is not submitted when an addon request fails
              console.error('Saving the addon fields failed', error)

              // The messages of the server are already shown by dpApi, so only show a generic one if there are none
              if (!error?.data?.meta?.messages) {
                dplan.notify.error(Translator.trans('error.api.generic'))
              }
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

    updateExtraFieldsPayload (payload) {
      if (payload instanceof Event) {
        return
      }

      this.extraFieldsPayload = payload
    },

    // Needed for the addon-modal on submit
    activateInterface (formElement) {
      const checkbox = document.getElementById('interfaceFieldsToTransmit-checkbox')

      if (checkbox && !checkbox.disabled && !checkbox.checked) {
        checkbox.click()
        dplan.notify.notify('confirm', Translator.trans('interface.activation.success'))
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

    setInterfaceAddonLoaded (addonNames) {
      this.isInterfaceAddonLoaded = addonNames.length > 0
    },

    submitWithoutInterfaceActivation (formElement) {
      this.interfaceWarningModalRef?.toggle()
      this.bypassAddonWarningModal = true
      this.submit(formElement)
    },
  },
}
</script>
