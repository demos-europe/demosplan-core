<template>
  <fieldset
    class="w-3/4">
    <legend
      v-if="user.isPublicAgency ||
        (user.isPublicAgency && hasPermission('field_organisation_email2_cc')) ||
        (hasPermission('feature_organisation_email_reviewer_admin') && hasPermission('field_organisation_email_reviewer_admin'))"
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

    <!-- Notifications Section -->
    <div v-if="hasNotificationSection && hasNotificationSectionPermission">
      <p class="o-form__label mb-1">
        {{ Translator.trans('email.notifications') }}
      </p>

      <!-- New Statement Notification -->
      <!-- TODO: type of organisation.emailNotificationNewStatement.content should be a boolean and not a string -->
      <dp-checkbox
        v-if="willReceiveNewStatementNotification && hasPermission('feature_notification_statement_new')"
        id="orga_emailNotificationNewStatement"
        class="mb-2"
        :name="`${organisation.id}:emailNotificationNewStatement`"
        data-cy="organisationData:notification:newStatement"
        :label="{
          text: Translator.trans('explanation.notification.new.statement')
        }"
        :checked="organisation.emailNotificationNewStatement.content === 'true'" />

      <!-- Ending Phase Notification -->
      <dp-checkbox
        v-if="user.isPublicAgency && hasPermission('feature_notification_ending_phase')"
        id="orga_emailNotificationEndingPhase"
        :name="`${organisation.id}:emailNotificationEndingPhase`"
        data-cy="organisationData:notification:endingPhase"
        :label="{
          text: Translator.trans('explanation.notification.phase.ending')
        }"
        :checked="organisation.emailNotificationEndingPhase.content === 'true'" />
    </div>
  </fieldset>
</template>

<script>
import { DpCheckbox, DpInput } from '@demos-europe/demosplan-ui'

export default {
  name: 'EmailNotificationSettings',

  components: {
    DpInput,
    DpCheckbox
  },

  props: {
    organisation: {
      type: Object,
      required: true
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

    hasNotificationSection: {
      type: Boolean,
      required: false,
      default: false
    }
  },

  computed: {
    hasNotificationSectionPermission () {
      return (this.willReceiveNewStatementNotification && hasPermission('feature_notification_statement_new')) ||
        (this.user.isPublicAgency && hasPermission('feature_notification_ending_phase'))
    }
  }
}
</script>
