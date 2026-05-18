<template>
  <div>
    <dp-map-modal
      ref="mapModal"
      :procedure-id="procedureId"
    />

    <dp-loading
      v-if="isLoading"
      class="u-mt"
    />

    <template v-else>
      <template v-if="hasPermission('feature_admin_export_original_statement')">
        <div v-if="!selectedItemsCount">
          <export-flyout
            class="block mt-1"
            csv
            docx
            @export="type => handleExport(type)"
          />
        </div>

        <dp-bulk-edit-header
          v-else
          class="layout__item w-full mt-2"
          :selected-items-text="Translator.trans('items.selected.multi.page', { count: selectedItemsCount })"
          @reset-selection="resetSelection"
        >
          <export-flyout
            class="inline-block top-[-3px]"
            csv
            docx
            @export="type => handleExport(type)"
          />
        </dp-bulk-edit-header>
      </template>

      <dp-pager
        v-if="pagination.currentPage && items.length > 0"
        :key="`pager1_${pagination.currentPage}_${pagination.perPage}`"
        :class="{ 'invisible': isLoading }"
        class="m-2"
        :current-page="pagination.currentPage"
        :limits="pagination.limits"
        :multi-page-all-selected="allSelectedVisually"
        :multi-page-selection-items-total="allItemsCount"
        :multi-page-selection-items-toggled="toggledItems.length"
        :per-page="pagination.perPage"
        :total-items="pagination.total"
        :total-pages="pagination.totalPages"
        @page-change="fetchOriginalStatementsByPage"
        @size-change="handleSizeChange"
      />

      <dp-data-table
        v-if="items.length > 0"
        has-flyout
        :header-fields="headerFields"
        is-expandable
        is-selectable
        :items="items"
        :should-be-selected-items="currentlySelectedItems"
        track-by="id"
        @items-toggled="handleToggleItem"
        @select-all="handleSelectAll"
      >
        <template v-slot:externId="{ externId }">
          <span
            class="font-semibold"
            v-text="externId"
          />
        </template>
        <template
          v-slot:submitter="{ id }"
        >
          <div v-cleanhtml="getSubmitterName(id)" />
        </template>
        <template v-slot:submitDate="{ submitDate }">
          <span>
            {{ formatDate(submitDate) }}
          </span>
        </template>
        <template v-slot:shortText="{ shortText }">
          <div
            v-cleanhtml="shortText"
            class="line-clamp-3 c-styled-html"
          />
        </template>
        <template v-slot:procedurePhase="{ procedurePhase }">
          <span
            v-if="procedurePhase.name"
          >
            {{ procedurePhase.name }}
          </span>
        </template>
        <template
          v-if="hasPermission('area_statement_anonymize')"
          v-slot:flyout="{ externId, id }"
        >
          <dp-flyout>
            <a
              class="block u-pt-0 leading-[2] whitespace-nowrap"
              :href="Routing.generate('DemosPlan_statement_anonymize_view', { procedureId: procedureId, statementId: id })"
            >
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
          }"
        >
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
                    @click="toggleLocationModal(JSON.parse(polygon))"
                  >
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
                      :title="getOriginalStatementAsAttachment(id).attributes.filename"
                    >
                      <i
                        aria-hidden="true"
                        class="fa fa-paperclip color--grey"
                        :title="Translator.trans('attachment.original')"
                      />
                      {{ getOriginalStatementAsAttachment(id).attributes.filename }}
                    </a>
                    <span
                      v-else
                      class="ml-0"
                    >
                      -
                    </span>
                  </dd>
                </template>

                <dt class="font-semibold">
                  {{ Translator.trans('more.attachments') }}:
                </dt>
                <dd
                  v-if="getGenericAttachments(id).length > 0"
                  class="ml-0"
                >
                  <a
                    v-for="(file, idx) in getGenericAttachments(id)"
                    :key="idx"
                    class="block"
                    :href="Routing.generate('core_file_procedure', { hash: file.hash, procedureId: procedureId })"
                    rel="noopener"
                    target="_blank"
                    :title="file.filename"
                  >
                    <i
                      aria-hidden="true"
                      class="fa fa-paperclip color--grey"
                      :title="file.filename"
                    />
                    {{ file.filename }}
                  </a>
                </dd>
                <dd
                  v-else
                  class="ml-0"
                >
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
                  @click.prevent.stop="() => fetchFullTextById(id)"
                  @keydown.enter="() => fetchFullTextById(id)"
                >
                  {{ Translator.trans('show.more') }}
                </a>
              </template>
              <template v-else>
                <div v-cleanhtml="originalStatements[id].attributes.isFulltextDisplayed ? fullText : shortText" />
                <a
                  class="cursor-pointer"
                  rel="noopener"
                  @click="() => toggleIsFullTextDisplayed(id, !originalStatements[id].attributes.isFulltextDisplayed)"
                  @keydown.enter="() => toggleIsFullTextDisplayed(id, !originalStatements[id].attributes.isFulltextDisplayed)"
                >
                  {{ Translator.trans(originalStatements[id].attributes.isFulltextDisplayed ? 'show.less' : 'show.more') }}
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
        type="info"
      />
    </template>
  </div>
</template>

<script>
import {
  formatDate as _formatDate,
  CleanHtml,
  dpApi,
  DpBulkEditHeader,
  DpDataTable,
  DpFlyout,
  DpInlineNotification,
  DpLoading,
  DpPager,
  dpRpc,
  hasAnyPermissions,
  hasOwnProp,
  tableSelectAllItems,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'
import ExportFlyout from './ExportFlyout'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'

export default {
  name: 'ListOriginalStatements',

  components: {
    DpBulkEditHeader,
    DpDataTable,
    DpFlyout,
    DpInlineNotification,
    DpLoading,
    DpMapModal: defineAsyncComponent(() => import('@DpJs/components/statement/assessmentTable/DpMapModal')),
    DpPager,
    ExportFlyout,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  mixins: [
    paginationMixin,
    tableSelectAllItems,
  ],

  props: {
    currentUserId: {
      type: String,
      required: true,
    },

    procedureId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      allOriginalStatementIds: [],
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 10,
      },
      headerFields: [
        {
          field: 'externId',
          label: Translator.trans('id'),
          colClass: 'w-2',
        },
        {
          field: 'submitDate',
          label: Translator.trans('date'),
          colClass: 'w-8',
        },
        {
          field: 'submitter',
          label: Translator.trans('submitter.invitable_institution'),
        },
        {
          field: 'shortText',
          label: Translator.trans('text'),
        },
        {
          field: 'procedurePhase',
          label: Translator.trans('procedure.public.phase'),
        },
      ],
      isExpanded: false,
      isLoading: false,
      pagination: {},
    }
  },

  computed: {
    ...mapState('OriginalStatement', {
      originalStatements: 'items',
    }),

    // Needs to be named items for tableSelectAllItems mixin to work
    items () {
      return Object.values(this.originalStatements)
        .map(originalStatement => {
          return {
            id: originalStatement.id,
            ...originalStatement.attributes,
          }
        })
        .sort((a, b) => {
          return new Date(b.submitDate) - new Date(a.submitDate)
        })
    },

    storageKeyPagination () {
      return `${this.currentUserId}:${this.procedureId}:paginationOriginalStatementList`
    },
  },

  methods: {
    ...mapActions('OriginalStatement', {
      fetchOriginalStatements: 'list',
    }),

    ...mapMutations('OriginalStatement', {
      setOriginalStatement: 'set',
    }),

    fetchOriginalStatementById (originalStatementId) {
      return dpApi.get(Routing.generate('api_resource_get', {
        resourceType: 'OriginalStatement',
        resourceId: originalStatementId,
        fields: {
          OriginalStatement: ['fullText'].join(),
        },
      }))
    },

    fetchOriginalStatementsByPage (page) {
      this.isLoading = true
      const payload = this.preparePayload(page)

      this.fetchOriginalStatements(payload)
        .then(response => {
          this.setLocalStorage(response.meta.pagination)
          this.updatePagination(response.meta.pagination)

          this.allItemsCount = response.meta.pagination.total
          this.isLoading = false
        })
    },

    /**
     * Get orgaName if the statement was submitted by an institution
     * Get authorName if the statement was submitted by a citizen
     * @param {String} originalStatementId
     */
    getSubmitterName (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta.get()
      const {
        isSubmittedByCitizen,
      } = originalStatement.attributes
      const {
        authorName,
        orgaName,
      } = originalStatementMeta.attributes

      // Statement Institution
      if (isSubmittedByCitizen === false) {
        return orgaName
      }

      if (isSubmittedByCitizen) {
        return authorName
      }
    },

    getSelectedStatementIds () {
      /**
       * ToggledIds can be either
       * - selected ids
       * or
       * - deselected ids after 'select all' was checked
       */
      const toggledIds = this.toggledItems.map(item => item.id)
      let selectedStatementIds = []
      const areSomeDeselectedAfterSelectAll = this.trackDeselected
      const areSomeSelected = !this.trackDeselected

      if (areSomeSelected) {
        selectedStatementIds = toggledIds
      }

      if (areSomeDeselectedAfterSelectAll) {
        const areNoneDeselected = this.toggledItems.length === 0

        selectedStatementIds = areNoneDeselected ?
          this.allOriginalStatementIds :
          this.allOriginalStatementIds
            .filter(id => !toggledIds.includes(id))
      }

      return selectedStatementIds
    },

    handleExport (type) {
      const payload = {
        filter: {
          sameProcedure: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId,
            },
          },
        },
        procedureId: this.procedureId,
        sort: '-submitDate',
      }

      if (this.selectedItemsCount !== 0 && this.selectedItemsCount < this.allItemsCount) {
        payload.filter = {
          ...payload.filter,
          statementFilterGroup: {
            group: {
              conjunction: this.trackDeselected ? 'AND' : 'OR',
            },
          },
        }

        this.toggledItems.forEach(item => {
          payload.filter[item.id] = {
            condition: {
              memberOf: 'statementFilterGroup',
              operator: this.trackDeselected ? '<>' : '=',
              path: 'id',
              value: item.id,
            },
          }
        })
      }

      const url = type === 'docx' ?
        'dplan_original_statement_docx_export' :
        'dplan_original_statement_csv_export'

      window.location.href = Routing.generate(url, payload)
    },

    toggleIsFullTextDisplayed (originalStatementId, isFullTextDisplayed, fullText = null) {
      const originalStatement = this.originalStatements[originalStatementId]

      this.setOriginalStatement({
        ...originalStatement,
        id: originalStatementId,
        attributes: {
          ...originalStatement.attributes,
          ...(fullText && { fullText }),
          isFulltextDisplayed: isFullTextDisplayed,
        },
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

    fetchOriginalStatementIds () {
      return dpRpc('originalStatement.load.id', {
        filter: {
          sameProcedure: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId,
            },
          },
        },
      })
        .then(response => {
          this.allOriginalStatementIds = (hasOwnProp(response, 0) && response[0].result) ? response[0].result : []
          this.allItemsCount = this.allOriginalStatementIds.length
        })
    },

    formatDate (date) {
      return _formatDate(date)
    },

    getAuthorName (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta?.data ? originalStatement.relationships.meta.get() : null

      return originalStatementMeta?.attributes.authorName ?? '-'
    },

    getDepartmentName (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta?.data ? originalStatement.relationships.meta.get() : null

      return originalStatementMeta?.attributes.orgaDepartmentName ?? '-'
    },

    getDocumentTitle (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const document = originalStatement.relationships.document?.data ? originalStatement.relationships.document.get() : null

      return document ? document.attributes.title : ''
    },

    getElementTitle (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const element = originalStatement.relationships.elements?.data ? originalStatement.relationships.elements.get() : null

      return element ? element.attributes.title : '-'
    },

    getGenericAttachments (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const genericAttachments = originalStatement.relationships.genericAttachments?.data.length > 0 ? originalStatement.relationships.genericAttachments.list() : []

      return Object.values(genericAttachments).length > 0 ?
        Object.values(genericAttachments)
          .map(attachment => {
            const file = attachment.relationships.file.data ? attachment.relationships.file.get() : null

            return file ?
              {
                filename: file.attributes.filename,
                hash: file.attributes.hash,
                id: attachment.id,
              } :
              null
          })
          .filter(file => file !== null) :
        []
    },

    getOrganisationName (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const originalStatementMeta = originalStatement.relationships.meta?.data ? originalStatement.relationships.meta.get() : null

      return originalStatementMeta?.attributes.orgaName ?? '-'
    },

    getOriginalStatementAsAttachment (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
      const attachments = originalStatement.relationships.sourceAttachment?.data.length > 0 ? Object.values(originalStatement.relationships.sourceAttachment.list()) : []

      return attachments?.length > 0 ? attachments[0].relationships?.file.get() : null
    },

    getParagraphTitle (originalStatementId) {
      const originalStatement = this.originalStatements[originalStatementId]
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
        'textIsTruncated',
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
        'orgaName',
      ]

      return {
        page: {
          number: page,
          size: this.pagination.perPage,
        },
        fields: {
          ElementsDetails: ['title'].join(),
          File: [
            'filename',
            'hash',
          ].join(),
          GenericStatementAttachment: [
            'file',
          ].join(),
          OriginalStatement: originalStatementFields.join(),
          ParagraphVersion: [
            'title',
          ].join(),
          SourceStatementAttachment: [
            'file',
          ].join(),
          StatementMeta: statementMetaFields.join(),
          SingleDocument: [
            'title',
          ].join(),
        },
        include: [
          'document',
          'elements',
          'genericAttachments',
          'genericAttachments.file',
          'meta',
          'paragraph',
          'sourceAttachment',
          'sourceAttachment.file',
        ].join(),
      }
    },

    toggleLocationModal (locationReference) {
      this.$refs.mapModal.toggleModal(locationReference)
    },
  },

  mounted () {
    this.initPagination()
    this.fetchOriginalStatementsByPage(1)

    if (hasPermission('feature_admin_export_original_statement')) {
      this.fetchOriginalStatementIds()
    }
  },
}
</script>
