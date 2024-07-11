<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    ref="sideBar"
    class="side-bar">
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
      class="overflow-y-scroll h-1/2 u-mb-0_5 u-pr-0_25">
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
      <label
        class="inline-block u-mb-0"
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

    <dp-button-row
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
      selectedAssignee: null,
      selectedPlace: null,
      showCreateForm: false
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

    getSideBarHeight () {
      const header = document.getElementById('header')
      const footer = document.getElementById('footer')

      const viewportHeight = window.innerHeight
      const headerHeight = header.offsetHeight
      const footerHeight = footer.offsetHeight

      const mainHeight = viewportHeight - headerHeight - footerHeight
      const sideBarHeight = mainHeight * 0.8 // 75% from the main height

      return `${sideBarHeight}px`
    },

    updateSidebarHeight () {
      const height = this.getSideBarHeight()

      if (this.$refs.sideBar) {
        this.$refs.sideBar.style.height = height
      }
    }
  },

  mounted () {
    this.setInitialValues()
    this.updateSidebarHeight() // Set initial height
    window.addEventListener('resize', this.updateSidebarHeight)
  },

  beforeDestroy () {
    window.removeEventListener('resize', this.updateSidebarHeight)
  }
}
</script>

<style scoped lang="scss">
// TODO: This can be replaced with tailwindcss as soon as we found a solution to implement it in addons.
.overflow-scroll-list {
  overflow-y: auto;
  max-height: 300px;
  min-height: 100px;
  display: flex;
  flex-direction: column;
}
</style>
