<template>
  <div>
    <fieldset class="w-3/4">
      <legend class="font-size-large weight--normal u-mb-0_75">
        {{ Translator.trans('organisation.data') }}
      </legend>

      <div class="u-mb-0_75">
        <!-- Legal Name -->
        <label
          :for="organisation.ident + ':name'"
          class="o-form__label w-full">
          {{ Translator.trans('name.legal') }}
        </label>
        <input
          type="text"
          id="orga_name"
          class="o-form__control-input w-full mt-1 color--grey"
          :name="organisation.ident + ':name'"
          v-model="organisation.nameLegal"
          :disabled="!isOrgaDataEditable"
          required />
      </div>

      <!-- Address -->
      <div class="flex items-start u-mb-0_75">
        <div>
          <label
            :for="organisation.ident + ':address_street'"
            class="o-form__label w-full">
            {{ Translator.trans('street') }}
          </label>
          <input
            type="text"
            id="orga_address_street"
            class="o-form__control-input w-full mt-1 mt-1 color--grey"
            :name="organisation.ident + ':address_street'"
            v-model="organisation.street"
            :disabled="!isOrgaDataEditable" />
        </div>

        <div class="u-mb-0_75">
          <label
            :for="organisation.ident + ':address_houseNumber'"
            class="o-form__label w-full">
            {{ Translator.trans('street.number.short') }}
          </label>
          <input
            type="text"
            id="orga_addressHouseNumber"
            class="o-form__control-input w-full mt-1 color--grey"
            :name="organisation.ident + ':address_houseNumber'"
            v-model="organisation.houseNumber"
            :size="5"
            :disabled="!isOrgaDataEditable" />
        </div>
      </div>

      <!-- Postal Code and City -->
      <div class="flex items-start u-mb-0_75">
        <div class="o-form__group-item shrink">
          <label
            :for="organisation.ident + ':address_postalcode'"
            class="o-form__label w-full">
            {{ Translator.trans('postalcode') }}
          </label>
          <input
            type="text"
            id="orga_address_postalcode"
            class="o-form__control-input w-full mt-1 color--grey"
            :name="organisation.ident + ':address_postalcode'"
            v-model="organisation.postalcode"
            :size="5"
            :pattern="isOrgaDataEditable ? '^[0-9]{5}$' : ''"
            :disabled="!isOrgaDataEditable" />
        </div>

        <div class="o-form__group-item">
          <label
            :for="organisation.ident + ':address_city'"
            class="o-form__label w-full">
            {{ Translator.trans('city') }}
          </label>
          <input
            type="text"
            id="orga_address_city"
            class="o-form__control-input w-full mt-1 color--grey"
            :name="organisation.ident + ':address_city'"
            v-model="organisation.city"
            :disabled="!isOrgaDataEditable" />
        </div>
      </div>

      <!-- Phone -->
      <div
        v-if="hasPermission('field_organisation_phone')"
        class="u-mb-0_75">
        <label
          :for="organisation.ident + ':address_phone'"
          class="o-form__label w-full">
          {{ Translator.trans('phone') }}
        </label>
        <input
          type="tel"
          id="orga_address_phone"
          :name="organisation.ident + ':address_phone'"
          v-model="organisation.phone"
          :disabled="!isOrgaDataEditable" />
      </div>

      <!-- Slug -->
      <div v-if="hasPermission('feature_orga_slug') && hasPermission('feature_orga_slug_edit')">
        <label
          :for="organisation.ident + ':slug'"
          :title="Translator.trans('organisation.procedurelist.slug.explanation')"
          class="o-form__label u-1-of-1">
          {{ Translator.trans('organisation.procedurelist.slug') }}
        </label>

        <p class="inline color--grey align-middle">
          {{ proceduresDirectlinkPrefix }}
        </p>

        <input
          type="text"
          id="orga_slug"
          :name="organisation.ident + ':slug'"
          v-model="organisation.currentSlug.name"
          :class="submittedAuthorClass"
          :data-organisation-id="organisation.ident"
          size="medium" />

        <div>
          <strong>{{ Translator.trans('preview') }}:</strong>
          <p
            :id="organisation.ident + ':urlPreview'"
            :data-shorturl="templateVars.proceduresDirectlinkPrefix + '/'" >
            {{ proceduresDirectlinkPrefix }}/{{ organisation.currentSlug.name || '' }}
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
            {{ proceduresDirectlinkPrefix }}/{{ organisation.currentSlug.name }}
          </dd>
        </div>

        <div v-if="displayCustomer">
          <dt class="weight--bold">
            {{ Translator.trans('customer', { count: organisation.customers.length }) }}
          </dt>
          <dd class="color--grey">
          <span
            v-for="(customer, index) in organisation.customers"
            :key="index">
            {{ customer.name }}<span v-if="index < organisation.customers.length - 1">, </span>
          </span>
          </dd>
        </div>
      </dl>
    </fieldset>

    <!-- Submission type  -->
    <fieldset
      v-if="hasPermission('feature_change_submission_type')"
      class="w-3/4">
      <legend class="font-size-large weight--normal u-mb-0_75">
        {{ Translator.trans('statement.submission.type') }}
      </legend>

      <input
        type="hidden"
        :name="`${organisation.ident || ''}:current_submission_type`"
        :value="organisation.submissionType" />

      <div class="u-mb-0_75">
        <div class="w-full o-form__element--radio">
          <input
            type="radio"
            :name="`${organisation.ident || ''}:submission_type`"
            :value="organisation.submissionType"
            id="submission_type_short"
            :checked="(organisation.submissionType || submissionTypeDefault) === submissionTypeShort" />
          <label
            for="submission_type_short"
            class="o-form__label w-full">
            {{ Translator.trans('statement.submission.shorthand') }}
            <p class="lbl__hint">
              {{ Translator.trans('explanation.statement.submit.process.short') }}
            </p>
          </label>
        </div>

        <div class="w-full o-form__element--radio">
          <input
            type="radio"
            :name="`${organisation.ident || ''}:submission_type`"
            :value="organisation.submissionType"
            id="submission_type_default"
            :checked="(organisation.submissionType || submissionTypeDefault) === submissionTypeDefault" />
          <label
            for="submission_type_default"
            class="o-form__label w-full">
            {{ Translator.trans('statement.submission.default') }}
            <p class="lbl__hint">
              {{ Translator.trans('explanation.statement.submit.process.default') }}
            </p>
          </label>
        </div>
      </div>
    </fieldset>
  </div>
</template>

<script>
export default {
  name: 'OrganisationDataEntry',

  props: {
    organisation:  {
      type: Object,
      required: false,
      default: {}
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
    submittedAuthorClass: {
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
    }
  },
  computed: {
    displaySlug() {
      return hasPermission('feature_orga_slug') &&
        !hasPermission('feature_orga_slug_edit') &&
        this.organisation.currentSlug.name !== ''
    },

    displayCustomer() {
      return hasPermission('feature_display_customer_names') &&
        this.organisation.customers && this.organisation.customers.length > 0
    }
  }
}
</script>
