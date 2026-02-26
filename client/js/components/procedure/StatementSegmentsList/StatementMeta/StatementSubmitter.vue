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
      class="mb-3 color-text-muted font-normal"
    >
      {{ Translator.trans('submitted.author') }}
    </legend>

    <!--  TO DO: add if not participationGuestOnly  -->
    <div
      v-if="hasPermission('field_statement_meta_orga_name')"
      aria-labelledby="submitter"
      class="mb-2"
    >
      {{ submitterRole }}
    </div>

    <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
      <dp-input
        v-if="hasPermission('field_statement_meta_orga_department_name') && !localStatement.attributes.isSubmittedByCitizen"
        id="statementDepartmentName"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('department')
        }"
        :model-value="getDisplayValue(localStatement.attributes.initialOrganisationDepartmentName)"
        class="mb-2"
        data-cy="statementSubmitter:departmentName"
        @update:model-value="value => localStatement.attributes.initialOrganisationDepartmentName = value"
      />

      <!--  TO DO: add if not participationGuestOnly -->
      <dp-input
        v-if="!localStatement.attributes.isSubmittedByCitizen"
        id="statementOrgaName"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('organisation')
        }"
        :model-value="getDisplayValue(localStatement.attributes.initialOrganisationName)"
        class="mb-2"
        data-cy="statementSubmitter:orgaName"
        @update:model-value="value => localStatement.attributes.initialOrganisationName = value"
      />

      <dp-contextual-help
        v-if="isSubmitterAnonymized()"
        :text="submitterHelpText"
        class="float-right mt-0.5"
      />
      <dp-input
        v-if="hasPermission('field_statement_meta_submit_name') && statementFormDefinitions.name.enabled"
        id="statementSubmitterName"
        :disabled="!isStatementManual || !editable || isSubmitterAnonymized()"
        :label="{
          text: Translator.trans('name')
        }"
        :model-value="getSubmitterNameValue()"
        class="mb-2"
        data-cy="statementSubmitter:submitterName"
        @update:model-value="value => localStatement.attributes[statementSubmitterField] = value"
      />

      <dp-input
        v-if="hasPermission('field_statement_submitter_email_address') || isStatementManual"
        id="statementEmailAddress"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('email')
        }"
        :model-value="getDisplayValue(localStatement.attributes.submitterEmailAddress)"
        class="mb-2"
        data-cy="statementSubmitter:emailAddress"
        type="email"
        @update:model-value="value => localStatement.attributes.submitterEmailAddress = value"
      />

      <dp-input
        v-if="localStatement.attributes.represents"
        id="statementRepresentation"
        :label="{
          text: Translator.trans('statement.representation.assessment')
        }"
        :model-value="localStatement.attributes.represents"
        data-cy="statementSubmitter:representation"
        disabled
      />
      <dp-checkbox
        v-if="localStatement.attributes.represents"
        id="representationCheck"
        v-model="localStatement.attributes.representationChecked"
        :disabled="!editable || !isStatementManual"
        :label="{
          text: Translator.trans('statement.representation.checked')
        }"
        data-cy="statementSubmitter:representationCheck"
      />

      <div class="o-form__group mb-2">
        <dp-input
          id="statementStreet"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('street')
          }"
          :model-value="getDisplayValue(localStatement.attributes.initialOrganisationStreet)"
          class="o-form__group-item"
          data-cy="statementSubmitter:street"
          @update:model-value="value => localStatement.attributes.initialOrganisationStreet = value"
        />
        <dp-input
          id="statementHouseNumber"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('street.number.short')
          }"
          :size="3"
          :model-value="getDisplayValue(localStatement.attributes.initialOrganisationHouseNumber)"
          class="o-form__group-item !w-1/5 shrink"
          data-cy="statementSubmitter:houseNumber"
          @update:model-value="value => localStatement.attributes.initialOrganisationHouseNumber = value"
        />
      </div>
      <div class="o-form__group mb-2">
        <dp-input
          id="statementPostalCode"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('postalcode')
          }"
          :model-value="getDisplayValue(localStatement.attributes.initialOrganisationPostalCode)"
          class="o-form__group-item !w-1/4 shrink"
          data-cy="statementSubmitter:postalCode"
          pattern="^[0-9]{4,5}$"
          @update:model-value="value => localStatement.attributes.initialOrganisationPostalCode = value"
        />
        <dp-input
          id="statementCity"
          :disabled="!editable || !isStatementManual"
          :label="{
            text: Translator.trans('city')
          }"
          :model-value="getDisplayValue(localStatement.attributes.initialOrganisationCity)"
          class="o-form__group-item"
          data-cy="statementSubmitter:city"
          @update:model-value="value => localStatement.attributes.initialOrganisationCity = value"
        />
      </div>
    </div>

    <dp-button-row
      v-if="editable && isStatementManual"
      class="mt-2 w-full"
      primary
      secondary
      @primary-action="dpValidateAction('statementSubmitterData', save, false)"
      @secondary-action="reset"
    />

    <similar-statement-submitters
      v-if="hasPermission('feature_similar_statement_submitter')"
      :editable="editable"
      :procedure-id="procedure.id"
      :similar-statement-submitters="similarStatementSubmitters"
      :statement-id="statement.id"
    />
  </fieldset>
</template>

<script>
import {
  DpButtonRow,
  DpCheckbox,
  DpInput,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'
import { mapState } from 'vuex'
import SimilarStatementSubmitters from '@DpJs/components/procedure/Shared/SimilarStatementSubmitters/SimilarStatementSubmitters'

export default {
  name: 'StatementSubmitter',

  components: {
    DpButtonRow,
    DpCheckbox,
    DpInput,
    SimilarStatementSubmitters,
  },

  mixins: [dpValidateMixin],

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false,
    },

    procedure: {
      type: Object,
      required: true,
    },

    statement: {
      type: Object,
      required: true,
    },

    statementFormDefinitions: {
      required: true,
      type: Object,
    },
  },

  emits: [
    'save',
  ],

  data () {
    return {
      localStatement: null,
    }
  },

  computed: {
    ...mapState('Statement', {
      statements: 'items',
    }),

    isStatementManual () {
      return this.localStatement.attributes.isManual
    },

    similarStatementSubmitters () {
      if (typeof this.statement.hasRelationship === 'function' && this.statement.hasRelationship('similarStatementSubmitters')) {
        return Object.values(this.statement.relationships.similarStatementSubmitters.list())
      }
      return null
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
    getDisplayValue (value) {
      const isDisabled = !this.editable || !this.isStatementManual

      return (isDisabled && (!value || value.trim() === '')) ? '-' : value
    },

    getSubmitterNameValue () {
      if (this.isSubmitterAnonymized()) {
        return Translator.trans('anonymized')
      }

      const value = this.localStatement.attributes[this.statementSubmitterField]

      return this.getDisplayValue(value)
    },

    isSubmitterAnonymized () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes

      return consentRevoked || submitterAndAuthorMetaDataAnonymized
    },

    reset () {
      this.setInitValues()
    },

    save () {
      // Get current statement from store (includes any relationship changes from SimilarStatementSubmitter)
      const currentStatement = this.statements[this.statement.id]

      const updatedStatement = {
        ...currentStatement,
        attributes: {
          ...currentStatement.attributes,
          initialOrganisationDepartmentName: this.localStatement.attributes.initialOrganisationDepartmentName,
          initialOrganisationName: this.localStatement.attributes.initialOrganisationName,
          authorName: this.localStatement.attributes.authorName,
          submitName: this.localStatement.attributes.submitName,
          submitterEmailAddress: this.localStatement.attributes.submitterEmailAddress,
          representationChecked: this.localStatement.attributes.representationChecked,
          initialOrganisationStreet: this.localStatement.attributes.initialOrganisationStreet,
          initialOrganisationHouseNumber: this.localStatement.attributes.initialOrganisationHouseNumber,
          initialOrganisationPostalCode: this.localStatement.attributes.initialOrganisationPostalCode,
          initialOrganisationCity: this.localStatement.attributes.initialOrganisationCity,
        },
      }

      this.$emit('save', updatedStatement)
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
    },
  },

  created () {
    this.setInitValues()
  },
}
</script>
