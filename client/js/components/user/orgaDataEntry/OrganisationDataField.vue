<template>
  <div>
    <fieldset class="w-3/4">
      <legend class="font-size-large weight--normal mb-3">
        {{ Translator.trans('organisation.data') }}
      </legend>

      <template v-if="displayFullData">
        <!-- Name -->
        <dp-input
          id="orga_name"
          v-model="organisation.name"
          class="mb-2"
          data-cy="organisationData:name"
          :name="`${organisation.id}:name`"
          :label="{
            text: Translator.trans('name.legal')
          }"
          :disabled="!isOrgaDataEditable"
          required />

        <!-- Street -->
        <div class="flex items-start gap-1 mb-2">
          <dp-input
            id="orga_address_street"
            v-model="organisation.street"
            data-cy="organisationData:address:street"
            :name="`${organisation.id}:address_street`"
            :label="{
              text: Translator.trans('street')
            }"
            :size="organisation.street.length"
            :disabled="!isOrgaDataEditable" />

          <dp-input
            id="orga_addressHouseNumber"
            v-model="organisation.houseNumber"
            data-cy="organisationData:address:houseNumber"
            :name="`${organisation.id}:address_houseNumber`"
            :label="{
            text: Translator.trans('street.number.short')
          }"
            :size="5"
            :disabled="!isOrgaDataEditable" />
        </div>

        <!-- Postal Code and City -->
        <div class="flex items-start gap-1 mb-2">
          <dp-input
            id="orga_address_postalcode"
            v-model="organisation.postalcode"
            data-cy="organisationData:address:postalcode"
            class="shrink"
            :name="`${organisation.id}:address_postalcode`"
            :label="{
              text: Translator.trans('postalcode')
            }"
            :pattern="isOrgaDataEditable ? '^[0-9]{5}$' : ''"
            :size="5"
            :disabled="!isOrgaDataEditable" />

          <dp-input
            id="orga_address_city"
            v-model="organisation.city"
            data-cy="organisationData:address:city"
            :name="`${organisation.id}:address_city`"
            :label="{
              text: Translator.trans('city')
            }"
            :disabled="!isOrgaDataEditable" />
        </div>

        <!-- Phone -->
        <dp-input
          v-if="hasPermission('field_organisation_phone')"
          id="orga_address_phone"
          class="mb-2"
          v-model="organisation.phone"
          data-cy="organisationData:phone"
          :name="`${organisation.id}:address_phone`"
          :label="{
            text: Translator.trans('phone')
          }"
          :disabled="!isOrgaDataEditable" />

        <!-- Slug -->
        <div v-if="hasPermission('feature_orga_slug') && hasPermission('feature_orga_slug_edit')">
          <label
            for="orga_slug"
            class="o-form__label">
            {{ Translator.trans('organisation.procedurelist.slug') }}
          </label>
          <small class="lbl_hint block">
            {{ Translator.trans('organisation.procedurelist.slug.explanation') }}
          </small>

          <div class="flex flex-row items-center">
          <span class="color--grey">
            {{ proceduresDirectlinkPrefix }}
          </span>
            <dp-input
              id="orga_slug"
              v-model="organisation.currentSlugName"
              data-cy="organisationData:currentSlugName"
              :data-organisation-id="organisation.id"
              :name="`${organisation.id}:slug`" />
          </div>

          <div>
            <label
              :for="`${organisation.id}:urlPreview`"
              class="o-form__label">
              {{ Translator.trans('preview') }}
            </label>
            <p
              :id="`${organisation.id}:urlPreview`"
              :data-shorturl="proceduresDirectlinkPrefix + '/'" >
              {{ proceduresDirectlinkPrefix }}/{{ organisation.currentSlugName || '' }}
            </p>
          </div>
        </div>

        <!-- Display Slug and Customer List -->
        <dl
          v-if="displaySlug || displayCustomer"
          class="description-list space-stack-s">
          <div v-if="displaySlug">
            <dt class="weight--bold">
              {{ Translator.trans('organisation.procedurelist.slug') }}
            </dt>
            <dd class="color--grey">
              {{ proceduresDirectlinkPrefix }}/{{ organisation.currentSlugName }}
            </dd>
          </div>

          <div v-if="displayCustomer">
            <dt class="weight--bold">
              {{ Translator.trans('customer', { count: customers.length }) }}
            </dt>
            <dd
              v-for="(customer, index) in customers"
              :key="customer.id"
              class="color--grey">
              {{ customer.name }}<span v-if="index < customers.length - 1">, </span>
            </dd>
          </div>
        </dl>
      </template>

      <template v-else>
        <div>
          <dp-input
            id="orga_name"
            v-model="organisation.name"
            class="mb-2 layout__item"
            data-cy="organisationData:name"
            :name="`${organisation.id}:name`"
            :label="{
              text: Translator.trans('name.legal')
            }"
            :disabled="!isOrgaDataEditable" />

          <dp-input
            id="orga_address_street"
            v-model="organisation.street"
            class="mb-2 layout__item"
            data-cy="organisationData:address:street"
            :name="`${organisation.id}:address_street`"
            :label="{
              text: Translator.trans('street')
            }"
            :size="organisation.street.length"
            :disabled="!isOrgaDataEditable" />

          <dp-input
            id="orga_address_postalcode"
            v-model="organisation.postalcode"
            data-cy="organisationData:address:postalcode"
            class="layout__item"
            :name="`${organisation.id}:address_postalcode`"
            :label="{
              text: Translator.trans('postalcode')
            }"
            :pattern="isOrgaDataEditable ? '^[0-9]{5}$' : ''"
            :size="5"
            :disabled="!isOrgaDataEditable" />

          <dp-input
            id="orga_address_city"
            v-model="organisation.city"
            class="layout__item mb-2"
            data-cy="organisationData:address:city"
            :name="`${organisation.id}:address_city`"
            :label="{
              text: Translator.trans('city')
            }"
            :disabled="!isOrgaDataEditable" />

          <dp-input
            v-if="hasPermission('field_organisation_phone')"
            id="orga_address_phone"
            class="mb-2 layout__item"
            v-model="organisation.phone"
            data-cy="organisationData:phone"
            :name="`${organisation.id}:address_phone`"
            :label="{
              text: Translator.trans('phone')
            }"
            :disabled="!isOrgaDataEditable" />
        </div>
      </template>
    </fieldset>

    <!-- Submission type -->
    <fieldset
      v-if="showSubmissionTypeSection"
      id="submissionType"
      class="w-3/4 mb-2">
      <legend class="font-size-large weight--normal mb-3">
        {{ Translator.trans('statement.submission.type') }}
      </legend>
      <input
        type="hidden"
        :name="`${organisation.id || ''}:current_submission_type`"
        :value="organisation.submissionType" />
      <dp-radio
        id="submission_type_short"
        :name="`${organisation.id || ''}:submission_type`"
        :value="submissionTypeShort"
        data-cy="organisationData:submissionType:short"
        :label="{
          text: Translator.trans('statement.submission.shorthand'),
          bold: true,
          hint: Translator.trans('explanation.statement.submit.process.short')
        }"
        :checked="organisation.submissionType === submissionTypeShort" />
      <dp-radio
        id="submission_type_default"
        :name="`${organisation.id || ''}:submission_type`"
        :value="submissionTypeDefault"
        data-cy="organisationData:submissionType:default"
        :label="{
          text: Translator.trans('statement.submission.default'),
          bold: true,
          hint: Translator.trans('explanation.statement.submit.process.default')
        }"
        :checked="organisation.submissionType === submissionTypeDefault" />
    </fieldset>
  </div>
</template>

<script>
import { DpInput, DpSelect, DpRadio, DpTextArea, DpEditor, DpCheckbox } from '@demos-europe/demosplan-ui'

export default {
  name: 'OrganisationDataField',

  components: {
    DpRadio,
    DpInput,
    DpTextArea,
    DpSelect,
    DpEditor,
    DpCheckbox
  },

  props: {
    displayFullData: {
      type: Boolean,
      required: false,
      default: false
    },

    organisation:  {
      type: Object,
      required: true
    },

    isOrgaDataEditable: {
      type: Boolean,
      required: true
    },

    proceduresDirectlinkPrefix: {
      type: String,
      required: false,
      default: ''
    },

    submissionTypeDefault: {
      type: String,
      required: false,
      default: ''
    },

    submissionTypeShort: {
      type: String,
      required: false,
      default: ''
    },

    customers: {
      type: Array,
      required: false,
      default: () => ([])
    }
  },

  computed: {
    displaySlug () {
      return hasPermission('feature_orga_slug') &&
        !hasPermission('feature_orga_slug_edit') &&
        this.organisation.currentSlugName !== ''
    },

    displayCustomer () {
      return hasPermission('feature_display_customer_names') &&
        this.customers && this.customers.length > 0
    },

    showSubmissionTypeSection () {
      return hasPermission('feature_change_submission_type')
    }
  }
}
</script>
