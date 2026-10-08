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
        value="default"
        :label="{
          text: Translator.trans('segments.view.default'),
          hint: Translator.trans('segments.view.default.description')
        }"
        @change="currentView = 'defaultView'"
      />
      <dp-radio
        v-for="bookmark in bookmarks"
        :id="bookmark.id"
        class="border my-2 p-1"
        :class="bookmark.id === currentView ? 'border-blue-base' : 'border-neutral-light-3'"
        :checked="bookmark.id === currentView"
        :value="bookmark.id"
        :label="{
          text: bookmark.attributes.name,
          hint: Translator.trans('segments.view.bookmark.info')
        }"
        @change="currentView = bookmark.id"
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

const currentView = ref('defaultView')
const newBookmarkName = ref('')

/* Computed */
const isDefaultViewActive = computed(() => currentView.value === 'defaultView')
const slidebar = computed(() => store.state.SegmentSlidebar.slidebar)
const isVisible = computed(() => slidebar.value.showTab === 'bookmarks')
const currentQueryHash = computed(() => store.state.SegmentSlidebar.currentQueryHash)
const bookmarks = computed(() => {
  const items = store.state.Bookmark?.items || {}
  return Object.values(items)
})

/* Store actions */
const listBookmarks = () => store.dispatch('Bookmark/list', {
  params: {
    fields: {
      Bookmark: 'name,queryHash,filter,searchPhrase,viewSettings'
    }
  }
})
const getBookmark = (id: string) => store.dispatch('Bookmark/get', {
  id,
  params: {
    fields: {
      Bookmark: 'name,queryHash,filter,searchPhrase,viewSettings'
    }
  }
})
const createBookmark = (payload: object) =>
  store.dispatch('Bookmark/create', payload)
const updateBookmark = (payload: { id: string, name?: string, queryHash?: string }) =>
  store.dispatch('Bookmark/update', payload)
const deleteBookmark = (id: string) => store.dispatch('Bookmark/delete', id)

/* Methods */
const abort = () => {
  console.log('Abort')
}

const createNewBookmark = () => {
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

  createBookmark(payload)
    .then(() => {
      newBookmarkName.value = ''
      listBookmarks()
    })
    .catch((error) => {
      console.error('Failed to create bookmark:', error)
    })
}

const save = () => {
  createNewBookmark()
}

onMounted(() => {
  listBookmarks()
})

</script>


