<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-mb">
    <!-- Current step title -->
    <h3
      class="u-mb"
      v-text="Translator.trans([
        'statement.anonymize.title.actions.choose',
        'statement.anonymize.title.actions.apply',
        'statement.anonymize.title.success'
      ][currentStep - 1])" />

    <!-- Step 1 - selection of actions -->
    <template v-if="currentStep === 1">
      <dp-checkbox
        id="r_anonymize_statement_meta"
        v-model="actions.anonymizeStatementMeta"
        :class="{'u-mb-0_5': !actions.anonymizeStatementMeta}"
        data-cy="statementAnonymize:meta"
        :label="{
          hint: Translator.trans('statement.anonymize.meta.hint'),
          text: Translator.trans('statement.anonymize.meta.label')
        }" />
      <ul
        v-show="actions.anonymizeStatementMeta"
        class="o-list o-list--col-2 u-mb u-ml">
        <li
          v-for="(item, key, index) in statementData"
          :key="index">
          <strong>{{ Translator.trans(key) }}:</strong>
          <span :class="[item === '' && 'color--grey-light']">{{ item !== '' ? item : Translator.trans('not.specified') }}</span>
        </li>
      </ul>

      <dp-checkbox
        v-if="hasPermission('feature_statement_text_history_delete')"
        id="r_delete_statement_text_history"
        v-model="actions.deleteStatementTextHistory"
        class="u-mb-0_5"
        data-cy="statementAnonymize:history"
        :label="{
          hint: Translator.trans('statement.anonymize.delete.history.hint'),
          text: Translator.trans('statement.anonymize.delete.history.label')
        }" />

      <dp-checkbox
        id="r_anonymize_statement_text"
        v-model="actions.anonymizeStatementText"
        :class="{'u-mb-0_5': !actions.anonymizeStatementText}"
        data-cy="statementAnonymize:text"
        :label="{
          hint: Translator.trans('statement.anonymize.text.hint'),
          text: Translator.trans('statement.anonymize.text.label')
        }" />
      <div
        v-show="actions.anonymizeStatementText"
        class="u-ml">
        <dp-contextual-help
          class="float-right"
          :text="Translator.trans('statement.anonymize.text.editor.hint')" />
        <p class="weight--bold u-mb-0_25">
          {{ Translator.trans('statement.anonymize.text.editor.title') }}
        </p>
        <dp-anonymize-text
          class="u-mb u-p-0_25 overflow-y-auto max-h-13"
          :value="anonymizeText"
          @change="text => anonymizeText = text" />
      </div>

      <dp-checkbox
        id="r_delete_statement_attachments"
        v-model="actions.deleteStatementAttachments"
        class="u-mb-0_5"
        data-cy="statementAnonymize:deleteAttachments"
        :label="{
          hint: Translator.trans('statement.anonymize.delete.attachments.hint'),
          text: Translator.trans('statement.anonymize.delete.attachments.label')
        }" />

      <div class="flow-root">
        <dp-button
          color="secondary"
          data-cy="statementAnonymize:backToOriginalStatements"
          :href="Routing.generate('dplan_assessmenttable_view_original_table', {
            procedureId: procedureId,
            filterHash: originalFilterHash
          })"
          :text="Translator.trans('back.to.statements.original')" />
        <dp-button
          class="float-right"
          data-cy="statementAnonymize:next"
          :disabled="isInvalid()"
          icon-after="chevron-right"
          :text="Translator.trans('continue.confirm')"
          @click="next" />
      </div>
    </template>

    <!-- Step 2 - confirmation of actions -->
    <template v-if="currentStep === 2">
      <p>
        {{ Translator.trans('statement.anonymize.confirm.actions') }}
      </p>
      <ul class="u-mb-0_5">
        <li v-if="actions.anonymizeStatementMeta">
          {{ Translator.trans('statement.anonymize.meta.label') }}
        </li>
        <li v-if="actions.deleteStatementTextHistory">
          {{ Translator.trans('statement.anonymize.delete.history.label') }}
        </li>
        <li v-if="actions.anonymizeStatementText">
          {{ Translator.trans('statement.anonymize.text.label') }}
          <div>
            <template
              v-for="(snippet, idx) in anonymizedTextSnippets"
              :key="idx + 'snippet'">
              <span v-if="idx !== 0">
               ...
              </span>
              <span
                class="font-size-small u-mb-0_5 o-box bg-color--grey-light-2 u-pl-0_25 u-pr-0_25 u-mr-0_5"
                v-clean-html="snippet" />
            </template>
          </div>
        </li>
        <li v-if="actions.deleteStatementAttachments">
          {{ Translator.trans('statement.anonymize.delete.attachments.label') }}
        </li>
      </ul>
      <div class="flash flash-warning u-mb">
        <p class="u-m-0">
          <strong>{{ Translator.trans('action.irreversible') }}</strong>
        </p>
      </div>

      <div class="flow-root">
        <dp-button
          color="secondary"
          data-cy="statementAnonymize:back"
          icon="chevron-left"
          :text="Translator.trans('bulk.edit.actions.edit')"
          @click="back" />
        <dp-button
          :busy="busy"
          class="float-right"
          data-cy="statementAnonymize:submit"
          icon-after="chevron-right"
          :text="Translator.trans('bulk.edit.actions.apply')"
          @click="submit" />
      </div>
    </template>

    <!-- Step 3 - confirmation of actions -->
    <template v-if="currentStep === 3">
      <div class="flash flash-confirm u-mb">
        <p
          v-if="actions.anonymizeStatementMeta"
          :class="{'u-mb-0_5': actions.deleteStatementTextHistory}"
          class="flow-root">
          <i
            class="fa u-mt-0_125 u-mr-0_25 float-left fa-check"
            aria-hidden="true" />
          <span class="u-ml block">
            {{ Translator.trans('statement.anonymize.meta.success') }}
          </span>
        </p>

        <p
          v-if="actions.deleteStatementTextHistory"
          class="u-mb-0_25 flow-root">
          <i
            class="fa u-mt-0_125 u-mr-0_25 float-left fa-check"
            aria-hidden="true" />
          <span class="u-ml block">
            {{ Translator.trans('statement.anonymize.delete.history.success') }}
          </span>
        </p>
        <p
          v-if="actions.deleteStatementAttachments"
          class="u-mb-0_25 flow-root">
          <i
            class="fa u-mt-0_125 u-mr-0_25 float-left fa-check"
            aria-hidden="true" />
          <span class="u-ml block">
            {{ Translator.trans('statement.anonymize.delete.attachments.success') }}
          </span>
        </p>
        <p
          v-if="actions.anonymizeStatementText"
          class="u-mb-0_25 flow-root">
          <i
            class="fa u-mt-0_125 u-mr-0_25 float-left fa-check"
            aria-hidden="true" />
          <span class="u-ml block">
            {{ Translator.trans('statement.anonymize.text.success') }}
          </span>
        </p>
      </div>
      <div
        v-if="actions.anonymizeStatementText"
        class="flash flash-warning u-mb">
        <p class="flow-root">
          <i
            class="fa u-mt-0_125 u-mr-0_25 float-left fa-exclamation-triangle"
            aria-hidden="true" />
          <span class="u-ml block">
            {{ Translator.trans('statement.anonymize.text.children.not.affected') }}
          </span>
        </p>
        <ul class="o-list--light u-ml">
          <li
            v-for="child in children"
            :key="child.id">
            {{ Translator.trans('id') }} {{ child.externId }}
          </li>
        </ul>
        <p>
          <a :href="aTableLink">{{ Translator.trans('statement.anonymize.text.children.link.to.list') }}</a><br>
          {{ Translator.trans('statement.anonymize.text.children.version.update') }}
        </p>
      </div>

      <div class="flow-root">
        <dp-button
          data-cy="statementAnonymize:backToOriginalStatements"
          :href="Routing.generate('dplan_assessmenttable_view_original_table', {
            procedureId: procedureId,
            filterHash: originalFilterHash
          })"
          :text="Translator.trans('back.to.statements.original')" />
      </div>
    </template>
  </div>
</template>

<script>
import {
  checkResponse,
  CleanHtml,
  DpAnonymizeText,
  dpApi,
  DpButton,
  DpCheckbox,
  DpContextualHelp
} from '@demos-europe/demosplan-ui'

export default {
  name: 'DpStatementAnonymize',

  components: {
    DpButton,
    DpCheckbox,
    DpAnonymizeText,
    DpContextualHelp
  },

  directives: {
    cleanHtml: CleanHtml
  },

  props: {
    aTableLink: {
      required: true,
      type: String
    },
    /**
     * Needed to construct the route back to the original statements list.
     */
    originalFilterHash: {
      required: true,
      type: String
    },

    procedureId: {
      required: true,
      type: String
    },

    statementData: {
      type: Object,
      required: true
    },

    statementText: {
      type: String,
      required: true
    },

    statementId: {
      required: true,
      type: String
    },

    children: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      actions: {
        anonymizeStatementMeta: false,
        deleteStatementTextHistory: false,
        anonymizeStatementText: false,
        deleteStatementAttachments: false
      },
      busy: false,
      currentStep: 1,
      totalSteps: 3,
      anonymizeText: this.statementText
    }
  },
  computed: {
    /**
     * Get the text snippets from all anonymized
     */
    anonymizedTextSnippets () {
      const text = this.anonymizeText
      const anonymizedSnippets = []
      const regex = /<span([^>]*?)title="([^"]*?)"([^>]*?)class="anonymize-me"([^>]*?)>([^<]*?)<\/span>/gm
      let result
      while ((result = regex.exec(text))) {
        anonymizedSnippets.push(result[2])
      }
      return anonymizedSnippets
    }
  },

  methods: {
    back () {
      this.currentStep = this.currentStep > 1 ? this.currentStep - 1 : 1
    },

    /**
     * If at least one action is applied, request may be sent.
     * @return {boolean}
     */
    isInvalid () {
      for (const action in this.actions) {
        if (this.actions[action] === true) {
          return false
        }
      }
      return true
    },

    next () {
      if (this.isInvalid()) {
        return
      }
      this.currentStep = this.currentStep < this.totalSteps ? this.currentStep + 1 : this.totalSteps
    },

    reset () {
      this.currentStep = 1
      this.actions.anonymizeStatementMeta = false
      this.actions.deleteStatementTextHistory = false
      this.actions.anonymizeStatementText = false
      this.actions.deleteStatementAttachments = false
      this.anonymizeText = this.statementText
    },

    prepareTextForApiCall (text) {
      return text.replace(/<span([^>]*?)title="([^"]*?)"([^>]*?)class="anonymize-me"([^>]*?)>([^<]*?)<\/span>/gm, (_text, _p1, p2) => (p2 !== '<br>') ? '<anonymize-text>' + p2 + '</anonymize-text>' : p2)
    },

    submit () {
      this.busy = true
      const payload = {
        data: {
          data: {
            statementId: this.statementId
          },
          actions: {
            anonymizeStatementText: this.actions.anonymizeStatementText ? this.prepareTextForApiCall(this.anonymizeText) : null,
            deleteStatementAttachments: this.actions.deleteStatementAttachments,
            anonymizeStatementMeta: this.actions.anonymizeStatementMeta,
            deleteStatementTextHistory: this.actions.deleteStatementTextHistory
          }
        }
      }
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_rpc_statement_anonymize'),
        data: payload,
        headers: {
          'Content-Type': 'application/json; charset=utf-8'
        }
      })
        .then(checkResponse)
        .then(() => {
          this.currentStep = 3
          this.busy = false
        })
        .catch(() => {
          this.busy = false
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    }
  }
}
</script>
