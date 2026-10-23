<template>
  <div
    v-show="isVisible"
    class="-ml-5 flex h-full flex-col overflow-auto"
  >
    <!-- Header -->
    <div class="shrink-0 border-b-2 border-neutral-light-3 px-4 py-2">
      <h2>
        {{ Translator.trans('views') }}
      </h2>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto min-h-0">
      <segments-bookmarks-unsaved-changes
        :current-bookmark="currentBookmark"
        :filters="filters"
        :is-open="showUnsavedChanges"
        @toggle="showUnsavedChanges = $event"
        @has-changes="hasUnsavedChanges = $event"
      />
      <div class="px-4 py-3">
        <segments-bookmarks-list
          :bookmarks="bookmarks"
          :current-bookmark-id="currentBookmarkId"
          :deleting-bookmark-id="deletingBookmarkId"
          :is-loading="isBookmarkLoading"
          :total-column-count="totalColumnCount"
          @select="handleViewChange"
          @delete="handleDeleteBookmark"
        />
        <div
          v-if="hasUnsavedChanges && showNewBookmarkForm"
          class="border-t border-neutral-light-3 my-4"
        >
          <span class="pt-4 block font-semibold mb-3">
            {{ Translator.trans('segments.bookmarks.new.save') }}
          </span>
          <dp-input
            id="newBookmarkName"
            v-model="newBookmarkName"
            data-cy="segmentsBookmarks:newName"
            :label="{
              text: Translator.trans('segments.bookmarks.name'),
              tooltip: Translator.trans('segments.bookmarks.name.tooltip')
            }"
          />
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div
      v-if="hasUnsavedChanges"
      class="flex w-full shrink-0 justify-end border-t-2 border-neutral-light-3 p-4"
    >
      <dp-button-row
        v-if="showNewBookmarkForm"
        primary
        secondary
        :disabled="!newBookmarkName || isBookmarkLoading"
        data-cy="segmentsBookmarks:save"
        @primary-action="handlePrimaryAction"
        @secondary-action="abort"
      />

      <div
        v-else
        class="grid grid-cols-2 gap-2"
      >
        <dp-button
          data-cy="segmentsBookmarks:abort"
          :disabled="isBookmarkLoading"
          :text="Translator.trans('segments.bookmarks.changes.discard')"
          variant="subtle"
          @click="abort"
        />
        <dp-button
          data-cy="segmentsBookmarks:showNewBookmarkForm"
          :disabled="isBookmarkLoading"
          :text="Translator.trans('segments.bookmarks.save.as.new')"
          variant="outline"
          @click="openNewBookmarkForm"
        />
        <dp-button
          v-if="currentBookmarkId"
          class="col-span-2 justify-self-end"
          data-cy="segmentsBookmarks:update"
          :disabled="isBookmarkLoading"
          :text="Translator.trans('segments.bookmarks.changes.apply')"
          @click="handleBookmarkUpdate"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type {
  Bookmark,
  CreateBookmarkPayload,
  UpdateBookmarkPayload,
} from '@DpJs/types/bookmarks'
import type {
  RootState,
  Slidebar,
} from '@DpJs/types/store'
import {
  DpButton,
  DpButtonRow,
  DpInput,
} from '@demos-europe/demosplan-ui'
import { computed, type ComputedRef, onMounted, type Ref, ref, watch } from 'vue'
import { useStore } from 'vuex'
import lscache from 'lscache'
import SegmentsBookmarksUnsavedChanges from './SegmentsBookmarksUnsavedChanges.vue'
import SegmentsBookmarksList from './SegmentsBookmarksList.vue'

const { Translator, dplan } = window as typeof globalThis

/* Props */
const props = withDefaults(defineProps<{
  procedureId: string
  totalColumnCount?: number
  filters?: Record<string, { labelTranslationKey: string, rootPath: string, comparisonOperator: string }>
}>(), {
  totalColumnCount: 0,
  filters: () => ({}),
})

const store = useStore<RootState>()
const state = store.state as RootState

const deletingBookmarkId: Ref<string | null> = ref(null)
const hasUnsavedChanges: Ref<boolean> = ref(false)
const lsKey = `${props.procedureId}:segments:currentBookmarkId`
const newBookmarkName: Ref<string> = ref('')
const showNewBookmarkForm: Ref<boolean> = ref(false)
const showUnsavedChanges: Ref<boolean> = ref(false)

/* Computed */
const bookmarks: ComputedRef<Bookmark[]> = computed(() => state.SegmentsBookmarks.allBookmarks)
const currentBookmark: ComputedRef<Bookmark | null> = computed(() => state.SegmentsBookmarks.currentBookmark)
const currentBookmarkId: ComputedRef<string | null> = computed(() => state.SegmentsBookmarks.currentBookmarkId)
const currentQueryHash: ComputedRef<string> = computed(() => state.SegmentSlidebar.currentQueryHash)

const slidebar: ComputedRef<Slidebar> = computed(() => state.SegmentSlidebar.slidebar)
const isVisible: ComputedRef<boolean> = computed(() => slidebar.value.showTab === 'bookmarks')
const isBookmarkLoading: ComputedRef<boolean> = computed(() => state.SegmentsBookmarks.isBookmarkLoading)

/* Store actions */
const applyBookmarkAction = (payload: { bookmarkId: string, bookmark: Bookmark }): Promise<boolean> => store.dispatch('SegmentsBookmarks/applyBookmark', payload)
const createBookmarkAction = (payload: CreateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/createBookmark', payload)
const deleteBookmarkAction = (id: string): Promise<boolean> => store.dispatch('SegmentsBookmarks/deleteBookmark', id)
const fetchBookmarksAction = (): Promise<Bookmark[]> => store.dispatch('SegmentsBookmarks/fetchBookmarks')
const updateBookmarkAction = (payload: UpdateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/updateBookmark', payload)

/* Emits */
interface SegmentsBookmarksEmits {
  (e: 'applyBookmark', bookmark: Bookmark): void
  (e: 'hasUnsavedChanges', value: boolean): void
  (e: 'resetView'): void
  (e: 'updateButtonText', text: string): void
}

const emit = defineEmits<SegmentsBookmarksEmits>()

/* Watch */
watch(hasUnsavedChanges, (newValue: boolean): void => {
  emit('hasUnsavedChanges', newValue)
})

watch(isVisible, (visible: boolean): void => {
  if (visible) {
    emit('hasUnsavedChanges', hasUnsavedChanges.value)
  }
})

/* Methods */
const abort = async (): Promise<void> => {
  if (currentBookmarkId.value) {
    await applySelectedBookmark(currentBookmarkId.value)
  } else {
    await setDefaultBookmark()
  }
}

const handleDeleteBookmark = async (bookmarkId: string): Promise<void> => {
  const wasSelected = currentBookmarkId.value === bookmarkId

  deletingBookmarkId.value = bookmarkId

  try {
    const success = await deleteBookmarkAction(bookmarkId)

    if (success && wasSelected) {
      setDefaultBookmark()
    }
  } finally {
    deletingBookmarkId.value = null
  }
}

const applySelectedBookmark = async (bookmarkId): Promise<void> => {
  const selectedBookmark = bookmarks.value.find(({ id }) => id === bookmarkId)

  if (!selectedBookmark) {
    return
  }

  const success = await applyBookmarkAction({
    bookmarkId,
    bookmark: selectedBookmark,
  })

  if (!success) {
    return
  }

  lscache.set(lsKey, bookmarkId)
  updateBookmarkButtonText(selectedBookmark)
  emit('applyBookmark', selectedBookmark)
  hasUnsavedChanges.value = false
  emit('hasUnsavedChanges', false)
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

  const success = await createBookmarkAction(payload)

  if (success) {
    newBookmarkName.value = ''
    showNewBookmarkForm.value = false
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))

    const newBookmark = bookmarks.value.find(b => b.attributes.name === name)

    if (newBookmark) {
      await applySelectedBookmark(newBookmark.id)
    }
  }
}
const handlePrimaryAction = async (): Promise<void> => {
  await createNewBookmark()
  showNewBookmarkForm.value = false
  showUnsavedChanges.value = false
}

const handleViewChange = async (bookmarkId: string | null): Promise<void> => {
  showNewBookmarkForm.value = false

  if (bookmarkId === null) {
    await setDefaultBookmark()

    return
  }

  await applySelectedBookmark(bookmarkId)
}

const openNewBookmarkForm = (): void => {
  showNewBookmarkForm.value = true
  showUnsavedChanges.value = false
}

const restoreSavedBookmark = async (): Promise<void> => {
  const savedBookmarkId: string | null = lscache.get(lsKey)

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

  const success: boolean = await applyBookmarkAction({
    bookmarkId: savedBookmarkId,
    bookmark: savedBookmark,
  })

  if (success) {
    updateBookmarkButtonText(savedBookmark)
    emit('applyBookmark', savedBookmark)
    hasUnsavedChanges.value = false
    emit('hasUnsavedChanges', false)
  }
}

const setDefaultBookmark = (): void => {
  lscache.remove(lsKey)
  updateBookmarkButtonText(null)
  emit('resetView')
}

const handleBookmarkUpdate = async (): Promise<void> => {
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

  const success = await updateBookmarkAction(payload)

  if (success) {
    dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
    showUnsavedChanges.value = false
    hasUnsavedChanges.value = false
    emit('hasUnsavedChanges', false)
  }
}

const updateBookmarkButtonText = (bookmark: Bookmark | null): void => {
  const buttonText = bookmark?.attributes?.name || Translator.trans('views')

  emit('updateButtonText', buttonText)
}

onMounted(async (): Promise<void> => {
  await fetchBookmarksAction()
  await restoreSavedBookmark()
})
</script>

