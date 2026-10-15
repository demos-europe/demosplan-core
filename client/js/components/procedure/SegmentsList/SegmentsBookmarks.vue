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
    <div v-if="currentViewChanges.length">
      <dp-inline-notification
        class="mt-3 mb-2"
        message="Da die Standardansicht nicht überschreibbar ist, speichern Sie die Änderungen als neue Ansicht oder verwerfen Sie sie."
        type="info"
      />

      <dp-accordion
        :is-open="showUnsavedChanges"
        title="Ungespeicherte Änderungen anzeigen"
        compressed
        @item:toggle="showUnsavedChanges = !showUnsavedChanges"
      >
        <div class="space-y-3 p-4">
          <div
            v-for="change in currentDetailedChanges"
            :key="change.type"
            class=""
          >
            <p class="font-semibold text-sm mb-2">
              {{ change.label }}
            </p>

            <!-- Filter details -->
            <div
              v-if="change.type === 'filter' && change.individualFilters"
              class="border-b border-neutral-light-3"
            >
              <div
                v-for="(filterChange, index) in change.individualFilters"
                :key="index"
                class="text-sm py-1"
              >
                <p :class="filterChange.action === 'removed' ? 'text-status-failed-icon' : ''">
                  <dp-icon
                    :icon="filterChange.action === 'added' ? 'plus' : 'minus'"
                    :class="['inline-block', 'mr-1', filterChange.action === 'removed' ? 'text-status-failed-icon' : '']"
                  />
                  <span>
                    {{ Translator.trans('filter') }} &gt; {{ filterChange.filterCategory }} &gt; {{ filterChange.filterValue }}
                  </span>
                </p>
              </div>
            </div>

            <!-- Search/Sorting details -->
            <div
              v-if="(change.type === 'search' || change.type === 'sorting') && change.oldValue !== undefined"
              class="border-b border-neutral-light-3 "
            >
              <p>
                <dp-icon
                  icon="minus"
                  class="inline-block text-status-failed-icon mr-1"
                />
                <span>{{ change.oldValue }}</span>
              </p>
              <p>
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
              class="border-b border-neutral-light-3 "
            >
              <div v-if="change.added && change.added.length > 0">
                <ul>
                  <li
                    v-for="col in change.added"
                    :key="col"
                    class="mb-1"
                  >
                    <dp-icon
                      icon="plus"
                      class="inline-block mr-1"
                    />
                    <span>{{ Translator.trans(col) }}</span>
                  </li>
                </ul>
              </div>

              <div v-if="change.removed && change.removed.length > 0">
                <ul>
                  <li
                    v-for="col in change.removed"
                    :key="col"
                    class="mb-1"
                  >
                    <dp-icon
                      icon="minus"
                      class="inline-block text-status-failed-icon mr-1"
                    />
                    <span>{{ Translator.trans(col) }}</span>
                  </li>
                </ul>
              </div>
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
        v-if="!bookmarks.length"
        class="text-sm"
      >
        {{ Translator.trans('segments.view.none.saved') }}
      </span>

      <div class="border-t border-neutral-light-3 my-4">
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
      <dp-button-row
        primary
        secondary
        data-cy="segmentsBookmarks:save"
        @primary-action="createNewBookmark"
        @secondary-action="abort"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  DpAccordion,
  DpButton,
  DpButtonRow,
  DpIcon,
  DpInlineNotification,
  DpInput,
  DpRadio,
} from '@demos-europe/demosplan-ui'
import type {
  Bookmark,
  CreateBookmarkPayload,
  DetailedChange,
  IndividualFilterChange,
  Slidebar,
  UpdateBookmarkPayload,
} from '@DpJs/types/bookmarks'

import { computed, type ComputedRef, onMounted, type Ref, ref, watch } from 'vue'
import lscache from 'lscache'
import { useStore } from 'vuex'

/* Interfaces */
interface FilterOption {
  id: string
  label: string
}

interface FilterGroup {
  options?: FilterOption[]
}

/* Props */
const props = withDefaults(defineProps<{
  procedureId: string
  totalColumnCount?: number
  currentSearchPhrase?: string
  currentSorting?: string
  currentSelectedColumns?: string[]
  filters?: Record<string, { labelTranslationKey: string, rootPath: string, comparisonOperator: string }>
}>(), {
  totalColumnCount: 0,
  currentSearchPhrase: '',
  currentSorting: '',
  currentSelectedColumns: () => [],
  filters: () => ({}),
})

const store = useStore()

const newBookmarkName: Ref<string> = ref('')
const showUnsavedChanges: Ref<boolean> = ref(false)
const lsKey = `${props.procedureId}:segments:currentBookmarkId`

// Cache for filter value labels - accumulates labels so removed options can still be looked up
const filterLabelCache: Ref<Record<string, string>> = ref({
  unassigned: Translator.trans('not.assigned'),
})

/**
 * Updates the label cache with all labels from the store's filter options.
 * Only adds new labels, never removes existing ones.
 */
const updateLabelCache = (): void => {
  const allGroupedOptions = store.state.FilterFlyout?.groupedOptions || {}
  const allUngroupedOptions = store.state.FilterFlyout?.ungroupedOptions || {}

  // Add labels from grouped options
  for (const groups of Object.values(allGroupedOptions)) {
    for (const group of (groups as FilterGroup[])) {
      group.options?.forEach(option => {
        if (option.id && option.label) {
          filterLabelCache.value[option.id] = option.label
        }
      })
    }
  }

  // Add labels from ungrouped options
  for (const options of Object.values(allUngroupedOptions)) {
    (options as FilterOption[]).forEach(option => {
      if (option.id && option.label) {
        filterLabelCache.value[option.id] = option.label
      }
    })
  }
}

// Watch store options and update cache when they change
watch(
  () => [store.state.FilterFlyout?.groupedOptions, store.state.FilterFlyout?.ungroupedOptions],
  () => updateLabelCache(),
  { deep: true },
)

/* Computed */
const bookmarks: ComputedRef<Bookmark[]> = computed(() => store.state.SegmentsBookmarks.allBookmarks)
const currentBookmark: ComputedRef<Bookmark | null> = computed(() => store.state.SegmentsBookmarks.currentBookmark)
const currentBookmarkId: ComputedRef<string | null> = computed(() => store.state.SegmentsBookmarks.currentBookmarkId)
const currentQueryHash: ComputedRef<string> = computed(() => store.state.SegmentSlidebar.currentQueryHash)

const slidebar: ComputedRef<Slidebar> = computed(() => store.state.SegmentSlidebar.slidebar)
const isVisible: ComputedRef<boolean> = computed(() => slidebar.value.showTab === 'bookmarks')
const isApplyingBookmark: ComputedRef<boolean> = computed(() => store.state.SegmentsBookmarks.isApplyingBookmark)

const currentViewChanges: ComputedRef<string[]> = computed(() => {
  if (!currentBookmarkId.value) {
    return []
  }

  return getBookmarkChanges(currentBookmarkId.value)
})

const changesMessage: ComputedRef<string> = computed(() => {
  if (currentViewChanges.value.length === 0) {
    return ''
  }

  return `${Translator.trans('segments.view.changes')}: ${currentViewChanges.value.join(', ')}`
})

const currentDetailedChanges: ComputedRef<DetailedChange[]> = computed(() => {
  if (!currentBookmarkId.value) {
    return []
  }

  return getDetailedBookmarkChanges(currentBookmarkId.value)
})

/* Store actions */
const createBookmark = (payload: CreateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/createBookmark', payload)
const deleteBookmark = (id: string): Promise<boolean> => store.dispatch('SegmentsBookmarks/deleteBookmark', id)
const fetchBookmarks = (): Promise<Bookmark[]> => store.dispatch('SegmentsBookmarks/fetchBookmarks')
const updateBookmark = (payload: UpdateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/updateBookmark', payload)

const getGroupedOptions = (category) => store.getters['FilterFlyout/getGroupedOptionsByCategoryId'](category.id) || []

const getUngroupedOptions = (category) => store.getters['FilterFlyout/getUngroupedOptionsByCategoryId'](category.id) || []

/* Emits */
interface SegmentsBookmarksEmits {
  (e: 'apply-bookmark', bookmark: Bookmark): void
  (e: 'reset-view'): void
  (e: 'update-button-text', text: string): void
}

const emit = defineEmits<SegmentsBookmarksEmits>()

/* Methods */
const abort = (): void => {
  console.log('Abort') // Todo: adjust method
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
  emit('apply-bookmark', selectedBookmark)
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

const getBookmarkChanges = (bookmarkId: string): string[] => getDetailedBookmarkChanges(bookmarkId).map(({ label }) => label)

const getBookmarkSummary = (bookmarkId: string): string => {
  const bookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!bookmark) {
    return ''
  }

  const { filter, searchPhrase, viewSettings } = bookmark.attributes
  const filterCount = Object.keys(filter).length
  const selectedColumnCount = viewSettings.selectedColumns.length

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

  const { groupedOptions = {}, ungroupedOptions = {} } = store.state.FilterFlyout || {}

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

const getDetailedColumnChanges = (
  bookmarkColumns: string[] = [],
  currentColumns: string[] = [],
): DetailedChange | null => {
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

  return {
    type: 'filter',
    label: Translator.trans('filter'),
    individualFilters: individualFilterChanges,
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
  if (bookmarkSorting === currentSorting) return null

  return {
    type: 'sorting',
    label: Translator.trans('sorting'),
    oldValue: bookmarkSorting || Translator.trans('none'),
    newValue: currentSorting || Translator.trans('none'),
  }
}

const getDetailedBookmarkChanges = (bookmarkId: string): DetailedChange[] => {
  const bookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!bookmark) return []

  const { filter, searchPhrase, viewSettings } = bookmark.attributes
  const currentFilters = store.state.FilterFlyout?.filterQuery || {}

  return [
    getDetailedFilterChanges(filter, currentFilters),
    getDetailedSearchChanges(searchPhrase, props.currentSearchPhrase),
    getDetailedSortingChanges(viewSettings.sorting, props.currentSorting),
    getDetailedColumnChanges(viewSettings.selectedColumns, props.currentSelectedColumns),
  ].filter((change): change is DetailedChange => change !== null)
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
const handleViewChange = async (bookmarkId: string | null): Promise<void> => {
  if (bookmarkId === null) {
    await setDefaultBookmark()

    return
  }

  await applySelectedBookmark(bookmarkId)
}

const setDefaultBookmark = async (): Promise<void> => {
  const success = await store.dispatch('SegmentsBookmarks/resetToDefaultView')

  if (!success) {
    return
  }

  lscache.remove(lsKey)
  updateBookmarkButtonText(null)
  emit('reset-view')
}

const updateBookmarkButtonText = (bookmark: Bookmark | null): void => {
  const buttonText = bookmark?.attributes?.name || Translator.trans('views')

  emit('update-button-text', buttonText)
}

const restoreSavedBookmark = async (): Promise<void> => {
  const savedBookmarkId: string | null = lscache.get(lsKey)

  if (!savedBookmarkId) {
    return
  }

  const savedBookmark: Bookmark | undefined = bookmarks.value.find(bookmark => bookmark.id === savedBookmarkId)

  if (!savedBookmark) {
    lscache.remove(lsKey)

    return
  }

  const success: boolean = await store.dispatch('SegmentsBookmarks/applyBookmark', {
    bookmarkId: savedBookmarkId,
    bookmark: savedBookmark,
  })

  if (success) {
    updateBookmarkButtonText(savedBookmark)
    emit('apply-bookmark', savedBookmark)
  }
}

onMounted(async (): Promise<void> => {
  await fetchBookmarks()
  await restoreSavedBookmark()
})
</script>

