<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-inline-notification
      class="mt-3 mb-2"
      dismissible
      :message="Translator.trans('explanation.invitable_institution.group.tags')"
      type="info" />

    <dp-loading
      class="u-mt"
      v-if="isLoading" />
    <template v-else>
      <div class="bg-color--grey-light-2 rounded-md ml-2">
          <span class="color--grey ml-1 align-middle">
            {{ Translator.trans('filter') }}
          </span>
        <filter-flyout
          v-for="filter in filters"
          ref="filterFlyout"
          :data-cy="`institutionListFilter:${filter.label}`"
          :initial-query="queryIds"
          :key="`filter_${filter.label}`"
          :label="filter.label"
          :operator="filter.comparisonOperator"
          :path="filter.rootPath"
          @filter-apply="sendFilterQuery"
         @filterOptions:request="createFilterOptions(filter.id)" />
      </div>
      <dp-column-selector
        data-cy="institutionList:selectableColumns"
        :initial-selection="currentSelection"
        local-storage-key="institutionList"
        :selectable-columns="selectableColumns"
        use-local-storage
        @selection-changed="setCurrentSelection" />

      <dp-data-table
        ref="dataTable"
        class="u-mt-2 overflow-x-auto scrollbar-none"
        data-dp-validate="tagsTable"
        data-cy="institutionList:dataTable"
        :header-fields="headerFields"
        is-resizable
        :items="institutionList"
        track-by="id">
        <template v-slot:name="institution">
          <ul class="o-list max-w-12">
            <li>
              {{ institution.name }}
            </li>
            <li class="o-list__item o-hellip--nowrap">
              {{ date(institution.createdDate) }}
            </li>
          </ul>
        </template>
        <template
          v-for="category in institutionTagCategories"
          v-slot:[category.attributes.name]="institution">
          <dp-multiselect
            v-if="institution.edit"
            v-model="editingInstitutionTags[category.id]"
            :data-cy="`institutionList:tags${category.attributes.name}`"
            label="name"
            multiple
            :options="getCategoryTags(category.id)"
            track-by="id" />
          <div
            v-else
            v-text="separateByCommas(institution.tags.filter(tag => tag.category.id === category.id))" />
        </template>
        <template v-slot:action="institution">
          <div class="float-right">
            <template v-if="institution.edit">
              <button
                :aria-label="Translator.trans('save')"
                class="btn--blank o-link--default u-mr-0_25"
                data-cy="institutionList:saveTag"
                @click="addTagsToInstitution(institution.id)">
                <dp-icon
                  icon="check"
                  aria-hidden />
              </button>
              <button
                :aria-label="Translator.trans('abort')"
                class="btn--blank o-link--default"
                data-cy="institutionList:abortTag"
                @click="abortEdit()">
                <dp-icon
                  icon="xmark"
                  aria-hidden />
              </button>
            </template>
            <button
              v-else
              :aria-label="Translator.trans('item.edit')"
              class="btn--blank o-link--default"
              data-cy="institutionList:editTag"
              @click="editInstitution(institution.id)">
              <dp-icon
                icon="edit"
                aria-hidden />
            </button>
          </div>
        </template>
      </dp-data-table>

      <div
        ref="scrollBar"
        class="sticky bottom-0 left-0 right-0 h-3 overflow-x-scroll overflow-y-hidden">
        <div />
      </div>
    </template>

    <dp-sliding-pagination
      v-if="totalPages > 1"
      class="u-mr-0_25 u-ml-0_5 u-mt-0_5"
      :current="currentPage"
      :total="totalPages"
      :non-sliding-size="50"
      @page-change="getInstitutionsByPage" />
  </div>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpColumnSelector,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpLoading,
  DpMultiselect,
  DpSlidingPagination,
  DpStickyElement,
  formatDate
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'
import tableScrollbarMixin from '@DpJs/components/shared/mixins/tableScrollbarMixin'

export default {
  name: 'InstitutionList',

  components: {
    DpColumnSelector,
    DpDataTable,
    DpMultiselect,
    DpIcon,
    DpInlineNotification,
    DpLoading,
    DpSlidingPagination,
    DpStickyElement,
    FilterFlyout
  },

  mixins: [tableScrollbarMixin],

  props: {
    initialFilter: {
      type: [Object, Array],
      default: () => ({})
    },
  },

  data () {
    return {
      appliedFilterQuery: this.initialFilter,
      currentSelection: [],
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionTags: {},
      isLoading: true,
    }
  },

  computed: {
    ...mapState('InstitutionTag', {
      institutionTagList: 'items'
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items'
    }),

    ...mapState('InvitableInstitution', {
      invitableInstitutionList: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages'
    }),

    categoryFieldsAvailable() {
      return this.institutionTagCategoriesValues.map(category => ({
        field: category.attributes.name,
        label: category.attributes.name
      }))
    },

    filters () {
      return this.institutionTagCategoriesValues.reduce((acc, category) => {
        acc[category.id] = {
          id: category.id,
          comparisonOperator: '=',
          label: category.attributes.name,
          rootPath: 'assignedTags',
          selected: false
        }
        return acc
      }, {})
    },

    headerFields () {
      const institutionField = {
        field: 'name',
        label: Translator.trans('institution')
      }

      const categoryFields = this.categoryFieldsAvailable.filter(headerField => this.currentSelection.includes(headerField.field))

      const actionField = {
        field: 'action'
      }

      return [institutionField, ...categoryFields, actionField]
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

    institutionList () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          createdDate: attributes.createdDate.date,
          edit: this.editingInstitutionId === id,
          id,
          name: attributes.name,
          tags: relationships.assignedTags.data.map(tag => {
            const tagDetails = this.getTagById(tag.id)

            return {
              id: tag.id,
              type: tag.type,
              name: tagDetails.name,
              category: tagDetails.category
            }
          })
        }
      })
    },

    institutionTagCategoriesValues() {
      return Object.values(this.institutionTagCategories)
    },

    selectableColumns () {
      return this.categoryFieldsAvailable.map(headerField => ([headerField.field, headerField.label]))
    },

    tagList () {
      return Object.values(this.institutionTagList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          id,
          name: attributes.name,
          category: relationships?.category?.data
        }
      })
    }
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list'
    }),

    ...mapActions('InvitableInstitution', {
      fetchInvitableInstitution: 'list',
      saveInvitableInstitution: 'save',
      restoreInstitutionFromInitial: 'restoreFromInitial'
    }),

    ...mapMutations('FilterFlyout', {
      setUngroupedFilterOptions: 'setUngroupedOptions'
    }),

    ...mapMutations('InvitableInstitution', {
      updateInvitableInstitution: 'setItem'
    }),

    abortEdit () {
      this.editingInstitutionId = null
      this.editingInstitutionTags = {}
    },

    addTagsToInstitution (id) {
      const institutionTagsArray = Object.values(this.editingInstitutionTags).flatMap(category => Object.values(category))
      const payload = institutionTagsArray.map(el => {
        return {
          id: el.id,
          type: 'InstitutionTag'
        }
      })

      this.updateInvitableInstitution({
        id,
        type: 'InvitableInstitution',
        attributes: { ...this.invitableInstitutionList[id].attributes },
        relationships: {
          assignedTags: {
            data: payload
          }
        }
      })

      this.saveInvitableInstitution(id)
        .then(dplan.notify.confirm(Translator.trans('confirm.saved')))
        .catch(err => {
          this.restoreInstitutionFromInitial(id)
          console.error(err)
        })
        .finally(() => {
          this.editingInstitutionId = null
        })
    },

    applyQuery (page) {
      // lscache.remove(this.lsKey.allSegments)
      // lscache.remove(this.lsKey.toggledSegments)
      // this.allItemsCount = null
      //
      // const filter = {
      //   ...this.getFilterQuery,
      //   sameProcedure: {
      //     condition: {
      //       path: 'parentStatement.procedure.id',
      //       value: this.procedureId
      //     }
      //   }
      // }
      // const payload = {
      //   include: [
      //     'assignee',
      //     'place',
      //     'tags',
      //     'parentStatement.genericAttachments.file',
      //     'parentStatement.sourceAttachment.file'
      //   ].join(),
      //   page: {
      //     number: page,
      //     size: this.pagination.perPage
      //   },
      //   sort: 'parentStatement.submitDate,parentStatement.externId,orderInProcedure',
      //   filter,
      //   fields: {
      //     File: [
      //       'hash'
      //     ].join(),
      //     GenericStatementAttachment: [
      //       'file'
      //     ].join(),
      //     Place: [
      //       'name'
      //     ].join(),
      //     SourceStatementAttachment: ['file'].join(),
      //     Statement: [
      //       'authoredDate',
      //       'authorName',
      //       'genericAttachments',
      //       'isSubmittedByCitizen',
      //       'initialOrganisationDepartmentName',
      //       'initialOrganisationName',
      //       'initialOrganisationStreet',
      //       'initialOrganisationHouseNumber',
      //       'initialOrganisationPostalCode',
      //       'initialOrganisationCity',
      //       'internId',
      //       'memo',
      //       'sourceAttachment',
      //       'status',
      //       'submitDate',
      //       'submitName',
      //       'submitType'
      //     ].join(),
      //     StatementSegment: [
      //       'assignee',
      //       'externId',
      //       'orderInProcedure',
      //       'parentStatement',
      //       'place',
      //       'tags',
      //       'text',
      //       'recommendation'
      //     ].join(),
      //     Tag: [
      //       'title'
      //     ].join()
      //   }
      // }
      // if (this.searchTerm !== '') {
      //   payload.search = {
      //     value: this.searchTerm,
      //     ...this.searchFieldsSelected.length !== 0 ? { fieldsToSearch: this.searchFieldsSelected } : {}
      //   }
      // }
      // this.isLoading = true
      // this.listSegments(payload)
      //   .catch(() => {
      //     dplan.notify.notify('error', Translator.trans('error.generic'))
      //   })
      //   .then((data) => {
      //     /**
      //      * We need to set the localStorage to be able to persist the last viewed page selected in the vue-sliding-pagination.
      //      */
      //     this.setLocalStorage(data.meta.pagination)
      //
      //     // Fake the count from meta info of paged request, until `fetchSegmentIds()` resolves
      //     this.allItemsCount = data.meta.pagination.total
      //     this.updatePagination(data.meta.pagination)
      //
      //     // Get all segments (without pagination) to save them in localStorage for bulk editing
      //     this.fetchSegmentIds({
      //       filter,
      //       search: payload.search
      //     })
      //   })
      //   .finally(() => {
      //     this.isLoading = false
      //     if (this.items.length > 0) {
      //       this.$nextTick(() => {
      //         this.$refs.imageModal.addClickListener(this.$refs.dataTable.$el.querySelectorAll('img'))
      //       })
      //     }
      //   })
    },

    createFilterOptions (categoryId) {
      let filterOptions = this.institutionTagCategories[categoryId]?.relationships?.tags?.data.length > 0 ? this.institutionTagCategories[categoryId].relationships.tags.list() : []

      if (Object.keys(filterOptions).length > 0) {
        filterOptions = Object.values(filterOptions).map(option => {
          const { id, attributes } = option
          const { name } = attributes

          // @todo missing: count, description (?)
          return {
            id,
            label: name,
            selected: false
          }
        })
      }

      this.setUngroupedFilterOptions(filterOptions)
    },

    date (d) {
      return formatDate(d)
    },

    editInstitution (id) {
      this.editingInstitutionTags = {}
      this.editingInstitutionId = id
      this.editingInstitution = this.invitableInstitutionList[id]

      // Initialize editingInstitutionTags with categoryId
      this.institutionTagCategoriesValues.forEach(category => {
        if (!this.editingInstitutionTags[category.id]) {
          this.$set(this.editingInstitutionTags, category.id, [])
        }
      })
      this.editingInstitution.relationships.assignedTags.data.forEach(el => {
        const tag = this.getTagById(el.id)
        this.editingInstitutionTags[tag.category.id].push(tag)
      })
    },

    getCategoryTags (categoryId) {
      const tags = this.institutionTagCategories[categoryId].relationships?.tags?.data.length > 0 ? this.institutionTagCategories[categoryId].relationships.tags.list() : []

      return Object.values(tags).map(tag => {
        return {
          id: tag.id,
          name: tag.attributes.name
        }
      })
    },

    getInstitutionsByPage (page) {
      this.fetchInvitableInstitution({
        page: {
          number: page,
          size: 50
        },
        sort: '-createdDate',
        fields: {
          InvitableInstitution: [
            'name',
            'createdDate',
            'assignedTags'
          ].join(),
          InstitutionTag: [
            'category',
            'name'
          ].join(),
          InstitutionTagCategory: [
            'name'
          ].join()
        },
        include: [
          'assignedTags',
          'assignedTags.category',
          'category'
        ].join()
      })
    },

    getInstitutionTagCategories () {
      this.isLoading = true

      this.fetchInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'name',
            'tags'
          ].join(),
          InstitutionTag: [
            'isUsed',
            'name',
            'category'
          ].join()
        },
        include: [
          'tags',
          'tags.category'
        ].join()
      })
      .then(() => {
        this.setInitialSelection()
      })
      .finally(() => {
        this.isLoading = false
      })
      .catch(err => {
        console.error(err)
      })
    },

    getTagById (tagId) {
      return this.tagList.find(el => el.id === tagId) ?? null
    },

    getTagNameById (tagId) {
      return this.tagList
        .filter(el => el.id === tagId)
        .map(el => el.name)
    },

    sendFilterQuery (filter) {
      // const isReset = Object.keys(filter).length === 0
      // if (isReset === false && Object.keys(this.appliedFilterQuery).length) {
      //   Object.values(filter).forEach(el => {
      //     this.appliedFilterQuery[el.condition.value] = el
      //   })
      // } else {
      //   if (isReset) {
      //     this.appliedFilterQuery = Object.keys(this.getFilterQuery).length ? this.getFilterQuery : []
      //   } else {
      //     this.appliedFilterQuery = filter
      //   }
      // }
      // this.updateQueryHash()
      // this.resetSelection()
      // this.applyQuery(1)
    },

    separateByCommas (institutionTags) {
      const tagsLabels = []

      institutionTags.forEach(el => {
        const name = this.getTagNameById(el.id)

        tagsLabels.push(name)
      })

      return tagsLabels.join(', ')
    },

    setCurrentSelection (selection) {
      this.currentSelection = selection
    },

    setInitialSelection () {
      this.currentSelection = this.institutionTagCategoriesValues
        .slice(0, 7)
        .map(category => category.attributes.name)
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
  },

  mounted () {
    this.getInstitutionsByPage(1)
    this.getInstitutionTagCategories()
  }
}
</script>
