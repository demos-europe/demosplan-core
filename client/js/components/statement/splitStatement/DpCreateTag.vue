<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div data-dp-validate="createTag">
    <!-- tag input -->
    <dp-input
      id="newTag"
      ref="tagInput"
      v-model="tag.title"
      class="u-mt-0_25"
      :label="{
        text: Translator.trans('tag')
      }"
      required />

    <dp-label
      :text="Translator.trans('topic')"
      for="newTagTopic"
      class="u-mt-0_25"
      required />
    <!-- topic select -->
    <dp-multiselect
      v-if="showSelect"
      ref="tagTopicSelect"
      v-model="tagTopic"
      class="u-mb-0_5"
      label="title"
      :options="tagTopics"
      required
      selection-controls
      track-by="id">
      <template v-slot:beforeList>
        <button
          @click="showInput"
          class="btn--blank o-link--default weight--bold u-ph-0_5 u-pv-0_5 text-left u-1-of-1 whitespace-nowrap">
          {{ Translator.trans('topic.new') }}
        </button>
      </template>
    </dp-multiselect>
    <!-- new topic input -->
    <dp-resettable-input
      v-else
      id="newTagTopic"
      ref="tagTopicInput"
      class="u-mb-0_5"
      @blur="handleClickOutside"
      @reset="handleReset"
      v-model="tagTopic.title"
      required />

    <dp-button-row
      align="left"
      primary
      secondary
      variant="outline"
      @primary-action="dpValidateAction('createTag', save, false)"
      @secondary-action="abort" />
  </div>
</template>

<script>
import {
  DpButtonRow,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpResettableInput,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'

export default {
  name: 'DpCreateTag',

  components: {
    DpButtonRow,
    DpInput,
    DpLabel,
    DpMultiselect,
    DpResettableInput
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      showSelect: true,
      tag: {
        title: ''
      },
      tagTopic: {
        title: '',
        id: ''
      }
    }
  },

  computed: {
    ...mapGetters('SplitStatement', {
      availableTags: 'availableTags',
      availableTagTopics: 'tagTopics'
    }),

    tagExists () {
      return this.availableTags.findIndex(el => el.attributes.title === this.tag.title) >= 0
    },

    tagTopicExists () {
      return this.availableTagTopics.findIndex(el => el.attributes.title === this.tagTopic.title) >= 0
    },

    tagTopics () {
      return this.availableTagTopics.map(topic => {
        return { title: topic.attributes.title, id: topic.id }
      })
    }
  },

  methods: {
    ...mapActions('SplitStatement', [
      'createTagAction',
      'createTopicAction',
      'updateCurrentTags'
    ]),

    ...mapMutations('SplitStatement', [
      'updateProperty'
    ]),

    abort () {
      this.$emit('close-create-form')
    },

    /**
     * When user clicks outside of tagTopic input, display multi select instead again
     */
    handleClickOutside () {
      if (this.tagTopic.title === '') {
        this.showSelect = true
        this.setDefaultTagTopic()
      }
    },

    handleReset () {
      this.tagTopic.title = ''
      this.handleClickOutside()
    },

    /**
     * Display error message if tag or tagTopic (if input is displayed) already exists
     */
    handleError () {
      let hasError = false
      if (this.tagExists) {
        hasError = true
        this.$refs.tagInput.$el.classList.add('border--error')
        this.$refs.tagInput.$el.addEventListener('input', () => {
          if (!this.tagExists) {
            this.$refs.tagInput.$el.classList.remove('border--error')
          }
        })
        dplan.notify.error(Translator.trans('error.tag.exists'))
      }

      if (this.tagTopicExists && !this.showSelect) {
        hasError = true
        this.$refs.tagTopicInput.$el.classList.add('border--error')
        this.$refs.tagTopicInput.$el.addEventListener('input', () => {
          if (!this.tagTopicExists) {
            this.$refs.tagTopicInput.$el.classList.remove('border--error')
          }
        })
        dplan.notify.error(Translator.trans('error.topic.exists'))
      }

      if (this.tagTopic.title === '' && this.showSelect) {
        hasError = true
        const activeInput = this.$refs.tagTopicSelect
        activeInput.$el.classList.add('border--error')
        activeInput.$el.addEventListener('input', () => {
          if (this.tagTopic.title !== '') {
            activeInput.$el && activeInput.$el.classList.remove('border--error')
          }
        })
        dplan.notify.error(Translator.trans('tag.topic.select.create'))
      }

      return hasError
    },

    save () {
      // Check if tag or tagTopic exists
      const hasError = this.handleError()
      const isNewTopic = this.tagTopic.id === ''

      if (!hasError) {
        this.$emit('close-create-form')

        // Prepare payload for tagTopics update
        const topic = {
          attributes: {
            title: this.tagTopic.title
          },
          id: '',
          type: 'TagTopic'
        }

        // Prepare payload for availableTags update
        const newTag = {
          attributes: {
            title: this.tag.title
          },
          relationships: {
            topic: {
              data: {
                id: this.tagTopic.id,
                type: 'TagTopic'
              }
            }
          },
          id: '',
          type: 'Tag'
        }

        // Optimistic update: add tag to store
        this.updateTags(newTag)

        // If topic doesn't exist yet, create it
        if (isNewTopic) {
          // Optimistic update: add tagTopic to store
          this.updateProperty({ prop: 'tagTopics', obj: topic })

          this.createTopicAction(this.tagTopic)
            .then((response) => {
              const topicId = response.data.data.id
              // Add id to tagTopic in store
              this.updateProperty({ prop: 'tagTopics', obj: { attributes: { title: response.data.data.attributes.title }, id: topicId, type: 'TagTopic' } })
              this.createTagAction({ tag: this.tag, topicId: topicId })
                .then((response) => {
                  const tagResource = response.data.data
                  tagResource.relationships = {
                    topic: {
                      data: {
                        id: topicId,
                        type: 'TagTopic'
                      }
                    }
                  }
                  this.updateTags(tagResource)
                })
                .catch(() => {
                  // Reset tags in store
                  this.updateTags(newTag)
                })
            })
            .catch(() => {
              // Reset tags in store
              this.updateTags(newTag)
              // Reset tagTopics in store
              this.updateProperty({ prop: 'tagTopics', obj: topic })
            })
        } else {
          this.createTagAction({ tag: this.tag, topicId: this.tagTopic.id })
            .then((response) => {
              const updatedAvailableTag = response.data.data
              this.updateTags(updatedAvailableTag)
            })
            .catch(() => {
              // Reset tags in store
              this.updateTags(newTag)
            })
        }
      }
    },

    /**
     * Set default tagTopic for multi select, remove it for input
     */
    setDefaultTagTopic () {
      this.tagTopic = { title: '', id: '' }
    },

    /**
     * Display input to create new topic, hide multi select
     * unselect default tagTopic
     * focus input
     */
    showInput () {
      this.toggleSelect()
      this.setDefaultTagTopic()
      this.$nextTick(() => {
        document.getElementById('newTagTopic').focus()
      })
    },

    /**
     * Toggle between multi select and input for tagTopic
     */
    toggleSelect () {
      this.showSelect = !this.showSelect
    },

    /**
     * Update tags in the store
     * @param tag  {Object} needs this format:
     * { attributes: { title: 'someTitle' }, id: 'tagId', type: 'Tag', relationships: { topic: { data: { id: 'topicId, type: 'TagTopic' } } } }
     */
    updateTags (tag) {
      this.updateProperty({ prop: 'availableTags', obj: tag })
      this.updateCurrentTags({ tagName: tag.attributes.title, id: tag.id })
      this.updateProperty({ prop: 'categorizedTags', obj: tag })
    }
  }
}
</script>
