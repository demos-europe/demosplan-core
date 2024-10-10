<template>
  <div>
    <fieldset class="w-3/4">
      <legend class="font-size-large weight--normal mb-3">
        {{ Translator.trans('organisation.data') }}
      </legend>

      <div class="mb-2">
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
      <div class="flex items-start mb-2">
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

        <div>
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
      <div class="flex items-start mb-2">
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
        class="mb-2">
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
          class="o-form__label w-full">
          {{ Translator.trans('organisation.procedurelist.slug') }}
        </label>

        <p class="inline color--grey align-middle">
          {{ proceduresDirectlinkPrefix }}
        </p>

        <input
          type="text"
          id="orga_slug"
          :name="organisation.ident + ':slug'"
          v-model="organisation.currentSlugName"
          :class="submittedAuthorClass"
          :data-organisation-id="organisation.ident"
          size="medium" />

        <div>
          <strong>{{ Translator.trans('preview') }}:</strong>
          <p
            :id="organisation.ident + ':urlPreview'"
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
    </fieldset>

    <!-- Submission type  -->
    <fieldset
      v-if="hasPermission('feature_change_submission_type')"
      class="w-3/4">
      <legend class="font-size-large weight--normal mb-3">
        {{ Translator.trans('statement.submission.type') }}
      </legend>

      <input
        type="hidden"
        :name="`${organisation.ident || ''}:current_submission_type`"
        :value="organisation.submissionType" />

      <div class="mb-2">
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
            <small class="lbl__hint block">
              {{ Translator.trans('explanation.statement.submit.process.short') }}
            </small>
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
            <small class="lbl__hint block">
              {{ Translator.trans('explanation.statement.submit.process.default') }}
            </small>
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
        class="w-full mb-3">
        <label
          :for="`${organisation.ident}:email2`"
          class="o-form__label w-full">
          {{ Translator.trans('email.participation') }}
          <small class="lbl__hint block">
            {{ Translator.trans('explanation.organisation.email.participation') }}
          </small>
        </label>
        <input
          type="email"
          :name="`${organisation.ident}:email2`"
          id="orga_email2"
          class="u-1-of-1 o-form__control-input"
          v-model="organisation.email2"
          required />
      </div>

      <!-- ccEmail2 for adding extra addresses for participation invitations -->
      <div
        v-if="user.isPublicAgency && hasPermission('field_organisation_email2_cc')"
        class="w-full mb-3">
        <label
          :for="`${organisation.ident}:ccEmail2`"
          class="o-form__label w-full">
          {{ Translator.trans('email.cc.participation') }}
          <small class="lbl__hint block">
            {{ Translator.trans('explanation.organisation.email.cc') }}
          </small>
        </label>
        <input
          type="text"
          :name="`${organisation.ident}:ccEmail2`"
          id="orga_ccEmail2"
          class="u-1-of-1 o-form__control-input"
          v-model="organisation.ccEmail2" />
      </div>

      <!-- Email for reviewer admins section (PLANNING_SUPPORTING_DEPARTMENT users may specify an email address to receive notifications whenever a fragment is assigned to someone) -->
      <div
        v-if="hasPermission('feature_organisation_email_reviewer_admin') && hasPermission('field_organisation_email_reviewer_admin')"
        class="w-full mb-3">
        <label
          :for="`${organisation.ident}:emailReviewerAdmin`"
          class="o-form__label w-full">
          {{ Translator.trans('email.reviewer.admin') }}
          <small class="lbl__hint block">
            {{ Translator.trans('explanation.organisation.email.reviewer.admin') }}
          </small>
        </label>
        <input
          type="email"
          :name="`${organisation.ident}:emailReviewerAdmin`"
          id="orga_emailReviewerAdmin"
          class="w-full o-form__control-input"
          v-model="organisation.emailReviewerAdmin" />
      </div>

      <!-- Notifications Settings Section -->
      <div v-if="showNotificationsSection">
        <p class="o-form__label mb-1">
          {{ Translator.trans('email.notifications') }}
        </p>

        <!-- New Statement Notification -->
        <div
          v-if="willReceiveNewStatementNotification && hasPermission('feature_notification_statement_new')"
          class="w-full o-form__element--checkbox">
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
          class="w-full o-form__element--checkbox">
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

    <!-- Paper copy -->
    <fieldset
      v-if="displayPaperCopySection"
      class="w-3/4">
      <legend class="font-size-large weight--normal u-mb-0_75">
        {{ Translator.trans('copies.paper') }}
      </legend>

      <div
        v-if="hasPermission('field_organisation_paper_copy')"
        class="w-full mb-3">
        <dp-select
          id="orga_paperCopy"
          :name="`${organisation.ident || ''}:paperCopy`"
          v-model="organisation.paperCopy"
          data-cy="orgaDataEntry:paperCopy:select"
          :label="{
            text: Translator.trans('copies.paper'),
            hint: Translator.trans('explanation.organisation.copies.paper')
          }"
          :selected="organisation.paperCopy"
          :options="paperCopyCountOptions">
        </dp-select>
      </div>

      <div
        v-if="hasPermission('field_organisation_paper_copy_spec')"
        class="w-full mb-3">
        <dp-text-area
          id="orga_paperCopySpec"
          data-cy="orgaDataEntry:paperCopy:specification"
          :name="`${organisation.ident || ''}:paperCopySpec`"
          :value="organisation.paperCopySpec"
          :label="Translator.trans('copies.kind')"
          :hint="Translator.trans('explanation.organisation.copies.kind')" />
      </div>

      <div
        v-if="hasPermission('field_organisation_competence')"
        class="w-full mb-3">
        <dp-text-area
          id="orga_competence"
          data-cy="orgaDataEntry:paperCopy:competence"
          :name="`${organisation.ident || ''}:competence`"
          :value="organisation.competence"
          :label="Translator.trans('competence.explanation')"
          :hint="Translator.trans('explanation.organisation.competence')" />
      </div>
    </fieldset>

    <!-- Organisation Branding -->
    <fieldset
      v-if="displayOrganisationBranding"
      class="w-3/4">
      <legend v-if="hasPermission('feature_orga_logo_edit')">
        <span>{{ Translator.trans('organisation.procedures.branding') }}</span>
        <span>{{ brandingExplanation }}</span>
      </legend>
      <legend v-else>
        <span>{{ Translator.trans('organisation.procedures.branding') }}</span>
      </legend>

      <!-- Data Protection Section -->
      <div
        v-if="hasPermission('field_data_protection_text_customized_edit_orga')"
        class="o-form__label w-full">
        <label :for="organisation.ident + ':data_protection'">
          {{ Translator.trans('data.protection.notes') }}
          <small class="lbl__hint">{{ Translator.trans('customer.data.protection.explanation') }}</small>
        </label>
        <editor
          :id="organisation.ident + ':data_protection'"
          class="o-form__control-tiptap u-mb"
          :value="organisation.dataProtection || ''"
          :hidden-input="organisation.ident + ':data_protection'"
          :headings="[3, 4]"
          link-button />
      </div>

      <!-- Imprint Section -->
      <div v-if="hasPermission('field_imprint_text_customized_edit_orga')" class="form-row">
        <label :for="organisation.ident + ':imprint'" class="o-form__label w-full">
          {{ Translator.trans('imprint') }}
          <span class="lbl__hint">{{ Translator.trans('organisation.imprint.hint') }}</span>
        </label>
        <editor
          :id="organisation.ident + ':imprint'"
          class="o-form__control-tiptap u-mb"
          :value="organisation.imprint || ''"
          :hidden-input="organisation.ident + ':imprint'"
          :headings="[3, 4]"
          link-button />
      </div>

      <!-- Public Display Section -->
      <div v-if="hasPermission('field_organisation_agreement_showname')" class="mb-0">
        <p class="u-mb-0">{{ Translator.trans('agree.publication') }}</p>
        <p class="lbl__hint">{{ Translator.trans('agree.publication.explanation', { projectName }) }}</p>

        <div class="u-1-of-1 o-form__element--checkbox">
          <label :for="organisation.ident + ':showname'" class="o-form__label u-1-of-1">
            {{ Translator.trans('agree.publication.text') }}
          </label>
          <input
            type="checkbox"
            :id="organisation.ident + ':showname'"
            class="o-form__control-input"
            :name="organisation.ident + ':showname'"
            :checked="organisation.showname === true" />
        </div>
      </div>
    </fieldset>
  </div>
</template>

<script>
import { DpSelect, DpTextArea } from '@demos-europe/demosplan-ui'

export default {
  name: 'OrganisationDataEntry',

  components: {
    DpTextArea,
    DpSelect
  },

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

    showNotificationsSection () {
      return (this.willReceiveNewStatementNotification && hasPermission('feature_notification_statement_new')) ||
        (this.user.isPublicAgency && hasPermission('feature_notification_ending_phase'))
    },

    displayPaperCopySection () {
      return hasPermission('field_organisation_paper_copy') ||
        hasPermission('field_organisation_paper_copy_spec') ||
        hasPermission('field_organisation_competence')
    },

    displayOrganisationBranding () {
      return hasPermission('feature_orga_logo_edit') ||
        hasPermission('field_data_protection_text_customized_edit_orga') ||
        hasPermission('field_imprint_text_customized_edit_orga') ||
        hasPermission('field_organisation_agreement_showname')
    },

    paperCopyCountOptions () {
      return Array.from({ length: 11 }, (_, i) => ({
        label: i.toString(),
        value: i,
      }))
    },

    brandingExplanation() {
      if (this.hasPermission('feature_orga_logo_edit')) {
        return Translator.trans('organisation.procedures.branding.link', {
          href: this.$router.resolve({
            name: 'DemosPlan_orga_branding_edit',
            params: { orgaId: this.organisation.ident || '' },
          }).href,
        })
      }
      return ''
    }
  }
}
</script>
