<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset data-dp-validate="statementSubmitterData">
    <legend
      id="submitter"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans('submitted.author') }}
    </legend>

    <!--  TO DO: add if not participationGuestOnly  -->
    <div
      v-if="hasPermission('field_statement_meta_orga_name')"
      aria-labelledby="submitter"
      class="mb-2">
      {{ submitterRole }}
    </div>

    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
      <dp-input
        v-if="hasPermission('field_statement_meta_orga_department_name') && !this.localStatement.attributes.isSubmittedByCitizen"
        id="statementDepartmentName"
        v-model="localStatement.attributes.initialOrganisationDepartmentName"
        class="mb-2"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('department')
        }" />

      <!--  TO DO: add if not participationGuestOnly -->
      <dp-input
        v-if="!this.localStatement.attributes.isSubmittedByCitizen"
        id="statementOrgaName"
        v-model="localStatement.attributes.initialOrganisationName"
        class="mb-2"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('organisation')
        }" />

      <dp-contextual-help
        v-if="isSubmitterAnonymized()"
        class="float-right mt-0.5"
        :text="submitterHelpText" />
      <dp-input
        v-if="hasPermission('field_statement_meta_submit_name') && this.statementFormDefinitions.name.enabled"
        id="statementSubmitterName"
        v-model="statementSubmitterValue"
        class="mb-2"
        :disabled="!isStatementManual || !editable || isSubmitterAnonymized()"
        :label="{
          text: Translator.trans('name')
        }" />

      <dp-input
        v-if="hasPermission('field_statement_submitter_email_address') || isStatementManual"
        id="statementEmailAddress"
        v-model="localStatement.attributes.submitterEmailAddress"
        class="mb-2"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('email')
        }"
        type="email" />

      <dp-input
        v-if="localStatement.attributes.represents"
        id="statementRepresentation"
        disabled
        :label="{
          text: Translator.trans('statement.representation.assessment')
        }"
        :value="localStatement.attributes.represents" />
<!--      // TO DO: DpCheckbox?-->
      <dp-input
        v-if="localStatement.attributes.represents"
        id="representationCheck"
        v-model="localStatement.attributes.representationChecked"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('statement.representation.checked')
        }"
        type="checkbox" />

      <div class="o-form__group mb-2">
        <dp-input
          id="statementStreet"
          v-model="localStatement.attributes.initialOrganisationStreet"
          class="o-form__group-item"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('street')
          }" />
        <dp-input
          id="statementHouseNumber"
          v-model="localStatement.attributes.initialOrganisationHouseNumber"
          class="o-form__group-item shrink"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('street.number.short')
          }"
          :size="3" />
      </div>
      <div class="o-form__group mb-2">
        <dp-input
          id="statementPostalCode"
          v-model="localStatement.attributes.initialOrganisationPostalCode"
          class="o-form__group-item shrink"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('postalcode')
          }"
          pattern="^[0-9]{4,5}$"
          :size="5" />
        <dp-input
          id="statementCity"
          v-model="localStatement.attributes.initialOrganisationCity"
          class="o-form__group-item"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('city')
          }" />
      </div>
    </div>

    <dp-button-row
      v-if="editable"
      class="mt-2 w-full"
      primary
      secondary
      @primary-action="dpValidateAction('statementSubmitterData', save, false)"
      @secondary-action="reset" />
  </fieldset>
</template>

<script>
import {
  DpButtonRow,
  DpInput,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
export default {
  name: 'StatementSubmitter',

  components: {
    DpButtonRow,
    DpInput
  },

  mixins: [dpValidateMixin],

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    statement: {
      type: Object,
      required: true
    },

    statementFormDefinitions: {
      required: true,
      type: Object
    }
  },

  data () {
    return {
      localStatement: null
    }
  },

  computed: {
    isStatementManual() {
      return this.localStatement.attributes.isManual
    },

    statementSubmitterField () {
      const attr = this.localStatement.attributes
      let submitterField = 'authorName'
      // If submitter is an orga and name has a value
      if (attr.submitName && !attr.isSubmittedByCitizen) {
        submitterField = 'submitName'
      }

      return submitterField
    },

    statementSubmitterValue: {
      get () {
        return this.isSubmitterAnonymized() ? Translator.trans('anonymized') : this.localStatement.attributes[this.statementSubmitterField]
      },

      set (value) {
        this.localStatement.attributes[this.statementSubmitterField] = value
      }
    },

    submitterHelpText () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes
      let helpText = ''

      const isAnonymized = hasPermission('area_statement_anonymize') && submitterAndAuthorMetaDataAnonymized

      if (consentRevoked) {
        helpText = Translator.trans('personal.data.usage.revoked')

        if (isAnonymized) {
          helpText = helpText + `<br><br>${Translator.trans('statement.anonymized.submitter.data')}`
        }
      }

      if (!consentRevoked && isAnonymized) {
        helpText = Translator.trans('statement.anonymized.submitter.data')
      }

      return helpText
    },

    submitterRole () {
      const isSubmittedByCitizen = this.localStatement.attributes.isSubmittedByCitizen &&
        this.localStatement.attributes.submitterRole !== 'publicagency'

      return isSubmittedByCitizen ? Translator.trans('role.citizen') : Translator.trans('institution')
    },
  },

  methods: {
    isSubmitterAnonymized () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes

      return consentRevoked || submitterAndAuthorMetaDataAnonymized
    },

    reset () {
      this.setInitValues()
    },

    save () {
      this.$emit('save', this.localStatement)
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
    },
  },

  created() {
    this.setInitValues()
  },
}
</script>
