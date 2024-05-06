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
          type="radio"
          id="publicCheck"
          name="r_publicVerified"
          value="publication_pending"
          data-cy="publicationPending"
          v-model="checked"
          class="cursor-pointer">
        <label
          for="publicCheck"
          class="inline weight--normal u-ml-0_25 align-text-top">
          {{ Translator.trans('explanation.statement.public.check') }}
        </label>
      </div>

      <div class="u-mb-0_25">
        <input
          type="radio"
          id="publicVerify"
          name="r_publicVerified"
          data-cy="publicationApproved"
          value="publication_approved"
          v-model="checked"
          class="cursor-pointer">
        <label
          for="publicVerify"
          class="inline weight--normal u-ml-0_25 align-text-top">
          {{ Translator.trans('explanation.statement.public.verify', { count: filesLength }) }}
        </label>
      </div>

      <div class="u-mb-0_25">
        <input
          type="radio"
          id="publicReject"
          name="r_publicVerified"
          data-cy="publicationRejected"
          value="publication_rejected"
          v-model="checked"
          class="cursor-pointer">
        <label
          for="publicReject"
          class="inline weight--normal u-ml-0_25 align-text-top">
          {{ Translator.trans('explanation.statement.public.reject') }}
        </label>
      </div>

      <div v-if="checked === 'publication_rejected' && showEmailField">
        <label class="u-mt u-mb-0_25">
          {{ Translator.trans('email.body') }}
        </label>
        <dp-editor
          :model-value="emailText"
          hidden-input="r_publicRejectionEmail" />
      </div>
    </div>
    <div v-else>
      {{ publicVerifiedTranslation }}
    </div>
  </div>
</template>

<script>
import { defineAsyncComponent } from 'vue'

export default {
  name: 'DpStatementPublish',

  components: {
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    })
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
