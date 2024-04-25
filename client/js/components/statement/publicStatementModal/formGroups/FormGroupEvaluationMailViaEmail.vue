<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="[statement.r_getFeedback === 'on' ? prefixClass('bg-color--grey-light-2') : '', prefixClass('c-statement__formblock')]">
    <dp-checkbox
      id="r_getFeedback"
      aria-labelledby="statement-detail-require-information-mail"
      :checked="statement.r_getFeedback === 'on'"
      :label="{
        text: Translator.trans('statement.detail.form.personal.require_information_mail')
      }"
      name="r_getFeedback"
      @change="val => setStatementData({r_getFeedback: val ? 'on' : 'off'})" />

    <div
      v-show="statement.r_getFeedback === 'on'"
      :class="prefixClass('u-mt-0_5')">
      <!--              {# email address #}-->
      <div :class="prefixClass('layout')">
        <dp-input
          id="r_email_feedback"
          ref="emailFeedback"
          aria-labelledby="statement-detail-email"
          autocomplete="email"
          :class="prefixClass('layout__item u-1-of-2')"
          data-dp-validate-if="#r_getFeedback"
          :label="{
            text: Translator.trans('email')
          }"
          name="r_email"
          required
          type="email"
          :value="statement.r_email"
          @update:model-value="val => hasPermission('feature_statements_feedback_check_email') ? setStatementData({r_email: val}) : setStatementData({r_email: val, r_email2: val})" /><!--

        if repeating of email input is enforced, display second email field
   --><dp-input
        v-if="hasPermission('feature_statements_feedback_check_email')"
        id="r_email2"
        ref="emailFeedback2"
        aria-labelledby="statement-detail-email-confirm"
        autocomplete="email"
        :class="prefixClass('layout__item u-1-of-2')"
        data-dp-validate-if="#r_getFeedback,#r_getEvaluation===email"
        data-dp-validate-should-equal="[name=r_email]"
        :label="{
          text: Translator.trans('email.confirm')
        }"
        name="r_email2"
        required
        type="email"
        :value="statement.r_email2"
        @update:model-value="val => setStatementData({r_email2: val})" />
      </div>
    </div>
  </div>
</template>

<script>
import { DpCheckbox } from '@demos-europe/demosplan-ui'
import formGroupMixin from '../mixins/formGroupMixin'

export default {
  name: 'EvaluationMailViaEmail',

  components: {
    DpCheckbox
  },

  mixins: [formGroupMixin]
}
</script>
