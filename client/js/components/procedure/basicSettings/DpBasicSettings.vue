<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    ref="configForm"
    name="configForm"
    enctype="multipart/form-data"
    data-dp-validate="configForm"
    :data-procedure="procedureIdent"
    method="post"
    :action="Routing.generate('DemosPlan_procedure_edit', {'procedure': procedureIdent })">

    <input type="hidden" name="_token" :value="formData.token" />
    <input type="hidden" value="edit" name="action">
    <input type="hidden" :value="procedureIdent" name="r_ident">

    <input
      v-if="hasPermission('feature_institution_participation')"
      type="hidden"
      name="r_currentPublicParticipationPhase"
      :value="initProcedurePhasePublic"
    >

    <procedure-general-settings
      :agencies="agencies"
      :authorized-users-options="authorizedUsersOptions"
      :data-input-orgas="dataInputOrgas"
      :form-data="formData"
      :init-agencies="initAgencies"
      :init-auth-users="initAuthUsers"
      :init-data-input-orgas="initDataInputOrgas"
      :has-procedure-user-restricted-access="hasProcedureUserRestrictedAccess"
      :procedure-settings="procedureSettings"
    />

    <!-- wizard item: Informationen zum Verfahren -->
    <procedure-info-settings
      :available-procedure-categories="availableProcedureCategories"
      :init-pictogram-copyright="initPictogramCopyright"
      :init-procedure-categories="initProcedureCategories"
      :init-pictogram-alt-text="initPictogramAltText"
      :procedure-settings="procedureSettings"
      :procedure-id="procedureId"
    />

    <!-- wizard item: Done -->
    <fieldset
      :data-wizard-topic="Translator.trans('wizard.topic.done')"
      class="o-wizard">
      <div class="o-wizard__content">
        <div class="o-wizard__main">
          <!-- There is a bunch of Html in here. In a future iteration, "next step" content could be shown
                 based on features, whereas entity naming differences would be resolved using placeholders. -->
          {{ Translator.trans('wizard.done.content') }}
        </div>
      </div>
    </fieldset>

    <!-- wizard template -->
    <div
      class="o-wizard__additional-elements hidden float-left"
      aria-hidden="true">
      <h2 class="o-wizard__header">{{ Translator.trans('adjustments.general') }}</h2>
      <div class="o-wizard__close">
        <i class="fa fa-times" data-wizard-action="close"></i>
      </div>
      <div class="o-wizard__menu">
        <ul class="o-wizard__menu-list"></ul>
      </div>
      <button
        type="button"
        data-cy="wizardPrevious"
        class="btn btn--secondary o-wizard__btn o-wizard__btn--prev">
        {{ Translator.trans('wizard.previous') }}
      </button>
      <button
        type="button"
        data-cy="wizardDone"
        class="btn btn--primary o-wizard__btn o-wizard__btn--done hidden">
        {{ Translator.trans('wizard.done') }}
      </button>
    </div>
    <div class="o-wizard__bg" data-wizard-action="close"></div>

    <!-- form controls when not in wizard mode -->
    <div class="text-right space-inline-s">
      <!-- A hidden input is used here to prevent the form from being submitted in wizard mode when the next button is clicked -->
      <input
        type="submit"
        class="hidden"
        :value="Translator.trans('save')">
      <button
        class="btn btn--primary"
        id="saveConfig"
        name="saveConfig"
        data-cy="saveConfig"
        type="button"
        @click="submit">
        {{ Translator.trans('save') }}
      </button>
      <a
        class="btn btn--secondary"
        data-cy="abortConfig"
        :href="Routing.generate('DemosPlan_procedure_administration_get')">
        {{ Translator.trans('abort') }}
      </a>
    </div>

  </form>
</template>

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
  DpLabel,
  DpMultiselect,
  dpValidateMixin,
  sortAlphabetically
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import { defineAsyncComponent } from 'vue'
import DpEmailList from './DpEmailList'
import ExportSettings from './ExportSettings'
import ParticipationPhases from './ParticipationPhases'
import ProcedureInfoSettings from './ProcedureInfoSettings.vue'
import ProcedureGeneralSettings from './ProcedureGeneralSettings.vue'

export default {
  name: 'DpBasicSettings',

  components: {
    ProcedureGeneralSettings,
    ProcedureInfoSettings,
    DpLabel,
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
    ParticipationPhases,
  },

  mixins: [dpValidateMixin],

  props: {
    agencies: {
      type: Array,
      required: false,
      default: () => [],
    },

    availableProcedureCategories: {
      type: Array,
      required: false,
      default: () => [],
    },

    authorizedUsersOptions: {
      type: Array,
      required: false,
      default: () => [],
    },

    dataInputOrgas: {
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

    hasProcedureUserRestrictedAccess: {
      required: false,
      type: Boolean,
      default: false,
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

    procedureIdent: {
      required: true,
      type: String,
    },

    procedureSettings: {
      required: true,
      type: Object,
    },

    formData: {
      type: Object,
      required: true,
      default: () => ({})
    },
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
      procedureDescription: this.procedureExternalDesc,
      procedureName: this.initProcedureName,
      selectedInternalPhase: this.initProcedurePhaseInternal,
      selectedPublicPhase: this.initProcedurePhasePublic,
      selectedSimilarRecommendationProcedures: this.initSimilarRecommendationProcedures,
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
          /** The 'is-invalid' class would be added to the addon field in case of an error */
          const input = document.getElementById('addonAdditionalField')
          input.classList.add('is-invalid')

          throw error
        })
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

    updateAddonPayload (payload) {
      this.addonPayload = payload
    },
  },

  mounted () {
    const users = JSON.parse(JSON.stringify(this.initAuthUsers))
    this.selectedAuthUsers = sortAlphabetically(users, 'name')
  },
}
</script>
