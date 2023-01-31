<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <form
    name="xsubmititem"
    :action="Routing.generate('DemosPlan_procedure_new')"
    enctype="multipart/form-data"
    method="post"
    data-dp-validate>
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
<!--    bobhh only -->
    <input type="hidden" name="r_name" value="">
    <input type="hidden" name="r_externalDesc" value="">
    <input type="hidden" name="r_mapExtent" value="">
    <fieldset>
      <!-- To wrap markup only if it exists, we have to first check if it outputs anything.-->
      <!--      {% set formNewProcedureMarkup = extensionPointMarkup('formNewProcedure') %}-->
      <!--      {% if formNewProcedureMarkup != '' %}-->
        <bimschg-antrag />
      <!--      {% endif %}-->
      <dp-form-row>
<!--        TODO correct permission? -->
        <div v-if="hasPermission('feature_use_plis')">
          <dp-select
            id="r_plisId"
            :label="{ text: Translator.trans('name'), hint: Translator.trans('explanation.plis.procedurename') }"
            name="r_plisId"
            :options="plisNameOptions" />

          <dl class="u-nojs-hide--block">
            <dt
              v-text="Translator.trans('public.participation.desc')"
              class="weight--bold" />
            <dd
              id="js__plisPlanungsanlass"
              class="list-style-none" />
          </dl>
        </div>
        <dp-input
          v-else
          data-cy="newProcedureTitle"
          id="r_name"
          :label="{ text: Translator.trans('name'), hint: Translator.trans('input.text.maxlength') }"
          maxlength="200"
          name="r_name"
          required
          type="text" />
      </dp-form-row>
      <!--      {% block master %}-->
      <dp-form-row v-if="hasPermission('feature_procedure_templates')">
        <dp-select
          id="blueprint"
          :label="{
            hint: blueprintHint,
            text: Translator.trans('master')
          }"
          name="r_copymaster"
          :options="blueprintOptions"
          :selected="masterBlueprintId"
          @select="setBlueprintData" />
      </dp-form-row>

      {# Only show select if there is more than one choice. Otherwise, pass the id as the value of a hidden field. #}
      {% if templateVars.procedureTypes|length > 1 %}
      <dp-label
        :text="Translator.trans('text.procedures.type')"
        :hint="Translator.trans('text.procedures.types.hint')"
        for="r_procedure_type"
        :required="true"></dp-label>
      <dp-multiselect
        class="layout__item u-1-of-1 u-pl-0 u-mb display--inline-block"
        :options="procedureTypes"
        v-model="currentProcedureType"
        track-by="id"
        label="name"
        required>
        <template v-slot:option="props">
          {% verbatim %}
          {{ props.option.name }}<br>
          <span class="font-size-small">{{ props.option.description}}</span>
          {% endverbatim %}
        </template>
      </dp-multiselect>
      <input type="hidden" :value="currentProcedureTypeId" name="r_procedure_type"/>
      {% else %}
      {# There should always be at least one procedureType defined #}
      <input type="hidden" name="r_procedure_type" value="{{ (templateVars.procedureTypes|first).id }}">
      {% endif %}

      <dp-form-row>
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
          :value="mainEmail">
        </dp-input>
      </dp-form-row>

      <dp-form-row>
        <dp-text-area
          :hint="internalNote.hint"
          :id="internalNote.id"
          :name="internalNote.name"
          :label="internalNote.label"
          :value="internalNote.value"
          :reduced-height="internalNote.reducedHeight" />
      </dp-form-row>

      <div class="u-mb-0_75">
<!--        TODO can permission be used for bobhh? -->
        <dp-label
          for="startdate"
          :hint="periodHint"
          :required="hasPermission('feature_auto_switch_to_procedure_end_phase')"
          :text="Translator.trans('period')" />

        <dp-date-range-picker
          class="u-2-of-4"
          start-id="startdate"
          start-name="r_startdate"
          end-id="enddate"
          end-name="r_enddate"
          :required="hasPermission('feature_auto_switch_to_procedure_end_phase')"
          :calendars-after="2"
          :enforce-plausible-dates="true">
        </dp-date-range-picker>

        <p
          v-if="hasPermission('feature_use_plis')"
          class="hide-visually flash"
          id="js__statusBox" />
      </div>

      <div v-if="hasPermission('feature_procedure_couple_by_token')">
        <h3 class="weight--normal color--grey u-mt-1_5">{{ 'procedure.couple_token.vht.title'|trans }}</h3>
        <div> {{ 'procedure.couple_token.vht.info'|trans }}</div>

        <dp-inline-notification
          message="{{ 'procedure.couple_token.vht.inline_notification'|trans }}"
          type="warning">
        </dp-inline-notification>

        <couple-token-input
          token-length="{{ constant('demosplan\\DemosPlanCoreBundle\\Entity\\Procedure\\ProcedureCoupleToken::TOKEN_LENGTH') }}">
        </couple-token-input>
      </div>

      {% set cancelPath = path('DemosPlan_procedure_administration_get') %}
      {{ uiComponent('button-row', {
      primary: uiComponent('button', { type: 'submit', attributes: ['data-cy=saveNewProcedure'] }),
      secondary: uiComponent('button', { color: 'secondary', href: cancelPath })
    }) }}
      <dp-button-row
        :href="Routing.generate('DemosPlan_procedure_administration_get')"
        :primary="true"
        :secondary="true" />
    </fieldset>
  </form>
</template>

<script>
import {
  DpDateRangePicker,
  DpFormRow,
  DpInlineNotification,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpSelect,
  DpTextArea
} from '@demos-europe/demosplan-ui'
import BimschgAntrag from './BimschgAntrag'
import CoupleTokenInput from './CoupleTokenInput'
import { dpApi } from '@demos-europe/demosplan-utils'

export default {
  name: 'DpNewProcedure',
  components: {
    BimschgAntrag,
    CoupleTokenInput,
    DpDateRangePicker,
    DpFormRow,
    DpInput,
    DpLabel,
    DpInlineNotification,
    DpMultiselect,
    DpSelect,
    DpTextArea
  },

  props: {
    blueprintHint: {
      type: String,
      required: false,
      default: ''
    },

    blueprintOptions: {
      type: Array,
      required: false,
      default: () => ([])
    },

    internalNote: {
      type: Object,
      required: false,
      default: () => ({
        hint: '',
        id: 'r_description',
        label: Translator.trans('internalnote'),
        name: 'r_description',
        reducedHeight: true,
        value: ''
      }),
      validator: (prop) => {
        return Object.keys(prop).every(key => ['hint', 'id', 'label', 'name', 'reducedHeight', 'value'].includes(key))
      }
    },

    masterBlueprintId: {
      type: String,
      required: false,
      default: () => ''
    },

    periodHint: {
      type: String,
      required: false,
      default: ''
    },

    plisNameOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    procedureTypes: {
      type: Array,
      required: true,
      default: () => []
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
    }
  }
}
</script>
