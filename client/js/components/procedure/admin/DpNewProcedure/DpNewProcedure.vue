<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    :action="Routing.generate('DemosPlan_procedure_new')"
    data-dp-validate="newProcedureForm"
    enctype="multipart/form-data"
    method="post"
    name="xsubmititem"
    ref="newProcedureForm">
    <input
      type="hidden"
      name="_token"
      :value="token">
    <input
      type="hidden"
      name="action"
      value="new">
    <input
      type="hidden"
      name="r_master"
      value="false">
    <!-- allow publishing of Statements by default -->
    <input
      type="hidden"
      name="r_publicParticipationPublicationEnabled"
      value="1">

    <template v-if="hasPermission('feature_use_plis')">
      <input
        type="hidden"
        name="r_name"
        value="">
      <input
        type="hidden"
        name="r_externalDesc"
        value="">
      <input
        type="hidden"
        name="r_mapExtent"
        value="">
    </template>
    <fieldset>
      <legend
        class="sr-only"
        v-text="Translator.trans('procedure.data')" />

      <addon-wrapper hook-name="procedure.fields" />

      <template v-if="hasPermission('feature_use_plis')">
        <dp-form-row class="u-mb-0_75">
          <dp-select
            id="r_plisId"
            :label="{ text: Translator.trans('name'), hint: Translator.trans('explanation.plis.procedurename') }"
            name="r_plisId"
            :options="plisNameOptions" />
        </dp-form-row>
        <dl>
          <dt
            v-text="Translator.trans('public.participation.desc')"
            class="weight--bold" />
          <dd
            v-text="Translator.trans('planningcause.select.hint')"
            id="js__plisPlanungsanlass"
            class="u-m-0 lbl__hint" />
        </dl>
      </template>

      <dp-form-row
        class="u-mb-0_75"
        v-else>
        <dp-input
          data-cy="newProcedureTitle"
          id="r_name"
          :label="{ text: Translator.trans('name') }"
          maxlength="200"
          name="r_name"
          :required="requireField"
          type="text" />
      </dp-form-row>
      <dp-form-row
        class="u-mb-0_75"
        v-if="hasPermission('feature_procedure_templates')">
        <dp-select
          id="blueprint"
          :label="{
            hint: procedureTemplateHint,
            text: Translator.trans('master')
          }"
          name="r_copymaster"
          data-cy="newProcedureForm:blueprintOptions"
          :options="blueprintOptions"
          :selected="masterBlueprintId"
          @select="setBlueprintData" />
      </dp-form-row>

      <!-- Only show select if there is more than one choice. Otherwise, pass the id as the value of a hidden field. -->
      <template v-if="procedureTypes.length > 1">
        <dp-label
          for="r_procedure_type"
          :hint="Translator.trans('text.procedures.types.hint')"
          :text="Translator.trans('text.procedures.type')"
          required />
        <dp-multiselect
          v-model="currentProcedureType"
          class="layout__item u-1-of-1 u-pl-0 u-mb inline-block"
          data-cy="procedureType"
          label="name"
          :data-dp-validate-error-fieldname="Translator.trans('text.procedures.type')"
          :options="procedureTypes"
          required
          track-by="id">
          <template v-slot:option="{ props }">
            {{ props.option.name }}<br>
            <span class="font-size-small">{{ props.option.description }}</span>
          </template>
        </dp-multiselect>
        <input
          type="hidden"
          name="r_procedure_type"
          :value="currentProcedureTypeId">
      </template>
      <!-- There should always be at least one procedureType defined -->
      <input
        v-else
        name="r_procedure_type"
        type="hidden"
        :value="procedureTypes[0].id">

      <dp-form-row class="u-mb-0_75">
        <dp-input
          id="main-email"
          data-cy="agencyMainEmailAddress"
          :label="{
            hint: Translator.trans('explanation.organisation.email.procedure.agency'),
            text: Translator.trans('email.procedure.agency')
          }"
          name="agencyMainEmailAddress[fullAddress]"
          required
          type="email"
          :value="mainEmail" />
      </dp-form-row>
      <dp-form-row class="u-mb-0_75">
        <dp-text-area
          :hint="Translator.trans('internalnote.visibility.hint')"
          id="r_desc"
          :label="Translator.trans('internalnote')"
          data-cy="newProcedureForm:internalNote"
          name="r_desc"
          reduced-height />
      </dp-form-row>

      <div class="u-mb-0_75">
        <dp-label
          for="startdate"
          :hint="Translator.trans('explanation.date.procedure')"
          :required="hasPermission('field_required_procedure_end_date')"
          :text="Translator.trans('period')"
          :tooltip="Translator.trans('explanation.date.format')" />

        <dp-date-range-picker
          class="u-2-of-4"
          start-id="startdate"
          start-name="r_startdate"
          end-id="enddate"
          end-name="r_enddate"
          data-cy="newProcedureForm"
          :data-dp-validate-error-fieldname="Translator.trans('period')"
          :required="hasPermission('field_required_procedure_end_date')"
          :calendars-after="2"
          enforce-plausible-dates />

        <p
          v-if="hasPermission('feature_use_plis')"
          class="sr-only flash"
          id="js__statusBox" />
      </div>

      <div
        v-if="hasPermission('feature_procedure_couple_by_token')"
        class="u-mb-0_75">
        <h3
          class="weight--normal color--grey u-mt-1_5"
          v-text="Translator.trans('procedure.couple_token.vht.title')" />

        <div v-text="Translator.trans('procedure.couple_token.vht.info')" />

        <dp-inline-notification
          :message="Translator.trans('procedure.couple_token.vht.inline_notification')"
          type="warning" />

        <couple-token-input :token-length="tokenLength" />
      </div>

      <div class="space-inline-s text-right">
        <dp-button
          id="saveBtn"
          :text="Translator.trans('save')"
          type="submit"
          @click.prevent="dpValidateAction('newProcedureForm', submit, false)"
          data-cy="newProcedureForm:saveNewProcedure" />
        <dp-button
          color="secondary"
          data-cy="newProcedureForm:abort"
          :href="Routing.generate('DemosPlan_procedure_administration_get')"
          :text="Translator.trans('abort')" />
      </div>
    </fieldset>
  </form>
</template>

<script>
import {
  dpApi,
  DpButton,
  DpDateRangePicker,
  DpFormRow,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpSelect,
  DpTextArea,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import CoupleTokenInput from './CoupleTokenInput'
import { mapState } from 'vuex'

export default {
  name: 'DpNewProcedure',
  components: {
    AddonWrapper,
    CoupleTokenInput,
    DpButton,
    DpDateRangePicker,
    DpFormRow,
    DpInput,
    DpLabel,
    DpInlineNotification,
    DpMultiselect,
    DpSelect,
    DpTextArea
  },

  mixins: [dpValidateMixin],

  props: {
    blueprintOptions: {
      type: Array,
      required: false,
      default: () => ([])
    },

    csrfToken: {
      type: String,
      required: true
    },

    masterBlueprintId: {
      type: String,
      required: false,
      default: () => ''
    },

    plisNameOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    procedureTemplateHint: {
      type: String,
      required: false,
      default: ''
    },

    procedureTypes: {
      type: Array,
      required: true,
      default: () => []
    },

    token: {
      type: String,
      required: false,
      default: ''
    },

    tokenLength: {
      type: length,
      required: false,
      default: 12
    }
  },

  data () {
    return {
      currentProcedureType: '',
      description: '',
      emptyBlueprintData: {
        description: '',
        agencyMainEmailAddress: ''
      },
      mainEmail: ''
    }
  },

  computed: {
    ...mapState('NewProcedure', [
      'requireField'
    ]),

    currentProcedureTypeId () {
      return this.currentProcedureType.id || ''
    }
  },

  methods: {
    async setBlueprintData (payload) {
      // Do not copy mail from master blueprint otherwise fetch mail from selected blueprint
      const blueprintData = payload.value === this.masterBlueprintId ? this.emptyBlueprintData : await this.fetchBlueprintData(payload)
      this.description = blueprintData.description
      this.mainEmail = blueprintData.agencyMainEmailAddress
    },

    fetchBlueprintData (blueprintId) {
      return dpApi.get(
        Routing.generate('api_resource_get', {
          resourceType: 'ProcedureTemplate',
          resourceId: blueprintId,
          fields: {
            ProcedureTemplate: [
              'agencyMainEmailAddress',
              'description'
            ].join()
          }
        })
      )
        .then(({ data }) => data.data.attributes)
        .catch(() => this.emptyBlueprintData) // When the request fails planners will have to fill in an address manually
    },

    submit () {
      this.$refs.newProcedureForm.submit()
    }
  }
}
</script>
