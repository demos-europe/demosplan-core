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
              v-if="change.type === 'filter' && change.details"
              class="border-b border-neutral-light-3 "
            >
              <div
                v-for="(detail, index) in change.details"
                :key="index"
                class="text-sm"
              >
                <p class="font-medium">
                  {{ detail.filterName }}
                </p>
                <p>
                  <dp-icon
                    icon="minus"
                    class="inline-block text-status-failed-icon mr-1"
                  />
                  <span>
                    {{ detail.oldValue }}
                  </span>
                </p>
                <p>
                  <dp-icon
                    icon="plus"
                    class="inline-block mr-1"
                  />
                  <span>
                    {{ detail.newValue }}
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
              <p >
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
  DpInput,
  DpInlineNotification,
  DpRadio,
} from '@demos-europe/demosplan-ui'
import type {
  Bookmark,
  CreateBookmarkPayload,
  UpdateBookmarkPayload,
  Slidebar,
  DetailedChange,
  FilterChange,
} from '@DpJs/types/bookmarks'

import { computed, onMounted, ref, toRaw, type ComputedRef, type Ref } from 'vue'
import lscache from 'lscache'
import { useStore } from 'vuex'

/* Props */
const props = withDefaults(defineProps<{
  procedureId: string
  totalColumnCount?: number
  currentSearchPhrase?: string
  currentSorting?: string
  currentSelectedColumns?: string[]
  currentFilters?: Record<string, any>
  filters?: Record<string, { labelTranslationKey: string, rootPath: string, comparisonOperator: string }>
}>(), {
  totalColumnCount: 0,
  currentSearchPhrase: '',
  currentSorting: '',
  currentSelectedColumns: () => [],
  currentFilters: () => ({}),
  filters: () => ({})
})

const store = useStore()

const newBookmarkName: Ref<string> = ref('')
const showUnsavedChanges: Ref<boolean> = ref(false)
const isInitialized: Ref<boolean> = ref(false)
const lsKey = `${props.procedureId}:segments:currentBookmarkId`

/* Computed */
const bookmarks: ComputedRef<Bookmark[]> = computed(() => store.state.SegmentsBookmarks.allBookmarks)
const currentBookmark: ComputedRef<Bookmark | null> = computed(() => store.state.SegmentsBookmarks.currentBookmark)
const currentBookmarkId: ComputedRef<string | null> = computed(() => store.state.SegmentsBookmarks.currentBookmarkId)
const currentQueryHash: ComputedRef<string> = computed(() => store.state.SegmentSlidebar.currentQueryHash)

const slidebar: ComputedRef<Slidebar> = computed(() => store.state.SegmentSlidebar.slidebar)
const isVisible: ComputedRef<boolean> = computed(() => slidebar.value.showTab === 'bookmarks')
const isApplyingBookmark: ComputedRef<boolean> = computed(() => store.state.SegmentsBookmarks.isApplyingBookmark)

const currentViewChanges: ComputedRef<string[]> = computed(() => {
  // Don't calculate changes until fully initialized or while applying a bookmark
  // to avoid interference during mount and bookmark application
  if (!isInitialized.value || !currentBookmarkId.value || isApplyingBookmark.value) {
    return []
  }

  return []

  //return getBookmarkChanges(currentBookmarkId.value)
})

const changesMessage: ComputedRef<string> = computed(() => {
  if (currentViewChanges.value.length === 0) {
    return ''
  }

  return `${Translator.trans('segments.view.changes')}: ${currentViewChanges.value.join(', ')}`
})

const currentDetailedChanges: ComputedRef<DetailedChange[]> = computed(() => {
  // Don't calculate changes until fully initialized or while applying a bookmark
  // to avoid interference during mount and bookmark application
  if (!isInitialized.value || !currentBookmarkId.value || isApplyingBookmark.value) {
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
    bookmark: selectedBookmark
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
      queryHash
    }
  }

  const success = await createBookmark(payload)

  if (success) {
    newBookmarkName.value = ''
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
  }
}

const getBookmarkChanges = (bookmarkId: string): string[] => {
  const bookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!bookmark) {
    return []
  }

  const changes: string[] = []

  // Use toRaw() to get non-reactive copies and avoid interfering with store state
  const bookmarkAttributes = toRaw(bookmark.attributes)
  const { filter, searchPhrase, viewSettings } = bookmarkAttributes
  const currentFilters = toRaw(props.currentFilters) || {}

  // Check filter changes
  const bookmarkFilterKeys = Object.keys(filter || {})
  const currentFilterKeys = Object.keys(currentFilters)

  if (JSON.stringify(bookmarkFilterKeys.sort()) !== JSON.stringify(currentFilterKeys.sort())) {
    changes.push(Translator.trans('filter'))
  }

  // Check search phrase changes
  const bookmarkSearch = searchPhrase?.trim() || ''
  const currentSearch = props.currentSearchPhrase?.trim() || ''

  if (bookmarkSearch !== currentSearch) {
    changes.push(Translator.trans('search'))
  }

  // Check sorting changes
  const bookmarkSorting = viewSettings.sorting || ''
  const currentSortingValue = props.currentSorting || ''

  if (bookmarkSorting !== currentSortingValue) {
    changes.push(Translator.trans('sorting'))
  }

  // Check column selection changes
  const bookmarkColumns = [...(viewSettings.selectedColumns || [])]
  const currentColumns = [...(props.currentSelectedColumns || [])]

  if (JSON.stringify(bookmarkColumns.sort()) !== JSON.stringify(currentColumns.sort())) {
    changes.push(Translator.trans('columns'))
  }

  return changes
}

const getFilterNameByPath = (filterPath: string): string => {
  // Use toRaw to avoid reactive dependencies
  const filters = toRaw(props.filters) || {}

  // Find the filter configuration by rootPath
  const filterConfig = Object.values(filters).find(
    filter => filter.rootPath === filterPath
  )

  if (filterConfig) {
    return Translator.trans(filterConfig.labelTranslationKey)
  }

  // Fallback to translation key if not found
  return Translator.trans(filterPath)
}

const formatFilterValue = (filterKey: string, value: any): string => {
  if (!value) {
    return Translator.trans('none')
  }

  // Handle array values (multiple selections)
  if (Array.isArray(value)) {
    return value.map(v => formatFilterValue(filterKey, v)).join(', ')
  }

  // Handle object values
  if (typeof value === 'object' && value !== null) {
    // If it has a display name property
    if (value.name) return value.name
    if (value.label) return value.label

    // Otherwise stringify
    return JSON.stringify(value)
  }

  // For specific filter types, try to get human-readable names
  // This is a simplified version - you may need to expand based on your filter types
  return String(value)
}

const getDetailedBookmarkChanges = (bookmarkId: string): DetailedChange[] => {
  const bookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!bookmark) {
    return []
  }

  const detailedChanges: DetailedChange[] = []

  // Use toRaw() and JSON.parse/stringify to create deep non-reactive copies
  // This encapsulates the change detection and prevents interference with store state
  const bookmarkAttributes = JSON.parse(JSON.stringify(toRaw(bookmark.attributes)))
  const { filter, searchPhrase, viewSettings } = bookmarkAttributes
  const currentFilters = JSON.parse(JSON.stringify(toRaw(props.currentFilters) || {}))

  // Check filter changes
  const bookmarkFilterEntries = Object.entries(filter || {})
  const currentFilterEntries = Object.entries(currentFilters)

  const filterChanges: FilterChange[] = []

  // Find filters that changed or were removed
  bookmarkFilterEntries.forEach(([key, bookmarkFilter]: [string, any]) => {
    const currentFilter = currentFilters[key]
    const filterPath = bookmarkFilter.condition?.path || key

    if (!currentFilter) {
      // Filter was removed
      filterChanges.push({
        filterName: getFilterNameByPath(filterPath),
        oldValue: formatFilterValue(filterPath, bookmarkFilter.condition?.value),
        newValue: Translator.trans('removed')
      })
    } else if (JSON.stringify(bookmarkFilter) !== JSON.stringify(currentFilter)) {
      // Filter changed
      filterChanges.push({
        filterName: getFilterNameByPath(filterPath),
        oldValue: formatFilterValue(filterPath, bookmarkFilter.condition?.value),
        newValue: formatFilterValue(filterPath, currentFilter.condition?.value)
      })
    }
  })

  // Find new filters
  currentFilterEntries.forEach(([key, currentFilter]: [string, any]) => {
    if (!filter[key]) {
      const filterPath = currentFilter.condition?.path || key

      filterChanges.push({
        filterName: getFilterNameByPath(filterPath),
        oldValue: Translator.trans('none'),
        newValue: formatFilterValue(filterPath, currentFilter.condition?.value)
      })
    }
  })

  if (filterChanges.length > 0) {
    detailedChanges.push({
      type: 'filter',
      label: Translator.trans('filter'),
      details: filterChanges
    })
  }

  // Check search phrase changes
  const bookmarkSearch = searchPhrase?.trim() || ''
  const currentSearch = props.currentSearchPhrase?.trim() || ''

  if (bookmarkSearch !== currentSearch) {
    detailedChanges.push({
      type: 'search',
      label: Translator.trans('search'),
      oldValue: bookmarkSearch || Translator.trans('none'),
      newValue: currentSearch || Translator.trans('none')
    })
  }

  // Check sorting changes
  const bookmarkSorting = viewSettings.sorting || ''
  const currentSortingValue = props.currentSorting || ''

  if (bookmarkSorting !== currentSortingValue) {
    detailedChanges.push({
      type: 'sorting',
      label: Translator.trans('sorting'),
      oldValue: bookmarkSorting || Translator.trans('none'),
      newValue: currentSortingValue || Translator.trans('none')
    })
  }

  // Check column selection changes
  const bookmarkColumns = [...(viewSettings.selectedColumns || [])]
  const currentColumns = [...(props.currentSelectedColumns || [])]

  const addedColumns = currentColumns.filter(col => !bookmarkColumns.includes(col))
  const removedColumns = bookmarkColumns.filter(col => !currentColumns.includes(col))

  if (addedColumns.length > 0 || removedColumns.length > 0) {
    detailedChanges.push({
      type: 'columns',
      label: Translator.trans('columns'),
      added: addedColumns,
      removed: removedColumns
    })
  }

  return detailedChanges
}

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

  const columnText = props.totalColumnCount > 0
    ? `${selectedColumnCount} von ${props.totalColumnCount} ${Translator.trans('columns')}`
    : `${selectedColumnCount} ${Translator.trans('columns')}`

  const summary = [
    filterText,
    searchText,
    columnText
  ].filter(Boolean).join(' · ')

  return summary
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

  if (!success) return

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
    bookmark: savedBookmark
  })

  if (success) {
    updateBookmarkButtonText(savedBookmark)
    emit('apply-bookmark', savedBookmark)
  }
}

onMounted(async (): Promise<void> => {
  await fetchBookmarks()
  await restoreSavedBookmark()

  // Mark as initialized after bookmark restoration is complete
  // This allows change detection to start working
  isInitialized.value = true
})
</script>


