<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="side-bar">
    <dp-label
      :text="Translator.trans('tags')"
      for="searchSelect"
      class="u-mb-0" />

    <div
      v-if="editingSegment && editingSegment.tags.length > 0"
      class="u-mt-0_5 u-mb-0_5">
      <div
        v-for="(tag, idx) in editingSegment.tags"
        :key="`tag_${idx}`"
        class="tag flex-inline font-size-small u-mr-0_25 u-mb-0_25">
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

    <div class="u-mb">
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
      class="u-mb-0_5">
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

    <div>
      <dp-select
        id="setPlace"
        v-model="selectedPlaceId"
        class="u-mb-0_5"
        :label="{
          text: Translator.trans('workflow.place')
        }"
        :options="availablePlaces"
        required
        :show-placeholder="false" />
      <dp-select
        id="assignUser"
        v-model="selectedAssigneeId"
        class="u-mb"
        :label="{
          text: Translator.trans('assignee')
        }"
        :options="assignableUsers"
        :show-placeholder="false" />
    </div>

    <dp-button-row
      align="left"
      secondary
      primary
      @primary-action="save"
      @secondary-action="$emit('abort')" />
  </div>
</template>

<script>
import { DpButtonRow, DpIcon, DpLabel, DpSelect, hasOwnProp } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import DpCreateTag from './DpCreateTag'
import SearchSelect from './SearchSelect'
import TagSelect from './TagSelect'

export default {
  name: 'SideBar',

  components: {
    DpButtonRow,
    DpCreateTag,
    DpIcon,
    DpLabel,
    DpSelect,
    SearchSelect,
    TagSelect
  },

  data () {
    return {
      selectedAssigneeId: '',
      selectedPlaceId: '',
      showCreateForm: false
    }
  },

  computed: {
    ...mapGetters('splitstatement', {
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
      return this.selectedAssigneeId !== this.initialAssigneeId
    },

    currentSegment () {
      if (this.editingSegment && this.segment(this.editingSegment.id)) {
        return JSON.parse(JSON.stringify(this.segment(this.editingSegment.id)))
      }

      return null
    },

    initialAssigneeId () {
      return this.currentSegment ? this.currentSegment.assigneeId : ''
    },

    initialPlaceId () {
      return this.currentSegment
        ? this.currentSegment.placeId
        : this.availablePlaces.length > 0
          ? this.availablePlaces[0].value
          : null
    },

    needsUpdate () {
      return this.assigneeNeedsUpdate || this.placeNeedsUpdate
    },

    placeNeedsUpdate () {
      return this.selectedPlaceId !== this.initialPlaceId || this.editingSegment.id === null
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
    ...mapActions('splitstatement', [
      'updateCurrentTags'
    ]),
    ...mapMutations('splitstatement', [
      'locallyUpdateSegments',
      'setProperty'
    ]),

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
      this.selectedAssigneeId = this.initialAssigneeId
      this.selectedPlaceId = this.initialPlaceId || this.availablePlaces[0].value
    },

    updateSegment () {
      const segment = { ...this.editingSegment }
      if (this.assigneeNeedsUpdate) {
        if (this.selectedAssigneeId === '' || this.selectedAssigneeId === 'noAssigneeId') {
          delete segment.assigneeId
        } else {
          segment.assigneeId = this.selectedAssigneeId
        }
      }
      if (this.placeNeedsUpdate) {
        // Place can't be empty
        if (this.selectedPlaceId !== '') {
          segment.placeId = this.selectedPlaceId
          const place = this.availablePlaces.find(place => {
            return place.value === this.selectedPlaceId
          })
          segment.place = place ? { id: place.value, name: place.label } : { id: this.availablePlaces[0].value, name: this.availablePlaces[0].label }
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
