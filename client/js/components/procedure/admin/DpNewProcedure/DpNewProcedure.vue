<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    ref="newProcedureForm"
    :action="Routing.generate('DemosPlan_procedure_new')"
    data-dp-validate="newProcedureForm"
    enctype="multipart/form-data"
    method="post"
    name="xsubmititem"
  >
    <input
      type="hidden"
      name="_token"
      :value="token"
    >
    <input
      type="hidden"
      name="action"
      value="new"
    >
    <input
      type="hidden"
      name="r_master"
      value="false"
    >
    <!-- allow publishing of Statements by default -->
    <input
      type="hidden"
      name="r_publicParticipationPublicationEnabled"
      value="1"
    >

    <template v-if="hasPermission('feature_use_plis')">
      <input
        type="hidden"
        name="r_name"
        value=""
      >
      <input
        type="hidden"
        name="r_externalDesc"
        value=""
      >
      <input
        type="hidden"
        name="r_mapExtent"
        value=""
      >
    </template>
    <fieldset>
      <legend
        class="sr-only"
        v-text="Translator.trans('procedure.data')"
      />

      <addon-wrapper hook-name="procedure.fields" />

      <template v-if="hasPermission('feature_use_plis')">
        <dp-select
          id="r_plisId"
          class="mb-3"
          :label="{ text: Translator.trans('name'), hint: Translator.trans('explanation.plis.procedurename') }"
          name="r_plisId"
          :options="plisNameOptions"
          required
        />

        <dl>
          <dt
            class="weight--bold"
            v-text="Translator.trans('public.participation.desc')"
          />
          <dd
            id="js__plisPlanungsanlass"
            class="u-m-0 lbl__hint"
            v-text="Translator.trans('planningcause.select.hint')"
          />
        </dl>
      </template>

      <dp-input
        v-else
        id="r_name"
        v-model="procedureName"
        :label="{ text: Translator.trans('name') }"
        :required="requireField"
        class="mb-4"
        data-cy="newProcedureTitle"
        maxlength="200"
        name="r_name"
        type="text"
      />

      <dp-select
        v-if="hasPermission('feature_procedure_templates')"
        id="blueprint"
        :label="{
          hint: procedureTemplateHint,
          text: Translator.trans('master')
        }"
        :options="blueprintOptions"
        :selected="masterBlueprintId"
        class="mb-4"
        data-cy="newProcedureForm:blueprintOptions"
        name="r_copymaster"
        @select="setBlueprintData"
      />

      <!-- Only show select if there is more than one choice. Otherwise, pass the id as the value of a hidden field. -->
      <template v-if="procedureTypes.length > 1">
        <dp-label
          for="r_procedure_type"
          :hint="Translator.trans('text.procedures.types.hint')"
          :text="Translator.trans('text.procedures.type')"
          required
        />
        <dp-multiselect
          v-model="currentProcedureType"
          class="layout__item u-1-of-1 u-pl-0 u-mb inline-block"
          data-cy="procedureType"
          label="name"
          :data-dp-validate-error-fieldname="Translator.trans('text.procedures.type')"
          :options="procedureTypes"
          required
          track-by="id"
        >
          <template v-slot:option="{ props }">
            {{ props.option.name }}<br>
            <span class="font-size-small">{{ props.option.description }}</span>
          </template>
        </dp-multiselect>
        <input
          type="hidden"
          name="r_procedure_type"
          :value="currentProcedureTypeId"
        >
      </template>
      <!-- There should always be at least one procedureType defined -->
      <input
        v-else
        name="r_procedure_type"
        type="hidden"
        :value="procedureTypes[0].id"
      >

      <dp-input
        id="main-email"
        v-model="mainEmail"
        class="mb-4"
        data-cy="agencyMainEmailAddress"
        :label="{
          hint: Translator.trans('explanation.organisation.email.procedure.agency'),
          text: Translator.trans('email.procedure.agency')
        }"
        name="agencyMainEmailAddress[fullAddress]"
        required
        type="email"
      />

      <dp-text-area
        id="r_desc"
        class="mb-4"
        :hint="Translator.trans('internalnote.visibility.hint')"
        :label="Translator.trans('internalnote')"
        data-cy="newProcedureForm:internalNote"
        name="r_desc"
        reduced-height
      />

      <fieldset class="pb-0">
        <legend class="weight--bold">
          {{ Translator.trans('period') }}
          <dp-contextual-help :text="Translator.trans('explanation.date.format')" />
        </legend>

        <dp-date-range-picker
          class="w-1/2"
          start-id="startdate"
          start-name="r_startdate"
          end-id="enddate"
          end-name="r_enddate"
          :start-label="Translator.trans('start')"
          :end-label="Translator.trans('end')"
          aria-labels="true"
          data-cy="newProcedureForm"
          :data-dp-validate-error-fieldname="Translator.trans('period')"
          :required="hasPermission('field_required_procedure_end_date')"
          :calendars-after="2"
          enforce-plausible-dates
        />

        <p
          v-if="hasPermission('feature_use_plis')"
          id="js__statusBox"
          class="sr-only flash"
        />
      </fieldset>

      <div
        v-if="hasPermission('feature_procedure_couple_by_token')"
        class="mb-4"
      >
        <h3
          class="weight--normal color--grey u-mt-1_5"
          v-text="Translator.trans('procedure.couple_token.vht.title')"
        />

        <div v-text="Translator.trans('procedure.couple_token.vht.info')" />

        <dp-inline-notification
          class="mt-3 mb-2"
          :message="Translator.trans('procedure.couple_token.vht.inline_notification')"
          type="warning"
        />

        <couple-token-input :token-length="tokenLength" />
      </div>

      <div class="space-inline-s text-right">
        <dp-button
          id="saveBtn"
          :text="Translator.trans('save')"
          type="submit"
          data-cy="newProcedureForm:saveNewProcedure"
          @click.prevent="dpValidateAction('newProcedureForm', submit, false)"
        />
        <dp-button
          color="secondary"
          data-cy="newProcedureForm:abort"
          :href="Routing.generate('DemosPlan_procedure_administration_get')"
          :text="Translator.trans('abort')"
        />
      </div>
    </fieldset>
  </form>
</template>

<script>
import {
  dpApi,
  DpButton,
  DpContextualHelp,
  DpDateRangePicker,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpSelect,
  DpTextArea,
  dpValidateMixin,
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
    DpContextualHelp,
    DpDateRangePicker,
    DpInput,
    DpLabel,
    DpInlineNotification,
    DpMultiselect,
    DpSelect,
    DpTextArea,
  },

  mixins: [dpValidateMixin],

  props: {
    blueprintOptions: {
      type: Array,
      required: false,
      default: () => ([]),
    },

    csrfToken: {
      type: String,
      required: true,
    },

    masterBlueprintId: {
      type: String,
      required: false,
      default: () => '',
    },

    plisNameOptions: {
      type: Array,
      required: false,
      default: () => [],
    },

    procedureTemplateHint: {
      type: String,
      required: false,
      default: '',
    },

    procedureTypes: {
      type: Array,
      required: true,
      default: () => [],
    },

    token: {
      type: String,
      required: false,
      default: '',
    },

    tokenLength: {
      type: Number,
      required: false,
      default: 12,
    },
  },

  data () {
    return {
      currentProcedureType: '',
      description: '',
      emptyBlueprintData: {
        description: '',
        agencyMainEmailAddress: '',
      },
      mainEmail: '',
      procedureName: '',
    }
  },

  computed: {
    ...mapState('NewProcedure', [
      'requireField',
    ]),

    currentProcedureTypeId () {
      return this.currentProcedureType.id || ''
    },
  },

  methods: {
    async setBlueprintData (payload) {
      // Do not copy mail from master blueprint otherwise fetch mail from selected blueprint
      const blueprintData = payload.value === this.masterBlueprintId ? this.emptyBlueprintData : await this.fetchBlueprintData(payload)
      this.description = blueprintData.description

      if (blueprintData.agencyMainEmailAddress !== '') {
        this.mainEmail = blueprintData.agencyMainEmailAddress
      }
    },

    fetchBlueprintData (blueprintId) {
      return dpApi.get(
        Routing.generate('api_resource_get', {
          resourceType: 'ProcedureTemplate',
          resourceId: blueprintId,
          fields: {
            ProcedureTemplate: [
              'agencyMainEmailAddress',
              'description',
            ].join(),
          },
        }),
      )
        .then(({ data }) => data.data.attributes)
        .catch(() => this.emptyBlueprintData) // When the request fails planners will have to fill in an address manually
    },

    submit () {
      this.$refs.newProcedureForm.submit()
    },
  },
}
</script>
