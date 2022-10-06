<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dl class="description-list u-mb-0_5">
      <dt class="weight--bold">
        {{ Translator.trans('username') }}
      </dt>
      <dd class="u-mb color--grey">
        {{ user.userName }}
      </dd>

      <template v-if="hasPermission('area_mydata_organisation')">
        <dt class="weight--bold">
          {{ Translator.trans('organisation') }}
        </dt>
        <dd class="u-mb color--grey">
          {{ user.organisationName }}
        </dd>
        <dt class="weight--bold">
          {{ Translator.trans('department') }}
        </dt>
        <dd class="u-mb color--grey">
          {{ user.departmentName }}
        </dd>
      </template>

      <dt class="weight--bold">
        {{ Translator.trans('name') }}
      </dt>
      <dd class="u-mb color--grey">
        {{ user.lastName }}
      </dd>
      <dt class="weight--bold">
        {{ Translator.trans('name.first') }}
      </dt>
      <dd class="u-mb color--grey">
        {{ user.firstName }}
      </dd>
      <dt class="weight--bold">
        {{ Translator.trans('email') }}
      </dt>
      <dd class="u-mb color--grey">
        {{ user.email }}
      </dd>
    </dl>

    <input
        type="hidden"
        name="email"
        :value="user.email">
    <input
        type="hidden"
        name="lastname"
        :value="user.lastName">

    <template v-if="hasPermission('feature_send_assigned_task_notification_email_setting')">
      <dp-checkbox
          id="assignedTaskNotification"
          name="assignedTaskNotification"
          class="u-mb-0_25"
          :label="Translator.trans('email.daily.subscribe')"
          v-model="isDailyDigestChecked"
          value-to-send="on"
          standalone />
      <p>
        {{ Translator.trans('email.daily.assigned.tasks.explanation') }}
      </p>
    </template>

    <template v-if="hasPermission('feature_statement_gdpr_consent')">
      <p class="u-mb-0 weight--bold">
        {{ Translator.trans('statements.yours') }}
      </p>
      <span v-cleanhtml="explanation" />
    </template>
  </div>
</template>

<script>
import { CleanHtml } from 'demosplan-ui/directives'
import DpCheckbox from '@DpJs/components/core/form/DpCheckbox'

export default {
  name: 'PersonalData',

  components: {
    DpCheckbox
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    isDailyDigestEnabled: {
      type: Boolean,
      default: true
    },

    user: {
      type: Object,
      required: true
    },

    // Set to true if username should be displayed
    userName: {
      type: Boolean,
      default: false
    }
  },

  data () {
    return {
      isDailyDigestChecked: this.isDailyDigestEnabled
    }
  },

  computed: {
    explanation () {
      const transkey = hasPermission('feature_statement_gdpr_consent_may_revoke')
        ? 'statements.yours.list.description.short.gdpr_consent_may_revoke'
        : 'statements.yours.list.description.short'
      return Translator.trans(transkey, { href: Routing.generate('DemosPlan_user_statements') })
    }
  }
}
</script>
