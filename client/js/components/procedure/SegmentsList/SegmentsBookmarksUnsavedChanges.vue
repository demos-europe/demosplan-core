<template>
  <div
    v-if="hasChanges"
    class="px-4 pt-4"
  >
    <dp-inline-notification
      class="mb-2"
      :message="Translator.trans('segments.bookmarks.default.not.overwritable')"
      type="info"
    />

    <dp-accordion
      font-weight="normal"
      compressed
      highlight-toggled-trigger
      icon-left
      icon-weight="fill"
      interactive
      :is-open="isOpen"
      :title="isOpen ? Translator.trans('segments.bookmarks.unsaved.changes.hide') : Translator.trans('segments.bookmarks.unsaved.changes.show')"
      :show-border="false"
      @item:toggle="$emit('toggle', !isOpen)"
    >
      <div
        v-for="change in detailedChanges"
        :key="change.type"
      >
        <p class="font-semibold my-2">
          {{ change.label }}
        </p>

        <!-- Filter details -->
        <div
          v-if="change.type === 'filter' && change.groupedFilterChanges?.length"
          class="border-b border-neutral-light-3 pb-3"
        >
          <div
            v-for="(groupedChange, index) in change.groupedFilterChanges"
            :key="index"
            class="py-1"
          >
            <p class="text-sm text-interactive-secondary font-semibold mb-1">
              {{ groupedChange.category }}
            </p>
            <p
              v-if="groupedChange.removed.length"
              class="mb-0"
            >
              <dp-icon
                icon="minus"
                class="inline-block mr-1 text-status-failed-icon"
              />
              <span>{{ groupedChange.removed.join('; ') }}</span>
            </p>

            <p
              v-if="groupedChange.added.length"
              class="mb-0"
            >
              <dp-icon
                icon="plus"
                class="inline-block mr-1"
              />
              <span>{{ groupedChange.added.join('; ') }}</span>
            </p>
          </div>
        </div>

        <!-- Search/Sorting details -->
        <div
          v-else-if="['search', 'sorting'].includes(change.type)"
          class="border-b border-neutral-light-3 pb-3"
        >
          <p
            v-if="change.oldValue"
            class="mb-0"
          >
            <dp-icon
              icon="minus"
              class="inline-block mr-1 text-status-failed-icon"
            />
            <span>{{ change.oldValue }}</span>
          </p>

          <p
            v-if="change.newValue"
            class="mb-0"
          >
            <dp-icon
              icon="plus"
              class="inline-block mr-1"
            />
            <span>{{ change.newValue }}</span>
          </p>
        </div>

        <!-- Column details -->
        <div
          v-else-if="change.type === 'columns'"
          class="border-b border-neutral-light-3 pb-3"
        >
          <p
            v-if="change.removed?.length"
            class="mb-0"
          >
            <dp-icon
              icon="minus"
              class="inline-block mr-1 text-status-failed-icon"
            />
            <span>{{ change.removed.map(getColumnLabel).join('; ') }}</span>
          </p>

          <p
            v-if="change.added?.length"
            class="mb-0"
          >
            <dp-icon
              icon="plus"
              class="inline-block mr-1"
            />
            <span>{{ change.added.map(getColumnLabel).join('; ') }}</span>
          </p>
        </div>
      </div>
    </dp-accordion>
  </div>
</template>

<script setup lang="ts">
import type {
  Bookmark,
  DetailedChange,
  FilterLabelCache,
  GroupedFilterChange,
  IndividualFilterChange,
} from '@DpJs/types/bookmarks'
import type {
  FilterGroup,
  FilterOption,
  RootState,
} from '@DpJs/types/store'
import {
  DpAccordion,
  DpIcon,
  DpInlineNotification,
} from '@demos-europe/demosplan-ui'
import { computed, type ComputedRef, type Ref, ref, watch } from 'vue'
import { useStore } from 'vuex'

/* Props */
const props = defineProps<{
  currentBookmark: Bookmark | null
  currentSearchPhrase: string
  currentSorting: string
  currentSelectedColumns: string[]
  defaultSelectedColumns: string[]
  filters: Record<string, { labelTranslationKey: string, rootPath: string, comparisonOperator: string }>
  isOpen: boolean
}>()

/* Emits */
interface UnsavedChangesEmits {
  (e: 'toggle', value: boolean): void
  (e: 'hasChanges', value: boolean): void
}

const emit = defineEmits<UnsavedChangesEmits>()

const store = useStore<RootState>()
const state = store.state as RootState

// Cache for filter value labels - accumulates labels so removed options can still be looked up
const filterLabelCache: Ref<FilterLabelCache> = ref({
  unassigned: Translator.trans('not.assigned'),
})

/**
 * Updates the label cache with all labels from the store's filter options.
 * Only adds new labels, never removes existing ones.
 */
const updateLabelCache = (): void => {
  const { groupedOptions = {}, ungroupedOptions = {} } = state.FilterFlyout || {}

  const options = [
    ...Object.values(groupedOptions).flatMap(groups =>
      (groups as FilterGroup[]).flatMap(group => group.options || []),
    ),
    ...Object.values(ungroupedOptions).flatMap(options => options as FilterOption[]),
  ]

  options.forEach(({ id, label }) => {
    if (id && label) {
      filterLabelCache.value[id] = label
    }
  })
}

/* Computed */
const currentFilters: ComputedRef<Record<string, any>> = computed(() => state.FilterFlyout?.filterQuery || {})

const detailedChanges: ComputedRef<DetailedChange[]> = computed(() => {
  if (props.currentBookmark) {
    return getDetailedBookmarkChanges()
  }

  return getDetailedDefaultViewChanges()
})
const hasChanges: ComputedRef<boolean> = computed(() => detailedChanges.value.length > 0)

/* Methods */
const extractFilterValues = (filters: Record<string, any> = {}) => {
  const filterValues = new Set<string>()
  const filterPaths: Record<string, string> = {}

  Object.entries(filters || {}).forEach(([key, filter]) => {
    if (filter.condition) {
      const valueKey = filter.condition.value || 'unassigned'

      filterValues.add(valueKey)
      filterPaths[valueKey] = filter.condition.path
    }
  })

  return {
    filterValues,
    filterPaths,
  }
}

const getDetailedDefaultViewChanges = (): DetailedChange[] => {
  const defaultFilter = {}
  const defaultSearchPhrase = ''
  const defaultSorting = ''

  return [
    getDetailedFilterChanges(defaultFilter, currentFilters.value),
    getDetailedSearchChanges(defaultSearchPhrase, props.currentSearchPhrase),
    getDetailedSortingChanges(defaultSorting, props.currentSorting),
    getDetailedColumnChanges(props.defaultSelectedColumns, props.currentSelectedColumns),
  ].filter((change): change is DetailedChange => change !== null)
}

const getDetailedBookmarkChanges = (): DetailedChange[] => {
  if (!props.currentBookmark) {
    return []
  }

  const { filter, searchPhrase, viewSettings } = props.currentBookmark.attributes

  return [
    getDetailedFilterChanges(filter, currentFilters.value),
    getDetailedSearchChanges(searchPhrase, props.currentSearchPhrase),
    getDetailedSortingChanges(viewSettings.sorting, props.currentSorting),
    getDetailedColumnChanges(viewSettings.selectedColumns, props.currentSelectedColumns),
  ].filter((change): change is DetailedChange => change !== null)
}

const getDetailedColumnChanges = (bookmarkColumns: string[] = [], currentColumns: string[] = []): DetailedChange | null => {
  const added = currentColumns.filter(col => !bookmarkColumns.includes(col))
  const removed = bookmarkColumns.filter(col => !currentColumns.includes(col))

  if (!added.length && !removed.length) {
    return null
  }

  return {
    type: 'columns',
    label: Translator.trans('columns'),
    added,
    removed,
  }
}

const getDetailedFilterChanges = (bookmarkFilters: Record<string, any>, currentFilters: Record<string, any>): DetailedChange | null => {
  const {
    filterValues: bookmarkFilterValues,
    filterPaths: bookmarkFilterPaths,
  } = extractFilterValues(bookmarkFilters)

  const {
    filterValues: currentFilterValues,
    filterPaths: currentFilterPaths,
  } = extractFilterValues(currentFilters)

  const individualFilterChanges: IndividualFilterChange[] = [
    ...getFilterChanges(
      bookmarkFilterValues,
      currentFilterValues,
      bookmarkFilterPaths,
      'removed',
    ),
    ...getFilterChanges(
      currentFilterValues,
      bookmarkFilterValues,
      currentFilterPaths,
      'added',
    ),
  ]

  if (!individualFilterChanges.length) {
    return null
  }

  const groupedByCategory: Record<string, GroupedFilterChange> = {}

  individualFilterChanges.forEach(change => {
    if (!groupedByCategory[change.filterCategory]) {
      groupedByCategory[change.filterCategory] = {
        category: change.filterCategory,
        removed: [],
        added: [],
      }
    }

    if (change.action === 'removed') {
      groupedByCategory[change.filterCategory].removed.push(change.filterValue)
    } else {
      groupedByCategory[change.filterCategory].added.push(change.filterValue)
    }
  })

  const groupedFilterChanges: GroupedFilterChange[] = Object.values(groupedByCategory)

  return {
    type: 'filter',
    label: Translator.trans('filter'),
    groupedFilterChanges,
  }
}

const getDetailedSearchChanges = (bookmarkSearch: string = '', currentSearch: string = ''): DetailedChange | null => {
  const oldValue = bookmarkSearch.trim()
  const newValue = currentSearch.trim()

  if (oldValue === newValue) {
    return null
  }

  return {
    type: 'search',
    label: Translator.trans('search'),
    oldValue: oldValue || Translator.trans('none'),
    newValue: newValue || Translator.trans('none'),
  }
}

const getDetailedSortingChanges = (bookmarkSorting: string = '', currentSorting: string = ''): DetailedChange | null => {
  if (bookmarkSorting === currentSorting) {
    return null
  }

  const sortingLabels = {
    'deadline-desc': Translator.trans('sort.deadline.descending'),
    'deadline-asc': Translator.trans('sort.deadline.ascending'),
    'externId-desc': Translator.trans('sort.externId.descending'),
    'externId-asc': Translator.trans('sort.externId.ascending'),
    'internId-desc': Translator.trans('sort.internId.descending'),
    'internId-asc': Translator.trans('sort.internId.ascending'),
    'submitName-desc': Translator.trans('sort.submitName.descending'),
    'submitName-asc': Translator.trans('sort.submitName.ascending'),
    'place-desc': Translator.trans('sort.place.descending'),
    'place-asc': Translator.trans('sort.place.ascending'),
  }

  return {
    type: 'sorting',
    label: Translator.trans('sorting'),
    oldValue: bookmarkSorting ? (sortingLabels[bookmarkSorting] || bookmarkSorting) : Translator.trans('none'),
    newValue: currentSorting ? (sortingLabels[currentSorting] || currentSorting) : Translator.trans('none'),
  }
}

const getFilterChanges = (sourceValues: Set<string>, targetValues: Set<string>, filterPaths: Record<string, string>, action: 'added' | 'removed'): IndividualFilterChange[] => {
  return [...sourceValues]
    .filter(valueKey => !targetValues.has(valueKey))
    .map(valueKey => ({
      filterCategory: getFilterNameByPath(filterPaths[valueKey]),
      filterValue: getFilterValueLabel(valueKey),
      action,
    }))
}

const getColumnLabel = (fieldName: string): string => {
  const columnTranslations: Record<string, string> = {
    externId: 'id',
    statementStatus: 'statement.status',
    internId: 'internId.shortened',
    deadline: 'deadline',
    submitter: 'submitter',
    organisation: 'organisation',
    text: 'text',
    recommendation: 'segment.recommendation',
    tags: 'segment.tags',
    place: 'workflow.place',
  }

  return Translator.trans(columnTranslations[fieldName] || fieldName)
}

const getFilterNameByPath = (filterPath: string): string => {
  const filterConfig = Object.values(props.filters || {}).find(
    filter => filter.rootPath === filterPath,
  )

  if (filterConfig) {
    return Translator.trans(filterConfig.labelTranslationKey)
  }

  return Translator.trans(filterPath)
}

/**
 * Looks up the label for a filter value.
 * First checks the label cache, then searches the store's options.
 */
const getFilterValueLabel = (valueId: string): string => {
  const cachedLabel = filterLabelCache.value[valueId]

  if (cachedLabel !== undefined) {
    return cachedLabel
  }

  const { groupedOptions = {}, ungroupedOptions = {} } = state.FilterFlyout || {}

  const grouped = Object.values(groupedOptions)
    .flatMap(groups => (groups as FilterGroup[]).flatMap(group => group.options || []))

  const ungrouped = Object.values(ungroupedOptions)
    .flatMap(options => options as FilterOption[])

  const label = [...grouped, ...ungrouped]
    .find(option => option.id === valueId)?.label

  if (label !== undefined) {
    filterLabelCache.value[valueId] = label
  }

  return label ?? valueId
}

/* Watchers */
watch(
  () => [state.FilterFlyout?.groupedOptions, state.FilterFlyout?.ungroupedOptions],
  () => updateLabelCache(),
  { deep: true },
)

watch(hasChanges, (value) => {
  emit('hasChanges', value)
}, { immediate: true })
</script>
