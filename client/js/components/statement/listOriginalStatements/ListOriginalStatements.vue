<template>
  <div>
    <dp-loading
      v-if="isLoading"
      class="u-mt"/>

    <template v-else>
      <dp-data-table
        v-if="originalStatements.length > 0"
        has-flyout
        :header-fields="headerFields"
        is-expandable
        :items="originalStatements"
        track-by="id">
        <template v-slot:externId="{ externId }">
          <span
            class="font-semibold"
            v-text="externId" />
        </template>
        <template
          v-slot:submitter="{
            authorName,
            isSubmittedByCitizen,
            initialOrganisationName,
            submitName
          }">
          <ul class="o-list max-w-12">
            <li
              v-if="authorName !== '' || submitName !== ''"
              class="o-list__item o-hellip--nowrap">
              {{ authorName ? authorName : (submitName ? submitName : Translator.trans('citizen')) }}
            </li>
            <li
              v-if="initialOrganisationName !== '' && !isSubmittedByCitizen"
              class="o-list__item o-hellip--nowrap">
              {{ initialOrganisationName }}
            </li>
          </ul>
        </template>
        <template v-slot:submitDate="{ submitDate }">
        <span>
          {{ formatDate(submitDate) }}
        </span>
        </template>
<!--        <template v-slot:text="{ text }">-->
<!--          <div-->
<!--            class="line-clamp-3 c-styled-html"-->
<!--            v-cleanhtml="text" />-->
<!--        </template>-->
        <template v-slot:document="{ document }">
        <span
          v-if="document">
          {{ document.title}}
        </span>
        </template>
        <template v-slot:phase="{ phase }">
        <span
          v-if="phase">
          {{ phase }}
        </span>
        </template>
        <template
          v-if="hasPermission('area_statement_anonymize')"
          v-slot:flyout="{ externId, id }">
          <a :href="Routing.generate('DemosPlan_statement_anonymize_view', { procedureId: procedureId, statementId: id })">
            {{ Translator.trans('statement.anonymize', { externId: externId }) }}
          </a>
        </template>
        <template v-slot:expandedContent="{ text, fullText, id }">
          <div class="u-pt-0_5 c-styled-html">
            <strong>{{ Translator.trans('statement.text.short') }}:</strong>
            <template v-if="typeof fullText === 'undefined'">
              <div v-cleanhtml="text" />
              <a
                v-if="items[id].attributes.textIsTruncated"
                class="show-more cursor-pointer"
                @click.prevent.stop="() => fetchFullTextById(id)"
                rel="noopener">
                {{ Translator.trans('show.more') }}
              </a>
            </template>
            <template v-else>
              <div v-cleanhtml="items[id].attributes.isFulltextDisplayed ? fullText : text" />
              <a
                class="cursor-pointer"
                @click="() => fetchFullTextById(id)"
                rel="noopener">
                {{ Translator.trans(items[id].attributes.isFulltextDisplayed ? 'show.less' : 'show.more') }}
              </a>
            </template>
          </div>
        </template>
      </dp-data-table>

      <dp-inline-notification
        v-else
        :message="Translator.trans('statements.none')"
        type="info" />
    </template>
  </div>
</template>

<script>
import {
  CleanHtml,
  DpDataTable,
  DpInlineNotification,
  DpLoading,
  formatDate as _formatDate
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
export default {
  name: 'ListOriginalStatements',

  components: {
    DpDataTable,
    DpInlineNotification,
    DpLoading
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [paginationMixin],

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 10
      },
      headerFields: [
        {
          field: 'externId',
          label: Translator.trans('id'),
          colClass: 'w-2'
        },
        {
          field: 'submitDate',
          label: Translator.trans('date'),
          colClass: 'w-8'
        },
        {
          field: 'submitter',
          label: Translator.trans('submitter.invitable_institution')
        },
        {
          field: 'text',
          label: Translator.trans('text')
        },
        {
          field: 'document',
          label: Translator.trans('document')
        },
        {
          field: 'phase',
          label: Translator.trans('procedure.public.phase')
        }
      ],
      isLoading: false,
      pagination: {}
    }
  },

  computed: {
    ...mapState('OriginalStatement', {
        items: 'items'
    }),

    originalStatements () {
      return Object.values(this.items).map(originalStatement => {
        return {
          id: originalStatement.id,
          ...originalStatement.attributes
        }
      })
    }
  },

  methods: {
    ...mapActions('OriginalStatement', {
        fetchOriginalStatements: 'list',
        fetchById: 'get',
    }),

    ...mapMutations('OriginalStatement', {
      setOriginalStatement: 'set'
    }),

    fetchOriginalStatementsByPage (page) {
      this.isLoading = true
      const payload = this.preparePayload(page)
      this.fetchOriginalStatements(payload)
        .then(() => {
          this.isLoading = false
        })
    },

    fetchFullTextById (originalStatementId) {
      return this.fetchById(originalStatementId, { fields: { Statement: ['fullText'].join() }})
        .then(response => {
          const oldStatement = Object.values(this.items).find(el => el.id === originalStatementId)
          const  { fullText } = response.data.data.attributes
          const updatedStatement = {
            ...oldStatement,
            attributes: {
              ...oldStatement.attributes,
              fullText,
              isFulltextDisplayed: true
            } }

          this.setOriginalStatement({
            ...updatedStatement,
            id: statementId
          })
        })
    },

    formatDate (date) {
      return _formatDate(date)
    },

    preparePayload (page) {
      const originalStatementFields = [
        'attachmentsDeleted',
        'authorName',
        'document',
        'elementId',
        'externId',
        'files',
        'isSubmittedByCitizen',
        'orgaDepartmentName',
        'orgaName',
        'phase',
        'polygon',
        'sourceAttachment',
        'submitDate',
        'submitName',
        'submitterAndAuthorMetaDataAnonymized',
        'text',
        'textPassagesAnonymized',
        'votesNum'
      ]

      return {
        page: {
          number: page,
          size: this.pagination.perPage
        },
        fields: {
          OriginalStatement: originalStatementFields.join()
        }
      }
    }
  },

  mounted () {
    this.initPagination()
    // this.fetchOriginalStatements()
    this.fetchOriginalStatementsByPage(1)
  }
}
</script>
