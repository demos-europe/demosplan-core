<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div v-if="editable">
      <div class="u-mb-0_25">
        <input
          class="cursor-pointer"
          data-cy="publicationPending"
          id="publicCheck"
          name="r_publicVerified"
          type="radio"
          value="publication_pending"
          v-model="checked"
          @input="event => $emit('update', event.target.value)">
        <label
          for="publicCheck"
          class="inline font-normal u-ml-0_25 align-text-top">
          {{ Translator.trans('explanation.statement.public.check') }}
        </label>
      </div>

      <div class="u-mb-0_25">
        <input
          class="cursor-pointer"
          data-cy="publicationApproved"
          id="publicVerify"
          name="r_publicVerified"
          type="radio"
          value="publication_approved"
          v-model="checked"
          @input="event => $emit('update', event.target.value)">
        <label
          for="publicVerify"
          class="inline font-normal u-ml-0_25 align-text-top">
          {{ Translator.trans('explanation.statement.public.verify', { count: filesLength }) }}
        </label>
      </div>

      <div class="u-mb-0_25">
        <input
          class="cursor-pointer"
          data-cy="publicationRejected"
          id="publicReject"
          name="r_publicVerified"
          type="radio"
          value="publication_rejected"
          v-model="checked"
          @input="event => $emit('update', event.target.value)">
        <label
          for="publicReject"
          class="inline font-normal u-ml-0_25 align-text-top">
          {{ Translator.trans('explanation.statement.public.reject') }}
        </label>
      </div>

      <div v-if="checked === 'publication_rejected' && showEmailField">
        <label class="u-mt u-mb-0_25">
          {{ Translator.trans('email.body') }}
        </label>
        <dp-editor
          :value="emailText"
          hidden-input="r_publicRejectionEmail" />
      </div>

      <dp-inline-notification
        v-if="hasPermission('feature_statements_vote')"
        class="mt-3 mb-2"
        :message="Translator.trans('explanation.statement.public.activate.voting')"
        type="info" />
    </div>

    <voting-status
      v-else
      class="mt-0.5"
      :public-verified="publicVerified" />
  </div>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import VotingStatus from './VotingStatus'

export default {
  name: 'StatementPublish',

  components: {
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }),
    DpInlineNotification: defineAsyncComponent(async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    }),
    VotingStatus
  },

  props: {
    editable: {
      type: Boolean,
      default: false
    },

    filesLength: {
      type: String,
      default: '0'
    },

    isManual: {
      type: Boolean,
      default: true
    },

    publicVerified: {
      type: String,
      default: ''
    },

    publicVerifiedTransKey: {
      type: String,
      default: ''
    },

    submitterEmail: {
      type: String,
      default: ''
    }
  },

  emits: [
    'update'
  ],

  data () {
    return {
      checked: 'publication_pending',
      emailText: Translator.trans('publication.rejection.email.text')
    }
  },

  computed: {
    publicVerifiedTranslation () {
      switch (this.publicVerified) {
        case 'publication_pending':
          return Translator.trans('publication.pending.detailed')
        case 'publication_approved':
          return Translator.trans('publication.approved.detailed')
        case 'publication_rejected':
          return Translator.trans('publication.rejected.detailed')
        default:
          return ''
      }
    },

    showEmailField () {
      return this.isManual === false && this.submitterEmail !== '' && hasPermission('feature_statements_publication_request_approval_or_rejection_notification_email')
    }
  },

  mounted () {
    // If this is not a new statement being created but an existing statement
    if (this.publicVerified !== '') {
      this.checked = this.publicVerified
    }
  }
}
</script>
