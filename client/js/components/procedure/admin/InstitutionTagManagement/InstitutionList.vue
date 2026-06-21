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

      <div
        ref="scrollContainer"
        class="mt-1 overflow-x-auto scrollbar-none"
      >
        <dp-data-table
          ref="dataTable"
          :header-fields="headerFields"
          :items="filteredItems || institutionList"
          data-cy="institutionList:dataTable"
          data-dp-validate="tagsTable"
          track-by="id"
          has-flyout
          has-sticky-header
          is-resizable
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
              :options="getCategoryTags(category.id)"
              label="name"
              track-by="id"
              multiple
            />
            <div
              v-else
              :key="`tags:${idx}`"
              v-text="separateByCommas(institution.tags.filter(tag => tag.category.id === category.id))"
            />
          </template>
          <template
            v-for="definition in customFieldDefinitions"
            :key="`cf:${definition.id}`"
            v-slot:[`cf_${definition.id}`]="institution"
          >
            <custom-field
              :data-cy="`institutionList:cf:${definition.id}`"
              :definition="definition"
              :field-data="{
                id: definition.id,
                value: institution.edit ?
                  editingInstitutionCustomFields[definition.id] :
                  (institution.customFields[definition.id] || null),
              }"
              :mode="institution.edit ? 'editable' : 'readonly'"
              :show-label="false"
              @update:value="value => updateEditingCustomField(definition.id, value)"
            />
          </template>
          <template v-slot:flyout="institution">
            <template v-if="institution.edit">
              <button
                :aria-label="Translator.trans('save')"
                class="btn--blank o-link--default mr-1"
                data-cy="institutionList:saveEdit"
                @click="saveInstitutionEdit(institution.id)"
              >
                <dp-icon
                  icon="check"
                  aria-hidden="true"
                />
              </button>
              <button
                :aria-label="Translator.trans('abort')"
                class="btn--blank o-link--default"
                data-cy="institutionList:abortEdit"
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
          </template>
        </dp-data-table>
      </div>

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
import CustomField from '@DpJs/components/customFields/CustomField'
import tableScrollbarMixin from '@DpJs/components/shared/mixins/tableScrollbarMixin'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

export default {
  name: 'InstitutionList',

  components: {
    ClientSideTagFilter,
    CustomField,
    DpColumnSelector,
    DpDataTable,
    DpIcon,
    DpInlineNotification,
    DpLoading,
    DpMultiselect,
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
      customFieldDefinitions: [],
      customFieldValuesByInstitutionId: {},
      editingInstitutionId: null,
      editingInstitution: null,
      editingInstitutionCustomFields: {},
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
        colWidth: '220px',
        initialMinWidth: 220,
      }))
    },

    customFieldsAvailable () {
      return this.customFieldDefinitions.map(definition => ({
        field: `cf_${definition.id}`,
        label: definition.attributes.name,
        colWidth: '180px',
        initialMinWidth: 180,
      }))
    },

    headerFields () {
      const institutionField = {
        colWidth: '200px',
        field: 'name',
        initialMinWidth: 200,
        label: Translator.trans('institution'),
      }

      const categoryFields = this.categoryFieldsAvailable
        .filter(headerField => this.currentlySelectedColumns.includes(headerField.field))
        .map(headerField => ({
          ...headerField,
          colWidth: '180px',
          initialMinWidth: 180,
        }))

      const selectedCustomFieldColumns = this.customFieldsAvailable.filter(headerField => this.currentlySelectedColumns.includes(headerField.field))

      return [institutionField, ...categoryFields, ...selectedCustomFieldColumns]
    },

    institutionList () {
      return Object.values(this.invitableInstitutionList).map(tag => {
        const { id, attributes, relationships } = tag

        return {
          createdDate: attributes.createdDate.date,
          customFields: this.customFieldValuesByInstitutionId[id] || {},
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
      return [...this.categoryFieldsAvailable, ...this.customFieldsAvailable]
        .map(headerField => ([headerField.field, headerField.label]))
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

        if (hasPermission('feature_organisations_custom_fields')) {
          this.loadCustomFieldDefinitions()
        }
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
      this.editingInstitutionCustomFields = {}
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

      // Initialize editingInstitutionCustomFields from the component-local value cache
      const currentValues = this.customFieldValuesByInstitutionId[id] || {}

      this.editingInstitutionCustomFields = this.customFieldDefinitions.reduce(
        (acc, definition) => ({
          ...acc,
          [definition.id]: currentValues[definition.id] || '',
        }),
        {},
      )
    },

    extractCustomFieldValues () {
      this.customFieldValuesByInstitutionId = Object.keys(this.invitableInstitutionList).reduce((byInstitution, id) => {
        const customFields = this.invitableInstitutionList[id].attributes?.customFields || []

        return {
          ...byInstitution,
          [id]: customFields.reduce((byField, field) => ({
            ...byField,
            [field.id]: field.value,
          }), {}),
        }
      }, {})
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
      const customFields = hasPermission('feature_organisations_custom_fields') ? ['customFields'] : []
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
            ...customFields,
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
        .then(() => this.extractCustomFieldValues())
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

    loadCustomFieldDefinitions () {
      return useCustomFields().fetchCustomFields(null, {
        sourceEntity: 'CUSTOMER',
        targetEntity: 'ORGA',
      })
        .then(definitions => {
          this.customFieldDefinitions = definitions
        })
        .catch(err => console.error(err))
    },

    resetSearch () {
      this.$refs.searchField.handleReset()
    },

    saveInstitutionEdit (id) {
      const previousCustomFieldValues = { ...this.customFieldValuesByInstitutionId[id] }
      const institutionTagsArray = Object.values(this.editingInstitutionTags).flatMap(category => Object.values(category))
      const tagPayload = institutionTagsArray.map(el => ({
        id: el.id,
        type: 'InstitutionTag',
      }))
      const customFieldsPayload = this.customFieldDefinitions.map(definition => ({
        id: definition.id,
        value: this.editingInstitutionCustomFields[definition.id] || null,
      }))

      // Optimistic Vuex update — tags only; CF values live outside the InvitableInstitution Vuex state
      this.updateInvitableInstitution({
        id,
        type: 'InvitableInstitution',
        attributes: { ...this.invitableInstitutionList[id].attributes },
        relationships: {
          assignedTags: {
            data: tagPayload,
          },
        },
      })

      // Optimistic component-local CF update
      this.customFieldValuesByInstitutionId = {
        ...this.customFieldValuesByInstitutionId,
        [id]: { ...this.editingInstitutionCustomFields },
      }

      let areTagsSaved = false

      this.saveInvitableInstitution(id)
        .then(() => {
          areTagsSaved = true
          if (customFieldsPayload.length === 0) {
            return null
          }

          return useCustomFields().updateCustomFields('InvitableInstitution', id, customFieldsPayload)
        })
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.saved'))
        })
        .catch(error => {
          if (!areTagsSaved) {
            this.restoreInstitutionFromInitial(id)
          }

          this.customFieldValuesByInstitutionId = {
            ...this.customFieldValuesByInstitutionId,
            [id]: previousCustomFieldValues,
          }
          const errorMessage = areTagsSaved ?
            Translator.trans('error.custom_fields.institution.save') :
            Translator.trans('error.changes.not.saved')

          dplan.notify.error(errorMessage)
          console.error(error)
        })
        .finally(() => {
          this.editingInstitutionId = null
        })
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

    updateEditingCustomField (definitionId, value) {
      this.editingInstitutionCustomFields = {
        ...this.editingInstitutionCustomFields,
        [definitionId]: value || '',
      }
    },
  },

  mounted () {
    const customFieldPromises = hasPermission('feature_organisations_custom_fields') ? [this.loadCustomFieldDefinitions()] : []
    const promises = [
      this.getInstitutionsByPage(1),
      this.getInstitutionTagCategories(true),
      ...customFieldPromises,
    ]

    Promise.allSettled(promises)
      .then(() => {
        this.isLoading = false
      })
  },
}
</script>
