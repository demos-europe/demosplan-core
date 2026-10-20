<template>
  <div
    v-show="isVisible"
    class="-ml-5 flex h-full flex-col"
  >
    <!-- Header -->
    <div class="border-b-2 border-neutral-light-3 px-4 py-2">
      <h2>
        {{ Translator.trans('views') }}
      </h2>
    </div>

    <!-- Content -->

    <!-- Unsaved changes -->
    <div
      v-if="currentViewChanges.length"
      class="px-4 pt-4"
    >
      <dp-inline-notification
        class="mb-2"
        message="Da die Standardansicht nicht überschreibbar ist, speichern Sie die Änderungen als neue Ansicht oder verwerfen Sie sie."
        type="info"
      />

      <dp-accordion
        :is-open="showUnsavedChanges"
        :title="showUnsavedChanges ? 'Ungespeicherte Änderungen ausblenden' : 'Ungespeicherte Änderungen anzeigen'"
        font-weight="normal"
        compressed
        highlight-toggled-trigger
        @item:toggle="showUnsavedChanges = !showUnsavedChanges"
      >
        <div class="py-2">
          <div
            v-for="change in currentDetailedChanges"
            :key="change.type"
            class=""
          >
            <p class="font-semibold my-2">
              {{ change.label }}
            </p>

            <!-- Filter details -->
            <div
              v-if="change.type === 'filter' && change.groupedFilterChanges"
              class="border-b border-neutral-light-3 pb-2"
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
              v-if="(change.type === 'search' || change.type === 'sorting') && change.oldValue"
              class="border-b border-neutral-light-3 pb-2"
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
              v-if="change.type === 'columns'"
              class="border-b border-neutral-light-3 pb-2"
            >
              <p
                v-if="change.removed && change.removed.length"
                class="mb-0"
              >
                <dp-icon
                  icon="minus"
                  class="inline-block mr-1 text-status-failed-icon"
                />
                <span>{{ change.removed.map(col => getColumnLabel(col)).join('; ') }}</span>
              </p>
              <p
                v-if="change.added && change.added.length"
                class="mb-0"
              >
                <dp-icon
                  icon="plus"
                  class="inline-block mr-1"
                />
                <span>{{ change.added.map(col => getColumnLabel(col)).join('; ') }}</span>
              </p>
            </div>
          </div>
        </div>
      </dp-accordion>
    </div>

    <div class="p-4">
      <p class="font-semibold">
        {{ Translator.trans('segments.view.saved') }}
      </p>
      <dp-radio
        id="defaultBookmark"
        class="border my-2 p-1"
        :class="currentBookmarkId === null ? 'border-blue-base' : 'border-neutral-light-3'"
        :checked="currentBookmarkId === null"
        :disabled="isApplyingBookmark"
        :label="{
          text: Translator.trans('segments.view.default'),
          hint: Translator.trans('segments.view.default.description')
        }"
        @change="handleViewChange(null)"
      />
      <div
        v-for="bookmark in bookmarks"
        :key="bookmark.id"
        class="flex justify-between border my-2 p-1"
        :class="bookmark.id === currentBookmarkId ? 'border-blue-base' : 'border-neutral-light-3'"
      >
        <dp-radio
          :id="bookmark.id"
          :checked="bookmark.id === currentBookmarkId"
          :disabled="isApplyingBookmark"
          :label="{
            text: bookmark.attributes.name,
            hint: getBookmarkSummary(bookmark.id),
          }"
          @change="handleViewChange(bookmark.id)"
        />
        <dp-button
          color="primary"
          data-cy="segmentsBookmarks:delete"
          hide-text
          icon="delete"
          :text="Translator.trans('delete')"
          variant="subtle"
          @click="deleteBookmark(bookmark.id)"
        />
      </div>

      <span
        v-if="!currentViewChanges.length"
        class="text-sm"
      >
        {{ Translator.trans('segments.view.none.saved') }}
      </span>

      <div
        v-if="currentViewChanges.length && shouldNewBookmarkCreated"
        class="border-t border-neutral-light-3 my-4"
      >
        <span class="pt-4 block font-semibold mb-3">
          {{ Translator.trans('segments.view.new.save') }}
        </span>
        <dp-input
          id="newBookmarkName"
          v-model="newBookmarkName"
          data-cy="segmentsBookmarks:newName"
          :label="{
            text: Translator.trans('segments.view.name'),
            tooltip: Translator.trans('segments.view.name.tooltip')
          }"
        />
      </div>
    </div>

    <!-- Footer -->
    <div class="border-t-2 border-neutral-light-3 w-full mt-auto flex justify-end p-4">
      <dp-button
        v-if="currentViewChanges.length && !shouldNewBookmarkCreated"
        data-cy="segmentsBookmarks:abort"
        :text="'Änderung verwerfen'"
        variant="outline"
        @click="abort"
      />
      <dp-button
        v-if="currentViewChanges.length && !shouldNewBookmarkCreated"
        data-cy="segmentsBookmarks:abort"
        :text="'Als neue Ansicht speichern'"
        @click="shouldNewBookmarkCreated = true"
      />

      <dp-button
        v-if="currentBookmarkId && currentViewChanges.length && !shouldNewBookmarkCreated"
        data-cy="segmentsBookmarks:abort"
        :text="'Änderung übernehmen'"
        variant="outline"
        @click="handleUpdateBookmark"
      />

      <dp-button-row
        v-if="currentViewChanges.length && shouldNewBookmarkCreated"
        primary
        secondary
        :disabled="newBookmarkName"
        data-cy="segmentsBookmarks:save"
        @primary-action="handlePrimaryAction"
        @secondary-action="abort"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type {
  Bookmark,
  CreateBookmarkPayload,
  DetailedChange,
  FilterLabelCache,
  GroupedFilterChange,
  IndividualFilterChange,
  UpdateBookmarkPayload,
} from '@DpJs/types/bookmarks'
import type {
  FilterGroup,
  FilterOption,
  RootState,
  Slidebar,
} from '@DpJs/types/store'
import {
  DpAccordion,
  DpButton,
  DpButtonRow,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpRadio,
} from '@demos-europe/demosplan-ui'
import { computed, type ComputedRef, onMounted, type Ref, ref, watch } from 'vue'
import { useStore } from 'vuex'
import lscache from 'lscache'

/* Props */
const props = withDefaults(defineProps<{
  procedureId: string
  totalColumnCount?: number
  currentSearchPhrase?: string
  currentSorting?: string
  currentSelectedColumns?: string[]
  defaultSelectedColumns?: string[]
  filters?: Record<string, { labelTranslationKey: string, rootPath: string, comparisonOperator: string }>
}>(), {
  totalColumnCount: 0,
  currentSearchPhrase: '',
  currentSorting: '',
  currentSelectedColumns: () => [],
  defaultSelectedColumns: () => [],
  filters: () => ({}),
})

const store = useStore<RootState>()
const state = store.state as RootState

const newBookmarkName: Ref<string> = ref('')
const showUnsavedChanges: Ref<boolean> = ref(false)
const lsKey = `${props.procedureId}:segments:currentBookmarkId`
const shouldNewBookmarkCreated: Ref<boolean> = ref(false)

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

// Watch store options and update cache when they change
watch(
  () => [state.FilterFlyout?.groupedOptions, state.FilterFlyout?.ungroupedOptions],
  () => updateLabelCache(),
  { deep: true },
)

/* Computed */
const bookmarks: ComputedRef<Bookmark[]> = computed(() => state.SegmentsBookmarks.allBookmarks)
const currentBookmarkId: ComputedRef<string | null> = computed(() => state.SegmentsBookmarks.currentBookmarkId)
const currentQueryHash: ComputedRef<string> = computed(() => state.SegmentSlidebar.currentQueryHash)

const slidebar: ComputedRef<Slidebar> = computed(() => state.SegmentSlidebar.slidebar)
const isVisible: ComputedRef<boolean> = computed(() => slidebar.value.showTab === 'bookmarks')
const isApplyingBookmark: ComputedRef<boolean> = computed(() => state.SegmentsBookmarks.isApplyingBookmark)

const currentViewChanges: ComputedRef<string[]> = computed(() => {
  if (currentBookmarkId.value) {
    return getBookmarkChangeLabels(currentBookmarkId.value)
  }

  return getDefaultViewChangeLabels()
})

const currentDetailedChanges: ComputedRef<DetailedChange[]> = computed(() => {
  if (currentBookmarkId.value) {
    return getDetailedBookmarkChanges(currentBookmarkId.value)
  }

  // For default view, get detailed changes from the default state
  return getDetailedDefaultViewChanges()
})

/* Store actions */
const createBookmark = (payload: CreateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/createBookmark', payload)
const deleteBookmark = (id: string): Promise<boolean> => store.dispatch('SegmentsBookmarks/deleteBookmark', id)
const fetchBookmarks = (): Promise<Bookmark[]> => store.dispatch('SegmentsBookmarks/fetchBookmarks')
const updateBookmark = (payload: UpdateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/updateBookmark', payload)

/* Emits */
interface SegmentsBookmarksEmits {
  (e: 'applyBookmark', bookmark: Bookmark): void
  (e: 'resetView'): void
  (e: 'updateButtonText', text: string): void
}

const emit = defineEmits<SegmentsBookmarksEmits>()

/* Methods */
const abort = async (): Promise<void> => {
  if (currentBookmarkId.value) {
    await applySelectedBookmark(currentBookmarkId.value)
  } else {
    await setDefaultBookmark()
  }
}

const applySelectedBookmark = async (bookmarkId): Promise<void> => {
  const selectedBookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!selectedBookmark) {
    return
  }

  const success = await store.dispatch('SegmentsBookmarks/applyBookmark', {
    bookmarkId,
    bookmark: selectedBookmark,
  })

  if (!success) {
    return
  }

  lscache.set(lsKey, bookmarkId)
  updateBookmarkButtonText(selectedBookmark)
  emit('applyBookmark', selectedBookmark)
}

const createNewBookmark = async (): Promise<void> => {
  const name = newBookmarkName.value.trim()
  const queryHash = currentQueryHash.value

  if (!name || !queryHash) {
    return
  }

  const payload: CreateBookmarkPayload = {
    type: 'Bookmark',
    attributes: {
      name,
      queryHash,
    },
  }

  const success = await createBookmark(payload)

  if (success) {
    newBookmarkName.value = ''
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
  }
}

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

const getBookmarkChangeLabels = (bookmarkId: string): string[] => getDetailedBookmarkChanges(bookmarkId).map(({ label }) => label)

const getDefaultViewChangeLabels = (): string[] => getDetailedDefaultViewChanges().map(({ label }) => label)

/**
 * Get detailed changes comparing current state against the default view.
 * Default view has: no filters, no search phrase, default sorting, default columns.
 */
const getDetailedDefaultViewChanges = (): DetailedChange[] => {
  const currentFilters = state.FilterFlyout?.filterQuery || {}

  // Default view has empty filters, empty search, empty sorting, but specific default columns
  const defaultFilter = {}
  const defaultSearchPhrase = ''
  const defaultSorting = ''

  return [
    getDetailedFilterChanges(defaultFilter, currentFilters),
    getDetailedSearchChanges(defaultSearchPhrase, props.currentSearchPhrase),
    getDetailedSortingChanges(defaultSorting, props.currentSorting),
    getDetailedColumnChanges(props.defaultSelectedColumns, props.currentSelectedColumns),
  ].filter((change): change is DetailedChange => change !== null)
}

const getBookmarkSummary = (bookmarkId: string): string => {
  const bookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!bookmark) {
    return ''
  }

  const { filter, searchPhrase, viewSettings } = bookmark.attributes
  const filterCount = Object.keys(filter).length
  const selectedColumnCount = viewSettings.selectedColumns?.length || 0 // ToDo: what if there are no selected column

  const filterText = `${filterCount} ${Translator.trans('filter')}`

  const searchText = searchPhrase?.trim() ? 'Suchbegriff' : ''

  const columnText = props.totalColumnCount > 0 ?
    `${selectedColumnCount} von ${props.totalColumnCount} ${Translator.trans('columns')}` :
    `${selectedColumnCount} ${Translator.trans('columns')}`

  const summary = [
    filterText,
    searchText,
    columnText,
  ].filter(Boolean).join(' · ')

  return summary
}

const getDetailedBookmarkChanges = (bookmarkId: string): DetailedChange[] => {
  const bookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!bookmark) {
    return []
  }

  const { filter, searchPhrase, viewSettings } = bookmark.attributes
  const currentFilters = state.FilterFlyout?.filterQuery || {}

  return [
    getDetailedFilterChanges(filter, currentFilters),
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

  // Group changes by filter category
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
    'internId-desc': Translator.trans('sort.internId.descending'),
    'internId-asc': Translator.trans('sort.internId.ascending'),
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

const handlePrimaryAction = async (): Promise<void> => {
  await createNewBookmark()
  shouldNewBookmarkCreated.value = false

/*  if (newBookmarkName.value) {
    await createNewBookmark()
  } else {
    await handleUpdateBookmark()
  }*/
}

const handleViewChange = async (bookmarkId: string | null): Promise<void> => {
  shouldNewBookmarkCreated.value = false

  if (bookmarkId === null) {
    await setDefaultBookmark()

    return
  }

  await applySelectedBookmark(bookmarkId)
}

const restoreSavedBookmark = async (): Promise<void> => {
  const savedBookmarkId: string | null = lscache.get(lsKey)

  // If no bookmark was saved, apply the default view to clear all settings
  if (!savedBookmarkId) {
    emit('resetView')

    return
  }

  const savedBookmark: Bookmark | undefined = bookmarks.value.find(bookmark => bookmark.id === savedBookmarkId)

  if (!savedBookmark) {
    lscache.remove(lsKey)
    emit('resetView')

    return
  }

  const success: boolean = await store.dispatch('SegmentsBookmarks/applyBookmark', {
    bookmarkId: savedBookmarkId,
    bookmark: savedBookmark,
  })

  if (success) {
    updateBookmarkButtonText(savedBookmark)
    emit('applyBookmark', savedBookmark)
  }
}

const setDefaultBookmark = (): void => {
  store.dispatch('SegmentsBookmarks/resetToDefaultView')

  lscache.remove(lsKey)
  updateBookmarkButtonText(null)
  emit('resetView')
}

const handleUpdateBookmark = async (): Promise<void> => {
  const bookmarkId = currentBookmarkId.value
  const queryHash = currentQueryHash.value

  if (!bookmarkId || !queryHash) {
    return
  }

  const payload: UpdateBookmarkPayload = {
    id: bookmarkId,
    type: 'Bookmark',
    attributes: {
      queryHash,
    },
  }

  const success = await updateBookmark(payload)

  if (success) {
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
  }
}

const updateBookmarkButtonText = (bookmark: Bookmark | null): void => {
  const buttonText = bookmark?.attributes?.name || Translator.trans('views')

  emit('updateButtonText', buttonText)
}

onMounted(async (): Promise<void> => {
  await fetchBookmarks()
  await restoreSavedBookmark()
})
</script>

