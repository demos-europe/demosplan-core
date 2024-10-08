<template>
  <fieldset class="u-3-of-4">
    <legend class="font-size-large weight--normal u-mb-0_75">
      {{ Translator.trans('organisation.data') }}
    </legend>

    <div class="o-form__group u-mb-0_75">
      <!-- Legal Name -->
      <div class="o-form__group-item">
        <label
          :for="organisation.ident + ':name'"
          class="o-form__label u-1-of-1">
          {{ Translator.trans('name.legal') }}
        </label>
        <input
          type="text"
          :id="'orga_name'"
          class="o-form__group-item u-1-of-1 o-form__control-input color--grey"
          :name="organisation.ident + ':name'"
          v-model="organisation.nameLegal"
          :disabled="!isOrgaDataEditable"
          required />
      </div>
    </div>

    <!-- Address -->
    <div class="o-form__group u-mb-0_75">
      <div class="o-form__group-item shrink">
        <label
          :for="organisation.ident + ':address_street'"
          class="o-form__label u-1-of-1">
          {{ Translator.trans('street') }}
        </label>
        <input
          type="text"
          :id="'orga_address_street'"
          class="o-form__group-item u-1-of-1 o-form__control-input color--grey"
          :name="organisation.ident + ':address_street'"
          v-model="organisation.street"
          :disabled="!isOrgaDataEditable" />
      </div>

      <div class="o-form__group-item shrink u-mb-0_75">
        <label
          :for="organisation.ident + ':address_houseNumber'"
          class="o-form__label u-1-of-1">
          {{ Translator.trans('street.number.short') }}
        </label>
        <input
          type="text"
          :id="'orga_addressHouseNumber'"
          class="o-form__group-item u-1-of-1 o-form__control-input color--grey"
          :name="organisation.ident + ':address_houseNumber'"
          v-model="organisation.houseNumber"
          :size="5"
          :disabled="!isOrgaDataEditable" />
      </div>
    </div>

    <!-- Postal Code and City -->
    <div class="o-form__group u-mb-0_75">
      <div class="o-form__group-item shrink">
        <label
          :for="organisation.ident + ':address_postalcode'"
          class="o-form__label u-1-of-1">
          {{ Translator.trans('postalcode') }}
        </label>
        <input
          type="text"
          :id="'orga_address_postalcode'"
          class="o-form__group-item u-1-of-1 o-form__control-input color--grey"
          :name="organisation.ident + ':address_postalcode'"
          v-model="organisation.postalcode"
          :size="5"
          :pattern="isOrgaDataEditable ? '^[0-9]{5}$' : ''"
          :disabled="!isOrgaDataEditable" />
      </div>

      <div class="o-form__group-item">
        <label
          :for="organisation.ident + ':address_city'"
          class="o-form__label u-1-of-1">
          {{ Translator.trans('city') }}
        </label>
        <input
          type="text"
          :id="'orga_address_city'"
          class="o-form__group-item u-1-of-1 o-form__control-input color--grey"
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
        class="o-form__label u-1-of-1">
        {{ Translator.trans('phone') }}
      </label>
      <input
        type="tel"
        :id="'orga_address_phone'"
        :name="organisation.ident + ':address_phone'"
        v-model="organisation.phone"
        :disabled="!isOrgaDataEditable" />
    </div>

    <!-- Slug (if permission) -->
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
        :id="'orga_slug'"
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
        <dt class="weight--bold">{{ Translator.trans('organisation.procedurelist.slug') }}</dt>
        <dd class="color--grey">{{ templateVars.proceduresDirectlinkPrefix }}/{{ organisation.currentSlug.name }}</dd>
      </div>

      <div v-if="displayCustomer">
        <dt class="weight--bold">
          {{ Translator.trans('customer', { count: organisation.customers.length }) }}
        </dt>
        <dd class="color--grey">
          <span v-for="(customer, index) in organisation.customers" :key="index">
            {{ customer.name }}<span v-if="index < organisation.customers.length - 1">, </span>
          </span>
        </dd>
      </div>

    </dl>
  </fieldset>
</template>

<script>
export default {
  name: 'OrgaDataEntry',

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



<style scoped>

</style>
