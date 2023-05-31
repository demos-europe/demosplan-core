<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-inline-notification :type="hintSeverity">
    {{ emailImportHint }}
    <button
      v-if="importEmailAddress"
      class="o-link--default btn--blank btn--text-selectable u-valign--inherit"
      v-tooltip="{
        content: copyEmailAddressProcedureToClipboardTooltip,
        delay: { show: 1000, hide: 200 }
      }"
      @click="copyEmailAddressProcedureToClipboard">
      {{ importEmailAddress }}
      <i
        class="fa fa-copy"
        aria-hidden="true" />
    </button>

    <div
      v-if="allowedEmailAddresses.length > 0"
      class="u-mt-0_5">
      <dp-details :summary="Translator.trans('statement.import_email.allowed_emails')">
        <ul class="o-list o-list--csv">
          <li
            v-for="(email, idx) in allowedEmailAddresses"
            :key="idx"
            class="o-list__item"
            v-text="email" />
        </ul>
      </dp-details>
    </div>
    <p
      v-else
      class="weight--bold u-mt-0_5 u-mb-0"
      v-text="Translator.trans('statement.import_email.no_allowed_emails')" />
  </dp-inline-notification>
</template>

<script>
import { DpDetails, DpInlineNotification } from '@demos-europe/demosplan-ui'

export default {
  name: 'EmailImportHint',

  inject: ['procedureId'],

  components: {
    DpDetails,
    DpInlineNotification
  },

  props: {
    allowedEmailAddresses: {
      type: Array,
      required: true
    },

    importEmailAddress: {
      type: String,
      required: false,
      default: null
    }

  },

  computed: {
    copyEmailAddressProcedureToClipboardTooltip () {
      return Translator.trans('clipboard.copy_to')
    },

    emailImportHint () {
      if (this.importEmailAddress === null) {
        return Translator.trans('statement.import_email_none.hint')
      }

      return Translator.trans('statement.import_email.hint')
    },

    hintSeverity () {
      return (this.importEmailAddress === null) ? 'warning' : 'info'
    }
  },

  methods: {
    copyEmailAddressProcedureToClipboard () {
      if (window.isSecureContext === false) {
        console.warn('navigator.clipboard is only exposed in secure contexts. localhost is no secure context.')
        return
      }

      navigator.clipboard.writeText(this.importEmailAddress)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('clipboard.copied'))
        })
    }
  }
}
</script>
