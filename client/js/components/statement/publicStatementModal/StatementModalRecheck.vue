<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset
    :class="prefixClass('c-statement__step')"
    id="check"
    tabindex="-1">
    <legend
      :class="prefixClass('sr-only')"
      v-text="Translator.trans('statement.recheck')" />

    <dp-inline-notification
      :class="prefixClass('mt-1')"
      :message="Translator.trans('statement.recheck')"
      type="warning"
      />

    <dp-inline-notification
      v-if="statementFormHintRecheck !== ''"
      type="info">
      <p v-cleanhtml="statementFormHintRecheck" />
    </dp-inline-notification>

    <div
      v-if="hasPermission('field_statement_public_allowed') && publicParticipationPublicationEnabled"
      :class="prefixClass('flow-root')">
      <span
        v-if="statement.r_makePublic === 'on'"
        v-cleanhtml="Translator.trans('explanation.statement.public', { projectName: dplan.projectName })" />
      <span
        v-else
        v-cleanhtml="Translator.trans('explanation.statement.dont.publish')" />
      <button
        type="button"
        data-cy="statementModalRecheck:statementDetailFormPersonalPublish"
        @click="$emit('edit-input', 'r_makePublic')"
        :class="prefixClass('o-link--default btn-icns u-ml float-right')"
        :title="Translator.trans('statement.form.input.change')"
        aria-labelledby="statementDetailFormPersonalPublish inputDataChange">
        <i
          :class="prefixClass('fa fa-pencil')"
          aria-hidden="true" />
      </button>
    </div>

    <div
      v-if="statement.r_useName === '1'"
      :class="prefixClass('flow-root border--top u-pt-0_25')">
      <div :class="prefixClass('layout--flush')">
        <span :class="prefixClass('layout__item u-1-of-1')">
          {{ Translator.trans('statement.detail.form.personal.post_publicly') }}
          <button
            type="button"
            data-cy="statementModalRecheck:useNameText"
            :class="prefixClass('o-link--default btn-icns u-ml float-right')"
            @click="$emit('edit-input', 'r_useName_1')"
            :title="Translator.trans('statement.form.input.change')"
            aria-labelledby="useNameText inputDataChange">
            <i
              :class="prefixClass('fa fa-pencil')"
              aria-hidden="true" />
          </button>
        </span>
      </div>
      <div :class="prefixClass('layout--flush')">
        <span :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <em>{{ Translator.trans('name') }}: </em> {{ statement.r_firstname }} {{ statement.r_lastname }}<br>
        </span><!--

     --><span
          v-if="hasPermission('field_statement_user_organisation') && (fieldIsActive('citizenXorOrgaAndOrgaName') || fieldIsActive('stateAndGroupAndOrgaNameAndPosition'))"
          :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <em>{{ Translator.trans('submitter') }}: </em>
          <template v-if="statement.r_submitter_role === 'publicagency'">
            {{ Translator.trans('invitable_institution') }} ({{ statement.r_userOrganisation }})
          </template>
          <template v-else>
            {{ Translator.trans('citizen') }}
          </template>
        </span><!--
     --><span
          v-if="hasPermission('field_statement_user_position') && fieldIsActive('stateAndGroupAndOrgaNameAndPosition') && formOptions.userPosition"
          :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <em>{{ Translator.trans('position') }}: </em> {{ statement.r_userPosition }}
        </span><!--
     --><span
          v-if="(hasPermission('field_statement_user_group') || hasPermission('field_statement_user_state')) && fieldIsActive('stateAndGroupAndOrgaNameAndPosition')"
          :class="prefixClass('layout__item u-1-of-4-desk-up u-pl-0')">
          <template v-if="hasPermission('field_statement_user_state') && statement.r_userState">
            <em>{{ Translator.trans('state') }}: </em> {{ statement.r_userState }}<br>
          </template>
          <template v-if="hasPermission('field_statement_user_group') && formOptions.userGroup">
            <em>{{ Translator.trans('organisation') }}: </em> {{ statement.r_userGroup }}
          </template>
        </span><!--
     --><span
          v-if="showEmail"
          :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <em>{{ Translator.trans('email') }}: </em> {{ statement.r_email }}
        </span><!--
     --><span
          v-if="statement.r_phone !== ''"
          :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <em>{{ Translator.trans('phone') }}: </em> {{ statement.r_phone }}
        </span><!--
     --><span
          v-if="(fieldIsActive('streetAndHouseNumber') || fieldIsActive('street')) && hasPermission('field_statement_meta_street')"
          :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <template v-if="showStreet">
            <em>{{ Translator.trans('street') }}: </em> {{ statement.r_street }}<br>
          </template>
          <template v-if="fieldIsActive('streetAndHouseNumber') && showHouseNumber">
            <em>{{ Translator.trans('street.number.short') }}: </em> {{ statement.r_houseNumber }}<br>
          </template>
        </span><!--
     --><span
          v-if="fieldIsActive('postalAndCity')"
          :class="prefixClass('layout__item u-1-of-4-desk-up')">
          <template v-if="showPostalCode">
            <em>{{ Translator.trans('postalcode') }}: </em> {{ statement.r_postalCode }}<br>
          </template>

          <template v-if="showCity">
            <em>{{ Translator.trans('city') }}: </em> {{ statement.r_city }}<br>
          </template>
        </span>
      </div>
    </div>
    <div
      v-else
      :class="prefixClass('flow-root border--top u-pt-0_25')">
      {{ Translator.trans('statement.detail.form.personal.post_anonymously') }}
      <button
        type="button"
        data-cy="statementModalRecheck:useNameText"
        :class="prefixClass('o-link--default btn-icns u-ml float-right')"
        @click="$emit('edit-input', 'r_useName_0')"
        :title="Translator.trans('statement.form.input.change')"
        aria-labelledby="useNameText inputDataChange">
        <i
          :class="prefixClass('fa fa-pencil')"
          aria-hidden="true" />
      </button>
    </div>

    <div
      v-if="statementFeedbackDefinitions.length > 0"
      :class="prefixClass('flow-root border--top u-pt-0_25')">
      <p
        v-if="hasPermission('feature_statements_feedback_postal')"
        :class="prefixClass('inline-block u-mb-0_25')">
        <template v-if="statement.r_getFeedback === 'on'">
          <span
            v-if="statement.r_getEvaluation === 'email'"
            v-cleanhtml="Translator.trans('statement.feedback.email', { email: statement.r_email })" />
          <span
            v-if="statement.r_getEvaluation === 'snailmail'"
            v-cleanhtml="Translator.trans('statement.feedback.postal')" />
        </template>
        <span
          v-else
          v-cleanhtml="Translator.trans('statement.feedback.none')" />
      </p>

      <p
        v-else
        :class="prefixClass('inline-block u-mb-0_25')">
        <template v-if="statement.r_getFeedback === 'on'">
          {{ Translator.trans('statement.detail.form.personal.feedback') }}<br>
          <em>{{ Translator.trans('email.address') }}:</em> {{ statement.r_email }}
        </template>
        <span
          v-else
          v-cleanhtml="Translator.trans('statement.detail.form.personal.feedback.no')" />
      </p>

      <!-- this span is only to combine aria-labelledby of some elements with the text 'Eingabe ändern' -->
      <span
        :class="prefixClass('hidden')"
        aria-hidden="true"
        id="inputDataChange">
        {{ Translator.trans('statement.form.input.change') }}
      </span>
      <button
        type="button"
        data-cy="statementModalRecheck:getFeedbackText"
        :class="prefixClass('o-link--default btn-icns u-ml float-right')"
        @click="$emit('edit-input', 'r_getFeedback')"
        :title="Translator.trans('statement.form.input.change')"
        aria-labelledby="getFeedbackText inputDataChange">
        <i
          :class="prefixClass('fa fa-pencil')"
          aria-hidden="true" />
      </button>
    </div>

    <div :class="prefixClass('flow-root border--top u-pt-0_25')">
      <span :class="prefixClass('flow-root')">
        <em>{{ Translator.trans('statement.my') }}: </em>
        <button
          type="button"
          data-cy="statementModalRecheck:statementAlter"
          :class="prefixClass('o-link--default btn-icns float-right')"
          @click="$emit('edit-input', 'r_text')"
          :title="Translator.trans('statement.alter')"
          :aria-label="Translator.trans('statement.alter')">
          <i
            :class="prefixClass('fa fa-pencil')"
            aria-hidden="true" />
        </button>
      </span>

      <div
        :class="prefixClass('sm:h-9 overflow-auto c-styled-html')"
        v-cleanhtml="statement.r_text" />
    </div>
  </fieldset>
</template>

<script>
import { CleanHtml, DpInlineNotification, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'StatementModalRecheck',

  components: {
    DpInlineNotification
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [prefixClassMixin],

  props: {
    formFields: {
      type: Array,
      required: false,
      default: () => []
    },

    formOptions: {
      type: Object,
      required: false,
      default: () => ({})
    },

    publicParticipationPublicationEnabled: {
      type: Boolean,
      required: false,
      default: false
    },

    statement: {
      type: Object,
      required: true
    },

    statementFeedbackDefinitions: {
      type: [Object, Array],
      required: false,
      default: () => ({})
    },

    statementFormHintRecheck: {
      type: String,
      required: false,
      default: ''
    }
  },

  emits: [
    'edit-input'
  ],

  computed: {
    showCity () {
      return hasPermission('field_statement_meta_city') && this.statement.r_city && this.statement.r_city !== ''
    },

    showEmail () {
      return this.statement.r_email && this.statement.r_email !== ''
    },

    showHouseNumber () {
      return this.statement.r_houseNumber && this.statement.r_houseNumber !== ''
    },

    showPostalCode () {
      return hasPermission('field_statement_meta_postal_code') && this.statement.r_postalCode && this.statement.r_postalCode !== ''
    },

    showStreet () {
      return this.statement.r_street && this.statement.r_street !== ''
    }
  },

  methods: {
    fieldIsActive (fieldKey) {
      return this.formFields.map(el => el.name).includes(fieldKey)
    }
  }
}
</script>
