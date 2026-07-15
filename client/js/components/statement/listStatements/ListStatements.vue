<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="{ 'top-0 left-0 flex flex-col w-full h-full fixed z-fixed bg-surface': isFullscreen }">
    <dp-sticky-element
      :class="{ 'fixed top-0 left-0 w-full px-2': isFullscreen }"
      class="pt-2 pb-3"
      border
    >
      <div class="flex items-center justify-between mb-2">
        <div class="flex">
          <custom-search-statements
            ref="customSearchStatements"
            :search-in-fields="searchFields"
            @change-fields="updateSearchFields"
            @reset="resetSearch"
            @search="(term) => applySearch(term)"
          />
        </div>
        <dp-button
          :icon="isFullscreen ? 'compress' : 'expand'"
          :text="isFullscreen ? Translator.trans('editor.fullscreen.close') : Translator.trans('editor.fullscreen')"
          data-cy="editorFullscreen"
          icon-size="medium"
          variant="outline"
          hide-text
          @click="handleFullscreenMode()"
        />
      </div>
      <dp-bulk-edit-header
        v-if="selectedItemsCount > 0 && hasPermission('feature_statements_sync_to_procedure')"
        :selected-items-text="Translator.trans('items.selected.multi.page', { count: selectedItemsCount })"
        class="layout__item u-12-of-12 u-mt-0_5"
        @reset-selection="resetSelection"
      >
        <dp-button
          :text="Translator.trans('procedure.share_statements.bulk.share')"
          data-cy="statementsBulkShare"
          variant="outline"
          @click.prevent="handleBulkShare"
        />
      </dp-bulk-edit-header>

      <dp-bulk-edit-header
        v-if="selectedItemsCount > 0 && hasPermission('feature_statement_cluster')"
        :selected-items-text="Translator.trans('statements.selected', { count: selectedItemsCount })"
        class="w-full mt-2"
        @reset-selection="resetSelection"
      >
        <dp-button
          :text="Translator.trans('selection.group')"
          data-cy="statementsBulkGroup"
          @click.prevent="handleBulkGroup"
        />
      </dp-bulk-edit-header>
      <statement-export-modal
        :has-permission-adjust-preamble="hasPermission('feature_adjust_preamble_export_file')"
        :procedure-id="procedureId"
        :procedure-name="procedureName"
        data-cy="listStatements:export"
        @export="showHintAndDoExport"
      />
      <div
        v-if="items.length > 0"
        class="flex mt-2"
      >
        <dp-pager
          v-if="pagination.currentPage"
          :key="`pager1_${pagination.currentPage}_${pagination.count}`"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :limits="pagination.limits"
          :per-page="pagination.perPage"
          :total-items="pagination.total"
          :total-pages="pagination.totalPages"
          @page-change="getItemsByPage"
          @size-change="handleSizeChange"
        />
        <div class="ml-auto flex items-center space-inline-xs">
          <label
            class="u-mb-0"
            for="applySortSelection"
          >
            {{ Translator.trans('sorting') }}
          </label>
          <dp-select
            id="applySortSelection"
            :options="sortOptions"
            :selected="selectedSort"
            @select="applySort"
          />
        </div>
      </div>
    </dp-sticky-element>

    <dp-loading
      v-if="isLoading"
      class="u-mt"
    />

    <template v-else>
      <dp-data-table
        v-if="items.length > 0"
        :class="{ 'px-2 overflow-y-scroll grow': isFullscreen }"
        :header-fields="headerFields"
        :is-selectable="(isSourceAndCoupledProcedure && hasPermission('feature_statements_sync_to_procedure')) || hasPermission('feature_statement_cluster')"
        :items="items"
        :multi-page-all-selected="allSelectedVisually"
        :multi-page-selection-items-toggled="toggledItems.length"
        :multi-page-selection-items-total="allItemsCount"
        :should-be-selected-items="currentlySelectedItems"
        :translations="{ lockedForSelection: Translator.trans('item.lockedForSelection') }"
        data-cy="listStatements"
        lock-checkbox-by="lockedForSelection"
        lock-message-by="lockedForSelectionMessage"
        track-by="id"
        has-flyout
        is-expandable
        @items-toggled="handleToggleItem"
        @select-all="handleSelectAll"
      >
        <template v-slot:externId="{ assignee = {}, externId, id: statementId, isCluster, synchronized }">
          <dp-icon
            v-if="isCluster"
            class="mr-1 text-interactive"
            icon="folders"
            weight="fill"
          />
          <span
            class="weight--bold"
            v-text="externId"
          />
          <dp-claim
            v-if="!synchronized"
            :assigned-id="assignee.id || ''"
            :assigned-name="assignee.name || ''"
            :assigned-organisation="assignee.orgaName || ''"
            :current-user-id="currentUserId"
            :is-loading="claimLoadingIds.indexOf(statementId) >= 0"
            entity-type="statement"
            @click="toggleClaimStatement(assignee.id, statementId)"
          />
        </template>
        <template
          v-slot:meta="{
            authorName,
            isSubmittedByCitizen,
            initialOrganisationName,
            submitDate,
            submitName
          }"
        >
          <ul class="o-list max-w-12">
            <li
              v-if="authorName !== '' || submitName !== ''"
              class="o-list__item o-hellip--nowrap"
            >
              {{ authorName ? authorName : (submitName ? submitName : Translator.trans('citizen')) }}
            </li>
            <li
              v-if="initialOrganisationName !== '' && !isSubmittedByCitizen"
              class="o-list__item o-hellip--nowrap"
            >
              {{ initialOrganisationName }}
            </li>
            <li class="o-list__item o-hellip--nowrap">
              {{ date(submitDate) }}
            </li>
          </ul>
        </template>
        <template v-slot:status="{ status }">
          <status-badge
            :status="status"
            class="mt-0.5"
          />
        </template>
        <template v-slot:internId="{ internId }">
          <div class="o-hellip__wrapper">
            <div
              v-tooltip="internId"
              class="o-hellip--nowrap text-right"
              v-text="internId"
            />
          </div>
        </template>
        <template v-slot:text="{ text }">
          <div
            v-cleanhtml="text"
            class="line-clamp-3 c-styled-html"
          />
        </template>
        <template v-slot:flyout="{ assignee, id, originalId, originalPdf, segmentsCount, synchronized }">
          <dp-flyout data-cy="listStatements:statementActionsMenu">
            <button
              v-if="hasPermission('area_statement_segmentation')"
              :class="{
                'is-disabled': segmentsCount > 0 && segmentsCount !== '-',
                'hover:underline active:underline': segmentsCount <= 0 || segmentsCount === '-' }"
              :disabled="segmentsCount > 0 && segmentsCount !== '-'"
              class="block btn--blank o-link--default leading-[2] whitespace-nowrap"
              data-cy="listStatements:statementSplit"
              rel="noopener"
              @click.prevent="handleStatementSegmentation(id, assignee, segmentsCount)"
            >
              {{ Translator.trans('split') }}
            </button>
            <a
              :href="Routing.generate('dplan_statement_segments_list', { statementId: id, procedureId: procedureId })"
              class="block leading-[2] whitespace-nowrap"
              data-cy="listStatements:statementDetailsAndRecommendation"
              rel="noopener"
              @click="storeNavigationContextInLocalStorage"
            >
              {{ Translator.trans('statement.details_and_recommendation') }}
            </a>
            <a
              v-if="hasPermission('feature_read_source_statement_via_api') && hasPermission('area_admin_import')"
              :class="{'is-disabled': !originalPdf}"
              :href="Routing.generate('core_file_procedure', { hash: originalPdf, procedureId: procedureId })"
              class="block leading-[2] whitespace-nowrap"
              data-cy="listStatements:originalPDF"
              rel="noreferrer noopener"
              target="_blank"
            >
              {{ Translator.trans('original.pdf') }}
            </a>
            <a
              v-if="hasPermission('area_admin_original_statement_list')"
              :class="{'is-disabled': !originalId}"
              :href="Routing.generate('dplan_procedure_original_statement_list', { procedureId: procedureId })"
              class="block leading-[2] whitespace-nowrap"
              data-cy="listStatements:originalStatement"
              rel="noreferrer noopener"
            >
              {{ Translator.trans('statement.original') }}
            </a>
            <button
              :class="{
                'is-disabled': synchronized || assignee.id !== currentUserId,
                'hover:underline active:underline': !(synchronized || assignee.id !== currentUserId) }"
              :disabled="synchronized || assignee.id !== currentUserId"
              class="btn--blank o-link--default block leading-[2] whitespace-nowrap"
              data-cy="listStatements:statementDelete"
              type="button"
              @click="triggerStatementDeletion(id)"
            >
              {{ Translator.trans('delete') }}
            </button>
          </dp-flyout>
        </template>
        <template v-slot:expandedContent="{ text, fullText, id }">
          <!-- Statement meta data -->
          <statement-meta-data
            :statement="statementsObject[id]"
            :submit-type-options="submitTypeOptions"
            class="u-pt-0_5"
          >
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
              }"
            >
              <div class="layout">
                <dl class="description-list-inline layout__item u-1-of-2">
                  <dt>{{ Translator.trans('submitter') }}:</dt>
                  <dd>{{ authorName ? authorName : submitName }}</dd>
                  <template
                    v-if="!isSubmittedByCitizen"
                  >
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
                  <dt>{{ Translator.trans('statement.associated.group') }}:</dt>
                  <dd v-if="statementsObject[id].attributes.isCluster">
                    {{ statementsObject[id].attributes.name }}
                    <span
                      v-if="groupMemberCounts[id] != null"
                      class="block color--grey"
                    >
                      {{ Translator.trans('statements.count.parenthesized', { count: groupMemberCounts[id] }) }}
                    </span>
                  </dd>
                  <dd v-else>
                    -
                  </dd>
                </dl>
              </div>
            </template>
          </statement-meta-data>

          <!-- Statement text -->
          <div class="u-pt-0_5 c-styled-html">
            <strong>{{ Translator.trans('statement.text.short') }}:</strong>
            <p v-cleanhtml="displayedText(id)" />
            <a
              v-if="statementsObject[id].attributes.textIsTruncated"
              :class="{ 'show-more': !statementsObject[id].attributes.isFulltextDisplayed }"
              class="cursor-pointer"
              rel="noopener"
              @click.prevent="handleFullTextAction(id)"
            >
              {{ toggleFullTextLabel(id) }}
            </a>
          </div>
        </template>
      </dp-data-table>

      <dp-inline-notification
        v-else
        :class="{ 'mx-2': isFullscreen }"
        :message="Translator.trans((searchValue === '' ? 'statements.none' : 'search.no.results'), {searchterm: searchValue})"
        type="info"
      />
    </template>
  </div>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpBulkEditHeader,
  DpButton,
  DpDataTable,
  DpFlyout,
  DpIcon,
  DpInlineNotification,
  DpLoading,
  DpPager,
  dpRpc,
  DpSelect,
  DpStickyElement,
  formatDate,
  sessionStorageMixin,
  tableSelectAllItems,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import CustomSearchStatements from './CustomSearchStatements'
import DpClaim from '@DpJs/components/statement/DpClaim'
import { inlineImageAnchors } from '@DpJs/lib/shared/inlineImageAnchors'
import lscache from 'lscache'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import StatementExportModal from '@DpJs/components/statement/StatementExportModal'
import StatementMetaData from '@DpJs/components/statement/StatementMetaData'
import StatusBadge from '@DpJs/components/procedure/Shared/StatusBadge'

export default {
  name: 'ListStatements',

  components: {
    CustomSearchStatements,
    DpBulkEditHeader,
    DpButton,
    DpClaim,
    DpDataTable,
    DpFlyout,
    DpIcon,
    DpInlineNotification,
    DpLoading,
    DpPager,
    DpSelect,
    DpStickyElement,
    StatementExportModal,
    StatementMetaData,
    StatusBadge,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  mixins: [paginationMixin, sessionStorageMixin, tableSelectAllItems],

  props: {
    currentUserId: {
      type: String,
      required: true,
    },

    /**
     * If inside a source procedure that is already coupled, HEARING_AUTHORITY_ADMIN users may copy statements to the
     * respective target procedure, while HEARING_AUTHORITY_WORKER users may see which statements are synchronized.
     */
    isSourceAndCoupledProcedure: {
      type: Boolean,
      required: false,
      default: false,
    },

    procedureId: {
      required: true,
      type: String,
    },

    procedureName: {
      required: false,
      type: String,
      default: '',
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => [],
    },
  },

  data () {
    return {
      claimLoadingIds: [],
      defaultPagination: {
        currentPage: 1,
        limits: [10, 25, 50, 100],
        perPage: 10,
      },
      // Member counts per group head (keyed by head statement id), fetched from the 3.0 StatementGroup endpoint.
      groupMemberCounts: {},
      headerFields: [
        { field: 'externId', label: Translator.trans('id') },
        { field: 'status', label: Translator.trans('status') },
        { field: 'internId', label: Translator.trans('internId.shortened'), colClass: 'w-8' },
        { field: 'meta', label: Translator.trans('submitter.invitable_institution') },
        { field: 'text', label: Translator.trans('text') },
        { field: 'segmentsCount', label: Translator.trans('segments') },
      ],
      isFullscreen: false,
      lsKey: {
        // LocalStorage keys
        toggledStatements: `${this.procedureId}:toggledStatements`,
      },
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
        'typeOfSubmission',
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
        { value: 'initialOrganisationName', label: Translator.trans('sort.organisation.ascending') },
      ],
    }
  },

  computed: {
    ...mapState('AssignableUser', {
      assignableUsersObject: 'items',
    }),

    ...mapState('Orga', {
      orgaObject: 'items',
    }),

    ...mapState('Statement', {
      statementsObject: 'items',
      currentPage: 'currentPage',
      totalFiles: 'totalFiles',
      isLoading: 'loading',
    }),

    assignableUsers () {
      return Object.keys(this.assignableUsersObject).length ?
        Object.values(this.assignableUsersObject)
          .map(user => ({
            name: user.attributes.firstname + ' ' + user.attributes.lastname,
            id: user.id,
          })) :
        []
    },

    exportRoute: function () {
      return (exportRoute, docxHeaders, fileNameTemplate, isObscured, isInstitutionDataCensored, isCitizenDataCensored, tagFilterIds, customHeaderText) => {
        const parameters = {
          filter: {
            procedureId: {
              condition: {
                path: 'procedure.id',
                value: this.procedureId,
              },
            },
          },
          procedureId: this.procedureId,
          search: {
            value: this.searchValue,
            ...this.searchFieldsSelected !== null ? { fieldsToSearch: this.searchFieldsSelected } : {},
          },
          sort: this.selectedSort,
          tagsFilter: {
            tagIds: tagFilterIds,
          },
          isObscured,
          isInstitutionDataCensored,
          isCitizenDataCensored,
        }

        if (docxHeaders) {
          parameters.tableHeaders = {
            col1: docxHeaders.col1,
            col2: docxHeaders.col2,
            col3: docxHeaders.col3,
          }
        }

        if (fileNameTemplate) {
          parameters.fileNameTemplate = fileNameTemplate
        }

        if (customHeaderText) {
          parameters.customHeaderText = customHeaderText
        }

        return Routing.generate(exportRoute, parameters)
      }
    },

    items () {
      return Object.values(this.statementsObject)
        .map(statement => {
          const { segmentsCount = 0 } = statement.attributes
          const originalPdf = this.getOriginalPdfAttachmentHash(statement)

          return {
            ...statement.attributes,
            assignee: this.getAssignee(statement),
            id: statement.id,
            segmentsCount: segmentsCount || '-',
            // Lock selection for synchronized statements, statements already split into segments, and groups.
            lockedForSelection: Boolean(statement.attributes.synchronized) || segmentsCount > 0 || Boolean(statement.attributes.isCluster),
            // Per-row tooltip for the locked checkbox, specific to the reason the statement is locked.
            lockedForSelectionMessage: this.getLockMessage(statement),
            originalPdf,
          }
        })
    },

    storageKeyPagination () {
      return `${this.currentUserId}:${this.procedureId}:paginationStatementList`
    },
  },

  methods: {
    ...mapActions('AssignableUser', {
      fetchAssignableUsers: 'list',
    }),

    ...mapActions('Statement', {
      deleteStatement: 'delete',
      fetchStatements: 'list',
      restoreStatementAction: 'restoreFromInitial',
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem',
    }),

    assigneeId (statement) {
      if (statement.hasRelationship('assignee') && statement.relationships.assignee.data.id !== '') {
        return statement.relationships.assignee.data.id
      } else {
        return null
      }
    },

    displayedText (statementId) {
      const { attributes } = this.statementsObject[statementId]

      if (!attributes) {
        return ''
      }

      return inlineImageAnchors(attributes.isFulltextDisplayed ? attributes.fullText : attributes.text)
    },

    getAssignee (statement) {
      if (this.assigneeId(statement)) {
        const assignee = this.assignableUsersObject[this.assigneeId(statement)]
        const assigneeOrga = assignee ? assignee.rel('orga') : null

        if (typeof assignee === 'undefined') {
          return {
            id: statement.relationships.assignee.data.id,
            name: 'Benutzer',
            orgaName: 'unbekannt',
          }
        }

        return {
          id: statement.relationships.assignee.data.id,
          name: `${assignee.attributes.firstname} ${assignee.attributes.lastname}`,
          orgaName: assigneeOrga ? assigneeOrga.attributes.name : '',

        }
      }

      return {
        id: '',
        name: '',
        orgaName: '',
      }
    },

    handleBulkGroup () {
      /*
       * Statements must be assigned to the current user. Only statements loaded on the current page
       * are present in statementsObject, so items selected on other pages (or via "select all") are
       * validated server-side on the group-creation page (handleConfirmStep1).
       */
      if (hasPermission('feature_statement_assignment') && !this.allSelectedVisually) {
        const allAssigned = this.toggledItems.every(item => {
          const statement = this.statementsObject[item.id]

          return !statement || this.assigneeId(statement) === this.currentUserId
        })

        if (!allAssigned) {
          dplan.notify.notify('error', Translator.trans('confirm.consolidation.not.assigned'))

          return
        }
      }

      this.storeToggledStatements()
      /*
       * Store the selection first, then navigate to the dedicated group-creation page,
       * whose form reads the selection from localStorage on mount.
       */
      globalThis.location.href = Routing.generate('dplan_procedure_statement_group_create', { procedureId: this.procedureId })
    },

    // Show the "group resolved" toast once when arriving here after the last group member was detached
    notifyClusterResolved () {
      const ToastData = lscache.get(`${this.procedureId}:clusterElementDetached`)

      if (ToastData) {
        // One-shot flag: remove it so a reload does not re-trigger the toast
        lscache.remove(`${this.procedureId}:clusterElementDetached`)

        const { statementId, clusterId: detachedClusterId } = JSON.parse(ToastData)

        dplan.notify.notify('confirm', Translator.trans('confirm.statement.detach.cluster.element', {
          statementId,
          clusterId: detachedClusterId,
        }))
      }

      const clusterId = lscache.get(`${this.procedureId}:clusterResolved`)

      if (!clusterId) {
        return
      }

      // One-shot flag: remove it so a reload does not re-trigger the toast
      lscache.remove(`${this.procedureId}:clusterResolved`)
      dplan.notify.notify('confirm', Translator.trans('confirm.statement.cluster.resolved', { clusterId }))
    },

    handleFullTextAction (statementId) {
      const attributes = this.statementsObject[statementId].attributes

      if (!attributes) {
        return
      }

      if (!attributes.isFulltextDisplayed) {
        this.getStatementsFullText(statementId)

        return
      }

      this.toggleFulltext(statementId)
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
        window.location.href = Routing.generate('dplan_drafts_list_edit', { statementId, procedureId: this.procedureId })
      }

      if (!isStatementClaimed || (isStatementClaimedByOtherUser && dpconfirm(Translator.trans('warning.statement.needLock.generic')))) {
        this.claimStatement(statementId)
          .then(() => {
            window.location.href = Routing.generate('dplan_drafts_list_edit', { statementId, procedureId: this.procedureId })
          })
          .catch(err => {
            console.error(err)
          })
      }
    },

    applySearch (term) {
      this.searchValue = term
      this.getItemsByPage(1)
    },

    applySort (sortValue) {
      this.selectedSort = sortValue
      this.updateSessionStorage('selectedSort', sortValue)
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
                  id: this.currentUserId,
                },
              },
            },
          },
        }

        return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: statementId }), {}, payload)
          .then(() => {
            dplan.notify.notify('confirm', Translator.trans('confirm.statement.assignment.assigned'))
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
        this.unclaimStatement(statementId)
      }
    },

    storeToggledStatements () {
      // Store selection as criteria so "select all" resolves across pages on load.
      const { search, filter } = this.getParamsForBulkShare()

      lscache.set(this.lsKey.toggledStatements, { search, filter })
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
              data: null,
            },
          },
        },
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: statementId }), {}, payload)
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

    /**
     * Fetch the member count for each group head on the current page from the 3.0 StatementGroup
     * endpoint. The 2.0 statement list does not carry the count, so it is loaded per head.
     */
    fetchGroupMemberCounts () {
      Object.values(this.statementsObject)
        .filter(statement => statement.attributes.isCluster && this.groupMemberCounts[statement.id] == null)
        .forEach(head => {
          dpApi.get(`${Routing.getBaseUrl()}/api/3.0/StatementGroup/${head.id}`, { properties: ['statementsCount'] })
            .then(response => {
              this.groupMemberCounts[head.id] = response.data.data.attributes.statementsCount
            })
            .catch(error => {
              console.error('Failed to fetch group member count for', head.id, error)
            })
        })
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
        'isCluster',
        'memo',
        'name',
        'originalId',
        'status',
        'segmentsCount',
        'submitDate',
        'submitName',
        'submitType',
        'submitterEmailAddress',
        'text',
        'textIsTruncated',
        // Relationships:
        'assignee',
        'sourceAttachment',
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
          size: this.pagination.perPage,
        },
        search: {
          value: this.searchValue,
          ...this.searchFieldsSelected !== null ? { fieldsToSearch: this.searchFieldsSelected } : {},
        },
        filter: {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId,
            },
          },
        },
        sort: this.selectedSort,
        include: [
          'assignee',
          'sourceAttachment',
          'sourceAttachment.file',
        ].join(),
        fields: {
          Statement: statementFields.join(),
          SourceStatementAttachment: [
            'file',
          ].join(),
          File: [
            'hash',
          ].join(),
        },
      }).then((data) => {
        /**
         * We need to set the localStorage to be able to persist the last viewed page selected in the vue-sliding-pagination.
         */
        this.setLocalStorage(data.meta.pagination)

        this.setNumSelectableItems(data)
        this.updatePagination(data.meta.pagination)
        this.fetchGroupMemberCounts()
      }).catch(() => {
        /*
         * DpApi rejects on HTTP >= 400. Don't let it bubble as an unhandled rejection: a stale
         * stored page can be recovered by falling back to page 1; otherwise inform the user.
         */
        if (page === 1) {
          dplan.notify.notify('error', Translator.trans('error.api.generic'))
        } else {
          this.getItemsByPage(1)
        }
      })
    },

    /**
     * Returns the tooltip message for a locked checkbox, specific to why the statement cannot be selected.
     */
    getLockMessage (statement) {
      const { isCluster, segmentsCount, synchronized } = statement.attributes

      if (synchronized) {
        return Translator.trans('item.lockedForSelection.sharedStatement')
      }

      if (isCluster) {
        return Translator.trans('item.lockedForSelection.cluster')
      }

      if (segmentsCount > 0) {
        return Translator.trans('item.lockedForSelection.segmented')
      }

      return ''
    },

    /**
     * Returns the hash of the original statement attachment
     */
    getOriginalPdfAttachmentHash (el) {
      if (!el.hasRelationship('sourceAttachment')) {
        return null
      }

      const attachments = el.relationships.sourceAttachment.list()
      const firstAttachment = Object.values(attachments)[0]

      if (!firstAttachment?.relationships?.file) {
        return null
      }

      return firstAttachment.relationships.file.get()?.attributes?.hash || null
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
     * {@link https://demoseurope.youtrack.cloud/articles/TECH-A-105}.
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
          ...this.searchFieldsSelected !== null ? { fieldsToSearch: this.searchFieldsSelected } : {},
        },
      }

      /*
       * When tracking deselected items, we want the action to apply to all but the deselected items.
       * That's why `AND` is used as conjunction, and `<>` (not equal) as operator, in that case.
       */
      const filterForToggledItems = {}

      if (this.toggledItems.length > 0) {
        filterForToggledItems.statementFilterGroup = {
          group: {
            conjunction: this.trackDeselected ? 'AND' : 'OR',
          },
        }
        this.toggledItems.forEach((item, idx) => {
          filterForToggledItems['statement_' + idx] = {
            condition: {
              path: 'id',
              value: item.id,
              memberOf: 'statementFilterGroup',
              operator: this.trackDeselected ? '<>' : '=',
            },
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
              value: this.procedureId,
            },
          },
        }
      } else if (this.trackDeselected) {
        // All but deselected
        params.filter = {
          procedureId: {
            condition: {
              path: 'procedure.id',
              value: this.procedureId,
            },
          },
          ...filterForToggledItems,
        }
      } else {
        // Only selected
        params.filter = filterForToggledItems
      }

      return params
    },

    getStatementsFullText (statementId) {
      return dpApi.get(Routing.generate('api_resource_get', { resourceType: 'Statement', resourceId: statementId }), { fields: { Statement: ['fullText'].join() } })
        .then(response => {
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
          .then(response => {
            /*
             * Error messages are displayed with "checkResponse", but we need to check for error here to, because
             * we also get 200 status with an error
             */
            if (!response.data[0].error) {
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
      this.$refs.customSearchStatements.toggleAllFields(false)
    },

    restoreSelectedSort () {
      const storedSort = this.getItemFromSessionStorage('selectedSort')

      if (storedSort) {
        this.selectedSort = storedSort
      }
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

    showHintAndDoExport ({ route, docxHeaders, fileNameTemplate, shouldConfirm, isObscured, isInstitutionDataCensored, isCitizenDataCensored, tagFilterIds, customHeaderText }) {
      const url = this.exportRoute(route, docxHeaders, fileNameTemplate, isObscured, isInstitutionDataCensored, isCitizenDataCensored, tagFilterIds, customHeaderText)

      if (!shouldConfirm || window.dpconfirm(Translator.trans('export.statements.hint'))) {
        window.location.href = url
      }
    },

    storeNavigationContextInLocalStorage () {
      lscache.set(`${this.procedureId}:navigation:source`, 'StatementsList')
    },

    triggerStatementDeletion (id) {
      if (window.confirm(Translator.trans('check.statement.delete'))) {
        // Override the default success callback to display a custom message
        this.$store.api.successCallbacks[0] = async (success) => this.$store.api.handleResponse(success, {
          200: { type: 'confirm', text: Translator.trans('confirm.statement.deleted') },
          204: { type: 'confirm', text: Translator.trans('confirm.statement.deleted') },
        })

        this.deleteStatement(id)
          .then(() => {
            this.getItemsByPage(this.pagination.currentPage)
            // Reset the custom success callback to the default one
            this.$store.api.successCallbacks[0] = this.$store.api.handleResponse
          })
      }
    },

    toggleFulltext (statementId) {
      const statement = this.statementsObject[statementId]
      const isFulltext = statement.attributes.isFulltextDisplayed

      this.setStatement({ ...{ ...statement, attributes: { ...statement.attributes, isFulltextDisplayed: !isFulltext }, id: statementId } })
    },

    toggleFullTextLabel (statementId) {
      const { attributes } = this.statementsObject[statementId]

      if (!attributes) {
        return ''
      }

      return Translator.trans(attributes.isFulltextDisplayed ? 'show.less' : 'show.more')
    },

    updateSearchFields (selectedFields) {
      this.searchFieldsSelected = selectedFields
    },
  },

  mounted () {
    /*
     * Defer to after the whole tree (incl. the root app) is mounted, since dplan.notify is only
     * set in the root's mounted hook, which runs after this child's mounted.
     */
    this.$nextTick(() => this.notifyClusterResolved())

    if (lscache.get(`${this.procedureId}:navigation:source`)) {
      lscache.remove(`${this.procedureId}:navigation:source`)
    }

    this.fetchAssignableUsers({
      include: 'orga',
      fields: {
        Orga: 'name',
      },
    })
    this.initPagination()
    /*
     * After grouping, the statement count shrinks, so the persisted page may no longer exist.
     * Start on page 1 to avoid an out-of-range request (and its slow double-fetch).
     */
    if (lscache.get(`${this.procedureId}:statementListResetPage`)) {
      lscache.remove(`${this.procedureId}:statementListResetPage`)
      this.pagination.currentPage = 1
    }

    this.restoreSelectedSort()
    this.getItemsByPage(this.pagination.currentPage)
  },
}
</script>
