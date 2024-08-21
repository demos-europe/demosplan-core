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
      <div class="flex items-center justify-between mb-2">
        <div class="flex">
          <search-modal
            :search-in-fields="searchFields"
            @search="(term, selectedFields) => applySearch(term, selectedFields)"
            ref="searchModal" />
          <dp-button
            class="ml-2"
            variant="outline"
            data-cy="listStatements:searchReset"
            :href="Routing.generate('dplan_procedure_statement_list', { procedureId: procedureId })"
            :disabled="searchValue === ''"
            :text="Translator.trans('search.reset')" />
        </div>
        <dp-button
          data-cy="editorFullscreen"
          :icon="isFullscreen ? 'compress' : 'expand'"
          icon-size="medium"
          hide-text
          variant="outline"
          :text="isFullscreen ? Translator.trans('editor.fullscreen.close') : Translator.trans('editor.fullscreen')"
          @click="handleFullscreenMode()" />
      </div>
      <dp-bulk-edit-header
        class="layout__item u-12-of-12 u-mt-0_5"
        v-if="selectedItemsCount > 0 && hasPermission('feature_statements_sync_to_procedure')"
        :selected-items-text="Translator.trans('items.selected.multi.page', { count: selectedItemsCount })"
        @reset-selection="resetSelection">
        <dp-button
          data-cy="statementsBulkShare"
          variant="outline"
          @click.prevent="handleBulkShare"
          :text="Translator.trans('procedure.share_statements.bulk.share')" />
      </dp-bulk-edit-header>
      <statement-export-modal
        data-cy="listStatements:export"
        @export="showHintAndDoExport" />
      <div
        v-if="items.length > 0"
        class="flex mt-2">
        <dp-pager
          v-if="pagination.currentPage"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :total-pages="pagination.totalPages"
          :total-items="pagination.total"
          :per-page="pagination.perPage"
          :limits="pagination.limits"
          @page-change="getItemsByPage"
          @size-change="handleSizeChange"
          :key="`pager1_${pagination.currentPage}_${pagination.count}`" />
        <div class="ml-auto flex items-center space-inline-xs">
          <label
            class="u-mb-0"
            for="applySortSelection">
            {{ Translator.trans('sorting') }}
          </label>
          <dp-select
            id="applySortSelection"
            :options="sortOptions"
            :selected="selectedSort"
            @select="applySort" />
        </div>
      </div>
    </dp-sticky-element>

    <dp-loading
      class="u-mt"
      v-if="isLoading" />

    <template v-else>
      <dp-data-table
        v-if="items.length > 0"
        data-cy="listStatements"
        :class="{ 'px-2 overflow-y-scroll grow': isFullscreen }"
        has-flyout
        :is-selectable="isSourceAndCoupledProcedure"
        :header-fields="headerFields"
        is-expandable
        :items="items"
        lock-checkbox-by="synchronized"
        :multi-page-all-selected="allSelectedVisually"
        :multi-page-selection-items-total="allItemsCount"
        :multi-page-selection-items-toggled="toggledItems.length"
        :should-be-selected-items="currentlySelectedItems"
        track-by="id"
        :translations="{ lockedForSelection: Translator.trans('item.lockedForSelection.sharedStatement') }"
        @select-all="handleSelectAll"
        @items-toggled="handleToggleItem">
        <template v-slot:externId="{ assignee = {}, externId, id: statementId, synchronized }">
          <span
            class="weight--bold"
            v-text="externId" />
          <dp-claim
            v-if="!synchronized"
            entity-type="statement"
            :assigned-id="assignee.id || ''"
            :assigned-name="assignee.name || ''"
            :assigned-organisation="assignee.orgaName || ''"
            :current-user-id="currentUserId"
            :is-loading="claimLoadingIds.indexOf(statementId) >= 0"
            @click="toggleClaimStatement(assignee.id, statementId)" />
        </template>
        <template
          v-slot:meta="{
            authorName,
            isSubmittedByCitizen,
            initialOrganisationName,
            submitDate,
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
            <li class="o-list__item o-hellip--nowrap">
              {{ date(submitDate) }}
            </li>
          </ul>
        </template>
        <template v-slot:status="{ status }">
          <status-badge
            class="mt-0.5"
            :status="status" />
        </template>
        <template v-slot:internId="{ internId }">
          <div class="o-hellip__wrapper">
            <div
              v-tooltip="internId"
              class="o-hellip--nowrap text-right"
              v-text="internId" />
          </div>
        </template>
        <template v-slot:text="{ text }">
          <div
            class="line-clamp-3 c-styled-html"
            v-cleanhtml="text" />
        </template>
        <template v-slot:flyout="{ assignee, id, originalPdf, segmentsCount, synchronized }">
          <dp-flyout data-cy="listStatements:statementActionsMenu">
            <button
              v-if="hasPermission('area_statement_segmentation')"
              data-cy="listStatements:statementSplit"
              :class="`${(segmentsCount > 0 && segmentsCount !== '-') ? 'is-disabled' : '' } btn--blank o-link--default`"
              :disabled="segmentsCount > 0 && segmentsCount !== '-'"
              @click.prevent="handleStatementSegmentation(id, assignee, segmentsCount)"
              rel="noopener">
              {{ Translator.trans('split') }}
            </button>
            <a
              data-cy="listStatements:statementDetailsAndRecommendation"
              :href="Routing.generate('dplan_statement_segments_list', { statementId: id, procedureId: procedureId })"
              rel="noopener">
              {{ Translator.trans('statement.details_and_recommendation') }}
            </a>
            <a
              v-if="hasPermission('feature_read_source_statement_via_api')"
              data-cy="listStatements:originalPDF"
              :class="{'is-disabled': originalPdf === null}"
              :href="Routing.generate('core_file_procedure', { hash: originalPdf, procedureId: procedureId })"
              rel="noreferrer noopener"
              target="_blank">
              {{ Translator.trans('original.pdf') }}
            </a>
            <button
              data-cy="listStatements:statementDelete"
              :class="`${ !synchronized || assignee.id === currentUserId ? 'hover:underline--hover' : 'is-disabled' } btn--blank o-link--default`"
              :disabled="synchronized || assignee.id !== currentUserId"
              type="button"
              @click="triggerStatementDeletion(id)">
              {{ Translator.trans('delete') }}
            </button>
          </dp-flyout>
        </template>
        <template v-slot:expandedContent="{ text, fullText, id }">
          <!-- Statement meta data -->
          <statement-meta-data
            class="u-pt-0_5"
            :statement="statementsObject[id]"
            :submit-type-options="submitTypeOptions">
            <template
              v-slot:default="{
                authorName,
                formattedAuthoredDate,
                formattedSubmitDate,
                isSubmittedByCitizen,
                initialOrganisationDepartmentName,
                initialOrganisationName,
                internId,
                memo,
                submitName,
                submitType,
                location
              }">
              <div class="layout">
                <dl class="description-list-inline layout__item u-1-of-2">
                  <dt>{{ Translator.trans('submitter') }}:</dt>
                  <dd>{{ authorName ? authorName : submitName }}</dd>
                  <template
                    v-if="!isSubmittedByCitizen">
                    <dt>{{ Translator.trans('organisation') }}:</dt>
                    <dd>{{ initialOrganisationName }}</dd>
                    <dt>{{ Translator.trans('department') }}:</dt>
                    <dd>{{ initialOrganisationDepartmentName }}</dd>
                  </template>
                  <dt>{{ Translator.trans('address') }}:</dt>
                  <dd>{{ location }}</dd>
                  <template v-if="hasPermission('field_statement_memo')">
                    <dt>{{ Translator.trans('memo') }}:</dt>
                    <dd>{{ memo }}</dd>
                  </template>
                </dl><!--

             --><dl class="description-list-inline layout__item u-1-of-2">
                  <dt>{{ Translator.trans('internId') }}:</dt>
                  <dd>{{ internId }}</dd>
                  <dt>{{ Translator.trans('statement.date.authored') }}:</dt>
                  <dd>{{ formattedAuthoredDate }}</dd>
                  <dt>{{ Translator.trans('statement.date.submitted') }}:</dt>
                  <dd>{{ formattedSubmitDate }}</dd>
                  <dt class="whitespace-nowrap">
                    {{ Translator.trans('submit.type') }}:
                  </dt>
                  <dd>{{ submitType }}</dd>
                </dl>
              </div>
            </template>
          </statement-meta-data>

          <!-- Statement text -->
          <div class="u-pt-0_5 c-styled-html">
            <strong>{{ Translator.trans('statement.text.short') }}:</strong>
            <template v-if="typeof fullText === 'undefined'">
              <div v-cleanhtml="text" />
              <a
                v-if="statementsObject[id].attributes.textIsTruncated"
                class="show-more cursor-pointer"
                @click.prevent.stop="() => getStatementsFullText(id)"
                rel="noopener">
                {{ Translator.trans('show.more') }}
              </a>
            </template>
            <template v-else>
              <div v-cleanhtml="statementsObject[id].attributes.isFulltextDisplayed ? fullText : text" />
              <a
                class="cursor-pointer"
                @click="() => toggleFulltext(id)"
                rel="noopener">
                {{ Translator.trans(statementsObject[id].attributes.isFulltextDisplayed ? 'show.less' : 'show.more') }}
              </a>
            </template>
          </div>
        </template>
      </dp-data-table>

      <dp-inline-notification
        v-else
        :class="{ 'mx-2': isFullscreen }"
        :message="Translator.trans((this.searchValue === '' ? 'statements.none' : 'search.no.results'), {searchterm: this.searchValue})"
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
  DpDataTable,
  DpFlyout,
  DpInlineNotification,
  DpLoading,
  DpPager,
  dpRpc,
  DpSelect,
  DpStickyElement,
  formatDate,
  tableSelectAllItems
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import DpClaim from '@DpJs/components/statement/DpClaim'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import SearchModal from '@DpJs/components/statement/assessmentTable/SearchModal/SearchModal'
import StatementExportModal from '@DpJs/components/statement/StatementExportModal'
import StatementMetaData from '@DpJs/components/statement/StatementMetaData'
import StatusBadge from '@DpJs/components/procedure/Shared/StatusBadge.vue'

export default {
  name: 'ListStatements',

  components: {
    DpBulkEditHeader,
    DpButton,
    DpClaim,
    DpDataTable,
    DpFlyout,
    DpInlineNotification,
    DpLoading,
    DpPager,
    DpSelect,
    DpStickyElement,
    SearchModal,
    StatementExportModal,
    StatementMetaData,
    StatusBadge
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

    /**
     * If inside a source procedure that is already coupled, HEARING_AUTHORITY_ADMIN users may copy statements to the
     * respective target procedure, while HEARING_AUTHORITY_WORKER users may see which statements are synchronized.
     */
    isSourceAndCoupledProcedure: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      required: true,
      type: String
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      claimLoadingIds: [],
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 10
      },
      isFullscreen: false,
      headerFields: [
        { field: 'externId', label: Translator.trans('id') },
        { field: 'status', label: Translator.trans('status') },
        { field: 'internId', label: Translator.trans('internId.shortened'), colClass: 'w-8' },
        { field: 'meta', label: Translator.trans('submitter.invitable_institution') },
        { field: 'text', label: Translator.trans('text') },
        { field: 'segmentsCount', label: Translator.trans('segments') }
      ],
      pagination: {},
      searchFields: [
        'authorName',
        'department',
        'internId',
        'memo',
        'municipalitiesNames',
        'orgaCity',
        'organisationName',
        'orgaPostalCode',
        'statementId',
        'statementText',
        'typeOfSubmission'
      ],
      searchFieldsSelected: null,
      searchValue: '',
      selectedSort: '-submitDate',
      sortOptions: [
        { value: '-submitDate', label: Translator.trans('sort.date.descending') },
        { value: 'submitDate', label: Translator.trans('sort.date.ascending') },
        { value: '-submitName', label: Translator.trans('sort.author.descending') },
        { value: 'submitName', label: Translator.trans('sort.author.ascending') },
        { value: '-internId', label: Translator.trans('sort.internId.descending') },
        { value: 'internId', label: Translator.trans('sort.internId.ascending') },
        { value: '-initialOrganisationName', label: Translator.trans('sort.organisation.descending') },
        { value: 'initialOrganisationName', label: Translator.trans('sort.organisation.ascending') }
      ]
    }
  },

  computed: {
    ...mapState('AssignableUser', {
      assignableUsersObject: 'items'
    }),

    ...mapState('Orga', {
      orgaObject: 'items'
    }),

    ...mapState('Statement', {
      statementsObject: 'items',
      currentPage: 'currentPage',
      totalFiles: 'totalFiles',
      isLoading: 'loading'
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

    exportRoute: function () {
      return (exportRoute, docxHeaders, fileNameTemplate) => {
        const parameters = {
          filter: {
            procedureId: {
              condition: {
                path: 'procedure.id',
                value: this.procedureId
              }
            }
          },
          procedureId: this.procedureId,
          search: {
            value: this.searchValue,
            ...this.searchFieldsSelected !== null ? { fieldsToSearch: this.searchFieldsSelected } : {}
          },
          sort: this.selectedSort
        }

        if (docxHeaders) {
          parameters.tableHeaders = {
            col1: docxHeaders.col1,
            col2: docxHeaders.col2,
            col3: docxHeaders.col3
          }
        }

        if (fileNameTemplate) {
          parameters.fileNameTemplate = fileNameTemplate
        }

        return Routing.generate(exportRoute, parameters)
      }
    },

    items () {
      return Object.values(this.statementsObject)
        .map(statement => {
          const segmentsCount = statement.relationships.segments.data.length
          const originalPdf = this.getOriginalPdfAttachmentHash(statement)
          return {
            ...statement.attributes,
            assignee: this.getAssignee(statement),
            id: statement.id,
            segmentsCount: segmentsCount || '-',
            originalPdf: originalPdf
          }
        })
    },

    storageKeyPagination () {
      return `${this.currentUserId}:${this.procedureId}:paginationStatementList`
    }
  },

  methods: {
    ...mapActions('AssignableUser', {
      fetchAssignableUsers: 'list'
    }),

    ...mapActions('Statement', {
      deleteStatement: 'delete',
      fetchStatements: 'list',
      restoreStatementAction: 'restoreFromInitial'
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem'
    }),

    assigneeId (statement) {
      if (statement.hasRelationship('assignee') && statement.relationships.assignee.data.id !== '') {
        return statement.relationships.assignee.data.id
      } else {
        return null
      }
    },

    getAssignee (statement) {
      if (this.assigneeId(statement)) {
        const assignee = this.assignableUsersObject[this.assigneeId(statement)]
        const assigneeOrga = assignee ? assignee.rel('orga') : null

        if (typeof assignee === 'undefined') {
          return {
            id: statement.relationships.assignee.data.id,
            name: 'Benutzer',
            orgaName: 'unbekannt'
          }
        }

        return {
          id: statement.relationships.assignee.data.id,
          name: `${assignee.attributes.firstname} ${assignee.attributes.lastname}`,
          orgaName: assigneeOrga ? assigneeOrga.attributes.name : ''

        }
      }

      return {
        id: '',
        name: '',
        orgaName: ''
      }
    },

    handleSizeChange (newSize) {
      const page = Math.floor((this.pagination.perPage * (this.pagination.currentPage - 1) / newSize) + 1)
      this.pagination.perPage = newSize
      this.getItemsByPage(page)
    },

    /**
     * If statement already has segments, do nothing
     * If statement is not claimed, silently assign it to the current user and go to split statement view
     * If statement is claimed by current user, go to split statement view
     * If statement is claimed by another user, ask the current user to claim it and, after they confirm, assign it to
     * the current user, then go to split statement view. If the user doesn't confirm, do nothing.
     * @param statementId {string}
     * @param assignee {object} { data: { id: string, type: string }}
     * @param segmentsCount {number || string} can be a number or '-'
     */
    handleStatementSegmentation (statementId, assignee, segmentsCount) {
      const isStatementSegmented = segmentsCount > 0 && segmentsCount !== '-'
      const isStatementClaimedByCurrentUser = assignee.id === this.currentUserId
      const isStatementClaimed = assignee.id !== ''
      const isStatementClaimedByOtherUser = isStatementClaimed && !isStatementClaimedByCurrentUser

      if (isStatementSegmented) {
        return
      }

      if (isStatementClaimedByCurrentUser) {
        window.location.href = Routing.generate('dplan_drafts_list_edit', { statementId: statementId, procedureId: this.procedureId })
      }

      if (!isStatementClaimed || (isStatementClaimedByOtherUser && dpconfirm(Translator.trans('warning.statement.needLock.generic')))) {
        this.claimStatement(statementId)
          .then(() => {
            window.location.href = Routing.generate('dplan_drafts_list_edit', { statementId: statementId, procedureId: this.procedureId })
          })
          .catch(err => {
            console.error(err)
          })
      }
    },

    applySearch (term, selectedFields) {
      this.searchValue = term
      this.searchFieldsSelected = selectedFields
      this.getItemsByPage(1)
    },

    applySort (sortValue) {
      this.selectedSort = sortValue
      this.getItemsByPage(1)
    },

    /**
     * Returns an error if claiming fails
     * @return {Promise<*>}
     */
    claimStatement (statementId) {
      const statement = this.statementsObject[statementId]
      if (typeof statement !== 'undefined') {
        const dataToUpdate = { ...statement, ...{ relationships: { ...statement.relationships, ...{ assignee: { data: { type: 'Claim', id: this.currentUserId } } } } } }
        this.setStatement({ ...dataToUpdate, id: statementId })

        const payload = {
          data: {
            id: statementId,
            type: 'Statement',
            relationships: {
              assignee: {
                data: {
                  type: 'Claim',
                  id: this.currentUserId
                }
              }
            }
          }
        }

        return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: statementId }), {}, payload)
          .then(response => {
            checkResponse(response)
            return response
          })
          .then(response => {
            dplan.notify.notify('confirm', Translator.trans('confirm.statement.assignment.assigned'))

            return response
          })
          .catch((err) => {
            console.error(err)
            // Restore statement in store in case request failed
            this.restoreStatementAction(statementId)
            return err
          })
          .finally(() => {
            this.claimLoadingIds.splice(this.claimLoadingIds.indexOf(statementId), 1)
          })
      }
    },

    toggleClaimStatement (assigneeId, statementId) {
      this.claimLoadingIds.push(statementId)

      if (assigneeId !== this.currentUserId) {
        this.claimStatement(statementId)
      } else {
        console.log('unclaim')
        this.unclaimStatement(statementId)
      }
    },

    unclaimStatement (statementId) {
      const statement = this.statementsObject[statementId]
      const dataToUpdate = { ...statement, ...{ relationships: { ...statement.relationships, ...{ assignee: { data: { type: 'Claim', id: null } } } } } }
      this.setStatement({ ...dataToUpdate, id: statementId })

      const payload = {
        data: {
          type: 'Statement',
          id: statementId,
          relationships: {
            assignee: {
              data: null
            }
          }
        }
      }
      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: statementId }), {}, payload)
        .then(checkResponse)
        .catch((err) => {
          this.restoreStatementAction(statementId)
          console.error(err)
        })
        .finally(() => {
          this.claimLoadingIds.splice(this.claimLoadingIds.indexOf(statementId), 1)
        })
    },

    date (d) {
      return formatDate(d)
    },

    getItemsByPage (page) {
      const statementFields = [
        // Attributes:
        'authoredDate',
        'authorName',
        'externId',
        'isSubmittedByCitizen',
        'initialOrganisationCity',
        'initialOrganisationDepartmentName',
        'initialOrganisationHouseNumber',
        'initialOrganisationName',
        'initialOrganisationPostalCode',
        'initialOrganisationStreet',
        'internId',
        'isCitizen',
        'memo',
        'status',
        'submitDate',
        'submitName',
        'submitType',
        'submitterEmailAddress',
        'text',
        'textIsTruncated',
        // Relationships:
        'assignee',
        'attachments',
        'segments'
      ]
      if (this.isSourceAndCoupledProcedure) {
        statementFields.push('synchronized')
      }
      if (hasPermission('area_statement_segmentation')) {
        statementFields.push('segmentDraftList')
      }
      this.fetchStatements({
        page: {
          number: page,
          size: this.pagination.perPage
        },
        search: {
          value: this.searchValue,
          ...this.searchFieldsSelected !== null ? { fieldsToSearch: this.searchFieldsSelected } : {}
        },
        filter: {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        },
        sort: this.selectedSort,
        include: [
          'segments',
          'assignee',
          'attachments',
          'attachments.file'
        ].join(),
        fields: {
          Statement: statementFields.join(),
          File: [
            'hash'
          ].join()
        }
      }).then((data) => {
        /**
         * We need to set the localStorage to be able to persist the last viewed page selected in the vue-sliding-pagination.
         */
        this.setLocalStorage(data.meta.pagination)

        this.setNumSelectableItems(data)
        this.updatePagination(data.meta.pagination)
      })
    },

    /**
     * Returns the hash of the original statement attachment
     */
    getOriginalPdfAttachmentHash (el) {
      if (el.hasRelationship('attachments')) {
        const originalAttachment = Object.values(el.relationships.attachments.list())
          .filter(attachment => attachment.attributes.attachmentType === 'source_statement')
        if (originalAttachment.length === 1) {
          return originalAttachment[0].relationships.file.get().attributes.hash
        }
      }

      return null
    },

    /**
     * Returns the params needed for the RPC to synchronize statements in coupled procedures.
     * We need to send a filter and a search with the call.
     * There are three different cases for the filter.
     * 1. All items
     * 2. All items minus deselected items
     * 3. Only selected items
     * We can group filters, so multiple conditions are applied.
     * You can find more on how to use filters in the documentation here
     * {@link https://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonapi/filter.html#background}.
     *
     * @param {boolean} [isDry=false] If set to true, the call will be executed as dry run - no actions will be applied.
     *
     * @returns {Object}
     */
    getParamsForBulkShare (isDry = false) {
      const params = {
        dry: isDry,
        search: {
          value: this.searchValue,
          ...this.searchFieldsSelected !== null ? { fieldsToSearch: this.searchFieldsSelected } : {}
        }
      }

      /*
       * When tracking deselected items, we want the action to apply to all but the deselected items.
       * That's why `AND` is used as conjunction, and `<>` (not equal) as operator, in that case.
       */
      const filterForToggledItems = {}
      if (this.toggledItems.length > 0) {
        filterForToggledItems.statementFilterGroup = {
          group: {
            conjunction: this.trackDeselected ? 'AND' : 'OR'
          }
        }
        this.toggledItems.forEach((item, idx) => {
          filterForToggledItems['statement_' + idx] = {
            condition: {
              path: 'id',
              value: item.id,
              memberOf: 'statementFilterGroup',
              operator: this.trackDeselected ? '<>' : '='
            }
          }
        })
      }

      // If we send a dry call, we always need to get all statements
      if (this.allSelectedVisually || isDry) {
        // All
        params.filter = {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          }
        }
      } else if (this.trackDeselected) {
        // All but deselected
        params.filter = {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId
            }
          },
          ...filterForToggledItems
        }
      } else {
        // Only selected
        params.filter = filterForToggledItems
      }

      return params
    },

    getStatementsFullText (statementId) {
      return dpApi.get(Routing.generate('api_resource_get', { resourceType: 'Statement', resourceId: statementId }), { fields: { Statement: ['fullText'].join() } })
        .then((response) => {
          const oldStatement = Object.values(this.statementsObject).find(el => el.id === statementId)
          const fullText = response.data.data.attributes.fullText
          const updatedStatement = { ...oldStatement, attributes: { ...oldStatement.attributes, fullText, isFulltextDisplayed: true } }
          this.setStatement({ ...updatedStatement, id: statementId })
        })
    },

    handleBulkShare () {
      if (window.dpconfirm(Translator.trans('procedure.share_statements.bulk.confirm'))) {
        const params = this.getParamsForBulkShare()

        dplan.notify.notify('warning', Translator.trans('procedure.share_statements.info.duration'))
        dpRpc('statement.procedure.sync', params)
          .then(checkResponse)
          .then((response) => {
            /*
             * Error messages are displayed with "checkResponse", but we need to check for error here to, because
             * we also get 200 status with an error
             */
            if (!response[0].error) {
              this.getItemsByPage(this.currentPage)
              this.resetSelection()
            }
          })
          .catch(e => {
            console.error(e)
          })
      }
    },

    handleFullscreenMode () {
      this.isFullscreen = !this.isFullscreen
      if (this.isFullscreen) {
        document.querySelector('html').setAttribute('style', 'overflow: hidden')
      } else {
        document.querySelector('html').removeAttribute('style')
      }
    },

    resetSearch () {
      this.searchValue = ''
      this.getItemsByPage(1)
    },

    /**
     * If the procedure is coupled get the num of total items, that are not synchronized yet and
     * therefor are selectable, and set the num of total items to it.
     * Otherwise, set it to the total number of items available.
     */
    setNumSelectableItems (data) {
      if (this.isSourceAndCoupledProcedure) {
        // Call without actually changing anything in the backend.
        dpRpc('statement.procedure.sync', this.getParamsForBulkShare(true), 'rpc_generic_post')
          .then((response) => {
            // ActuallySynchronizedStatementCount is the num of items that are not synchronized yet, but can be
            this.allItemsCount = response.data[0].result.actuallySynchronizedStatementCount
          })
          .catch(e => {
            console.error(e)
          })
      } else {
        this.allItemsCount = data.meta.pagination.total
      }
    },

    showHintAndDoExport ({ route, docxHeaders, fileNameTemplate }) {
      if (window.dpconfirm(Translator.trans('export.statements.hint'))) {
        window.location.href = this.exportRoute(route, docxHeaders, fileNameTemplate)
      }
    },

    triggerStatementDeletion (id) {
      if (window.confirm(Translator.trans('check.statement.delete'))) {
        this.deleteStatement(id)
          .then(response => checkResponse(response, {
            200: { type: 'confirm', text: 'confirm.statement.deleted' },
            204: { type: 'confirm', text: 'confirm.statement.deleted' }
          }))
      }
    },

    toggleFulltext (statementId) {
      const statement = this.statementsObject[statementId]
      const isFulltext = statement.attributes.isFulltextDisplayed
      this.setStatement({ ...{ ...statement, attributes: { ...statement.attributes, isFulltextDisplayed: !isFulltext }, id: statementId } })
    }
  },

  mounted () {
    this.fetchAssignableUsers({
      include: 'orga',
      fields: {
        Orga: 'name'
      }
    })
    this.initPagination()
    this.getItemsByPage(this.pagination.currentPage)
  }
}
</script>
