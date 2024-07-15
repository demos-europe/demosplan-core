<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    ref="sideBar"
    class="side-bar flex flex-col">

    <!-- Selected Tags Section -->
    <div class="relative px-2 pt-2">
      <dp-label
        :text="Translator.trans('tags')"
        for="searchSelect"
        class="u-mb-0" />
      <div v-if="editingSegment && editingSegment.tags.length > 0">
        <div
          v-for="(tag, idx) in editingSegment.tags"
          :key="`tag_${idx}`"
          class="tag inline-flex font-size-small u-mr-0_25 u-mb-0_25">
          {{ tag.tagName }}
          <button
            type="button"
            class="tag__remove btn--blank o-link--default u-ml-0_25"
            @click="removeTag(tag.id)">
            <dp-icon
              icon="close"
              size="small" />
          </button>
        </div>
      </div>

      <!--   Floating Context Button -->
      <button
        v-show="showFloatingContextButton.tags"
        id="floatingContextButton_tags"
        aria-controls="tags"
        :aria-expanded="isCollapsed.tags"
        class="bg-white rounded shadow absolute z-[100] right-[-26px] bottom-[-38px] p-0.5"
        data-cy=""
        @click="toggleVisibility('tags')"
        @mouseover="showFloatingContextButton.tags = true"
        @mouseleave="showFloatingContextButton.tags = false">
        <dp-icon
          aria-hidden="true"
          class="w-4 h-4 hover:bg-slate-200"
          :icon="isCollapsed.tags ? 'chevron-up' : 'chevron-down'"
          size="medium" />
      </button>
    </div>

    <!-- Tags Section -->
    <div
      id="tags"
      aria-labelledby="floatingContextButton_tags"
      class="flex-1 flex overflow-y-hidden py-1 px-2"
      @mouseover="showFloatingContextButton.tags = true"
      @mouseleave="showFloatingContextButton.tags = false">

      <button
        v-if="!isCollapsed.tags"
        @click="toggleVisibility('tags')"
        class="relative btn--blank o-link--default text-left pt-1 pr-20">
          Schlagworte auswählen
      </button>

      <div
        v-else
        class="flex flex-col">
        <div>
          <!-- search available tags -->
          <search-select
            v-if="showCreateForm === false"
            @open-create-form="showCreateForm = true"
            :selected="selectedTags"
            :place-holder="Translator.trans('tag.search')"
            :options="searchableTags" />

          <!-- create tags + topics -->
          <dp-create-tag
            v-else
            @close-create-form="showCreateForm = false" />
        </div>

        <div
          v-if="tagTopics.length"
          class="flex-1 overflow-y-scroll my-2 pr-1">
          <!-- categorized tags -->
          <tag-select
            v-for="(topic, idx) in tagTopics"
            class="u-mb-0_5"
            :entity="topic"
            :selected="selectedTags.filter(tag => (hasOwnProp(tag, 'relationships') && hasOwnProp(tag.relationships, 'topic')) ? tag.relationships.topic.data.id === topic.id : false)"
            :key="`category_${idx}`" />
          <!-- uncategorized tags -->
          <tag-select
            v-if="tags.length > 0"
            class="u-mb-0_5"
            :selected="selectedTags.filter(tag => (hasOwnProp(tag, 'relationships') || (hasOwnProp(tag, 'relationships') && hasOwnProp(tag.relationships, 'topic'))) === false)"
            :entity="{ id: 'category.none', attributes: { title: Translator.trans('category.none') } }"
            key="category_none" />
        </div>
      </div>
    </div>

    <!-- Places and Assignee Section -->
    <div
      id="placesAndAssignee"
      aria-labelledby="floatingContextButton_placesAndAssignee"
      class="relative py-1 px-2"
      @mouseover="showFloatingContextButton.placesAndAssignee = true"
      @mouseleave="showFloatingContextButton.placesAndAssignee = false">

      <!--   Floating Context Button -->
      <button
        v-show="showFloatingContextButton.placesAndAssignee"
        id="floatingContextButton_placesAndAssignee"
        aria-controls="placesAndAssignee"
        :aria-expanded="isCollapsed.placesAndAssignee"
        class="bg-white rounded shadow absolute z-[100] right-[-26px] top-0 p-0.5"
        data-cy=""
        @click="toggleVisibility('placesAndAssignee')"
        @mouseover="showFloatingContextButton.placesAndAssignee = true"
        @mouseleave="showFloatingContextButton.placesAndAssignee = false">
        <dp-icon
          aria-hidden="true"
          class="w-4 h-4 hover:bg-slate-200"
          :icon="isCollapsed.placesAndAssignee ? 'chevron-up' : 'chevron-down'"
          size="medium" />
      </button>

      <button
        v-if="!isCollapsed.placesAndAssignee"
        @click="toggleVisibility('placesAndAssignee')"
        class="relative btn--blank o-link--default text-left w-full">
        {{ Translator.trans('workflow.place') }}
      </button>

      <div v-else>
        <label
          class="inline-block m-0"
          for="setPlace">
          {{ Translator.trans('workflow.place') }}
        </label>
        <dp-multiselect
          id="setPlace"
          v-model="selectedPlace"
          label="name"
          class="u-mb-0_5"
          :allow-empty="false"
          :options="availablePlaces"
          :show-placeholder="false"
          track-by="id">
          <template v-slot:option="{ props }">
            {{ props.option.name }}
            <dp-contextual-help
              v-if="props.option.description"
              class="float-right"
              :text="props.option.description" />
          </template>
        </dp-multiselect>
        <label
          class="inline-block u-mb-0"
          for="assignUser">
          {{ Translator.trans('assignee') }}
        </label>
        <dp-multiselect
          id="assignUser"
          v-model="selectedAssignee"
          label="name"
          class="u-mb-0_5"
          :allow-empty="false"
          :options="assignableUsers"
          :show-placeholder="false"
          track-by="id" />
      </div>
    </div>

    <dp-button-row
      id="buttonRow"
      class="p-2"
      secondary
      primary
      @primary-action="save"
      @secondary-action="$emit('abort')" />
  </div>
</template>

<script>
import { DpButtonRow, DpContextualHelp, DpIcon, DpLabel, DpMultiselect, DpSelect, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import DpCreateTag from './DpCreateTag'
import SearchSelect from './SearchSelect'
import TagSelect from './TagSelect'

export default {
  name: 'SideBar',

  components: {
    DpButtonRow,
    DpCreateTag,
    DpContextualHelp,
    DpIcon,
    DpLabel,
    DpMultiselect,
    DpSelect,
    SearchSelect,
    TagSelect
  },

  data () {
    return {
      isCollapsed: {
        tags: false,
        placesAndAssignee: false
      },
      selectedAssignee: null,
      selectedPlace: null,
      showCreateForm: false,
      showFloatingContextButton: {
        tags: false,
        placesAndAssignee: false
      }
    }
  },

  computed: {
    ...mapGetters('SplitStatement', {
      assignableUsers: 'assignableUsers',
      availablePlaces: 'availablePlaces',
      availableTags: 'availableTags',
      editingSegment: 'editingSegment',
      isBusy: 'isBusy',
      isOpen: 'editModeActive',
      procedureId: 'procedureId',
      segment: 'segmentById',
      tagTopics: 'tagTopics',
      tags: 'uncategorizedTags'
    }),

    assigneeNeedsUpdate () {
      return this.selectedAssignee.id !== this.initialAssignee.id
    },

    currentSegment () {
      if (this.editingSegment && this.segment(this.editingSegment.id)) {
        return JSON.parse(JSON.stringify(this.segment(this.editingSegment.id)))
      }

      return null
    },

    initialAssignee () {
      if (this.currentSegment && hasOwnProp(this.currentSegment, 'assigneeId')) {
        return this.getAssignableUserById(this.currentSegment.assigneeId)
      }
      const noAssignee = this.getAssignableUserById('noAssigneeId')

      return noAssignee || null
    },

    initialPlace () {
      if (this.currentSegment && hasOwnProp(this.currentSegment, 'placeId')) {
        return this.getPlaceById(this.currentSegment.placeId)
      }

      return this.availablePlaces.length > 0 ? this.availablePlaces[0] : null
    },

    needsUpdate () {
      return this.assigneeNeedsUpdate || this.placeNeedsUpdate
    },

    placeNeedsUpdate () {
      const hasPlaceIdProp = this.currentSegment && hasOwnProp(this.currentSegment, 'placeId')

      return !hasPlaceIdProp ||
          this.selectedPlace.id !== this.initialPlace.id ||
          this.editingSegment === null
    },

    searchableTags () {
      return this.availableTags
        .map((tag) => ({ title: tag.attributes.title, id: tag.id }))
        .sort((a, b) => a.title.localeCompare(b.title, 'de', { sensitivity: 'base' }))
    },

    selectedTags () {
      return this.editingSegment ? this.editingSegment.tags.map(el => this.availableTags.find(tag => tag.id === el.id || tag.attributes.title === el.tagName)) : []
    }
  },

  methods: {
    ...mapActions('SplitStatement', [
      'updateCurrentTags'
    ]),
    ...mapMutations('SplitStatement', [
      'locallyUpdateSegments',
      'setProperty'
    ]),

    toggleVisibility (key) {
      this.isCollapsed[key] = !this.isCollapsed[key]
    },

    getAssignableUserById (id) {
      return this.assignableUsers.find(user => user.id === id)
    },

    getPlaceById () {
      return this.availablePlaces.find(place => place.id === this.currentSegment.placeId)
    },

    handleClick () {
      this.setProperty({ prop: 'isBusy', val: true })
      this.$emit('save-and-finish')
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    removeTag (id) {
      const tagToBeDeleted = this.availableTags.find(tag => tag.id === id)
      this.updateCurrentTags({ id: id, tagName: tagToBeDeleted.attributes.title })
    },

    reset () {
      this.showCreateForm = false
      this.$emit('abort')
    },

    save () {
      if (this.needsUpdate) {
        this.updateSegment()
      }

      this.$emit('save')
    },

    setInitialValues () {
      this.selectedAssignee = this.initialAssignee
      this.selectedPlace = this.initialPlace || this.availablePlaces[0]
    },

    updateSegment () {
      const segment = { ...this.editingSegment }

      if (this.assigneeNeedsUpdate) {
        if (this.selectedAssignee.id === '' || this.selectedAssignee.id === 'noAssigneeId') {
          delete segment.assigneeId
        } else {
          segment.assigneeId = this.selectedAssignee.id
        }
      }

      if (this.placeNeedsUpdate) {
        // Place can't be empty
        if (this.selectedPlace.id !== '') {
          segment.placeId = this.selectedPlace.id
          const place = this.availablePlaces.find(aPlace => aPlace.id === this.selectedPlace.id)
          segment.place = place ? { id: place.id, name: place.name } : { id: this.availablePlaces[0].id, name: this.availablePlaces[0].name }
        }
      }

      this.setProperty({ prop: 'editingSegment', val: segment })
      this.locallyUpdateSegments([this.editingSegment])
    },

    getSideBarMaxHeight () {
      const header = document.getElementById('header')
      const footer = document.getElementById('footer')

      const viewportHeight = window.innerHeight
      const headerHeight = header.offsetHeight
      const footerHeight = footer.offsetHeight

      const mainHeight = viewportHeight - headerHeight - footerHeight

      return mainHeight * 0.8 // 75% from the main height
    },

    updateSidebarMaxHeight () {
      const sideBarMaxHeight = this.getSideBarMaxHeight()

      if (this.$refs.sideBar) {
        this.$refs.sideBar.style.maxHeight = `${sideBarMaxHeight}px`
      }
    }
  },

  mounted () {
    this.setInitialValues()
    this.updateSidebarMaxHeight() // Set initial height
    window.addEventListener('resize', this.updateSidebarMaxHeight)
  },

  beforeDestroy () {
    window.removeEventListener('resize', this.updateSidebarMaxHeight)
  }
}
</script>
