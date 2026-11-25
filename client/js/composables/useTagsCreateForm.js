/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { computed, getCurrentInstance, ref, watch } from 'vue'
import { useStore } from 'vuex'

const TAG = 'Tag'
const TAG_TOPIC = 'TagTopic'

const emptyTag = () => ({ title: '', topic: '' })

/**
 * Shared state and logic for creating tags and tag topics.
 * Used by the core TagsCreateForm and by project-specific variants that only differ in markup.
 *
 * Requires the `Tag` and `TagTopic` api stores to be registered.
 * Emits `tag:created` on $root after a tag was created (listened to by addons).
 *
 * @param {Object} props                     Component props
 * @param {Boolean} props.isMasterProcedure
 * @param {String} props.procedureId
 *
 * @example
 * import { useTagsCreateForm } from '@DpJs/composables/useTagsCreateForm'
 *
 * export default {
 *   props: { isMasterProcedure: Boolean, procedureId: String },
 *   setup (props) {
 *     return useTagsCreateForm(props)
 *   },
 * }
 */
export function useTagsCreateForm (props) {
  const store = useStore()
  const { proxy } = getCurrentInstance()

  const currentForm = ref('')
  const isSaving = ref(false)
  const newTag = ref(emptyTag())
  const newTopic = ref({ title: '' })
  const tagError = ref('')
  const topicError = ref('')

  // Remove the error state as soon as the user changes the title
  watch(() => newTag.value.title, () => { tagError.value = '' })
  watch(() => newTopic.value.title, () => { topicError.value = '' })

  const tags = computed(() => store.state[TAG].items)
  const tagTopics = computed(() => store.state[TAG_TOPIC].items)

  /*
   * Mirror backend uniqueness rules for early validation:
   * - Tag titles: exact match (TagResourceType::checkTitleUnique)
   * - TagTopic titles: case-insensitive match (TagTopicResourceType::checkTitleUniqueForProcedure)
   */
  const isTagTitleTaken = (title) => Object.values(tags.value)
    .some(({ attributes }) => attributes.title.trim() === title.trim())

  const isTopicTitleTaken = (title) => Object.values(tagTopics.value)
    .some(({ attributes }) => attributes.title.trim().localeCompare(title.trim(), undefined, { sensitivity: 'accent' }) === 0)

  const topicsAsOptions = computed(() => Object.values(tagTopics.value)
    .map(({ attributes, id }) => ({
      label: attributes.title,
      value: id,
    }))
    .sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true, sensitivity: 'base' })))

  const resetFormData = () => {
    newTopic.value.title = ''
    newTag.value = emptyTag()
  }

  const closeForm = () => {
    currentForm.value = ''
    resetFormData()
    tagError.value = ''
    topicError.value = ''
  }

  /**
   * @param {'tag'|'topic'} form
   */
  const openForm = (form) => {
    currentForm.value = form
  }
  
  const updateForm = ({ key, value }) => {
    newTag.value[key] = value
  }

  const addTagToTopic = (topicId, tagId) => {
    const parentTopic = tagTopics.value[topicId]
    const tagIdentifier = { type: TAG, id: tagId }

    store.commit(`${TAG_TOPIC}/setItem`, {
      id: topicId,
      type: TAG_TOPIC,
      attributes: parentTopic.attributes,
      relationships: parentTopic.relationships ?
        {
          ...parentTopic.relationships,
          tags: {
            data: parentTopic.relationships.tags.data.concat(tagIdentifier),
          },
        } :
        {
          tags: {
            data: [tagIdentifier],
          },
        },
    })
  }

  const saveNewTag = () => {
    const topicId = newTag.value.topic

    if (isTagTitleTaken(newTag.value.title)) {
      tagError.value = Translator.trans('tag.name.unique.error')

      return Promise.resolve()
    }

    isSaving.value = true

    return store.dispatch(`${TAG}/create`, {
      type: TAG,
      attributes: {
        title: newTag.value.title,
      },
      relationships: {
        topic: {
          data: {
            type: TAG_TOPIC,
            id: topicId,
          },
        },
      },
    })
      .then(response => {
        if (!response.data || !response.data.Tag || !tagTopics.value[topicId]) {
          return
        }

        const newTagId = Object.keys(response.data.Tag)[0]

        addTagToTopic(topicId, newTagId)
        store.dispatch(`${TAG_TOPIC}/save`, topicId)
        proxy.$root.$emit('tag:created', newTagId)
        closeForm()
      })
      .finally(() => {
        isSaving.value = false
      })
  }

  const saveNewTopic = () => {
    if (isTopicTitleTaken(newTopic.value.title)) {
      topicError.value = Translator.trans('tag.topic.name.duplicate.error')

      return Promise.resolve()
    }

    isSaving.value = true

    return store.dispatch(`${TAG_TOPIC}/create`, {
      type: TAG_TOPIC,
      attributes: {
        title: newTopic.value.title,
      },
      relationships: {
        procedure: {
          data: {
            type: props.isMasterProcedure ? 'ProcedureTemplate' : 'Procedure',
            id: props.procedureId,
          },
        },
      },
    })
      .then(response => {
        if (!response.data) {
          return
        }

        closeForm()
      })
      .finally(() => {
        isSaving.value = false
      })
  }

  return {
    closeForm,
    currentForm,
    isSaving,
    newTag,
    newTopic,
    openForm,
    saveNewTag,
    saveNewTopic,
    tagError,
    topicError,
    topicsAsOptions,
    updateForm,
  }
}
