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
      data-cy="personalInformationMail"
      aria-labelledby="statement-detail-require-information-mail"
      :checked="statement.r_getFeedback === 'on'"
      :label="{
        text: Translator.trans('statement.detail.form.personal.require_information_mail')
      }"
      name="r_getFeedback"
      @change="val => setStatementData({r_getFeedback: val ? 'on' : 'off'})" />

    <div
      v-show="statement.r_getFeedback === 'on'"
      :class="prefixClass('mb-2')">
      <dp-radio
        v-if="hasPermission('feature_statements_feedback_postal')"
        id="r_getEvaluation"
        name="r_getEvaluation"
        data-cy="personalAnswerEmail"
        :class="prefixClass('mt-2')"
        @change="val => setStatementData({r_getEvaluation: 'email'})"
        :checked="statement.r_getEvaluation === 'email' && statement.r_getFeedback === 'on'"
        aria-labelledby="statement-detail-require-response-email"
        :label="{
          text: Translator.trans('statement.form.personal.require_answer_email')
        }"
        value="email" />

      <!--              {# email address #}-->
      <div :class="prefixClass('layout pl-4')">
        <dp-input
          id="r_email_feedback"
          ref="emailFeedback"
          data-cy="statementDetailEmail"
          aria-labelledby="statement-detail-email"
          autocomplete="email"
          :class="prefixClass('u-pl-1_5 mt-1')"
          data-dp-validate-if="#r_getEvaluation"
          :label="{
            text: Translator.trans('email')
          }"
          name="r_email"
          :required="statement.r_getEvaluation === 'email'"
          type="email"
          :value="statement.r_email"
          width="u-1-of-1-palm u-1-of-2"
          @input="val => hasPermission('feature_statements_feedback_check_email') ? setStatementData({r_email: val}) : setStatementData({r_email: val, r_email2: val})" /><!--

        if repeating of email input is enforced, display second email field
     --><dp-input
          v-if="hasPermission('feature_statements_feedback_check_email')"
          id="r_email2"
          ref="emailFeedback2"
          data-cy="statementDetailEmailConfirm"
          aria-labelledby="statement-detail-email-confirm"
          autocomplete="email"
          :class="prefixClass('u-pl-1_5 mt-2')"
          data-dp-validate-if="#r_getEvaluation"
          data-dp-validate-should-equal="[name=r_email]"
          :label="{
            text: Translator.trans('email.confirm')
          }"
          name="r_email2"
          :required="statement.r_getEvaluation === 'email'"
          type="email"
          :value="statement.r_email2"
          width="u-1-of-1-palm u-1-of-2"
        @input="val => setStatementData({r_email2: val})" /><!--
     --><dp-radio
          v-if="hasPermission('feature_statements_feedback_postal')"
          id="r_getEvaluation_snailmail"
          data-cy="personalAnswerPost"
          :class="prefixClass('mt-3')"
          name="r_getEvaluation"
          :disabled="statement.r_useName === '0'"
          @change="val => setStatementData({r_getEvaluation: 'snailmail'})"
          :checked="statement.r_getEvaluation === 'snailmail' && statement.r_getFeedback === 'on'"
          :label="{
            text: Translator.trans('statement.form.personal.require_answer_post')
          }"
          aria-labelledby="statement-detail-require-response-post"
          value="snailmail" />
        <form-group-street-and-number
          v-show="statement.r_useName !== '0'"
          :class="prefixClass('layout__item u-1-of-1-palm u-2-of-3 mt-2 u-pl-1_5')"
          :disabled="statement.r_useName === '0'"
          :required="statement.r_getFeedback === 'on' && statement.r_getEvaluation === 'snailmail' && statement.r_useName !== '0'" /><!--
     --><form-group-postal-and-city
          v-show="statement.r_useName !== '0'"
          :class="prefixClass('layout__item u-1-of-1-palm u-2-of-3 mt-2 u-pl-1_5')"
          :disabled="statement.r_useName === '0'"
          :required="statement.r_getFeedback === 'on' && statement.r_getEvaluation === 'snailmail' && statement.r_useName !== '0'" />
      </div>
    </div>
  </div>
</template>

<script>
import { DpCheckbox, DpRadio, prefixClassMixin } from '@demos-europe/demosplan-ui'
import formGroupMixin from '../mixins/formGroupMixin'
import FormGroupPostalAndCity from './FormGroupPostalAndCity'
import FormGroupStreetAndNumber from './FormGroupStreetAndHouseNumber'

export default {
  name: 'FormGroupEvaluationMailViaSnailMailOrEmail',

  components: {
    FormGroupPostalAndCity,
    FormGroupStreetAndNumber,
    DpCheckbox,
    DpRadio
  },

  mixins: [formGroupMixin, prefixClassMixin]
}
</script>
