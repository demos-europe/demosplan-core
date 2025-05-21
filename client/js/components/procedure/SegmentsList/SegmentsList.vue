<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="{ 'top-0 left-0 flex flex-col w-full h-full fixed z-fixed bg-surface': isFullscreen }">
    <dp-sticky-element
      border
      class="pt-2 pb-3"
      :class="{ 'fixed top-0 left-0 w-full px-2': isFullscreen }">
      <div class="flex items-start mb-2">
        <custom-search
          id="customSearch"
          ref="customSearch"
          :elasticsearch-field-definition="{
            entity: 'statementSegment',
            function: 'search',
            accessGroup: 'planner'
          }"
          :search-term="searchTerm"
          @changeFields="updateSearchFields"
          @search="term => updateSearchQuery(term)"
          @reset="handleResetSearch" />
        <div class="ml-2 space-x-1 space-x-reverse">
          <filter-flyout
            v-for="(filter, idx) in Object.values(filters)"
            ref="filterFlyout"
            :additional-query-params="{ searchPhrase: searchTerm }"
            :category="{ id: `${filter.labelTranslationKey}:${idx}`, label: Translator.trans(filter.labelTranslationKey) }"
            class="inline-block first:mr-1"
            :data-cy="`segmentsListFilter:${filter.labelTranslationKey}`"
            :groups-object="filter.groupsObject"
            :initial-query-ids="queryIds"
            :items-object="filter.itemsObject"
            :key="`filter_${filter.labelTranslationKey}`"
            :operator="filter.comparisonOperator"
            :path="filter.rootPath"
            :show-count="{
              groupedOptions: true,
              ungroupedOptions: true
            }"
            @filterApply="sendFilterQuery"
            @filterOptions:request="(params) => sendFilterOptionsRequest({ ...params, category: { id: `${filter.labelTranslationKey}:${idx}`, label: Translator.trans(filter.labelTranslationKey) }})" />
        </div>
        <dp-button
          class="ml-2 h-fit"
          data-cy="segmentsList:resetFilter"
          :disabled="noQuery"
          :text="Translator.trans('reset')"
          variant="outline"
          v-tooltip="Translator.trans('search.filter.reset')"
          @click="resetQuery" />
        <dp-button
          class="ml-auto"
          data-cy="editorFullscreen"
          :icon="isFullscreen ? 'compress' : 'expand'"
          icon-size="medium"
          hide-text
          variant="outline"
          :text="isFullscreen ? Translator.trans('editor.fullscreen.close') : Translator.trans('editor.fullscreen')"
          @click="handleFullscreenMode()" />
      </div>
      <dp-bulk-edit-header
        v-if="selectedItemsCount > 0"
        class="layout__item u-12-of-12 u-mt-0_5"
        :selected-items-text="Translator.trans('items.selected.multi.page', { count: selectedItemsCount })"
        @reset-selection="resetSelection">
        <dp-button
          :text="Translator.trans('segments.bulk.edit')"
          variant="outline"
          @click.prevent="handleBulkEdit" />
      </dp-bulk-edit-header>
      <div
        v-if="items.length > 0"
        class="flex justify-between items-center mt-4">
        <dp-pager
          v-if="pagination.currentPage"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :key="`pager1_${pagination.currentPage}_${pagination.count}`"
          :limits="pagination.limits"
          :per-page="pagination.perPage"
          :total-pages="pagination.totalPages"
          :total-items="pagination.total"
          @page-change="applyQuery"
          @size-change="handleSizeChange" />
        <dp-column-selector
          data-cy="segmentsList:selectableColumns"
          :initial-selection="currentSelection"
          local-storage-key="segmentList"
          :selectable-columns="selectableColumns"
          use-local-storage
          @selection-changed="setCurrentSelection" />
      </div>
    </dp-sticky-element>

    <dp-loading
      class="u-mt"
      v-if="isLoading" />

    <template v-else>
      <template v-if="items.length > 0">
        <image-modal
          ref="imageModal"
          data-cy="segment:imgModal" />
        <dp-data-table
          ref="dataTable"
          class="overflow-x-auto pb-3 min-h-12"
          :class="{ 'px-2 overflow-y-scroll grow': isFullscreen, 'scrollbar-none': !isFullscreen }"
          data-cy="segmentsList"
          has-flyout
          :header-fields="availableHeaderFields"
          is-resizable
          is-selectable
          :items="items"
          :multi-page-all-selected="allSelectedVisually"
          :multi-page-selection-items-total="allItemsCount"
          :multi-page-selection-items-toggled="toggledItems.length"
          :should-be-selected-items="currentlySelectedItems"
          track-by="id"
          @select-all="handleSelectAll"
          @items-toggled="handleToggleItem">
          <template v-slot:externId="rowData">
            <v-popover trigger="hover focus">
              <div class="whitespace-nowrap">
                {{ rowData.attributes.externId }}
              </div>
              <template v-slot:popover>
                <statement-meta-tooltip
                  :assignable-users="assignableUsers"
                  :statement="statementsObject[rowData.relationships.parentStatement.data.id]"
                  :segment="rowData"
                  :places="places" />
              </template>
            </v-popover>
          </template>
          <template v-slot:statementStatus="rowData">
            <status-badge
              class="mt-0.5"
              :status="statementsObject[rowData.relationships.parentStatement.data.id].attributes.status" />
          </template>
          <template v-slot:internId="rowData">
            <div class="o-hellip__wrapper">
              <div
                class="o-hellip--nowrap text-right"
                v-tooltip="statementsObject[rowData.relationships.parentStatement.data.id].attributes.internId"
                dir="rtl">
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.internId }}
              </div>
            </div>
          </template>
          <template v-slot:submitter="rowData">
            <ul class="o-list max-w-12">
              <li
                v-if="statementsObject[rowData.relationships.parentStatement.data.id].attributes.authorName !== ''"
                class="o-list__item o-hellip--nowrap">
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.authorName }}
              </li>
              <li
                v-else
                class="o-list__item o-hellip--nowrap">
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.submitName }}
              </li>
              <li
                v-if="statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationName !== ''"
                class="o-list__item o-hellip--nowrap">
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationName }}
              </li>
            </ul>
          </template>
          <template v-slot:address="rowData">
            <ul class="o-list">
              <li
                v-if="statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationStreet !== ''"
                class="o-list__item o-hellip--nowrap">
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationStreet }}
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationHouseNumber }}
              </li>
              <li
                v-if="statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationPostalCode !== ''"
                class="o-list__item o-hellip--nowrap">
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationPostalCode }}
                {{ statementsObject[rowData.relationships.parentStatement.data.id].attributes.initialOrganisationCity }}
              </li>
            </ul>
          </template>
          <template v-slot:place="rowData">
            {{ placesObject[rowData.relationships.place.data.id].attributes.name }}
          </template>
          <template v-slot:text="rowData">
            <text-content-renderer
              class="overflow-word-break c-styled-html"
              :text="rowData.attributes.text" />
          </template>
          <template v-slot:recommendation="rowData">
            <div v-cleanhtml="rowData.attributes.recommendation !== '' ? rowData.attributes.recommendation : '-'" />
          </template>
          <template v-slot:tags="rowData">
            <span
              v-for="tag in getTagsBySegment(rowData.id)"
              :key="tag.id"
              class="rounded-md"
              style="color: #63667e; background: #EBE9E9; padding: 2px 4px; margin: 4px 2px; display: inline-block;">
              {{ tag.attributes.title }}
            </span>
          </template>
          <template
            v-for="customField in selectedCustomFields"
            :key="customField.field"
            v-slot:[customField.field]="rowData">
            <div>{{ rowData.attributes.customFields?.find(el => el.id === customField.fieldId)?.value || '' }}</div>
          </template>
          <template v-slot:flyout="rowData">
            <dp-flyout data-cy="segmentsList:flyoutEditMenu">
              <a
                class="block leading-[2] whitespace-nowrap"
                :href="Routing.generate('dplan_statement_segments_list', {
                  action: 'editText',
                  procedureId: procedureId,
                  segment: rowData.id,
                  statementId: rowData.relationships.parentStatement.data.id
                })"
                data-cy="segmentsList:edit"
                rel="noopener">
                {{ Translator.trans('edit') }}
              </a>
              <a
                v-if="hasPermission('feature_segment_recommendation_edit')"
                class="block leading-[2] whitespace-nowrap"
                :href="Routing.generate('dplan_statement_segments_list', {
                  procedureId: procedureId,
                  segment: rowData.id,
                  statementId: rowData.relationships.parentStatement.data.id
                })"
                data-cy="segmentsList:segmentsRecommendationsCreate"
                rel="noopener">
                {{ Translator.trans('segments.recommendations.create') }}
              </a>
              <!-- Version history view -->
              <button
                type="button"
                class="btn--blank o-link--default block leading-[2] whitespace-nowrap"
                data-cy="segmentsList:segmentVersionHistory"
                @click.prevent="showVersionHistory(rowData.id, rowData.attributes.externId)">
                {{ Translator.trans('history') }}
              </button>
              <a
                v-if="hasPermission('feature_read_source_statement_via_api')"
                class="block leading-[2] whitespace-nowrap"
                :class="{'is-disabled': getOriginalPdfAttachmentHashBySegment(rowData) === null}"
                data-cy="segmentsList:originalPDF"
                target="_blank"
                :href="Routing.generate('core_file_procedure', { hash: getOriginalPdfAttachmentHashBySegment(rowData), procedureId: procedureId })"
                rel="noopener noreferrer">
                {{ Translator.trans('original.pdf') }}
              </a>
            </dp-flyout>
          </template>
        </dp-data-table>

        <div
          v-show="!isFullscreen"
          ref="scrollBar"
          class="sticky bottom-0 left-0 right-0 h-3 overflow-x-scroll overflow-y-hidden">
          <div />
        </div>
      </template>

      <dp-inline-notification
        v-else
        :class="{ 'mx-2': isFullscreen }"
        :message="Translator.trans('segments.none')"
        type="info" />
    </template>
  </div>
</template>

<script>
import {
  checkResponse,
  CleanHtml,
  dpApi,
  DpBulkEditHeader,
  DpButton,
  DpColumnSelector,
  DpDataTable,
  DpFlyout,
  DpInlineNotification,
  DpLoading,
  DpPager,
  dpRpc,
  DpStickyElement,
  hasOwnProp,
  tableSelectAllItems,
  VPopover
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import CustomSearch from './CustomSearch'
import FilterFlyout from './FilterFlyout'
import fullscreenModeMixin from '@DpJs/components/shared/mixins/fullscreenModeMixin'
import ImageModal from '@DpJs/components/shared/ImageModal'
import lscache from 'lscache'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import StatementMetaTooltip from '@DpJs/components/statement/StatementMetaTooltip'
import StatusBadge from '../Shared/StatusBadge'
import tableScrollbarMixin from '@DpJs/components/shared/mixins/tableScrollbarMixin'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'

export default {
  name: 'SegmentsList',

  components: {
    CustomSearch,
    DpBulkEditHeader,
    DpButton,
    DpColumnSelector,
    DpDataTable,
    DpFlyout,
    DpInlineNotification,
    DpLoading,
    DpPager,
    DpStickyElement,
    FilterFlyout,
    ImageModal,
    StatementMetaTooltip,
    StatusBadge,
    TextContentRenderer,
    VPopover
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [fullscreenModeMixin, paginationMixin, tableScrollbarMixin, tableSelectAllItems],

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    /**
     * {Object of objects}
     * {
     *   assignee: {
     *     comparisonOperator: string,
     *     grouping?: {
     *       labelTranslationKey: string,
     *       targetPath: string
     *     },
     *     labelTranslationKey: string,
     *     rootPath: string,
     *     selected: boolean
     *   },
     *   place: s. assignee,
     *   tags: s. assignee
     * }
     */
    filters: {
      type: Object,
      required: false,
      default: () => ({})
    },

    initialFilter: {
      type: [Object, Array],
      default: () => ({})
    },

    initialSearchTerm: {
      type: String,
      required: false,
      default: ''
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      appliedFilterQuery: this.initialFilter,
      currentQueryHash: '',
      currentSelection: ['text', 'tags'],
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 10
      },
      headerFieldsAvailable: [
        { field: 'externId', label: Translator.trans('id') },
        { field: 'statementStatus', label: Translator.trans('statement.status') },
        { field: 'internId', label: Translator.trans('internId.shortened'), colWidth: '150px' },
        { field: 'submitter', label: Translator.trans('submitter') },
        { field: 'address', label: Translator.trans('address') },
        { field: 'text', label: Translator.trans('text'), colWidth: '200px' },
        { field: 'recommendation', label: Translator.trans('segment.recommendation'), colWidth: '200px' },
        { field: 'tags', label: Translator.trans('segment.tags') },
        { field: 'place', label: Translator.trans('workflow.place') }
      ],
      isLoading: true,
      lsKey: {
        // LocalStorage keys
        allSegments: `${this.procedureId}:allSegments`,
        currentQueryHash: `${this.procedureId}:segments:currentQueryHash`,
        toggledSegments: `${this.procedureId}:toggledSegments`
      },
      pagination: {},
      searchTerm: this.initialSearchTerm,
      searchFieldsSelected: []
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', [
      'getFilterQuery',
      'getIsExpandedByCategoryId'
    ]),

    ...mapState('AssignableUser', {
      assignableUsersObject: 'items'
    }),

    ...mapState('CustomField', {
      customFields: 'items'
    }),

    ...mapState('Orga', {
      orgaObject: 'items'
    }),

    ...mapState('StatementSegment', {
      segmentsObject: 'items'
    }),

    ...mapState('Statement', {
      statementsObject: 'items'
    }),

    ...mapState('Tag', {
      tagsObject: 'items'
    }),

    ...mapState('Place', {
      placesObject: 'items'
    }),

    assignableUsers () {
      return Object.keys(this.assignableUsersObject).length
        ? Object.values(this.assignableUsersObject)
          .map(user => ({
            name: user.attributes.firstname + ' ' + user.attributes.lastname,
            id: user.id
          }))
        : []
    },

    availableHeaderFields () {
      if (!hasPermission('field_segments_custom_fields')) {
        return this.headerFields
      }

      const customFields = Object.values(this.customFields)
      const selectedCustomFields = customFields
        .filter(customField => this.currentSelection.includes(`customField_${customField.id}`))
        .map(customField => ({
          field: `customField_${customField.id}`,
          label: customField.attributes.name
        }))

      return [
        ...this.headerFields,
        ...selectedCustomFields
      ]
    },

    headerFields () {
      return this.headerFieldsAvailable.filter(headerField => this.currentSelection.includes(headerField.field))
    },

    items () {
      return Object.values(this.segmentsObject)
        // This is not working! better pass createdDate into segmentsObject
        .sort((a, b) => (b.attributes.externId.substring(1) - a.attributes.externId.substring(1)))
    },

    noQuery () {
      return this.searchTerm === '' && this.searchFieldsSelected.length === 0 && Array.isArray(this.appliedFilterQuery) && this.appliedFilterQuery.length === 0
    },

    places () {
      return Object.keys(this.placesObject).length
        ? Object.values(this.placesObject)
          .map(place => ({
            name: place.attributes.name,
            id: place.id
          }))
        : []
    },

    queryIds () {
      let ids = []
      if (Array.isArray(this.appliedFilterQuery) === false && Object.values(this.appliedFilterQuery).length > 0) {
        ids = Object.values(this.appliedFilterQuery).map(el => {
          if (!el.condition.value) {
            return 'unassigned'
          }

          return el.condition.value
        })
      }
      return ids
    },

    /**
     * Returns both static and custom headerFields that can be selected in the ColumnSelector
     * @return {[string, string][]}
     */
    selectableColumns () {
      const staticColumns = this.headerFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))

      if (!hasPermission('field_segments_custom_fields')) {
        return staticColumns
      }

      const customFields = Object.values(this.customFields).map(customField => ([`customField_${customField.id}`, customField.attributes.name]))

      return [
        ...staticColumns,
        ...customFields
      ]
    },

    selectedCustomFields () {
      if (!hasPermission('field_segments_custom_fields')) {
        return []
      }

      return Object.values(this.customFields)
        .filter(customField => this.currentSelection.includes(`customField_${customField.id}`))
        .map(customField => {
          return {
            field: `customField_${customField.id}`,
            fieldId: customField.id
          }
        })
    },

    storageKeyPagination () {
      return `${this.currentUserId}:${this.procedureId}:paginationSegmentsList`
    }
  },

  methods: {
    ...mapActions('AssignableUser', {
      fetchAssignableUsers: 'list'
    }),

    ...mapActions('AdminProcedure', {
      getCustomFieldsForProcedure: 'get'
    }),

    ...mapActions('FilterFlyout', [
      'updateFilterQuery'
    ]),

    ...mapActions('Place', {
      fetchPlaces: 'list'
    }),

    ...mapActions('StatementSegment', {
      fetchSegments: 'list'
    }),

    ...mapMutations('FilterFlyout', {
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsLoadingFilterFlyout: 'setIsLoading',
      setGroupedFilterOptions: 'setGroupedOptions',
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    applyQuery (page) {
      lscache.remove(this.lsKey.allSegments)
      lscache.remove(this.lsKey.toggledSegments)
      this.allItemsCount = null

      const filter = {
        ...this.getFilterQuery,
        sameProcedure: {
          condition: {
            path: 'parentStatement.procedure.id',
            value: this.procedureId
          }
        }
      }
      const statementSegmentFields = [
        'assignee',
        'externId',
        'orderInProcedure',
        'parentStatement',
        'place',
        'tags',
        'text',
        'recommendation'
      ]

      if (hasPermission('field_segments_custom_fields')) {
        statementSegmentFields.push('customFields')
      }

      const payload = {
        include: [
          'assignee',
          'place',
          'tags',
          'parentStatement.genericAttachments.file',
          'parentStatement.sourceAttachment.file'
        ].join(),
        page: {
          number: page,
          size: this.pagination.perPage
        },
        sort: 'parentStatement.submitDate,parentStatement.externId,orderInProcedure',
        filter,
        fields: {
          File: [
            'hash'
          ].join(),
          GenericStatementAttachment: [
            'file'
          ].join(),
          Place: [
            'name'
          ].join(),
          SourceStatementAttachment: ['file'].join(),
          Statement: [
            'authoredDate',
            'authorName',
            'genericAttachments',
            'isSubmittedByCitizen',
            'initialOrganisationDepartmentName',
            'initialOrganisationName',
            'initialOrganisationStreet',
            'initialOrganisationHouseNumber',
            'initialOrganisationPostalCode',
            'initialOrganisationCity',
            'internId',
            'memo',
            'sourceAttachment',
            'status',
            'submitDate',
            'submitName',
            'submitType'
          ].join(),
          StatementSegment: statementSegmentFields.join(),
          Tag: [
            'title'
          ].join()
        }
      }
      if (this.searchTerm !== '') {
        payload.search = {
          value: this.searchTerm,
          ...this.searchFieldsSelected.length !== 0 ? { fieldsToSearch: this.searchFieldsSelected } : {}
        }
      }
      this.isLoading = true
      this.fetchSegments(payload)
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.generic'))
        })
        .then((data) => {
          /**
           * We need to set the localStorage to be able to persist the last viewed page selected in the vue-sliding-pagination.
           */
          this.setLocalStorage(data.meta.pagination)

          // Fake the count from meta info of paged request, until `fetchSegmentIds()` resolves
          this.allItemsCount = data.meta.pagination.total
          this.updatePagination(data.meta.pagination)

          // Get all segments (without pagination) to save them in localStorage for bulk editing
          this.fetchSegmentIds({
            filter,
            search: payload.search
          })
        })
        .finally(() => {
          this.isLoading = false
          if (this.items.length > 0) {
            this.$nextTick(() => {
              this.$refs.imageModal.addClickListener(this.$refs.dataTable.$el.querySelectorAll('img'))
            })
          }
        })
    },

    fetchSegmentIds (payload) {
      return dpRpc('segment.load.id', payload)
        .then(response => checkResponse(response))
        .then(response => {
          const allSegments = (hasOwnProp(response, 0) && response[0].result) ? response[0].result : []
          this.storeAllSegments(allSegments)
          this.allItemsCount = allSegments.length
        })
    },

    getCustomFields () {
      const payload = {
        id: this.procedureId,
        fields: {
          AdminProcedure: [
            'segmentCustomFields'
          ].join(),
          CustomField: [
            'name',
            'description',
            'options'
          ].join()
        },
        include: [
          'segmentCustomFields'
        ].join()
      }

      this.getCustomFieldsForProcedure(payload)
    },

    getTagsBySegment (id) {
      const segment = this.segmentsObject[id]
      const relatedTagIds = segment.relationships.tags && segment.relationships.tags.data.map(tag => tag.id)
      return relatedTagIds.map(id => this.tagsObject[id])
    },

    /**
     * Returns the hash of the original statement attachment
     */
    getOriginalPdfAttachmentHashBySegment (segment) {
      const parentStatement = segment.rel('parentStatement')
      if (parentStatement.hasRelationship('attachments')) {
        const originalAttachment = Object.values(parentStatement.relationships.attachments.list()).filter(attachment => attachment.attributes.attachmentType === 'source_statement')[0]
        if (originalAttachment) {
          return originalAttachment.rel('file').attributes.hash
        }
      }

      return null
    },

    handleBulkEdit () {
      this.storeToggledSegments()
      // Persist currentQueryHash to load the filtered SegmentsList after returning from bulk edit flow.
      lscache.set(this.lsKey.currentQueryHash, this.currentQueryHash)
      window.location.href = Routing.generate('dplan_segment_bulk_edit_form', { procedureId: this.procedureId })
    },

    handleResetSearch () {
      this.resetSearchQuery()
      this.applyQuery(1)
    },

    handleSizeChange (newSize) {
      // Compute new page with current page for changed number of items per page
      const page = Math.floor((this.pagination.perPage * (this.pagination.currentPage - 1) / newSize) + 1)
      this.pagination.perPage = newSize
      this.applyQuery(page)
    },

    resetQuery () {
      this.resetSearchQuery()
      this.appliedFilterQuery = []
      this.$refs.filterFlyout?.forEach(flyout => {
        flyout.reset()
      })
      this.updateQueryHash()
      this.resetSelection()
      this.applyQuery(1)
    },

    resetSearchQuery () {
      this.searchTerm = ''
      this.$refs.customSearch.reset()
    },

    /**
     *
     * @param params {Object}
     * @param params.additionalQueryParams {Object}
     * @param params.category {Object} id, label
     * @param params.filter {Object}
     * @param params.isInitialWithQuery {Boolean}
     * @param params.path {String}
     * @param params.searchPhrase {String}
     */
    sendFilterOptionsRequest (params) {
      const { additionalQueryParams, category, filter, isInitialWithQuery, path } = params
      const requestParams = {
        ...additionalQueryParams,
        filter: {
          ...filter,
          sameProcedure: {
            condition: {
              path: 'parentStatement.procedure.id',
              value: this.procedureId
            }
          }
        },
        path
      }

      // We have to set the searchPhrase to null if its empty to satisfy the backend
      if (requestParams.searchPhrase === '') {
        requestParams.searchPhrase = null
      }

      dpRpc('segments.facets.list', requestParams, 'filterList')
        .then(response => checkResponse(response))
        .then(response => {
          const result = (hasOwnProp(response, 0) && response[0].id === 'filterList') ? response[0].result : null

          if (result) {
            const groupedOptions = []
            const ungroupedOptions = []

            result.included?.forEach(resource => {
              const filter = result.data.find(type => type.attributes.path === path)
              const resourceIsGroup = resource.type === 'AggregationFilterGroup'
              const filterHasGroups = filter.relationships.aggregationFilterGroups?.data.length > 0
              const groupBelongsToFilterType = resourceIsGroup && filterHasGroups ? !!filter.relationships.aggregationFilterGroups.data.find(group => group.id === resource.id) : false
              const resourceIsFilterOption = resource.type === 'AggregationFilterItem'
              const filterHasFilterOptions = filter.relationships.aggregationFilterItems?.data.length > 0
              const filterOptionBelongsToFilterType = resourceIsFilterOption && filterHasFilterOptions ? !!filter.relationships.aggregationFilterItems.data.find(option => option.id === resource.id) : false

              if (resourceIsGroup && groupBelongsToFilterType) {
                const filterOptionsIds = resource.relationships.aggregationFilterItems?.data.length > 0 ? resource.relationships.aggregationFilterItems.data.map(item => item.id) : []
                const filterOptions = filterOptionsIds.map(id => {
                  const option = result.included.find(item => item.id === id)

                  if (option) {
                    const { attributes, id } = option
                    const { count, description, label, selected } = attributes

                    return {
                      count,
                      description,
                      id,
                      label,
                      selected
                    }
                  }

                  return null
                }).filter(option => option !== null)

                if (filterOptions.length > 0) {
                  const { id, attributes } = resource
                  const { label } = attributes
                  const group = {
                    id,
                    label,
                    options: filterOptions
                  }

                  groupedOptions.push(group)
                }
              }

              // Ungrouped filter options
              if (resourceIsFilterOption && filterOptionBelongsToFilterType) {
                const { id, attributes } = resource
                const { count, description, label, selected } = attributes

                ungroupedOptions.push({
                  id,
                  count,
                  description,
                  label,
                  selected,
                  ungrouped: true
                })
              }
            })

            // Needs to be added to ungroupedOptions
            if (result.data[0].attributes.path === 'assignee') {
              ungroupedOptions.push({
                id: 'unassigned',
                count: result.data[0].attributes.missingResourcesSum,
                label: Translator.trans('not.assigned'),
                ungrouped: true,
                selected: result.meta.unassigned_selected
              })
            }

            if (isInitialWithQuery && this.queryIds.length > 0) {
              const allOptions = [...groupedOptions.flatMap(group => group.options), ...ungroupedOptions]

              const currentFlyoutFilterIds = this.queryIds.filter(queryId => {
                const item = allOptions.find(item => item.id === queryId)
                return item ? item.id : null
              })

              this.setInitialFlyoutFilterIds({
                categoryId: category.id,
                filterIds: currentFlyoutFilterIds
              })
            }

            this.setGroupedFilterOptions({
              categoryId: category.id,
              groupedOptions
            })

            this.setUngroupedFilterOptions({
              categoryId: category.id,
              options: ungroupedOptions
            })

            this.setIsLoadingFilterFlyout({ categoryId: category.id, isLoading: false })
            if (this.getIsExpandedByCategoryId(category.id)) {
              document.getElementById(`searchField_${path}`).focus()
            }
          }
        })
    },

    setCurrentSelection (selection) {
      this.currentSelection = selection
    },

    // Saves the Ids of all segments represented by the current filterQuery for later use in bulk edit view.
    storeAllSegments (allSegments) {
      lscache.set(this.lsKey.allSegments, allSegments)
    },

    storeToggledSegments () {
      lscache.set(this.lsKey.toggledSegments, {
        trackDeselected: this.trackDeselected,
        toggledSegments: this.toggledItems
      })
    },

    // Called by apply as well as by reset in filterFlyout
    sendFilterQuery (filter) {
      const isReset = Object.keys(filter).length === 0
      if (isReset === false && Object.keys(this.appliedFilterQuery).length) {
        Object.values(filter).forEach(el => {
          this.appliedFilterQuery[el.condition.value] = el
        })
      } else {
        if (isReset) {
          this.appliedFilterQuery = Object.keys(this.getFilterQuery).length ? this.getFilterQuery : []
        } else {
          this.appliedFilterQuery = filter
        }
      }
      this.updateQueryHash()
      this.resetSelection()
      this.applyQuery(1)
    },

    showVersionHistory (segmentId, externId) {
      this.$root.$emit('version:history', segmentId, 'segment', externId)
      this.$root.$emit('show-slidebar')
    },

    updateQueryHash () {
      const hrefParts = window.location.href.split('/')
      const oldQueryHash = hrefParts[hrefParts.length - 1]
      const url = Routing.generate('dplan_rpc_segment_list_query_update', { queryHash: oldQueryHash })

      const data = { filter: this.getFilterQuery }
      if (this.searchterm !== '') {
        data.searchPhrase = this.searchTerm
      }
      return dpApi.patch(url, {}, data)
        .then(response => checkResponse(response))
        .then(response => {
          if (response) {
            this.updateQueryHashInURL(oldQueryHash, response)
            this.currentQueryHash = response
          }
        })
        .catch(err => console.log(err))
    },

    updateQueryHashInURL (oldQueryHash, newQueryHash) {
      const newHref = window.location.href.replace(oldQueryHash, newQueryHash)
      window.history.pushState({ html: newHref, pageTitle: document.title }, document.title, newHref)
    },

    updateSearchFields (selectedFields) {
      this.searchFieldsSelected = selectedFields
    },

    updateSearchQuery (term) {
      this.searchTerm = term
      this.resetSelection()
      this.applyQuery(1)
    }
  },

  mounted () {
    // Get queryHash from URL
    const hrefParts = window.location.href.split('/')
    this.currentQueryHash = hrefParts[hrefParts.length - 1]

    // When returning from bulk edit flow, the currentQueryHash which was used there to build a return link must be deleted.
    lscache.remove(this.lsKey.currentQueryHash)

    if (Array.isArray(this.initialFilter) === false && Object.keys(this.initialFilter).length) {
      Object.values(this.initialFilter).forEach(filter => {
        const query = {}
        query[filter.condition.value] = filter
        this.updateFilterQuery(query)
      })
    }
    this.initPagination()
    this.getCustomFields()
    this.applyQuery(this.pagination.currentPage)

    this.fetchPlaces()
    this.fetchAssignableUsers()
  }
}
</script>
