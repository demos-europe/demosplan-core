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
      content-body-classes="flex flex-col h-[95%]"
    >
      <h2 class="mb-5">
        {{ exportModalTitle }}
      </h2>

      <fieldset v-if="!isSingleStatementExport">
        <legend
          class="o-form__label text-base"
          v-text="Translator.trans('export.type')"
        />
        <div class="grid grid-cols-3 mt-2 mb-5 gap-x-2 gap-y-5">
          <dp-radio
            v-for="(exportType, key) in exportTypes"
            :id="key"
            :key="key"
            :data-cy="`exportType:${key}`"
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
              :label="{
                text: Translator.trans('export.censored.citizen')
              }"
            />
            <dp-checkbox
              id="censoredInstitution"
              v-model="isInstitutionDataCensored"
              :label="{
                text: Translator.trans('export.censored.institution')
              }"
            />
            <dp-checkbox
              id="obscured"
              v-model="isObscure"
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
            :label="{
              text: Translator.trans('export.censored.citizen')
            }"
          />
          <dp-checkbox
            id="singleStatementInstitution"
            v-model="isInstitutionDataCensored"
            :label="{
              text: Translator.trans('export.censored.institution')
            }"
          />
          <dp-checkbox
            id="singleStatementObscure"
            v-model="isObscure"
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
        <div class="grid grid-cols-5 gap-3 mt-1 mb-5">
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

      <filter-flyout
        ref="filterFlyout"
        :style="dropdownStyle"
        :key="`filter_${filter.labelTranslationKey}`"
        :additional-query-params="{ searchPhrase: searchTerm }"
        :category="{ id: `${filter.labelTranslationKey}`, label: Translator.trans(filter.labelTranslationKey) }"
        class="dropdown inline-block first:mr-1"
        :data-cy="`statementExportModal:${filter.labelTranslationKey}`"
        :operator="filter.comparisonOperator"
        :path="filter.rootPath"
        :show-count="{
          groupedOptions: true,
          ungroupedOptions: true
        }"
        @filter-apply="getFilterValues"
        @filter-options:request="(params) => sendFilterOptionsRequest({ ...params, category: { id: `${filter.labelTranslationKey}`, label: Translator.trans(filter.labelTranslationKey) }})"
      />

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
  sessionStorageMixin
} from '@demos-europe/demosplan-ui'
import FilterFlyout from '../procedure/SegmentsList/FilterFlyout.vue'
import { mapMutations } from 'vuex'

export default {
  name: 'StatementExportModal',

  components: {
    FilterFlyout,
    DpButton,
    DpButtonRow,
    DpCheckbox,
    DpContextualHelp,
    DpInput,
    DpModal,
    DpRadio,
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
      searchTerm: '',
      selectedTags: [],
      filter: {
        comparisonOperator: "ARRAY_CONTAINS_VALUE",
        grouping: {
          labelTranslationKey: 'topic',
          targetPath: 'tags.topic.label'
        },
        labelTranslationKey: 'tags',
        rootPath: 'tags',
        selected: false
      },
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
      isInstitutionDataCensored: false,
      isCitizenDataCensored: false,
      isObscure: false,
      singleStatementExportPath: 'dplan_segments_export', /** Used in the statements detail page */
    }
  },

  computed: {
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
      setInitialFlyoutFilterIds: 'setInitialFlyoutFilterIds',
      setIsLoadingFilterFlyout: 'setIsLoading',
      setGroupedFilterOptions: 'setGroupedOptions',
      setUngroupedFilterOptions: 'setUngroupedOptions',
    }),

    getFilterValues (filter) {
      this.selectedTagIds = Object.values(filter)
        .filter(el => el?.condition?.path === 'tags')
        .map(el => el.condition.value)
    },

    buildFilterOption (option) {
      if (!option) {
        return null
      }

      const { attributes, id } = option
      const { count, description, label, selected } = attributes

      return { id, count, description, label, selected }
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
      const {
        additionalQueryParams,
        category,
        filter,
        isInitialWithQuery,
        path,
        currentQuery,
      } = params

      if (currentQuery && currentQuery.length > 0) {
        return
      }

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

      // We have to set the searchPhrase to null if its empty to satisfy the backend
      if (requestParams.searchPhrase === '') {
        requestParams.searchPhrase = null
      }

      dpRpc('segments.facets.list', requestParams, 'filterList')
        .then(({ data }) => {
          const result = (hasOwnProp(data, 0) && data[0].id === 'filterList') ? data[0].result : null
          if (!result) {
            return
          }

          const filter = result.data.find(type => type.attributes.path === path)
          if (!filter) {
            return
          }

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

          // Needs to be added to ungroupedOptions
          if (result.data[0].attributes.path === 'assignee') {
            ungroupedOptions.push({
              id: 'unassigned',
              count: result.data[0].attributes.missingResourcesSum,
              label: Translator.trans('not.assigned'),
              ungrouped: true,
              selected: result.meta.unassigned_selected,
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
              filterIds: currentFlyoutFilterIds,
            })
          }

          this.setGroupedFilterOptions({
            categoryId: category.id,
            groupedOptions,
          })

          this.setUngroupedFilterOptions({
            categoryId: category.id,
            options: ungroupedOptions,
          })

          this.setIsLoadingFilterFlyout({ categoryId: category.id, isLoading: false })

          if (this.getIsExpandedByCategoryId(category.id)) {
            document.getElementById(`searchField_${path}`).focus()
          }
      })
    },

    closeModal () {
      this.selectedTagIds = []
      this.$refs.filterFlyout.reset()
      this.$refs.exportModalInner.toggle()
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
        route: this.isSingleStatementExport ? this.singleStatementExportPath : this.exportTypes[this.active].exportPath,
        docxHeaders: ['docx_normal', 'zip_normal'].includes(this.active) ? columnTitles : null,
        fileNameTemplate: this.fileName || null,
        shouldConfirm,
        isInstitutionDataCensored: this.isInstitutionDataCensored,
        isCitizenDataCensored: this.isCitizenDataCensored,
        isObscured: this.isObscure,
        tagFilterIds: this.selectedTagIds
      })
      this.closeModal()
    },

    openModal () {
      this.setInitialValues()
      this.$refs.exportModalInner.toggle()
    },

    setInitialValues () {
      this.active = 'docx_normal'

      Object.keys(this.docxColumns).forEach(key => {
        const storageKey = `exportModal:docxCol:${key}`
        const storedColumnTitle = this.getItemFromSessionStorage(storageKey)
        this.docxColumns[key].title = storedColumnTitle || null /** Setting the value to null will display the placeholder titles of the column */
      })
    },
  },
}
</script>
