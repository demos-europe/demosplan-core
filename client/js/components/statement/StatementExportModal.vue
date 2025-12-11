<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-button
      data-cy="exportModal:open"
      :text="Translator.trans('export.verb')"
      variant="subtle"
      @click.prevent="openModal"
    />

    <dp-modal
      ref="exportModalInner"
      content-classes="w-11/12 sm:w-10/12 md:w-8/12 lg:w-6/12 xl:w-5/12 h-fit"
      content-body-classes="flex flex-col h-14"
      @modal:toggled="onModalToggle"
    >
      <h2 class="mb-5">
        {{ exportModalTitle }}
      </h2>

      <fieldset v-if="!isSingleStatementExport">
        <legend
          class="o-form__label text-base"
          v-text="Translator.trans('export.type')"
        />
        <div class="grid grid-cols-3 mt-2 mb-3 gap-x-2 gap-y-5">
          <dp-radio
            v-for="(exportType, key) in exportTypes"
            :id="key"
            :key="key"
            :data-cy="`exportModal:exportType:${key}`"
            :label="{
              hint: active === key ? exportType.hint : '',
              text: Translator.trans(exportType.label)
            }"
            :value="key"
            :checked="active === key"
            @change="active = key"
          />
          <template v-if="active !== 'xlsx_normal'">
            <dp-checkbox
              id="censoredCitizen"
              v-model="isCitizenDataCensored"
              data-cy="exportModal:censoredCitizen"
              :label="{
                text: Translator.trans('export.censored.citizen')
              }"
            />
            <dp-checkbox
              id="censoredInstitution"
              v-model="isInstitutionDataCensored"
              data-cy="exportModal:censoredInstitution"
              :label="{
                text: Translator.trans('export.censored.institution')
              }"
            />
            <dp-checkbox
              id="obscured"
              v-model="isObscure"
              data-cy="exportModal:obscured"
              :label="{
                text: Translator.trans('export.docx.obscured')
              }"
            />
          </template>
        </div>
      </fieldset>

      <fieldset v-if="isSingleStatementExport">
        <div class="flex mt-1 mb-5">
          <dp-checkbox
            id="singleStatementCitizen"
            v-model="isCitizenDataCensored"
            data-cy="exportModal:singleStatementCitizen"
            :label="{
              text: Translator.trans('export.censored.citizen')
            }"
          />
          <dp-checkbox
            id="singleStatementInstitution"
            v-model="isInstitutionDataCensored"
            data-cy="exportModal:singleStatementInstitution"
            :label="{
              text: Translator.trans('export.censored.institution')
            }"
          />
          <dp-checkbox
            id="singleStatementObscure"
            v-model="isObscure"
            data-cy="exportModal:singleStatementObscure"
            :label="{
              text: Translator.trans('export.docx.obscured')
            }"
          />
        </div>
      </fieldset>

      <fieldset v-if="['docx_normal', 'zip_normal'].includes(active)">
        <legend
          id="docxColumnTitles"
          class="o-form__label text-base float-left mr-1"
          v-text="Translator.trans('docx.export.column.title')"
        />
        <dp-contextual-help
          aria-labelledby="docxColumnTitles"
          :text="Translator.trans('docx.export.column.title.hint')"
        />
        <div class="grid grid-cols-5 gap-3 mt-1 mb-3">
          <dp-input
            v-for="(column, key) in docxColumns"
            :id="key"
            :key="key"
            v-model="column.title"
            :data-cy="column.dataCy"
            :placeholder="Translator.trans(column.placeholder)"
            type="text"
            :width="column.width"
          />
        </div>
        <fieldset v-if="active === 'zip' || isSingleStatementExport">
          <legend
            id="docxFileName"
            class="o-form__label text-base float-left mr-1"
            v-text="Translator.trans('docx.export.file_name')"
          />
          <dp-contextual-help
            aria-labelledby="docxFileName"
            :text="Translator.trans('docx.export.file_name.hint')"
          />
          <dp-input
            id="fileName"
            v-model="fileName"
            data-cy="exportModal:fileName"
            class="mt-1"
            :placeholder="Translator.trans('docx.export.file_name.placeholder')"
            type="text"
          />
          <div class="font-size-small mt-2">
            <span
              class="weight--bold"
              v-text="Translator.trans('docx.export.example_file_name')"
            />
            <span v-text="exampleFileName" />
          </div>
        </fieldset>
      </fieldset>

      <fieldset v-if="!isSingleStatementExport">
        <legend
          id="tagsFilter"
          class="o-form__label text-base mb-1"
          v-text="Translator.trans('segments.export.filter.tags.only')"
        />
        <filter-flyout
          ref="filterFlyout"
          :key="`filter_${filter.labelTranslationKey}`"
          :additional-query-params="{ searchPhrase: searchTerm }"
          appearance="basic"
          :category="{
            id: `${filter.labelTranslationKey}`,
            label: Translator.trans('search.list')
          }"
          :data-cy="`exportModal:filter:${filter.labelTranslationKey}`"
          flyout-align="top"
          flyout-position="relative"
          :operator="filter.comparisonOperator"
          :path="filter.rootPath"
          :show-count="{
            groupedOptions: true,
            ungroupedOptions: true
          }"
          @filter-apply="getFilterValues"
          @filter-options:request="loadFilterFlyoutOptions"
          @update:expanded="(value) => isFilterExpanded = value"
        />
        <ul
          v-if="!isFilterExpanded && selectedTags.length"
          class="mt-2"
        >
          <li
            v-for="(tag) in selectedTags"
            :key="tag.id"
            class="mt-1"
          >
            <span>{{ tag.label }}</span>
          </li>
        </ul>
      </fieldset>

      <dp-button-row
        class="text-right mt-auto"
        data-cy="exportModal"
        primary
        secondary
        :primary-text="Translator.trans('export.statements')"
        :secondary-text="Translator.trans('abort')"
        @primary-action="handleExport"
        @secondary-action="closeModal"
      />
    </dp-modal>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpCheckbox,
  DpContextualHelp,
  DpInput,
  DpModal,
  DpRadio,
  dpRpc,
  hasOwnProp,
  sessionStorageMixin,
} from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations } from 'vuex'
import FilterFlyout from '@DpJs/components/procedure/SegmentsList/FilterFlyout'

export default {
  name: 'StatementExportModal',

  components: {
    DpButton,
    DpButtonRow,
    DpCheckbox,
    DpContextualHelp,
    DpInput,
    DpModal,
    DpRadio,
    FilterFlyout,
  },

  mixins: [sessionStorageMixin],

  props: {
    isSingleStatementExport: {
      required: false,
      type: Boolean,
      default: false,
    },

    procedureId: {
      required: true,
      type: String,
    },
  },

  emits: [
    'export',
  ],

  data () {
    return {
      active: 'docx_normal',
      docxColumns: {
        col1: {
          width: 'col-span-1',
          dataCy: 'exportModal:input:col1',
          placeholder: Translator.trans('segments.export.segment.id'),
          title: null,
        },
        col2: {
          width: 'col-span-2',
          dataCy: 'exportModal:input:col2',
          placeholder: Translator.trans('segments.export.statement.label'),
          title: null,
        },
        col3: {
          width: 'col-span-2',
          dataCy: 'exportModal:input:col3',
          placeholder: Translator.trans('segment.recommendation'),
          title: null,
        },
      },
      exportTypes: {
        docx_normal: {
          label: 'export.docx',
          hint: '',
          exportPath: 'dplan_statement_segments_export',
          dataCy: 'exportModal:export:docx',
        },
        zip_normal: {
          label: 'export.zip',
          hint: '',
          exportPath: 'dplan_statement_segments_export_packaged',
          dataCy: 'exportModal:export:zip',
        },
        xlsx_normal: {
          label: 'export.xlsx',
          hint: Translator.trans('export.xlsx.hint'),
          exportPath: 'dplan_statement_xls_export',
          dataCy: 'exportModal:export:xlsx',
        },
      },
      fileName: '',
      filter: {
        comparisonOperator: "ARRAY_CONTAINS_VALUE",
        grouping: {
          labelTranslationKey: 'topic',
          targetPath: 'tags.topic.label',
        },
        labelTranslationKey: 'tags',
        rootPath: 'tags',
        selected: false,
      },
      isCitizenDataCensored: false,
      isFilterExpanded: false,
      isInstitutionDataCensored: false,
      isObscure: false,
      searchTerm: '',
      selectedTags: [],
      selectedTagIds: [],
      singleStatementExportPath: 'dplan_segments_export', /** Used in the statements detail page */
    }
  },

  computed: {
    ...mapGetters('FilterFlyout', [
      'getIsExpandedByCategoryId',
    ]),

    exportModalTitle () {
      return this.isSingleStatementExport ? Translator.trans('statement.export.do') : Translator.trans('export.statements')
    },

    exampleFileName () {
      let exampleFileName = 'm101-jacob-meier-e5089.docx'
      const exampleId = 'm101'
      const exampleName = 'jacob-meier'
      const exampleInternId = 'e5089'

      if (this.fileName) {
        exampleFileName = this.fileName
          .replace(/{ID}/g, exampleId)
          .replace(/{NAME}/g, exampleName)
          .replace(/{EINGANGSNR}/g, exampleInternId)
          .replace(/[_\s]/g, '-')

        // Add example unique id if no placeholder was found
        if (exampleFileName === this.fileName) {
          exampleFileName += '-837474df23'
        }
        exampleFileName += '.docx'
      }

      return exampleFileName
    },
  },

  methods: {
    ...mapMutations('FilterFlyout', {
      setGroupedFilterOptions: 'setGroupedOptions',
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsLoadingFilterFlyout: 'setIsLoading',
      setUngroupedFilterOptions: 'setUngroupedOptions',
    }),

    buildFilterOption (option) {
      if (!option) {
        return null
      }

      const { attributes, id } = option
      const { count, description, label, selected } = attributes

      return { id, count, description, label, selected }
    },

    buildOptionsFromResult (result, filter) {
      const groupedOptions = []
      const ungroupedOptions = []

      result.included?.forEach(resource => {
        const group = this.getGroupedOptions(resource, filter, result)
        if (group) {
          groupedOptions.push(group)
        }

        const item = this.getUngroupedOptions(resource, filter)
        if (item) {
          ungroupedOptions.push(item)
        }
      })

      // Add "unassigned" pseudo-option to ungroupedOptions when the filter is "assignee"
      if (result.data[0].attributes.path === 'assignee') {
        const { missingResourcesSum } = result.data[0].attributes

        ungroupedOptions.push({
          id: 'unassigned',
          count: missingResourcesSum,
          label: Translator.trans('not.assigned'),
          ungrouped: true,
          selected: result.meta.unassigned_selected,
        })
      }

      return {
        groupedOptions,
        ungroupedOptions,
      }
    },

    closeModal () {
      this.resetExportModalState()
      this.resetFilterFlyout()
      this.resetExportModalInner()
    },

    async fetchFilterOptions (requestParams) {
      try {
        const { data } = await dpRpc('segments.facets.list', requestParams, 'filterList')

        const result = (hasOwnProp(data, 0) && data[0].id === 'filterList')
          ? data[0].result
          : null

        return result || null
      } catch (error) {
        console.error('Failed to fetch filter options', error)
        return null
      }
    },

    findFilterDefinition (result, path) {
      return result.data.find(type => type.attributes.path === path) || null
    },

    focusSearchField (path) {
      document.getElementById(`searchField_${path}`)?.focus()
    },

    getFilterValues (filter = {}) {
      this.updateSelectedTagIds(filter)
      this.updateSelectedTags()
    },

    getGroupedOptions (resource, filter, result) {
      const isGroup = resource.type === 'AggregationFilterGroup'
      const filterHasGroups = filter.relationships.aggregationFilterGroups?.data.length > 0
      const groupBelongsToFilterType = isGroup && filterHasGroups && filter.relationships.aggregationFilterGroups.data.some(group => group.id === resource.id)

      if (isGroup && groupBelongsToFilterType) {
        const filterOptionsIds = resource.relationships.aggregationFilterItems?.data?.map(item => item.id) ?? []

        const filterOptions = filterOptionsIds
          .map(id => this.buildFilterOption(result.included.find(item => item.id === id)))
          .filter(Boolean)

        if (filterOptions.length === 0) {
          return null
        }

        const { id, attributes } = resource
        const { label } = attributes

        return {
          id,
          label,
          options: filterOptions,
        }
      }
    },

    getUngroupedOptions (resource, filter) {
      const isFilterItem = resource.type === 'AggregationFilterItem'
      const filterHasFilterOptions = filter.relationships.aggregationFilterItems?.data.length > 0
      const filterOptionBelongsToFilterType = isFilterItem && filterHasFilterOptions && filter.relationships.aggregationFilterItems.data.some(option => option.id === resource.id)

      if (isFilterItem && filterOptionBelongsToFilterType) {
        const option = this.buildFilterOption(resource)

        if (!option) {
          return null
        }

        return {
          ...option,
          ungrouped: true,
        }
      }
    },

    handleAfterOptionsLoaded (path) {
      const filterId = this.filter.labelTranslationKey
      const isExpanded = this.getIsExpandedByCategoryId(filterId)

      if (isExpanded) {
        this.focusSearchField(path)
      }

      this.scrollModalToBottom()
    },

    handleExport () {
      const columnTitles = {}
      const shouldConfirm = /^(docx|zip)_/.test(this.active)

      Object.keys(this.docxColumns).forEach(key => {
        const columnTitle = this.docxColumns[key].title
        const storageKey = `exportModal:docxCol:${key}`

        if (columnTitle) {
          this.updateSessionStorage(storageKey, columnTitle)
          columnTitles[key] = columnTitle
        } else {
          this.removeFromSessionStorage(storageKey)
          columnTitles[key] = null /** Setting the value to null will trigger the display of the default column titles */
        }
      })

      this.$emit('export', {
        docxHeaders: ['docx_normal', 'zip_normal'].includes(this.active) ? columnTitles : null,
        fileNameTemplate: this.fileName || null,
        isCitizenDataCensored: this.isCitizenDataCensored,
        isInstitutionDataCensored: this.isInstitutionDataCensored,
        isObscured: this.isObscure,
        route: this.isSingleStatementExport ? this.singleStatementExportPath : this.exportTypes[this.active].exportPath,
        shouldConfirm,
        tagFilterIds: this.selectedTagIds,
      })
      this.closeModal()
    },

    initInitialFlyoutFilterSelection ({ isInitialWithQuery, groupedOptions, ungroupedOptions }) {
      if (!isInitialWithQuery || this.queryIds.length === 0) {
        return
      }

      const allOptions = [
        ...groupedOptions.flatMap(group => group.options),
        ...ungroupedOptions,
      ]

      const currentFlyoutFilterIds = this.queryIds.filter(queryId =>
        allOptions.some(item => item.id === queryId),
      )

      this.setInitialFlyoutFilterIds({
        categoryId: this.filter.labelTranslationKey,
        filterIds: currentFlyoutFilterIds,
      })
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
    async loadFilterFlyoutOptions (params) {
      const {
        additionalQueryParams,
        filter,
        isInitialWithQuery,
        path,
        currentQuery,
      } = params

      // Load filter options only when no filters are active. If filters are active, skip loading and scroll to the flyout.
      if (currentQuery && currentQuery.length > 0) {
        this.scrollModalToBottom()
        return
      }

      const requestParams = this.setRequestParams({
        additionalQueryParams,
        filter,
        path,
        currentQuery,
      })

      const result = await this.fetchFilterOptions(requestParams)
      if (!result) {
        return
      }

      const filterDefinition = this.findFilterDefinition(result, path)
      if (!filterDefinition) {
        return
      }

      const {
        groupedOptions,
        ungroupedOptions,
      } = this.buildOptionsFromResult(result, filterDefinition)

      this.initInitialFlyoutFilterSelection({
        isInitialWithQuery,
        groupedOptions,
        ungroupedOptions,
      })

      this.updateFilterOptionsInStore({
        groupedOptions,
        ungroupedOptions,
      })

      this.handleAfterOptionsLoaded(path)
    },

    onModalToggle (isOpen) {
      if (!isOpen) {
        this.resetExportModalState()
        this.resetFilterFlyout()
      }
    },

    openModal () {
      this.setInitialValues()
      this.resetExportModalInner()
    },

    resetFilterFlyout () {
      this.$refs.filterFlyout?.reset?.()
    },

    resetExportModalInner () {
      this.$refs.exportModalInner?.toggle?.()
    },

    resetExportModalState () {
      this.active = 'docx_normal'
      this.isCitizenDataCensored = false
      this.isInstitutionDataCensored = false
      this.isObscure = false
      this.selectedTagIds = []
      this.selectedTags = []
    },

    scrollModalToBottom () {
      this.$nextTick(() => {
        const modalBody = this.$refs.exportModalInner.$el.querySelector('.o-modal__body')

        if (!modalBody) {
          return
        }

        modalBody.scrollTo({
          top: modalBody.scrollHeight,
          behavior: "smooth",
        })
      })
    },

    setInitialValues () {
      this.active = 'docx_normal'

      Object.keys(this.docxColumns).forEach(key => {
        const storageKey = `exportModal:docxCol:${key}`
        const storedColumnTitle = this.getItemFromSessionStorage(storageKey)
        this.docxColumns[key].title = storedColumnTitle || null /** Setting the value to null will display the placeholder titles of the column */
      })
    },

    setRequestParams ({ additionalQueryParams, filter, path, currentQuery }) {
      const requestParams = {
        ...additionalQueryParams,
        filter: {
          ...filter,
          sameProcedure: {
            condition: {
              path: 'parentStatement.procedure.id',
              value: this.procedureId,
            },
          },
        },
        path,
      }

      if (requestParams.searchPhrase === '') {
        requestParams.searchPhrase = null // The backend expects `searchPhrase` to be null when it is empty
      }

      return requestParams
    },

    syncSelectedItemsFromFlyout() {
      const filterFlyout = this.$refs.filterFlyout

      if (!filterFlyout || !Array.isArray(filterFlyout.itemsSelected)) {
        this.selectedTags = []
        return
      }

      this.selectedTags = filterFlyout.itemsSelected
    },

    updateFilterOptionsInStore ({ category, groupedOptions, ungroupedOptions }) {
      this.setGroupedFilterOptions({
        categoryId: this.filter.labelTranslationKey,
        groupedOptions,
      })

      this.setUngroupedFilterOptions({
        categoryId: this.filter.labelTranslationKey,
        options: ungroupedOptions,
      })

      this.setIsLoadingFilterFlyout({
        categoryId: this.filter.labelTranslationKey,
        isLoading: false,
      })
    },

    updateSelectedTags () {
      if (this.selectedTagIds.length === 0) {
        this.selectedTags = []
        return
      }

      this.syncSelectedItemsFromFlyout()
    },

    updateSelectedTagIds (filter) {
      this.selectedTagIds = Object.values(filter)
        .filter(item => item?.condition?.path === 'tags')
        .map(item => item.condition.value)
    },
  },
}
</script>
