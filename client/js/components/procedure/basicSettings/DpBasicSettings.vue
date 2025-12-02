<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import {
  dpApi,
  DpButton,
  DpContextualHelp,
  DpDateRangePicker,
  DpDatetimePicker,
  DpEditor,
  DpInlineNotification,
  DpInput,
  DpModal,
  DpMultiselect,
  dpValidateMixin,
  sortAlphabetically,
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
    DpModal,
    DpMultiselect,
    DpProcedureCoordinate: defineAsyncComponent(() => import(/* webpackChunkName: "dp-procedure-coordinate" */ './DpProcedureCoordinate')),
    DpUploadFiles: defineAsyncComponent(async () => {
      const { DpUploadFiles } = await import('@demos-europe/demosplan-ui')
      return DpUploadFiles
    }),
    ExportSettings,
    ParticipationPhases,
  },

  mixins: [dpValidateMixin],

  props: {
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

    plisId: {
      required: false,
      type: String,
      default: '',
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
      selectedSimilarRecommendationProcedures: this.initSimilarRecommendationProcedures,
    }
  },

  computed: {
    authUsersOptions () {
      const users = JSON.parse(JSON.stringify(this.authorizedUsersOptions))
      return sortAlphabetically(users, 'name')
    },

    // Needed for the addon-modal on submit
    isAddonInterfaceActivated () {
      return this.addonPayload.attributes?.isInterfaceActivated ?? false
    },

    isAddonLoaded () {
      return !!globalThis.dplan.loadedAddons['interface.fields.to.transmit']
    },

    isPublicParticipationPhaseActive () {
      // Check current public phase
      const currentPhaseIsPublic = this.publicParticipationPhases.includes(this.selectedPublicPhase)
      // Check auto-switch phase ONLY if auto-switch is enabled and it's for public phases
      const autoSwitchPhaseIsPublic = this.addonCheckAutoSwitchEnabled &&
        this.publicParticipationPhases.includes(this.addonCheckAutoSwitchPhase)

      return currentPhaseIsPublic || autoSwitchPhaseIsPublic
    },

    publicParticipationPhases () {
      return ['earlyparticipation', 'participation', 'anotherparticipation']
    },

    shouldShowInterfaceWarningModal () {
      // Check if checkbox is not disabled
      const checkbox = document.getElementById('interfaceFieldsToTransmit-checkbox')
      const isCheckboxDisabled = checkbox?.disabled ?? true

      return this.isAddonLoaded && !this.isAddonInterfaceActivated && this.isPublicParticipationPhaseActive && !this.bypassAddonWarningModal && !isCheckboxDisabled
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

    getDataPlis (plisId, routeName) {
      return dpApi({
        method: 'GET',
        url: Routing.generate(routeName, { uuid: plisId }),
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
          ...(this.addonPayload.url === 'api_resource_update' && { resourceId: this.addonPayload.id }),
        }),
        data: {
          data: payload,
        },
      })

      return addonRequest
        .catch(error => {
          /** The 'is-invalid' class would be added to the addon input-field in case of an error */
          const input = document.getElementById('interfaceFieldsToTransmit-input')
          if (input) {
            input.classList.add('is-invalid')
          }

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
      if (this.shouldShowInterfaceWarningModal) {
        this.$refs.interfaceWarningOnSubmit.toggle()
        return
      }

      const addonExists = this.isAddonLoaded
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
    },

    // Needed for the addon-modal on submit
    activateInterface () {
      this.collapseSectionsIfExpanded(['wizardNameUrl', 'wizardSettings'])
      this.expandSectionIfCollapsed('wizardPhaseExternal')
      this.scrollToInterfaceFields()
      this.$refs.interfaceWarningOnSubmit.toggle()
    },

    collapseSectionsIfExpanded (sectionIds) {
      sectionIds.forEach(sectionId => {
        this.toggleWizardSection(sectionId, false)
      })
    },

    expandSectionIfCollapsed (sectionId) {
      this.toggleWizardSection(sectionId, true)
    },

    handleAutoSwitchPhaseUpdate (payload) {
      if (!payload.isInternal) {
        this.addonCheckAutoSwitchPhase = payload.phase
        this.addonCheckAutoSwitchEnabled = payload.enabled
      }
    },

    scrollToInterfaceFields () {
      this.$nextTick(() => {
        const addonWrapper = document.getElementById('interfaceFieldsToTransmit')
        addonWrapper?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      })
    },

    submitWithoutInterfaceActivation () {
      this.$refs.interfaceWarningOnSubmit.toggle()
      this.bypassAddonWarningModal = true
      this.submit()
    },

    toggleWizardSection (sectionId, shouldBeExpanded) {
      const fieldset = document.getElementById(sectionId)
      if (!fieldset) {
        return
      }

      const wizardContent = fieldset.querySelector('.o-wizard__content')
      const isCurrentlyExpanded = wizardContent?.classList.contains('is-active')

      if (isCurrentlyExpanded !== shouldBeExpanded) {
        const legend = fieldset.querySelector('legend')
        legend?.click()
      }
    },
  },

  mounted () {
    const users = JSON.parse(JSON.stringify(this.initAuthUsers))
    this.selectedAuthUsers = sortAlphabetically(users, 'name')
  },
}
</script>
