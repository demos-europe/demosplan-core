<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-sticky-element
      border
      class="u-pv-0_5">
      <div class="flex items-start space-inline-s">
        <custom-search
          ref="customSearch"
          id="customSearch"
          :elasticsearch-field-definition="{
            entity: 'statementSegment',
            function: 'search',
            accessGroup: 'planner'
          }"
          :search-term="searchTerm"
          @change-fields="updateSearchFields"
          @search="updateSearchQuery"
          @reset="updateSearchQuery" />
        <div class="flex bg-color--grey-light-2 rounded-md space-inline-xs">
          <span class="color--grey u-ml-0_5 line-height--2">
            {{ Translator.trans('filter') }}
          </span>
          <filter-flyout
            v-for="filter in filters"
            :data-cy="`segmentsListFilter:${filter.labelTranslationKey}`"
            :initial-query="queryIds"
            :key="`filter_${filter.labelTranslationKey}`"
            :additional-query-params="{ searchPhrase: searchTerm }"
            ref="filterFlyout"
            :label="Translator.trans(filter.labelTranslationKey)"
            :operator="filter.comparisonOperator"
            :path="filter.rootPath"
            :procedure-id="procedureId"
            @filter-apply="sendFilterQuery" />
        </div>
        <dp-button
          class="ml-auto"
          data-cy="segmentsList:resetFilter"
          variant="outline"
          @click="resetQuery"
          v-tooltip="Translator.trans('search.filter.reset')"
          :disabled="noQuery"
          :text="Translator.trans('reset')" />
      </div>
      <dp-bulk-edit-header
        class="layout__item u-12-of-12 u-mt-0_5"
        v-if="selectedItemsCount > 0"
        :selected-items-text="Translator.trans('items.selected.multi.page', { count: selectedItemsCount })"
        @reset-selection="resetSelection">
        <dp-button
          variant="outline"
          @click.prevent="handleBulkEdit"
          :text="Translator.trans('segments.bulk.edit')" />
      </dp-bulk-edit-header>
      <div class="flex justify-between items-center mt-4">
        <dp-pager
          v-if="pagination.currentPage"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :total-pages="pagination.totalPages"
          :total-items="pagination.total"
          :per-page="pagination.perPage"
          :limits="pagination.limits"
          @page-change="applyQuery"
          @size-change="handleSizeChange"
          :key="`pager1_${pagination.currentPage}_${pagination.count}`" />
        <dp-column-selector
          data-cy="segmentsList:selectableColumns"
          :initial-selection="currentSelection"
          :selectable-columns="selectableColumns"
          @selection-changed="setCurrentSelection"
          use-local-storage
          local-storage-key="segmentList" />
      </div>
    </dp-sticky-element>

    <dp-loading
      class="u-mt"
      v-if="isLoading" />

    <template v-else>
      <dp-data-table
        class="overflow-x-auto"
        v-if="items"
        :header-fields="headerFields"
        :items="items"
        has-flyout
        :multi-page-all-selected="allSelectedVisually"
        :multi-page-selection-items-total="allItemsCount"
        :multi-page-selection-items-toggled="toggledItems.length"
        is-selectable
        track-by="id"
        @select-all="handleSelectAll"
        @items-toggled="handleToggleItem"
        :should-be-selected-items="currentlySelectedItems">
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
          <div
            v-cleanhtml="rowData.attributes.text"
            class="overflow-word-break c-styled-html" />
        </template>
        <template v-slot:recommendation="rowData">
          <div v-cleanhtml="rowData.attributes.recommendation !== '' ? rowData.attributes.recommendation : '-'" />
        </template>
        <template v-slot:tags="rowData">
          <span
            :key="tag.id"
            class="rounded-md"
            v-for="tag in getTagsBySegment(rowData.id)"
            style="color: #63667e; background: #EBE9E9; padding: 2px 4px; margin: 4px 2px; display: inline-block;">
            {{ tag.attributes.title }}
          </span>
        </template>
        <template v-slot:flyout="rowData">
          <dp-flyout data-cy="segmentsList:flyoutEditMenu">
            <a
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
              class="btn--blank o-link--default"
              data-cy="segmentsList:segmentVersionHistory"
              @click.prevent="showVersionHistory(rowData.id, rowData.attributes.externId)">
              {{ Translator.trans('history') }}
            </button>
            <a
              v-if="hasPermission('feature_read_source_statement_via_api')"
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

      <div v-else>
        <p class="flash flash-info">
          <i
            class="fa fa-info-circle"
            aria-hidden="true" />
          {{ Translator.trans('segments.none') }}
        </p>
      </div>
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
import lscache from 'lscache'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import StatementMetaTooltip from '@DpJs/components/statement/StatementMetaTooltip'

export default {
  name: 'SegmentsList',

  components: {
    CustomSearch,
    DpBulkEditHeader,
    DpButton,
    DpColumnSelector,
    DpDataTable,
    DpFlyout,
    DpLoading,
    DpPager,
    DpStickyElement,
    FilterFlyout,
    StatementMetaTooltip,
    VPopover
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [paginationMixin, tableSelectAllItems],

  props: {
    currentUserId: {
      type: String,
      required: true
    },

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
        { field: 'internId', label: Translator.trans('internId.shortened'), colClass: 'w-8' },
        { field: 'submitter', label: Translator.trans('submitter') },
        { field: 'address', label: Translator.trans('address') },
        { field: 'text', label: Translator.trans('text') },
        { field: 'recommendation', label: Translator.trans('segment.recommendation') },
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
    ...mapGetters('segmentfilter', {
      getFilterQuery: 'filterQuery'
    }),

    ...mapState('assignableUser', {
      assignableUsersObject: 'items'
    }),

    ...mapState('orga', {
      orgaObject: 'items'
    }),

    ...mapState('statementSegment', {
      segmentsObject: 'items'
    }),

    ...mapState('statement', {
      statementsObject: 'items'
    }),

    ...mapState('tag', {
      tagsObject: 'items'
    }),

    ...mapState('place', {
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
          return el.condition.value
        })
      }
      return ids
    },

    selectableColumns () {
      return this.headerFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },

    storageKeyPagination () {
      return `${this.currentUserId}:${this.procedureId}:paginationSegmentsList`
    }
  },

  methods: {
    ...mapActions('assignableUser', {
      fetchAssignableUsers: 'list'
    }),

    ...mapActions('statementSegment', {
      listSegments: 'list'
    }),

    ...mapActions('place', {
      fetchPlaces: 'list'
    }),

    ...mapMutations('segmentfilter', ['updateFilterQuery']),

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
      const payload = {
        include: ['assignee', 'place', 'tags', 'parentStatement.attachments.file'].join(),
        page: {
          number: page,
          size: this.pagination.perPage
        },
        sort: 'parentStatement.submitDate,parentStatement.externId,orderInProcedure',
        filter: filter,
        fields: {
          Place: [
            'name'
          ].join(),
          StatementSegment: [
            'assignee',
            'externId',
            'orderInProcedure',
            'parentStatement',
            'place',
            'tags',
            'text',
            'recommendation'
          ].join(),
          Statement: [
            'attachments',
            'authoredDate',
            'authorName',
            'isSubmittedByCitizen',
            'initialOrganisationDepartmentName',
            'initialOrganisationName',
            'initialOrganisationStreet',
            'initialOrganisationHouseNumber',
            'initialOrganisationPostalCode',
            'initialOrganisationCity',
            'internId',
            'memo',
            'submitDate',
            'submitName',
            'submitType'
          ].join(),
          Tag: 'title',
          StatementAttachment: ['file', 'attachmentType'].join(),
          File: 'hash'
        }
      }
      if (this.searchTerm !== '') {
        payload.search = {
          value: this.searchTerm,
          ...this.searchFieldsSelected.length !== 0 ? { fieldsToSearch: this.searchFieldsSelected } : {}
        }
      }
      this.isLoading = true
      this.listSegments(payload)
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
            filter: filter,
            search: payload.search
          })
        })
        .finally(() => {
          this.isLoading = false
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

    handleSizeChange (newSize) {
      // Compute new page with current page for changed number of items per page
      const page = Math.floor((this.pagination.perPage * (this.pagination.currentPage - 1) / newSize) + 1)
      this.pagination.perPage = newSize
      this.applyQuery(page)
    },

    resetQuery () {
      this.searchTerm = ''
      this.$refs.customSearch.reset()
      this.appliedFilterQuery = []
      Object.keys(this.filters).forEach((filter, idx) => {
        this.$refs.filterFlyout[idx].reset()
      })
      this.updateQueryHash()
      this.resetSelection()
      this.applyQuery(1)
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
    this.applyQuery(this.pagination.currentPage)

    this.fetchPlaces()
    this.fetchAssignableUsers()
  }
}
</script>
