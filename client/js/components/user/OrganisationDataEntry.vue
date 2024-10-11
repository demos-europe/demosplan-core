<template>
  <div>
    <fieldset
      id="organisationData"
      class="w-3/4">
      <legend class="font-size-large weight--normal mb-3">
        {{ Translator.trans('organisation.data') }}
      </legend>

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
      <div class="flex items-start mb-2">
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
      <div class="flex items-start mb-2">
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
    </fieldset>

    <!-- Submission type Section -->
    <fieldset
      v-if="hasPermission('feature_change_submission_type')"
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

    <!-- Email Notifications Section -->
    <fieldset
      v-if="user.isPublicAgency ||
      hasPermission('field_organisation_email2_cc') ||
      (hasPermission('feature_organisation_email_reviewer_admin') &&
      hasPermission('field_organisation_email_reviewer_admin'))"
      id="mailNotification"
      class="w-3/4">
      <legend
        class="font-size-large weight--normal u-mb-0_75">
        {{ Translator.trans('email.notifications') }}
      </legend>
      <!-- Email2 address for Public Agencies -->
      <dp-input
        v-if="user.isPublicAgency"
        id="orga_email2"
        class="mb-3"
        data-cy="organisationData:email2"
        :name="`${organisation.id}:email2`"
        :label="{
          text: Translator.trans('email.participation'),
          hint: Translator.trans('explanation.organisation.email.participation')
        }"
        v-model="organisation.email2"
        required />

      <!-- ccEmail2 for adding extra addresses for participation invitations -->
      <dp-input
        v-if="user.isPublicAgency && hasPermission('field_organisation_email2_cc')"
        id="orga_ccEmail2"
        class="mb-3"
        data-cy="organisationData:ccEmail2"
        :name="`${organisation.id}:ccEmail2`"
        :label="{
          text: Translator.trans('email.cc.participation'),
          hint: Translator.trans('explanation.organisation.email.cc')
        }"
        v-model="organisation.ccEmail2" />

      <!-- PLANNING_SUPPORTING_DEPARTMENT users may specify an email address to receive notifications whenever a fragment is assigned to someone -->
      <dp-input
        v-if="hasPermission('feature_organisation_email_reviewer_admin') && hasPermission('field_organisation_email_reviewer_admin')"
        id="orga_emailReviewerAdmin"
        class="mb-3"
        data-cy="organisationData:emailReviewerAdmin"
        :name="`${organisation.id}:emailReviewerAdmin`"
        :label="{
          text: Translator.trans('email.reviewer.admin'),
          hint: Translator.trans('explanation.organisation.email.reviewer.admin')
        }"
        v-model="organisation.emailReviewerAdmin" />

      <!-- Notifications Settings Section -->
      <div v-if="showNotificationsSection">
        <p class="o-form__label mb-1">
          {{ Translator.trans('email.notifications') }}
        </p>

        <!-- New Statement Notification -->
        <!-- TODO: type of organisation.emailNotificationNewStatement.content should be a boolean and not a string -->
        <dp-checkbox
          v-if="willReceiveNewStatementNotification && hasPermission('feature_notification_statement_new')"
          id="orga_emailNotificationNewStatement"
          :name="`${organisation.id}:emailNotificationNewStatement`"
          data-cy="organisationData:notification:newStatement"
          :label="{
            text: Translator.trans('explanation.notification.new.statement'),
          }"
          :checked="organisation.emailNotificationNewStatement.content" />

        <!-- Ending Phase Notification -->
        <dp-checkbox
          v-if="user.isPublicAgency && hasPermission('feature_notification_ending_phase')"
          id="orga_emailNotificationEndingPhase"
          :name="`${organisation.id}:emailNotificationEndingPhase`"
          data-cy="organisationData:notification:endingPhase"
          :label="{
            text: Translator.trans('explanation.notification.phase.ending'),
          }"
          :checked="organisation.emailNotificationEndingPhase.content" />
      </div>
    </fieldset>

    <!-- Paper copy Section -->
    <fieldset
      v-if="showPaperCopySection"
      id="paperCopy"
      class="w-3/4">
      <legend class="font-size-large weight--normal u-mb-0_75">
        {{ Translator.trans('copies.paper') }}
      </legend>

      <div
        v-if="hasPermission('field_organisation_paper_copy')"
        class="w-full mb-3">
        <!-- TODO: create PR in demosplan-ui -->
        <dp-select
          id="orga_paperCopy"
          :name="`${organisation.id || ''}:paperCopy`"
          v-model="organisation.paperCopy"
          data-cy="organisationData:paperCopy:select"
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
          data-cy="organisationData:paperCopy:specification"
          :name="`${organisation.id || ''}:paperCopySpec`"
          :value="organisation.paperCopySpec"
          :label="Translator.trans('copies.kind')"
          :hint="Translator.trans('explanation.organisation.copies.kind')" />
      </div>

      <div
        v-if="hasPermission('field_organisation_competence')"
        class="w-full mb-3">
        <dp-text-area
          id="orga_competence"
          data-cy="organisationData:paperCopy:competence"
          :name="`${organisation.id || ''}:competence`"
          :value="organisation.competence"
          :label="Translator.trans('competence.explanation')"
          :hint="Translator.trans('explanation.organisation.competence')" />
      </div>
    </fieldset>

    <!-- Organisation Branding Section -->
    <fieldset
      v-if="showOrganisationBrandingSection"
      class="w-3/4">
      <legend
        v-if="hasPermission('feature_orga_logo_edit')"
        class="font-size-large weight--normal u-mb-0_25">
        {{ Translator.trans('organisation.procedures.branding') }}
      </legend>
      <p v-cleanhtml="brandingExplanation"></p>
      <legend class="font-size-large weight--normal u-mb-0_75">
        <span>{{ Translator.trans('organisation.procedures.branding') }}</span>
      </legend>

      <!-- Data Protection -->
      <div
        v-if="hasPermission('field_data_protection_text_customized_edit_orga')"
        class="o-form__label w-full">
        <label
          :for="`${organisation.id}:data_protection`"
          class="o-form__label w-full">
          {{ Translator.trans('data.protection.notes') }}
          <small class="lbl__hint block">
            {{ Translator.trans('customer.data.protection.explanation') }}
          </small>
        </label>
        <dp-editor
          :id="`${organisation.id}:data_protection`"
          class="o-form__control-tiptap u-mb-0_75"
          data-cy="organisationData:branding:dataProtection"
          :hidden-input="organisation.dataProtection || ''"
          :toolbar-items="{
            linkButton: true,
            headings: [3, 4]
          }"
          :value="organisation.dataProtection || ''" />
      </div>

      <!-- Imprint -->
      <div
        v-if="hasPermission('field_imprint_text_customized_edit_orga')"
        class="w-full">
        <label
          :for="`${organisation.id}:imprint`"
          class="o-form__label w-full">
          {{ Translator.trans('imprint') }}
          <small class="lbl__hint block">
            {{ Translator.trans('organisation.imprint.hint') }}
          </small>
        </label>
        <dp-editor
          :id="`${organisation.id}:imprint`"
          class="o-form__control-tiptap u-mb-0_75"
          data-cy="organisationData:branding:imprint"
          :hidden-input="`${organisation.id}:imprint`"
          :toolbar-items="{
            linkButton: true,
            headings: [3, 4]
          }"
          :value="organisation.imprint || ''" />
      </div>

      <!-- Public Display -->
      <div
        v-if="hasPermission('field_organisation_agreement_showname')"
        class="mb-0">
        <label
          :for="`${organisation.id}:showname`"
          class="o-form__label">
          {{ Translator.trans('agree.publication') }}
        </label>
        <small class="lbl__hint block">
          {{ Translator.trans('agree.publication.explanation', { projectName }) }}
        </small>
        <dp-checkbox
          :id="`${organisation.id}:showname`"
          :name="`${organisation.id}:showname`"
          :checked="organisation.showname"
          :label="{
            text: Translator.trans('agree.publication.text'),
            bold: true
          }" />
      </div>
    </fieldset>
  </div>
</template>

<script>
import { CleanHtml, DpInput, DpSelect, DpRadio, DpTextArea, DpEditor, DpCheckbox } from '@demos-europe/demosplan-ui'

export default {
  name: 'OrganisationDataEntry',

  components: {
    DpRadio,
    DpInput,
    DpTextArea,
    DpSelect,
    DpEditor,
    DpCheckbox
  },

  directives: {
    cleanhtml: CleanHtml,
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
    },
    projectName: {
      type: String,
      required: false,
      default: ''
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

    showPaperCopySection () {
      return hasPermission('field_organisation_paper_copy') ||
        hasPermission('field_organisation_paper_copy_spec') ||
        hasPermission('field_organisation_competence')
    },

    showOrganisationBrandingSection () {
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
      if (hasPermission('feature_orga_logo_edit')) {
        return Translator.trans('organisation.procedures.branding.link', {
          href: Routing.generate('DemosPlan_orga_branding_edit', {
            orgaId: this.organisation.id || ''
          })
        })
      }
      return ''
    }
  }
}
</script>
