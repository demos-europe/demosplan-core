<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <tr
    class="c-at-orig__row"
    :id="`itemdisplay_${statement.id}`">
    <td
      colspan="6"
      class="overflow-visible">
      <table :aria-label="Translator.trans('statement')">
        <colgroup>
          <col class="w-[10%]">
          <col class="w-[10%] text-left">
          <col
            span="3"
            class="w-1/4">
          <col class="w-[5%] text-right">
        </colgroup>
        <thead>
          <tr class="sr-only">
            <th scope="col">
              {{ Translator.trans('statement.id') }}
            </th>
            <th scope="col">
              {{ Translator.trans('statement.date.submitted') }}
            </th>
            <th scope="col">
              {{ Translator.trans('submitter.invitable_institution') }}
            </th>
            <th scope="col">
              {{ Translator.trans('document.reference') }}
            </th>
            <th scope="col">
              {{ Translator.trans('procedure.public.phase') }}
            </th>
            <th />
          </tr>
        </thead>
        <tbody
          class="c-at-orig__body c-at-orig__headrow"
          data-cy="originalStatementHeader">
          <tr>
            <td>
              <label class="whitespace-nowrap u-m-0">
                <input
                  type="checkbox"
                  name="item_check[]"
                  data-cy="originalStatementCheckItem"
                  :id="`checkStatement:${statement.id}`"
                  :checked="isSelected"
                  @change="toggleSelection"
                  :value="statement.id">
                {{ statement.externId }}
              </label>
            </td>
            <td :title="Translator.trans('statement.date.submitted')">
              {{ formatDate(statement.submitDate) }}
            </td>
            <td>
              {{ submitter }}
            </td>
            <td v-cleanhtml="element">
              {{ element }}
            </td>
            <td>
              {{ statement.phase }}
            </td>
            <td class="text-right">
              <dp-flyout v-if="hasPermission('area_statement_anonymize')">
                <a :href="Routing.generate('DemosPlan_statement_anonymize_view', { procedureId: procedureId, statementId: statement.id })">
                  {{ Translator.trans('statement.anonymize', { externId: statement.externId }) }}
                </a>
              </dp-flyout>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="currentTableView === 'expanded'"
        data-cy="originalStatementText"
        class="c-at-orig__body">
        <div class="c-at-orig__statement-text u-ph">
          <h3 class="font-size-medium weight--bold">
            {{ Translator.trans('statementtext') }}
          </h3>
          <height-limit
            :short-text="!statement.shortText ? statement.text : statement.shortText"
            :full-text="statement.text"
            :is-shortened="statement.textIsTruncated"
            @heightLimit:toggle="loadFullText"
            element="statement"
            class="c-styled-html u-mr"
          />
        </div>

        <div
          v-if="statement.sourceAttachment !== '' || statement.files.length > 0 || statement.polygon !== ''"
          class="u-ml u-pr text-left border--top">
          <div
            v-if="statement.sourceAttachment !== '' || statement.files.length > 0"
            class="break-words">
            <i
              :title="Translator.trans('attachment.original')"
              aria-hidden="true"
              class="fa fa-paperclip color--grey" />
            <a
              v-if="statement.sourceAttachment !== '' && hasPermission('feature_read_source_statement_via_api')"
              :title="statement.sourceAttachment.filename"
              target="_blank"
              rel="noopener"
              class="o-hellip"
              :class="statement.files.length > 0 ? 'border--right border-color--grey-light u-mr-0_5 u-pr-0_5' : ''"
              :href="Routing.generate('core_file_procedure', { hash: statement.sourceAttachment.hash, procedureId: procedureId })">
              {{ statement.sourceAttachment.filename }}
            </a>

            <a
              v-for="(file, idx) in statement.files"
              :key="idx"
              :title="file.name"
              target="_blank"
              rel="noopener"
              class="o-hellip"
              :href="Routing.generate('core_file_procedure', { hash: file.hash, procedureId: procedureId })">
              {{ file.filename }}
            </a>
          </div>

          <button
            v-if="statement.polygon !== ''"
            class="btn--blank o-link--default"
            type="button"
            @click="toggleModal">
            <i
              class="fa fa-map-marker"
              aria-hidden="true" />
            {{ Translator.trans('see') }}
          </button>
        </div>

        <template v-if="hasPermission('feature_statement_gdpr_consent')">
          <div
            v-if="statement.consented"
            class="border--top">
            <input
              type="checkbox"
              checked
              disabled>
            <span> {{ Translator.trans('personal.data.usage.allowed') }} </span>
          </div>
          <div
            v-else-if="statement.consetRevoked"
            class="border--top">
            <span> {{ Translator.trans('personal.data.usage.revoked') }} </span>
            <span> {{ Translator.trans('personal.data.usage.revoked.statement') }} </span>
          </div>
        </template>

        <div
          v-if="hasPermission('area_statement_anonymize') && (statement.submitterAndAuthorMetaDataAnonymized || statement.textPassagesAnonymized || statement.attachmentsDeleted)"
          class="border--top">
          <ul class="u-mb-0 u-ml-0_5">
            <li v-if="statement.submitterAndAuthorMetaDataAnonymized">
              {{ Translator.trans('statement.anonymized.submitter.data') }}
            </li>
            <li v-if="statement.textPassagesAnonymized">
              {{ Translator.trans('statement.anonymized.text.passages') }}
            </li>
            <li v-if="statement.attachmentsDeleted">
              {{ Translator.trans('statement.anonymized.attachments') }}
            </li>
          </ul>
        </div>
      </div>
    </td>
  </tr>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpFlyout,
  formatDate,
  hasOwnProp
} from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations, mapState } from 'vuex'
import HeightLimit from '@DpJs/components/statement/HeightLimit'

export default {
  name: 'OriginalStatementsTableItem',

  components: {
    DpFlyout,
    HeightLimit
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    currentTableView: {
      type: String,
      required: true
    },

    isSelected: {
      required: true,
      type: Boolean
    },

    procedureId: {
      type: String,
      required: true
    },

    statementId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      fullTextLoaded: false
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', ['elements', 'paragraph']),
    ...mapState('Statement', ['statements', 'selectedElements']),

    element () {
      let elementTitle = ''
      const element = this.statement.elementId ? this.elements.find((el) => el.id === this.statement.elementId) : null

      if (element && element.title !== '') {
        elementTitle = element.title
        if (hasOwnProp(this.statement, 'document') && this.statement.document.title !== '') {
          elementTitle += ` / ${this.statement.document.title}`
        }
      } else {
        elementTitle = Translator.trans('notspecified')
      }

      if (hasOwnProp(this.statement, 'paragraph')) {
        const elementParagraphs = this.selectedElementParagraph()

        if (elementParagraphs && this.statement.paragraphParentId && this.statement.elementId) {
          const paragraph = elementParagraphs.find((el) => el.elementId === this.statement.elementId)
          elementTitle += `<br>${paragraph.title}`
        }
      }

      return elementTitle
    },

    statement () {
      return this.statements[this.statementId]
    },

    submitter () {
      let name = ''

      // Statement Institution
      if (this.statement.isSubmittedByCitizen === false) {
        name += this.statement.orgaName

        if (this.statement.orgaDepartmentName) {
          name += `<br>${this.statement.orgaDepartmentName}`
        }

      // Manual statement
      } else if (!this.statement.orgaName) {
        name += this.statement.authorName

      // Statement 'Citizen'
      } else if (this.statement.isSubmittedByCitizen) {
        name += (this.statement.authorName !== '')
          ? this.statement.authorName
          : `${Translator.trans('role.citizen')} (${Translator.trans('anonymous')})`

        if (this.statement.votesNum > 0) {
          name += `<br>${Translator.trans('voters')}: ${this.statement.votesNum}`
        }

        if (hasPermission('feature_statements_like') && this.statement.publicAllowed) {
          name += `<br>${Translator.trans('liked.by')}: ${this.statement.likesNum}`
        }
      } else {
        name += Translator.trans('notspecified')
      }

      return name
    }
  },

  methods: {
    ...mapMutations('Statement', [
      'updateStatement'
    ]),

    formatDate (date) {
      return formatDate(date)
    },

    loadFullText (callback) {
      if (this.fullTextLoaded) {
        callback()
        return
      }

      dpApi.get(Routing.generate('dm_plan_assessment_get_statement_ajax', { statementId: this.statementId }))
        .then(response => {
          this.fullTextLoaded = true

          this.updateStatement({
            id: this.statementId,
            shortText: this.statement.text,
            text: response.data.data.original
          })
        })
        .then(callback)
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },

    selectedElementParagraph () {
      return this.statement.elementId && this.paragraph[this.statement.elementId] ? this.paragraph[this.statement.elementId] : []
    },

    toggleModal () {
      this.$parent.$refs.mapModal.toggleModal(JSON.parse(this.statement.polygon))
    },

    toggleSelection () {
      if (this.isSelected) {
        this.$emit('remove-from-selection', this.statementId)
      } else {
        this.$emit('add-to-selection', this.statementId)
      }
    }
  }
}
</script>
