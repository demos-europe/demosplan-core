<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="u-pb-0_5">
    <dp-loading v-if="isLoading" />
    <div v-else>
      <!-- Pagination above table header -->
      <div
        v-if="pagination && pagination.currentPage"
        class="flex justify-between items-center mb-4"
      >
        <dp-pager
          :key="`segmentsPagerTop_${pagination.currentPage}_${pagination.count || 0}`"
          :class="{ 'invisible': isLoading }"
          :current-page="pagination.currentPage"
          :limits="pagination.limits || defaultPagination.limits"
          :per-page="pagination.perPage || defaultPagination.perPage"
          :total-pages="pagination.totalPages || 1"
          :total-items="pagination.total || 0"
          @page-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>

      <div class="segment-list-row">
        <div class="segment-list-col--m" />
        <div class="segment-list-col--l weight--bold">
          {{ Translator.trans('segment') }}
        </div>
        <div class="segment-list-col--s">
          <button
            v-tooltip="Translator.trans(isAllCollapsed ? 'aria.expand.all' : 'aria.collapse.all')"
            class="segment-list-toggle-button btn--blank u-mh-auto"
            :class="{'reverse': isAllCollapsed === false}"
            @click="toggleAll"
          >
            <i class="fa fa-arrow-up" />
            <i class="fa fa-arrow-down" />
          </button>
        </div>
        <div class="segment-list-col--l weight--bold text-right">
          {{ Translator.trans('segment.recommendation') }}
        </div>
        <div class="segment-list-col--m w-10" />
      </div>

      <!-- info that statement has not been segmented yet -->
      <div v-if="hasSegments === false">
        <p class="flash flash-info u-mb">
          {{ Translator.trans('statement.not.segmented') }}
        </p>
        <div class="text-right u-mb-2">
          <dp-button
            :text="Translator.trans('split.now')"
            @click="claimAndRedirect"
          />
        </div>
      </div>
      <!--Segments, if there are any-->
      <div v-else>
        <statement-segment
          v-for="segment in segments"
          :key="'segment_' + segment.id"
          ref="segment"
          :segment="segment"
          :statement-id="statementId"
          :current-user-id="currentUser.id"
          :current-user-first-name="currentUser.firstname"
          :current-user-last-name="currentUser.lastname"
          :current-user-orga="currentUser.orgaName"
        />

        <!-- Pagination below segments list -->
        <div
          v-if="pagination && pagination.currentPage"
          class="flex justify-between items-center mt-4"
        >
          <dp-pager
            :key="`segmentsPagerBottom_${pagination.currentPage}_${pagination.count || 0}`"
            :class="{ 'invisible': isLoading }"
            :current-page="pagination.currentPage"
            :limits="pagination.limits || defaultPagination.limits"
            :per-page="pagination.perPage || defaultPagination.perPage"
            :total-pages="pagination.totalPages || 1"
            :total-items="pagination.total || 0"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { dpApi, DpButton, DpLoading, DpPager } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { handleSegmentNavigation } from '@DpJs/lib/segment/handleSegmentNavigation'
import paginationMixin from '@DpJs/components/shared/mixins/paginationMixin'
import { scrollTo } from 'vue-scrollto'
import StatementSegment from './StatementSegment'

export default {
  name: 'SegmentsRecommendations',

  inject: ['procedureId'],

  components: {
    DpButton,
    DpLoading,
    DpPager,
    StatementSegment,
  },

  mixins: [paginationMixin],

  props: {
    currentUser: {
      type: Object,
      required: true,
    },

    statementId: {
      type: String,
      required: true,
    },
  },

  data () {
    return {
      isAllCollapsed: true,
      isLoading: false,
      defaultPagination: {
        currentPage: 1,
        limits: [10, 20, 50],
        perPage: 20,
      },
      pagination: {},
      storageKeyPagination: `segmentsRecommendations_${this.statementId}_pagination`,
      segmentNavigation: null,
    }
  },

  computed: {
    ...mapState('StatementSegment', {
      segments: 'items',
    }),

    hasSegments () {
      return Object.keys(this.segments).length > 0
    },

    statement () {
      return this.$store.state.Statement.items[this.statementId] || null
    },
  },

  methods: {
    ...mapActions('AssignableUser', {
      fetchAssignableUsers: 'list',
    }),

    ...mapActions('Place', {
      fetchPlaces: 'list',
    }),

    ...mapActions('Statement', {
      restoreStatementAction: 'restoreFromInitial',
    }),

    ...mapActions('StatementSegment', {
      listSegments: 'list',
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem',
    }),

    /**
     * Claim statement if necessary/desired, then go to new view:
     * - if statement has assignee and assignee is not currentUser, ask if statement should be claimed and if so, continue
     * - if statement is claimed by currentUser, continue
     * - if statement has no assignee, assign it to currentUser and continue
     */
    claimAndRedirect () {
      if (this.statement.hasRelationship('assignee')) {
        if (this.statement.relationships.assignee.data.id !== this.currentUser.id) {
          if (globalThis.dpconfirm(Translator.trans('warning.statement.needLock.generic'))) {
            this.claimStatement()
              .then(err => {
                if (typeof err === 'undefined') {
                  this.goToSplitStatementView()
                }
              })
          }
        } else {
          this.goToSplitStatementView()
        }
      } else {
        this.claimStatement()
          .then(err => {
            if (typeof err === 'undefined') {
              this.goToSplitStatementView()
            }
          })
      }
    },

    /**
     * Returns an error if claiming fails
     * @return {Promise<*>}
     */
    claimStatement () {
      const dataToUpdate = { ...this.statement, ...{ relationships: { ...this.statement.relationships, ...{ assignee: { data: { type: 'Claim', id: this.currentUser.id } } } } } }
      this.setStatement({ ...dataToUpdate, id: this.statementId })

      const payload = {
        data: {
          id: this.statementId,
          type: 'Statement',
          relationships: {
            assignee: {
              data: {
                type: 'Claim',
                id: this.currentUser.id,
              },
            },
          },
        },
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: this.statementId }),
        {},
        payload,
        {
          messages: {
            200: {
              text: Translator.trans('confirm.statement.assignment.assigned'),
              type: 'confirm',
            },
            204: {
              text: Translator.trans('confirm.statement.assignment.assigned'),
              type: 'confirm',
            },
          },
        },
      )
        .catch((err) => {
          // Restore statement in store in case request failed
          this.restoreStatementAction(this.statementId)
          return err
        })
    },

    async fetchSegments (page = 1) {
      const statementSegmentFields = [
        'tags',
        'text',
        'assignee',
        'place',
        'comments',
        'externId',
        'internId',
        'orderInProcedure',
        'polygon',
        'recommendation',
      ]

      if (hasPermission('field_segments_custom_fields')) {
        statementSegmentFields.push('customFields')
      }

      this.isLoading = true

      // Calculate correct page for segment parameter (only runs once)
      const { calculatedPage, perPage } = await this.segmentNavigation.calculatePageForSegment()
      let shouldRemoveSegmentParam = false

      if (calculatedPage) {
        page = calculatedPage
        this.pagination.currentPage = calculatedPage

        if (perPage) {
          this.pagination.perPage = perPage
        }

        // Mark that we need to remove segment param after scroll completes
        shouldRemoveSegmentParam = true
      }

      await this.fetchPlaces({
        fields: {
          Place: [
            'description',
            'name',
            'solved',
            'sortIndex',
          ].join(),
        },
        sort: 'sortIndex',
      })

      await this.fetchAssignableUsers({
        fields: {
          AssignableUser: [
            'firstname',
            'lastname',
          ].join(),
        },
        include: 'department',
        sort: 'lastname',
      })

      const response = await this.listSegments({
        include: [
          'assignee',
          'comments',
          'comments.place',
          'comments.submitter',
          'place',
          'tags',
        ].join(),
        fields: {
          StatementSegment: statementSegmentFields.join(),
          SegmentComment: [
            'creationDate',
            'text',
            'submitter',
            'place',
          ].join(),
        },
        page: {
          number: page,
          size: this.pagination?.perPage || this.defaultPagination.perPage,
        },
        sort: 'orderInProcedure',
        filter: {
          parentStatementOfSegment: {
            condition: {
              path: 'parentStatement.id',
              value: this.statementId,
            },
          },
          sameProcedure: {
            condition: {
              path: 'parentStatement.procedure.id',
              value: this.procedureId,
            },
          },
        },
      })

      // Update pagination with response metadata
      if (response && response.meta && response.meta.pagination) {
        this.setLocalStorage(response.meta.pagination)
        this.updatePagination(response.meta.pagination)
      }

      this.isLoading = false

      await this.$nextTick()

      const queryParams = new URLSearchParams(globalThis.location.search)
      const segmentId = queryParams.get('segment') || ''

      if (segmentId) {
        scrollTo('#segment_' + segmentId, { offset: -110 })
        const segmentComponent = this.$refs.segment.find(el => el.segment.id === segmentId)

        if (segmentComponent) {
          segmentComponent.isCollapsed = false
        }

        // Remove segment parameter after scroll completes to prevent re-navigation on tab toggle
        if (shouldRemoveSegmentParam) {
          this.segmentNavigation.removeSegmentParameter()
        }
      }
    },

    goToSplitStatementView () {
      globalThis.location.href = Routing.generate('dplan_drafts_list_edit', { statementId: this.statementId, procedureId: this.procedureId })
    },

    toggleAll () {
      this.isAllCollapsed = this.isAllCollapsed === false

      this.$refs.segment.forEach(segment => {
        if (segment) {
          segment.isCollapsed = this.isAllCollapsed
        }
      })
    },

    handlePageChange (page) {
      this.fetchSegments(page)
    },

    handleSizeChange (newSize) {
      if (newSize <= 0) {
        // Prevent division by zero or negative page size
        return
      }
      // Compute new page with current page for changed number of items per page
      const page = Math.floor((this.pagination?.perPage * (this.pagination?.currentPage - 1) / newSize) + 1)
      this.pagination.perPage = newSize
      this.fetchSegments(page)
    },
  },

  created () {
    this.segmentNavigation = handleSegmentNavigation({
      statementId: this.statementId,
      storageKey: this.storageKeyPagination,
      currentPerPage: this.pagination?.perPage,
      defaultPagination: this.defaultPagination,
    })
  },

  mounted () {
    /**
     * Check if the user navigated here from a specific segment in the segments list; if so, navigate to the page on which
     * that segment is found (i.e., override pagination)
     */
    const paginationOverride = this.segmentNavigation.initializeSegmentPagination(() => this.initPagination())

    if (paginationOverride) {
      this.pagination = paginationOverride
    }

    /**
     * Fetch segments for current page from pagination (either based on the segment the user navigated from or on localStorage),
     * default to 1st page
     */
    this.fetchSegments(this.pagination?.currentPage || 1)
  },
}
</script>
