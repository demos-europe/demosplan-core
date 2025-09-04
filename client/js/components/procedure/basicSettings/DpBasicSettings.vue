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
    :data-procedure="procedureIdent"
    data-dp-validate="configForm"
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

    <!-- {# wizard item: Intern #} -->
    <fieldset
      class="o-wizard"
      :data-wizard-finished="procedureSettings.internalComplete ? 'true' : null"
      :data-wizard-topic="Translator.trans('wizard.topic.internal')"
      :data-dp-validate-topic="Translator.trans('wizard.topic.internal')"
      data-dp-validate="internalForm">

      <legend
        data-toggle-id="internal"
        data-cy="internal"
        class="js__toggleAnything">
        <i class="caret"></i>
        {{ Translator.trans('wizard.topic.internal') }}
        <i class="fa fa-check"></i>
      </legend>

      <div
        data-toggle-id="internal"
        class="o-wizard__content">
        <div class="o-wizard__main">
          <div
            v-if="hasPermission('field_procedure_adjustments_planning_agency')"
            class="u-mb">
            <label
              class="inline-block u-mb-0"
              for="r_agency">
              {{ Translator.trans('planningagency.participating') }}
            </label>

            <dp-contextual-help
              class="mb-1"
              :text="Translator.trans('text.procedure.edit.planning_agency')"
            />

            <dp-multiselect
              id="r_agency"
              v-model="selectedAgencies"
              data-cy="internal:selectedAgencies"
              label="name"
              multiple
              :options="agencies"
              track-by="id">
              <template v-slot:option="{ props }">
                {{ props.option.name }}
              </template>
              <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                  {{ props.option.name }}
                  <i
                    aria-hidden="true"
                    @click="props.remove(props.option)"
                    tabindex="1"
                    class="multiselect__tag-icon">
                  </i>
                  <input
                    type="hidden"
                    :value="props.option.id"
                    name="r_agency[]"/>
                </span>
              </template>
            </dp-multiselect>
          </div>

          <div class="u-mb">
            <label
              class="inline-block u-mb-0"
              :for="formData.agencyMainEmailAddress.id">
                {{ Translator.trans('email.procedure.agency') }}*
              <p class="weight--normal">
                {{ Translator.trans('explanation.organisation.email.procedure.agency') }}
              </p>
            </label>

            <dp-contextual-help
              class="mb-1"
              :text="Translator.trans('email.procedure.agency.help')"
            />

            <dp-inline-notification
              v-if="formData.agencyMainEmailAddress.errors && formData.agencyMainEmailAddress.errors.length > 0"
              type="error"
              :message="formData.agencyMainEmailAddress.errors.join(', ')"
            />

            <dp-input
              :id="formData.agencyMainEmailAddress.id"
              data-cy="agencyMainEmailAddress"
              :data-dp-validate-error-fieldname="Translator.trans('email.procedure.agency')"
              name="agencyMainEmailAddress[fullAddress]"
              required
              type="email"
              :model-value="formData.agencyMainEmailAddress.fullAddress">
            </dp-input>
          </div>

          <div class="u-mb">
            <label
              class="inline-block u-mb-0"
              :for="formData.agencyExtraEmailAddresses.id">
                {{ Translator.trans('email.address.more') }}
              <p class="weight--normal">
                {{ Translator.trans('email.address.more.explanation') }}
              </p>
            </label>

            <dp-contextual-help
              class="mb-1"
              :text="Translator.trans('email.address.more.explanation.help')"
            />

            <dp-email-list
              data-cy="administrationEdit"
              :init-emails="[]"> <!-- ToDo: formData.agencyExtraEmailAddresses -->
            </dp-email-list>
          </div>

          <div class="u-mb">
            <addon-wrapper
              hook-name="administration.edit.extra.fields"
              :addon-props="{
                procedureId: procedureSettings.id
              }">
            </addon-wrapper>
          </div>

          <div
            v-if="hasPermission('feature_use_data_input_orga')"
            class="u-mb">
            <label class="inline-block u-mb-0" for="r_dataInputOrga">
              {{ Translator.trans('data.input.orga') }}
            </label>

            <dp-contextual-help
              class="mb-1"
              :text="Translator.trans('text.procedure.edit.data_input_orgas')"
            />

            <dp-multiselect
              id="r_dataInputOrgaTest"
              data-cy="internal:selectedDataInputOrgas"
              v-model="selectedDataInputOrgas"
              label="name"
              multiple
              :options="dataInputOrgas"
              track-by="id">
              <template v-slot:option="{ props }">
                {{ props.option.name }}
              </template>
              <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                    {{ props.option.name }}
                    <i
                      aria-hidden="true"
                      @click="props.remove(props.option)"
                      tabindex="1"
                      class="multiselect__tag-icon">
                    </i>
                    <input
                      type="hidden"
                      :value="props.option.id"
                      name="r_dataInputOrga[]" />
                </span>
              </template>
            </dp-multiselect>
          </div>

          <div
            v-if="hasPermission('feature_procedure_user_restrict_access_edit') && hasProcedureUserRestrictedAccess"
            class="u-mb">
            <label class="inline-block u-mb-0" for="r_authorizedUsers">
              {{ Translator.trans('authorized.users') }}
            </label>

            <dp-contextual-help
              class="mb-1"
              :text="Translator.trans('text.procedure.edit.authorized.users')"
            />

            <dp-multiselect
              id="r_authorizedUsers"
              v-model="selectedAuthUsers"
              data-cy="internal:selectedAuthUsers"
              label="name"
              multiple
              :options="authUsersOptions"
              selection-controls
              track-by="id"
              @selectAll="selectAllAuthUsers"
              @deselectAll="unselectAllAuthUsers">
              <template v-slot:option="{ props }">
                {{ props.option.name }}
              </template>
              <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                    {{ props.option.name }}
                    <i
                      aria-hidden="true"
                      @click="props.remove(props.option)"
                      tabindex="1" class="multiselect__tag-icon">
                    </i>
                    <input
                      type="hidden"
                      :value="props.option.id"
                      name="r_authorizedUsers[]" />
                </span>
              </template>
            </dp-multiselect>
          </div>

          <!-- The procedure type is set only once, when creating a procedure.
          It can't nbe changed afterwards. Anyhow, the user is informed here which type
          the procedure was created with. -->
          <div class="u-mb">
            <p class="weight--bold u-mb-0">
              {{ Translator.trans('text.procedures.type') }}
            </p>
            <p
              v-if="procedureSettings.procedureType"
              class="u-mb-0">
              {{ procedureSettings.procedureType.name }}<br>
              {{ procedureSettings.procedureType.description }}
            </p>
            <p v-else>
              {{ Translator.trans('procedure.type.not.set') }}
            </p>
          </div>

          <div class="u-mb-0_5">
            <label class="inline-block u-mb-0" for="r_desc">
              {{ Translator.trans('internalnote') }}
            </label>

            <dp-contextual-help
              class="mb-1"
              :text="Translator.trans('text.procedure.edit.note')"
            />

            <!-- Bei textareas muss der Inhalt ohne Leerzeichen zwischen den Tags stehen, sonst werden die Leerzeichen ausgegeben und multiplizieren sich im Frontend. -->
            <dp-text-area
              class="bg-surface"
              data-cy="internal:internalNote"
              grow-to-parent
              id="r_desc"
              name="r_desc"
              reduced-height
              :value="procedureSettings.desc ? procedureSettings.desc : Translator.trans('notspecified')"
            />
          </div>
        </div>

        <label class="o-wizard__mark u-mb-0">
          <input
            data-wizard-cb
            data-cy="fieldInternCompletions"
            name="fieldCompletions[]"
            value="internalComplete"
            type="checkbox"
            v-model="procedureSettings.internalComplete"
          />
          {{ Translator.trans('wizard.mark_as_done') }}
        </label>
      </div>
      <button
        type="button"
        data-cy="wizardNext"
        data-dp-validate-capture-click
        class="btn btn--primary o-wizard__btn o-wizard__btn--next hidden submit">
        {{ Translator.trans('wizard.next') }}
      </button>
    </fieldset>


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
    },
  },

  mounted () {
    const users = JSON.parse(JSON.stringify(this.initAuthUsers))
    this.selectedAuthUsers = sortAlphabetically(users, 'name')
  },
}
</script>
