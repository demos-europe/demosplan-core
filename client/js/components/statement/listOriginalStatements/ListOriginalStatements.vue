<template>
  <div>
    <dp-map-modal
      ref="mapModal"
      :procedure-id="procedureId" />

    <dp-loading
      v-if="isLoading"
      class="u-mt" />

    <template v-else>
      <dp-pager
        v-if="pagination.currentPage && originalStatements.length > 0"
        :class="{ 'invisible': isLoading }"
        class="mt-4 mb-2"
        :current-page="pagination.currentPage"
        :key="`pager1_${pagination.currentPage}_${pagination.perPage}`"
        :limits="pagination.limits"
        :per-page="pagination.perPage"
        :total-items="pagination.total"
        :total-pages="pagination.totalPages"
        @page-change="fetchOriginalStatementsByPage"
        @size-change="handleSizeChange" />

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
          v-slot:submitter="{ id }">
          <div v-cleanhtml="getSubmitterName(id)" />
        </template>
        <template v-slot:submitDate="{ submitDate }">
          <span>
            {{ formatDate(submitDate) }}
          </span>
        </template>
        <template v-slot:shortText="{ shortText }">
          <div
            class="line-clamp-3 c-styled-html"
            v-cleanhtml="shortText" />
        </template>
        <template v-slot:procedurePhase="{ procedurePhase }">
          <span
            v-if="procedurePhase.name">
            {{ procedurePhase.name }}
          </span>
        </template>
        <template
          v-if="hasPermission('area_statement_anonymize')"
          v-slot:flyout="{ externId, id }">
          <dp-flyout>
            <a
              class="block u-pt-0 leading-[2] whitespace-nowrap"
              :href="Routing.generate('DemosPlan_statement_anonymize_view', { procedureId: procedureId, statementId: id })">
              {{ Translator.trans('statement.anonymize', { externId: externId }) }}
            </a>
          </dp-flyout>
        </template>
        <template
          v-slot:expandedContent="{
            fullText,
            id,
            polygon,
            shortText,
            textIsTruncated
          }">
          <div class="u-pt-0_5">
            <!-- Meta data -->
            <div>
              <!-- Submission meta data -->
              <dl class="inline-grid grid-cols-[25%_75%] w-[49%]">
                <dt class="font-semibold">
                  {{ Translator.trans('submitter') }}:
                </dt>
                <dd class="ml-0">
                  {{ getAuthorName(id) }}
                </dd>
                <dt class="font-semibold">
                  {{ Translator.trans('organisation') }}:
                </dt>
                <dd class="ml-0">
                  {{ getOrganisationName(id) }}
                </dd>
                <dt class="font-semibold">
                  {{ Translator.trans('department') }}:
                </dt>
                <dd class="ml-0">
                  {{ getDepartmentName(id) }}
                </dd><!--
          -->
              </dl>
              <!-- Document & location reference -->
              <dl class="inline-grid grid-cols-[25%_75%] w-[49%]">
                <dt class="font-semibold">
                  {{ Translator.trans('document') }}:
                </dt>
                <dd class="ml-0">
                  <span>
                    {{ getElementTitle(id) }}
                  </span>
                  <span v-if="getElementTitle(id) !== '-' && getDocumentTitle(id)">
                    / {{ getDocumentTitle(id) }}
                  </span>
                </dd>

                <dt class="font-semibold">
                  {{ Translator.trans('paragraph') }}:
                </dt>
                <dd class="ml-0">
                  {{ getParagraphTitle(id) }}
                </dd>

                <dt class="font-semibold">
                  {{ Translator.trans('public.participation.relation') }}:
                </dt>
                <dd class="ml-0">
                  <button
                    v-if="polygon !== ''"
                    class="btn--blank o-link--default"
                    data-cy="originalStatementList:toggleLocationModal"
                    type="button"
                    @click="toggleLocationModal(JSON.parse(polygon))">
                    {{ Translator.trans('see') }}
                  </button>
                  <span v-else>
                    -
                  </span>
                </dd>
              </dl>

              <!-- Attachments -->
              <dl class="grid grid-cols-[25%_75%]">
                <template v-if="hasPermission('feature_read_source_statement_via_api')">
                  <dt class="font-semibold">
                    {{ Translator.trans('attachment.original') }}:
                  </dt>
                  <dd class="ml-0">
                    <a
                      v-if="getOriginalStatementAsAttachment(id) !== null"
                      :href="Routing.generate('core_file_procedure', { hash: getOriginalStatementAsAttachment(id).attributes.hash, procedureId: procedureId })"
                      rel="noopener"
                      target="_blank"
                      :title="getOriginalStatementAsAttachment(id).attributes.filename">
                      <i
                        aria-hidden="true"
                        class="fa fa-paperclip color--grey"
                        :title="Translator.trans('attachment.original')" />
                      {{ getOriginalStatementAsAttachment(id).attributes.filename }}
                    </a>
                    <span
                      v-else
                      class="ml-0">
                      -
                    </span>
                  </dd>
                </template>

                <dt class="font-semibold">
                  {{ Translator.trans('more.attachments') }}:
                </dt>
                <dd
                  v-if="getGenericAttachments(id).length > 0"
                  class="ml-0">
                  <a
                    v-for="(file, idx) in getGenericAttachments(id)"
                    class="block"
                    :href="Routing.generate('core_file_procedure', { hash: file.hash, procedureId: procedureId })"
                    :key="idx"
                    rel="noopener"
                    target="_blank"
                    :title="file.filename">
                    <i
                      aria-hidden="true"
                      class="fa fa-paperclip color--grey"
                      :title="file.filename" />
                    {{ file.filename }}
                  </a>
                </dd>
                <dd
                  v-else
                  class="ml-0">
                  -
                </dd>
              </dl>
            </div>

            <div class="c-styled-html">
              <strong>
                {{ Translator.trans('statement.text.short') }}:
              </strong>
              <template v-if="typeof fullText === 'undefined'">
                <div v-cleanhtml="shortText" />
                <a
                  v-if="textIsTruncated"
                  class="show-more cursor-pointer"
                  rel="noopener"
                  @click.prevent.stop="() => fetchFullTextById(id)">
                  {{ Translator.trans('show.more') }}
                </a>
              </template>
              <template v-else>
                <div v-cleanhtml="items[id].attributes.isFulltextDisplayed ? fullText : shortText" />
                <a
                  class="cursor-pointer"
                  rel="noopener"
                  @click="() => toggleIsFullTextDisplayed(id, !items[id].attributes.isFulltextDisplayed)">
                  {{ Translator.trans(items[id].attributes.isFulltextDisplayed ? 'show.less' : 'show.more') }}
                </a>
              </template>
            </div>
          </div>
        </template>
      </dp-data-table>

      <dp-inline-notification
        v-else
        class="mt-3"
        :message="Translator.trans('statements.none')"
        type="info" />
    </template>
  </div>
</template>

<script>
import {
  formatDate as _formatDate,
  CleanHtml,
  dpApi,
  DpDataTable,
  DpFlyout,
  DpInlineNotification,
  DpLoading,
  DpPager,
  hasAnyPermissions
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'

export default {
  name: 'ListOriginalStatements',

  components: {
    DpDataTable,
    DpFlyout,
    DpInlineNotification,
    DpLoading,
    DpMapModal: () => import('@DpJs/components/statement/assessmentTable/DpMapModal'),
    DpPager
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [paginationMixin],

  props: {
    currentUserId: {
      type: String,
      required: true
    },

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
          field: 'shortText',
          label: Translator.trans('text')
        },
        {
          field: 'procedurePhase',
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
    },

    storageKeyPagination () {
      return `${this.currentUserId}:${this.procedureId}:paginationOriginalStatementList`
    }
  },

  methods: {
    ...mapActions('OriginalStatement', {
      fetchOriginalStatements: 'list'
    }),

    ...mapMutations('OriginalStatement', {
      setOriginalStatement: 'set'
    }),

    /**
     * Get orgaName if the statement was submitted by an institution
     * Get authorName if the statement was submitted by a citizen
     * @param {String} originalStatementId
     */
    getSubmitterName (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta.get()
      const {
        isSubmittedByCitizen
      } = originalStatement.attributes
      const {
        authorName,
        orgaName
      } = originalStatementMeta.attributes

      // Statement Institution
      if (isSubmittedByCitizen === false) {
        return orgaName
      }

      if (isSubmittedByCitizen) {
        return authorName
      }
    },

    fetchOriginalStatementById (originalStatementId) {
      return dpApi.get(Routing.generate('api_resource_get', {
        resourceType: 'OriginalStatement',
        resourceId: originalStatementId,
        fields: {
          OriginalStatement: ['fullText'].join()
        }
      }))
    },

    fetchOriginalStatementsByPage (page) {
      this.isLoading = true
      const payload = this.preparePayload(page)

      this.fetchOriginalStatements(payload)
        .then(response => {
          this.setLocalStorage(response.meta.pagination)
          this.updatePagination(response.meta.pagination)

          this.isLoading = false
        })
    },

    toggleIsFullTextDisplayed (originalStatementId, isFullTextDisplayed, fullText = null) {
      const originalStatement = this.items[originalStatementId]

      this.setOriginalStatement({
        ...originalStatement,
        id: originalStatementId,
        attributes: {
          ...originalStatement.attributes,
          ...(fullText && { fullText }),
          isFulltextDisplayed: isFullTextDisplayed
        }
      })
    },

    /**
     * If fullText is already displayed, set isFullTextDisplayed to false and display
     * shortText instead
     * If fullText is not displayed, fetch fullText from API, set isFullTextDisplayed
     * to true and display fullText
     * @param originalStatementId
     */
    fetchFullTextById (originalStatementId) {
      return this.fetchOriginalStatementById(originalStatementId)
        .then(response => {
          const { fullText } = response.data.data.attributes
          this.toggleIsFullTextDisplayed(originalStatementId, true, fullText)
        })
    },

    formatDate (date) {
      return _formatDate(date)
    },

    getAuthorName (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta?.data ? originalStatement.relationships.meta.get() : null

      return originalStatementMeta?.attributes.authorName ?? '-'
    },

    getDepartmentName (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta?.data ? originalStatement.relationships.meta.get() : null

      return originalStatementMeta?.attributes.orgaDepartmentName ?? '-'
    },

    getDocumentTitle (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const document = originalStatement.relationships.document?.data ? originalStatement.relationships.document.get() : null

      return document ? document.attributes.title : ''
    },

    getElementTitle (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const element = originalStatement.relationships.elements?.data ? originalStatement.relationships.elements.get() : null

      return element ? element.attributes.title : '-'
    },

    getGenericAttachments (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const genericAttachments = originalStatement.relationships.genericAttachments?.data.length > 0 ? originalStatement.relationships.genericAttachments.list() : []

      return Object.values(genericAttachments).length > 0
        ? Object.values(genericAttachments)
          .map(attachment => {
            const file = attachment.relationships.file.data ? attachment.relationships.file.get() : null

            return file
              ? {
                  filename: file.attributes.filename,
                  hash: file.attributes.hash,
                  id: attachment.id
                }
              : null
          })
          .filter(file => file !== null)
        : []
    },

    getOrganisationName (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta?.data ? originalStatement.relationships.meta.get() : null

      return originalStatementMeta?.attributes.orgaName ?? '-'
    },

    getOriginalStatementAsAttachment (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const attachments = originalStatement.relationships.sourceAttachment?.data.length > 0 ? Object.values(originalStatement.relationships.sourceAttachment.list()) : []

      return attachments?.length > 0 ? attachments[0].relationships?.file.get() : null
    },

    getParagraphTitle (originalStatementId) {
      const originalStatement = this.items[originalStatementId]
      const paragraph = originalStatement.relationships.paragraph?.data ? originalStatement.relationships.paragraph.get() : null

      return paragraph ? paragraph.attributes.title : '-'
    },

    handleSizeChange (newSize) {
      const page = Math.floor((this.pagination.perPage * (this.pagination.currentPage - 1) / newSize) + 1)
      this.pagination.perPage = newSize
      this.fetchOriginalStatementsByPage(page)
    },

    preparePayload (page) {
      const originalStatementFields = [
        'document',
        'elements',
        'externId',
        'genericAttachments',
        'meta',
        'paragraph',
        'procedurePhase',
        'polygon',
        'sourceAttachment',
        'shortText',
        'submitDate',
        'textIsTruncated'
      ]

      if (hasAnyPermissions([
        'feature_segments_of_statement_list',
        'area_statement_segmentation',
        'area_admin_statement_list',
        'area_admin_submitters'])) {
        originalStatementFields.push('isSubmittedByCitizen')
      }

      const statementMetaFields = [
        'authorName',
        'orgaDepartmentName',
        'orgaName'
      ]

      return {
        page: {
          number: page,
          size: this.pagination.perPage
        },
        fields: {
          ElementsDetails: ['title'].join(),
          File: [
            'filename',
            'hash'
          ].join(),
          GenericStatementAttachment: [
            'file'
          ].join(),
          OriginalStatement: originalStatementFields.join(),
          ParagraphVersion: [
            'title'
          ].join(),
          SourceStatementAttachment: [
            'file'
          ].join(),
          StatementMeta: statementMetaFields.join(),
          SingleDocument: [
            'title'
          ].join()
        },
        include: [
          'document',
          'elements',
          'genericAttachments',
          'genericAttachments.file',
          'meta',
          'paragraph',
          'sourceAttachment',
          'sourceAttachment.file'
        ].join()
      }
    },

    toggleLocationModal (locationReference) {
      this.$refs.mapModal.toggleModal(locationReference)
    }
  },

  mounted () {
    this.initPagination()
    this.fetchOriginalStatementsByPage(1)
  }
}
</script>
