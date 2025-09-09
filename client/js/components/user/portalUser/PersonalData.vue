<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dl class="description-list u-mb-0_5">
      <template v-if="!hasIdentityProvider">
        <dt class="weight--bold">
          {{ Translator.trans('username') }}
        </dt>
        <dd class="u-mb color--grey">
          {{ userData.userName }}
        </dd>
      </template>

      <template v-if="hasPermission('area_mydata_organisation')">
        <dt class="weight--bold">
          {{ Translator.trans('organisation') }}
        </dt>
        <dd class="u-mb color--grey">
          {{ userData.organisationName }}
        </dd>
        <dt class="weight--bold">
          {{ Translator.trans('department') }}
        </dt>
        <dd class="u-mb color--grey">
          {{ userData.departmentName }}
        </dd>
      </template>

      <dt class="weight--bold">
        {{ Translator.trans('name') }}
      </dt>
      <dd class="u-mb color--grey">
        {{ userData.lastName }}
      </dd>
      <dt class="weight--bold">
        {{ Translator.trans('name.first') }}
      </dt>
      <dd class="u-mb color--grey">
        {{ userData.firstName }}
      </dd>

      <template v-if="!hasIdentityProvider">
        <dt class="weight--bold">
          {{ Translator.trans('email') }}
        </dt>
        <dd class="u-mb color--grey">
          {{ userData.email }}
        </dd>
      </template>
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
        v-model="isDailyDigestChecked"
        class="u-mb-0_25"
        :label="{
          bold: true,
          text: Translator.trans('email.daily.subscribe')
        }"
        name="assignedTaskNotification"
        value-to-send="on" />
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
import { CleanHtml, DpCheckbox } from '@demos-europe/demosplan-ui'

const userProperties = [
  'organisationName',
  'departmentName',
  'lastName',
  'firstName',
  'userName',
  'email'
]

export default {
  name: 'PersonalData',

  components: {
    DpCheckbox
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
  /**
   * If the User logs in through a service provider (via Keycloak), the username becomes cryptic and can be confusing.
   * Therefor we want to hide it from the user.
   */
    hasIdentityProvider: {
      type: Boolean,
      required: false,
      default: false
    },

    isDailyDigestEnabled: {
      type: Boolean,
      default: true
    },

    user: {
      type: Object,
      required: true,
      validator: (prop) => {
        return Object.keys(prop).every(key => userProperties.includes(key))
      }
    }
  },

  data () {
    return {
      isDailyDigestChecked: this.isDailyDigestEnabled,
      userData: this.setUserData()
    }
  },

  computed: {
    explanation () {
      const transkey = hasPermission('feature_statement_gdpr_consent_may_revoke')
        ? 'statements.yours.list.description.short.gdpr_consent_may_revoke'
        : 'statements.yours.list.description.short'
      return Translator.trans(transkey, { href: Routing.generate('DemosPlan_user_statements') })
    }
  },

  methods: {
    setUserData () {
      const userData = { ...this.user }

      return this.changeDefaultValues(userData)
    },

    changeDefaultValues (user) {
      for (const key of userProperties) {
        if (!user[key]) {
          user[key] = '-'
        }
      }

      return user
    }
  }
}
</script>
