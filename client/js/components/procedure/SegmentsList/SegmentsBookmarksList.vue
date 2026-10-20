<template>
  <div>
    <p class="font-semibold">
      {{ Translator.trans('segments.bookmarks.saved') }}
    </p>

    <!-- Default bookmark -->
    <dp-radio
      id="defaultBookmark"
      class="border my-2 p-1"
      :class="!currentBookmarkId ? 'border-blue-base' : 'border-neutral-light-3'"
      :checked="!currentBookmarkId"
      :disabled="isLoading"
      :label="{
        hint: Translator.trans('segments.bookmarks.default.description'),
        text: Translator.trans('segments.bookmarks.default'),
      }"
      @change="$emit('select', null)"
    />

    <!-- Saved bookmarks -->
    <div
      v-for="bookmark in bookmarks"
      :key="bookmark.id"
      class="flex justify-between border my-2 p-1"
      :class="bookmark.id === currentBookmarkId ? 'border-blue-base' : 'border-neutral-light-3'"
    >
      <dp-radio
        :id="bookmark.id"
        :checked="bookmark.id === currentBookmarkId"
        :disabled="isLoading"
        :label="{
          hint: getBookmarkSummary(bookmark),
          text: bookmark.attributes.name,
        }"
        @change="$emit('select', bookmark.id)"
      />
      <dp-button
        v-if="deletingBookmarkId !== bookmark.id"
        color="primary"
        data-cy="segmentsBookmarks:delete"
        hide-text
        icon="delete"
        :text="Translator.trans('delete')"
        variant="subtle"
        @click="$emit('delete', bookmark.id)"
      />
      <dp-loading
        v-if="deletingBookmarkId === bookmark.id"
        class="inline-flex mr-1"
        hide-label
      />
    </div>

    <dp-loading
      v-if="isLoading"
      class="justify-center my-2"
      hide-label
    />

    <span
      v-if="!bookmarks.length && !isLoading"
      class="text-sm"
    >
      {{ Translator.trans('segments.bookmarks.none.saved') }}
    </span>
  </div>
</template>

<script setup lang="ts">
import type { Bookmark } from '@DpJs/types/bookmarks'
import {
  DpButton,
  DpRadio,
  DpLoading,
} from '@demos-europe/demosplan-ui'

/* Props */
const props = defineProps<{
  bookmarks: Bookmark[]
  currentBookmarkId: string | null
  deletingBookmarkId: string | null
  isLoading: boolean
  totalColumnCount: number
}>()

/* Emits */
interface BookmarksListEmits {
  (e: 'select', bookmarkId: string | null): void
  (e: 'delete', bookmarkId: string): void
}

const emit = defineEmits<BookmarksListEmits>()

/* Methods */
const getBookmarkSummary = (bookmark: Bookmark): string => {
  const { filter, searchPhrase, viewSettings } = bookmark.attributes
  const filterCount = Object.keys(filter).length
  const selectedColumnCount = viewSettings.selectedColumns?.length || 0

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
</script>
