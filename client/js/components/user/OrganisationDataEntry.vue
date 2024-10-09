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

    <!-- Email Notifications -->
    <fieldset class="w-3/4">
      <legend v-if="user.isPublicAgency || hasPermission('field_organisation_email2_cc') ||
      (hasPermission('feature_organisation_email_reviewer_admin') && hasPermission('field_organisation_email_reviewer_admin'))">
        {{ Translator.trans('email.notifications') }}
      </legend>

      <!-- Email2 address for Public Agencies -->
      <div
        v-if="user.isPublicAgency"
        class="form-row">
        <label :for="`${organisation.ident}:email2`">
          {{ Translator.trans('email.participation') }}
          <p class="lbl__hint">
            {{ Translator.trans('explanation.organisation.email.participation') }}
          </p>
        </label>
        <input
          type="email"
          :name="`${organisation.ident}:email2`"
          id="orga_email2"
          v-model="organisation.email2"
          required />
      </div>

      <!-- ccEmail2 for adding extra addresses for participation invitations -->
      <div
        v-if="user.isPublicAgency && hasPermission('field_organisation_email2_cc')"
        class="form-row">
        <label :for="`${organisation.ident}:ccEmail2`">
          {{ Translator.trans('email.cc.participation') }}
          <span class="lbl__hint">
            {{ Translator.trans('explanation.organisation.email.cc') }}
          </span>
        </label>
        <input
          type="text"
          :name="`${organisation.ident}:ccEmail2`"
          id="orga_ccEmail2"
          v-model="organisation.ccEmail2" />
      </div>

      <!-- Email for reviewer admins section (PLANNING_SUPPORTING_DEPARTMENT users may specify an email address to receive notifications whenever a fragment is assigned to someone) -->
      <div
        v-if="hasPermission('feature_organisation_email_reviewer_admin') && hasPermission('field_organisation_email_reviewer_admin')"
        class="form-row">
        <label :for="`${organisation.ident}:emailReviewerAdmin`">
          {{ Translator.trans('email.reviewer.admin') }}
          <span class="lbl__hint">
            {{ Translator.trans('explanation.organisation.email.reviewer.admin') }}
          </span>
        </label>
        <input
          type="email"
          :name="`${organisation.ident}:emailReviewerAdmin`"
          id="orga_emailReviewerAdmin"
          v-model="organisation.emailReviewerAdmin" />
      </div>

      <!-- Notifications Settings Section -->
      <div v-if="showNotificationsSection">
        <p class="o-form__label u-mb-0_25">
          {{ Translator.trans('email.notifications') }}
        </p>

        <!-- New Statement Notification -->
        <div
          v-if="willReceiveNewStatementNotification && hasPermission('feature_notification_statement_new')"
          class="u-1-of-1 o-form__element--checkbox">
          <label
            for="orga_emailNotificationNewStatement">
            {{ Translator.trans('explanation.notification.new.statement') }}
          </label>
          <input
            type="checkbox"
            :name="`${organisation.ident}:emailNotificationNewStatement`"
            id="orga_emailNotificationNewStatement"
            class="o-form__control-input"
            :checked="organisation.emailNotificationNewStatement.content" />
        </div>

        <!-- Ending Phase Notification -->
        <div
          v-if="user.isPublicAgency && hasPermission('feature_notification_ending_phase')"
          class="u-1-of-1 o-form__element--checkbox">
          <label
            for="orga_emailNotificationEndingPhase">
            {{ Translator.trans('explanation.notification.phase.ending') }}
          </label>
          <input
            type="checkbox"
            :name="`${organisation.ident}:emailNotificationEndingPhase`"
            :id="`orga_emailNotificationEndingPhase`"
            class="o-form__control-input"
            :checked="organisation.emailNotificationEndingPhase?.content" />
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
    },
    user: {
      type: Object,
      required: true
    },
    willReceiveNewStatementNotification: {
      type: Boolean,
      required: false,
      default: false
    },
    customers: {
      type: Array,
      required: true
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
    },

    showNotificationsSection() {
      return (this.willReceiveNewStatementNotification && this.hasPermission('feature_notification_statement_new')) ||
        (this.user.isPublicAgency && this.hasPermission('feature_notification_ending_phase'))
    }
  }
}
</script>
