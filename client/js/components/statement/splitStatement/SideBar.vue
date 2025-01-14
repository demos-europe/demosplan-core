<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    ref="sideBar"
    :style="`max-height: calc(100vh - ${offset}px - 8px);`"
    class="side-bar flex flex-col">
    <!-- Selected Tags Section -->
    <div class="relative px-2 py-2">
      <dp-label
        :text="Translator.trans('tags')"
        for="searchSelect"
        class="mb-1" />
      <assigned-tags
        :available-tags="availableTags"
        :current-segment="currentSegment"
        :initial-segments="initialSegments"
        :segment="this.editingSegment"
        @remove="updateCurrentTags" />
      <floating-context-button
        class="right-[-24px] bottom-[-30px]"
        section="tags"
        :is-visible="showFloatingContextButton.tags"
        :is-content-collapsed="isCollapsed.tags"
        @toggle-content-visibility="toggleVisibility"
        @show="showFloatingContextButton.tags = true"
        @hide="showFloatingContextButton.tags = false" />
    </div>

    <!-- Tags Section -->
    <div
      aria-labelledby="floatingContextButton_tags"
      :class="['flex-1', 'flex', 'pl-2', 'pr-5', '-mr-4', { 'overflow-y-hidden': availableTags.length && tagTopics.length > 8 }]"
      @mouseover="showFloatingContextButton.tags = true"
      @mouseleave="showFloatingContextButton.tags = false">
      <button
        v-if="!isCollapsed.tags"
        data-cy="sidebar:toggleVisibility:tags"
        @click="toggleVisibility('tags')"
        class="relative btn--blank o-link--default font-semibold w-full text-left pr-2 pt-0.5">
        {{ Translator.trans('tags.select') }}
      </button>

      <div
        v-else
        class="flex flex-col w-full">
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
          :class="['flex-1', 'mt-2', 'pr-1', { 'overflow-y-scroll': tagTopics.length > 8 }]"
          data-cy="tagTopicsContainer">
          <!-- categorized tags -->
          <tag-select
            v-for="(topic, idx) in tagTopics"
            :class="{'mb-1': idx < tagTopics.length + 1}"
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
      aria-labelledby="floatingContextButton_placesAndAssignee"
      class="relative py-1 pl-2 pr-5 -mr-4"
      @mouseover="showFloatingContextButton.placesAndAssignee = true"
      @mouseleave="showFloatingContextButton.placesAndAssignee = false">
      <FloatingContextButton
        class="right-0 top-0"
        section="placesAndAssignee"
        :is-visible="showFloatingContextButton.placesAndAssignee"
        :is-content-collapsed="isCollapsed.placesAndAssignee"
        @toggle-content-visibility="toggleVisibility"
        @show="showFloatingContextButton.placesAndAssignee = true"
        @hide="showFloatingContextButton.placesAndAssignee = false" />

      <button
        v-if="!isCollapsed.placesAndAssignee"
        data-cy="sidebar:toggleVisibility:placesAndAssignee"
        @click="toggleVisibility('placesAndAssignee')"
        class="relative btn--blank o-link--default font-semibold text-left w-full">
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
          class="mb-1"
          data-cy="selectedPlace"
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
          class="inline-block mb-0"
          for="assignUser">
          {{ Translator.trans('assignee') }}
        </label>
        <dp-multiselect
          id="assignUser"
          v-model="selectedAssignee"
          label="name"
          class="mb-1"
          data-cy="selectedAssignee"
          :allow-empty="false"
          :options="assignableUsers"
          :show-placeholder="false"
          track-by="id" />
      </div>
    </div>

    <dp-button-row
      class="p-2"
      data-cy="assignedTags"
      alignment="left"
      secondary
      primary
      variant="outline"
      @primary-action="save"
      @secondary-action="$emit('abort')" />
  </div>
</template>

<script>
import {
  DpButtonRow,
  DpContextualHelp,
  DpIcon,
  DpLabel,
  DpMultiselect,
  DpSelect,
  hasOwnProp,
  Tooltip
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import AssignedTags from './AssignedTags.vue'
import DpCreateTag from './DpCreateTag'
import FloatingContextButton from './FloatingContextButton.vue'
import SearchSelect from './SearchSelect'
import TagSelect from './TagSelect'

export default {
  name: 'SideBar',

  components: {
    AssignedTags,
    DpButtonRow,
    DpCreateTag,
    DpContextualHelp,
    DpIcon,
    DpLabel,
    DpMultiselect,
    DpSelect,
    FloatingContextButton,
    SearchSelect,
    TagSelect
  },

  directives: {
    tooltip: Tooltip
  },

  props: {
    offset: {
      type: Number,
      required: true
    }
  },

  data () {
    return {
      isCollapsed: {
        tags: true,
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
      initialSegments: 'initialSegments',
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

    toggleVisibility (section) {
      this.isCollapsed[section] = !this.isCollapsed[section]
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

    reset () {
      this.showCreateForm = false
      this.$emit('abort')
    },

    save () {
      if (this.availablePlaces.length < 1) {
        dplan.notify.notify(
          'error',
          Translator.trans('error.split_statement.no_place'),
          Routing.generate('DemosPlan_procedure_places_list', { procedureId: this.procedureId }),
          Translator.trans('places.addPlace'))

        return
      }

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
        if (this.selectedPlace?.id) {
          segment.placeId = this.selectedPlace.id
          const place = this.availablePlaces.find(aPlace => aPlace.id === this.selectedPlace.id)
          segment.place = place ? { id: place.id, name: place.name } : { id: this.availablePlaces[0].id, name: this.availablePlaces[0].name }
        }
      }

      this.setProperty({ prop: 'editingSegment', val: segment })
      this.locallyUpdateSegments([this.editingSegment])
    }
  },

  mounted () {
    this.setInitialValues()
  }
}
</script>
