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
        id="defaultView"
        class="border my-2 p-1"
        :class="isDefaultViewActive ? 'border-blue-base' : 'border-neutral-light-3'"
        :checked="isDefaultViewActive"
        :disabled="isApplyingBookmark"
        value="default"
        :label="{
          text: Translator.trans('segments.view.default'),
          hint: Translator.trans('segments.view.default.description')
        }"
        @change="handleViewChange(null)"
      />
      <dp-radio
        v-for="bookmark in bookmarks"
        :id="bookmark.id"
        :key="bookmark.id"
        class="border my-2 p-1"
        :class="bookmark.id === currentBookmarkId ? 'border-blue-base' : 'border-neutral-light-3'"
        :checked="bookmark.id === currentBookmarkId"
        :disabled="isApplyingBookmark"
        :value="bookmark.id"
        :label="{
          text: bookmark.attributes.name,
          hint: Translator.trans('segments.view.bookmark.info')
        }"
        @change="handleViewChange(bookmark.id)"
      />
      <span class="text-sm">
        {{ Translator.trans('segments.view.none.saved') }}
      </span>

      <div class="border-t border-neutral-light-3 my-4">
        <span class="pt-4 block font-semibold">
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
        @primary-action="save"
        @secondary-action="abort"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useStore } from 'vuex'
import { DpRadio, DpButton, dpApi, DpInput, DpButtonRow } from '@demos-europe/demosplan-ui'

const store = useStore()

const newBookmarkName = ref('')

/* Computed */
const currentBookmark = computed(() => store.state.SegmentsBookmarks.currentBookmark)
const currentBookmarkId = computed(() => store.state.SegmentsBookmarks.currentBookmarkId)
const isDefaultViewActive = computed(() => currentBookmarkId.value === null)
const slidebar = computed(() => store.state.SegmentSlidebar.slidebar)
const isVisible = computed(() => slidebar.value.showTab === 'bookmarks')
const currentQueryHash = computed(() => store.state.SegmentSlidebar.currentQueryHash)
const isApplyingBookmark = computed(() => store.state.SegmentsBookmarks.isApplyingBookmark)
const bookmarks = computed(() => store.state.SegmentsBookmarks.allBookmarks)

/* Store actions */
const fetchBookmarks = () => store.dispatch('SegmentsBookmarks/fetchBookmarks')
const createBookmark = (payload: object) => store.dispatch('SegmentsBookmarks/createBookmark', payload)
const updateBookmark = (payload: { id: string, name?: string, queryHash?: string }) =>
  store.dispatch('SegmentsBookmarks/updateBookmark', payload)
const deleteBookmark = (id: string) => store.dispatch('SegmentsBookmarks/deleteBookmark', id)

/* Emit */
const emit = defineEmits(['apply-bookmark', 'reset-view'])

/* Methods */
const abort = () => {
  console.log('Abort')
}

const createNewBookmark = async () => {
  if (!currentQueryHash.value) {
    return
  }

  if (!newBookmarkName.value.trim()) {
    return
  }

  const payload = {
    type: 'Bookmark',
    attributes: {
      name: newBookmarkName.value.trim(),
      queryHash: currentQueryHash.value
    }
  }

  const success = await createBookmark(payload)
  if (success) {
    newBookmarkName.value = ''
  }
}

const handleViewChange = async (bookmarkId) => {
  if (bookmarkId === null) {
    const success = await store.dispatch('SegmentsBookmarks/resetToDefaultView')
    if (success) {
      emit('reset-view')
    }
    return
  }

  const selectedBookmark = bookmarks.value.find(bookmark => bookmark.id === bookmarkId)
  if (!selectedBookmark) {
    return
  }

  const success = await store.dispatch('SegmentsBookmarks/applyBookmark', {
    bookmarkId,
    bookmark: selectedBookmark
  })

  if (success) {
    emit('apply-bookmark', selectedBookmark)
  }
}

const save = () => {
  createNewBookmark()
}

onMounted(() => {
  fetchBookmarks()
})

</script>


