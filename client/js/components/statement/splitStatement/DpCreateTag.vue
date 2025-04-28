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

    <addon-wrapper
      class="block mb-4"
      hook-name="tag.create.form"
      :addon-props="{
        hasInlineNotification: false
      }" />

    <dp-button-row
      alignment="left"
      primary
      secondary
      variant="outline"
      @primary-action="dpValidateAction('createTag', save, false)"
      @secondary-action="closeForm" />
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
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'

export default {
  name: 'DpCreateTag',

  components: {
    AddonWrapper,
    DpButtonRow,
    DpInput,
    DpLabel,
    DpMultiselect,
    DpResettableInput
  },

  mixins: [dpValidateMixin],

  emits: [
    'closeCreateForm'
  ],

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

    closeForm () {
      this.$emit('closeCreateForm')
    },

    createTag (topicId) {
      const tagPayload = this.prepareTagPayload()

      this.createTagAction({ tag: this.tag, topicId })
        .then((response) => {
          const newTag = response.data.data

          newTag.relationships = {
            topic: {
              data: {
                id: topicId,
                type: 'TagTopic'
              }
            }
          }

          // Update tags in store
          this.updateTags(newTag)
          this.$root.$emit('tag:created', newTag.id)
          this.closeForm()
        })
        .catch(() => {
          // Reset tags in store
          this.updateTags(tagPayload)
          this.closeForm()
        })
    },

    createTopicAndTag () {
      const tagPayload = this.prepareTagPayload()
      const topicPayload = this.prepareTopicPayload()

      this.createTopicAction(this.tagTopic)
        .then(({ data }) => {
          const { id, attributes } = data.data

          // Add id to tagTopic in store
          this.updateProperty({
            prop: 'tagTopics',
            obj: { attributes: { title: attributes.title }, id, type: 'TagTopic' }
          })

          this.createTag(id, tagPayload)
        })
        .catch(() => {
          // Reset tags in store
          this.updateTags(tagPayload)
          // Reset tagTopics in store
          this.updateProperty({ prop: 'tagTopics', obj: topicPayload })
          this.closeForm()
        })
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

    prepareTagPayload () {
      return {
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
    },

    prepareTopicPayload () {
      return {
        attributes: {
          title: this.tagTopic.title
        },
        id: '',
        type: 'TagTopic'
      }
    },

    save () {
      if (this.handleError()) return

      const isNewTopic = this.tagTopic.id === ''

      if (isNewTopic) {
        this.createTopicAndTag()
      } else {
        this.createTag(this.tagTopic.id)
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
