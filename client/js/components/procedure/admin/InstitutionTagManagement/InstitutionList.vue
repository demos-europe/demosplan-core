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
      type="info"
    />

    <dp-loading
      v-if="isLoading"
      class="mt-4"
    />

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-1">
        <dp-search-field
          ref="searchField"
          class="h-fit mt-1 col-span-1 sm:col-span-3"
          data-cy="institutionList:searchField"
          input-width="u-1-of-1"
          @reset="handleReset"
          @search="val => handleSearch(val)"
        />

        <client-side-tag-filter
          v-if="hasPermission('feature_institution_tag_read')"
          :filter-categories="allFilterCategories"
          :raw-items="institutionList"
          :search-applied="isSearchApplied"
          @items-filtered="filteredItems = $event"
          @reset="resetSearch"
        />
      </div>

      <div class="flex justify-end mt-4">
        <dp-column-selector
          data-cy="institutionList:selectableColumns"
          :initial-selection="initiallySelectedColumns"
          local-storage-key="institutionList"
          :selectable-columns="selectableColumns"
          use-local-storage
          @selection-changed="setCurrentlySelectedColumns"
        />
      </div>

      <dp-data-table
        ref="dataTable"
        class="mt-1 overflow-x-auto scrollbar-none"
        data-dp-validate="tagsTable"
        data-cy="institutionList:dataTable"
        :header-fields="headerFields"
        is-resizable
        :items="filteredItems || institutionList"
        track-by="id"
      >
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
          v-for="(category, idx) in institutionTagCategoriesCopy"
          v-slot:[category.attributes.name]="institution"
        >
          <dp-multiselect
            v-if="institution.edit"
            :key="idx"
            v-model="editingInstitutionTags[category.id]"
            :data-cy="`institutionList:tags${category.attributes.name}`"
            label="name"
            multiple
            :options="getCategoryTags(category.id)"
            track-by="id"
          />
          <div
            v-else
            :key="`tags:${idx}`"
            v-text="separateByCommas(institution.tags.filter(tag => tag.category.id === category.id))"
          />
        </template>
        <template v-slot:action="institution">
          <div class="float-right">
            <template v-if="institution.edit">
              <button
                :aria-label="Translator.trans('save')"
                class="btn--blank o-link--default mr-1"
                data-cy="institutionList:saveTag"
                @click="addTagsToInstitution(institution.id)"
              >
                <dp-icon
                  icon="check"
                  aria-hidden="true"
                />
              </button>
              <button
                :aria-label="Translator.trans('abort')"
                class="btn--blank o-link--default"
                data-cy="institutionList:abortTag"
                @click="abortEdit()"
              >
                <dp-icon
                  icon="xmark"
                  aria-hidden="true"
                />
              </button>
            </template>
            <button
              v-else
              :aria-label="Translator.trans('item.edit')"
              class="btn--blank o-link--default"
              data-cy="institutionList:editTag"
              @click="editInstitution(institution.id)"
            >
              <dp-icon
                icon="edit"
                aria-hidden="true"
              />
            </button>
          </div>
        </template>
      </dp-data-table>

      <div
        v-show="scrollbarVisible"
        ref="scrollBar"
        class="sticky bottom-0 left-0 right-0 h-3 overflow-x-scroll overflow-y-hidden"
      >
        <div
          :style="scrollbarInnerStyle"
        />
      </div>
    </template>

    <dp-sliding-pagination
      v-if="totalPages > 1"
      class="mr-1 ml-2 mt-2"
      :current="currentPage"
      :total="totalPages"
      :non-sliding-size="50"
      @page-change="getInstitutionsByPage"
    />
  </div>
</template>

<script>
import {
  DpColumnSelector,
  DpDataTable,
  DpIcon,
  DpInlineNotification,
  DpLoading,
  DpMultiselect,
  DpSearchField,
  DpSlidingPagination,
  formatDate,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import ClientSideTagFilter from '@DpJs/components/procedure/admin/InstitutionTagManagement/ClientSideTagFilter'
import tableScrollbarMixin from '@DpJs/components/shared/mixins/tableScrollbarMixin'

export default {
  name: 'InstitutionList',

  components: {
    ClientSideTagFilter,
    DpColumnSelector,
    DpDataTable,
    DpIcon,
    DpMultiselect,
    DpInlineNotification,
    DpLoading,
    DpSearchField,
    DpSlidingPagination,
  },

  mixins: [tableScrollbarMixin],

  props: {
    isActive: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  data () {
    return {
      currentlySelectedColumns: [],
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionTags: {},
      filteredItems: null,
      initiallySelectedColumns: [],
      institutionTagCategoriesCopy: {},
      isLoading: true,
      searchTerm: '',
    }
  },

  computed: {
    ...mapState('InstitutionTag', {
      institutionTagList: 'items',
    }),

    ...mapState('InstitutionTagCategory', {
      institutionTagCategories: 'items',
    }),

    ...mapState('InvitableInstitution', {
      invitableInstitutionList: 'items',
      currentPage: 'currentPage',
      totalPages: 'totalPages',
    }),

    allFilterCategories () {
      return (Object.values(this.institutionTagCategoriesCopy) || [])
        .filter(category => category && category.id && category.attributes)
        .map(category => {
          const { id, attributes } = category
          const groupKey = `${id}_group`

          return {
            id,
            comparisonOperator: 'ARRAY_CONTAINS_VALUE',
            label: attributes.name,
            rootPath: 'assignedTags',
            selected: false,
            memberOf: groupKey,
          }
        })
    },

    categoryFieldsAvailable () {
      return this.institutionTagCategoriesValues.map(category => ({
        field: category.attributes.name,
        label: category.attributes.name,
      }))
    },

    headerFields () {
      const institutionField = {
        field: 'name',
        label: Translator.trans('institution'),
      }

      const categoryFields = this.categoryFieldsAvailable.filter(headerField => this.currentlySelectedColumns.includes(headerField.field))

      const actionField = {
        field: 'action',
      }

      return [institutionField, ...categoryFields, actionField]
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
              category: tagDetails.category,
            }
          }),
        }
      })
    },

    institutionTagCategoriesValues () {
      return Object.values(this.institutionTagCategoriesCopy)
        .sort((a, b) => new Date(a.attributes.creationDate) - new Date(b.attributes.creationDate))
    },

    isSearchApplied () {
      return this.searchTerm !== ''
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
          category: relationships?.category?.data,
        }
      })
    },
  },

  watch: {
    isActive (newValue) {
      if (newValue) {
        this.getInstitutionTagCategories()
      }
    },
  },

  methods: {
    ...mapActions('InstitutionTagCategory', {
      fetchInstitutionTagCategories: 'list',
    }),

    ...mapActions('InvitableInstitution', {
      fetchInvitableInstitution: 'list',
      saveInvitableInstitution: 'save',
      restoreInstitutionFromInitial: 'restoreFromInitial',
    }),

    ...mapMutations('InvitableInstitution', {
      updateInvitableInstitution: 'setItem',
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
          type: 'InstitutionTag',
        }
      })

      this.updateInvitableInstitution({
        id,
        type: 'InvitableInstitution',
        attributes: { ...this.invitableInstitutionList[id].attributes },
        relationships: {
          assignedTags: {
            data: payload,
          },
        },
      })

      this.saveInvitableInstitution(id)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(err => {
          this.restoreInstitutionFromInitial(id)
          console.error(err)
        })
        .finally(() => {
          this.editingInstitutionId = null
        })
    },

    /**
     * Format date for display
     * @param {string} d - Date string to format
     * @returns {string} Formatted date
     */
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
          this.editingInstitutionTags = {
            ...this.editingInstitutionTags,
            [category.id]: [],
          }
        }
      })
      this.editingInstitution.relationships.assignedTags.data.forEach(el => {
        const tag = this.getTagById(el.id)
        this.editingInstitutionTags[tag.category.id].push(tag)
      })
    },

    getCategoryTags (categoryId) {
      const tags = this.institutionTagCategoriesCopy[categoryId].relationships?.tags?.data.length > 0 ? this.institutionTagCategoriesCopy[categoryId].relationships.tags.list() : []

      return Object.values(tags).map(tag => {
        return {
          id: tag.id,
          name: tag.attributes.name,
        }
      })
    },

    getInstitutionsByPage (page) {
      const args = {
        page: {
          number: page,
          size: 50,
        },
        sort: '-createdDate',
        fields: {
          InvitableInstitution: [
            'name',
            'createdDate',
            'assignedTags',
          ].join(),
          InstitutionTag: [
            'category',
            'name',
          ].join(),
          InstitutionTagCategory: [
            'name',
          ].join(),
        },
        filter: {
          namefilter: {
            condition: {
              path: 'name',
              operator: 'STRING_CONTAINS_CASE_INSENSITIVE',
              value: this.searchTerm,
            },
          },
        },
        include: [
          'assignedTags',
          'assignedTags.category',
        ].join(),
      }

      return this.fetchInvitableInstitution(args)
        .catch(err => {
          console.error(err)
        })
    },

    getInstitutionTagCategories (isInitial = false) {
      return this.fetchInstitutionTagCategories({
        fields: {
          InstitutionTagCategory: [
            'creationDate',
            'name',
            'tags',
          ].join(),
          InstitutionTag: [
            'creationDate',
            'isUsed',
            'name',
            'category',
          ].join(),
        },
        include: [
          'tags',
          'tags.category',
        ].join(),
      })
        .then(() => {
          // Copy the object to avoid issues with filter requests that update the categories in the store
          this.institutionTagCategoriesCopy = { ...this.institutionTagCategories }

          if (isInitial) {
            this.setInitiallySelectedColumns()
          }
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

    handleReset () {
      this.searchTerm = ''
      this.getInstitutionsByPage(1)
    },

    handleSearch (searchTerm) {
      this.searchTerm = searchTerm

      this.getInstitutionsByPage(1)
        .then(() => {
          this.isLoading = false
        })
    },

    resetSearch () {
      this.$refs.searchField.handleReset()
    },

    separateByCommas (institutionTags) {
      const tagsLabels = []

      institutionTags.forEach(el => {
        const name = this.getTagNameById(el.id)

        tagsLabels.push(name)
      })

      return tagsLabels.join(', ')
    },

    setCurrentlySelectedColumns (selectedColumns) {
      this.currentlySelectedColumns = selectedColumns
    },

    setInitiallySelectedColumns () {
      this.initiallySelectedColumns = this.institutionTagCategoriesValues
        .slice(0, 5)
        .map(category => category.attributes.name)
    },
  },

  mounted () {
    const promises = [
      this.getInstitutionsByPage(1),
      this.getInstitutionTagCategories(true),
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  },
}
</script>
