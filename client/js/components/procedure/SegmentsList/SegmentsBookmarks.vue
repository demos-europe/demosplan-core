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
        class="flex justify-between border my-2 p-1"
        :class="bookmark.id === currentBookmarkId ? 'border-blue-base' : 'border-neutral-light-3'"
      >
        <dp-radio
          :id="bookmark.id"
          :key="bookmark.id"
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
  DpRadio,
  DpButton,
  DpInput,
  DpButtonRow,
} from '@demos-europe/demosplan-ui'
import type {
  Bookmark,
  CreateBookmarkPayload,
  UpdateBookmarkPayload,
  Slidebar,
} from '@DpJs/types/bookmarks'

import { computed, onMounted, ref, type Ref, type ComputedRef } from 'vue'
import lscache from 'lscache'
import { useStore } from 'vuex'

/* Props */
const props = defineProps<{
  procedureId: string
}>()

const store = useStore()

const newBookmarkName: Ref<string> = ref('')
const lsKey = `${props.procedureId}:segments:currentBookmarkId`

/* Computed */
const bookmarks: ComputedRef<Bookmark[]> = computed(() => store.state.SegmentsBookmarks.allBookmarks)
const currentBookmark: ComputedRef<Bookmark | null> = computed(() => store.state.SegmentsBookmarks.currentBookmark)
const currentBookmarkId: ComputedRef<string | null> = computed(() => store.state.SegmentsBookmarks.currentBookmarkId)
const currentQueryHash: ComputedRef<string> = computed(() => store.state.SegmentSlidebar.currentQueryHash)

const slidebar: ComputedRef<Slidebar> = computed(() => store.state.SegmentSlidebar.slidebar)
const isVisible: ComputedRef<boolean> = computed(() => slidebar.value.showTab === 'bookmarks')
const isApplyingBookmark: ComputedRef<boolean> = computed(() => store.state.SegmentsBookmarks.isApplyingBookmark)

/* Store actions */
const createBookmark = (payload: CreateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/createBookmark', payload)
const deleteBookmark = (id: string): Promise<boolean> => store.dispatch('SegmentsBookmarks/deleteBookmark', id)
const fetchBookmarks = (): Promise<Bookmark[]> => store.dispatch('SegmentsBookmarks/fetchBookmarks')
const updateBookmark = (payload: UpdateBookmarkPayload): Promise<boolean> => store.dispatch('SegmentsBookmarks/updateBookmark', payload)

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

const getBookmarkSummary = (bookmarkId: string): string => {
  const bookmark = bookmarks.value.find(bookmark => bookmark.id === bookmarkId)

  if (!bookmark) {
    return ''
  }

  const { filter, viewSettings } = bookmark.attributes
  const filterCount = Object.values(filter).length
  const columnCount = viewSettings.selectedColumns.length

  return `${filterCount} ${Translator.trans('filter')} · ${columnCount} ${Translator.trans('columns')}` // ToDo: add information about the total column count
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
})
</script>


