/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { computed, onMounted, ref } from 'vue'
import { dpRpc } from '@demos-europe/demosplan-ui'
import { useStore } from 'vuex'

const TAG = 'Tag'
const TAG_TOPIC = 'TagTopic'

/**
 * Natural sort for topic titles ("1, 2, 3, 11" instead of "1, 11, 2, 3")
 */
const compareTitles = (a, b) => a.attributes.title.localeCompare(b.attributes.title, undefined, { numeric: true, sensitivity: 'base' })

const toTreeLeaf = ({ attributes, id, relationships, type }) => ({
  attributes,
  id,
  relationships: {
    boilerplate: relationships?.boilerplate?.get ? relationships.boilerplate.get() : null,
  },
  type,
})

const toTreeBranch = ({ attributes, id, relationships, type }) => {
  const tags = relationships?.tags?.data.length > 0 ? Object.values(relationships.tags.list()) : []

  return {
    attributes,
    children: tags.map(toTreeLeaf),
    id,
    relationships,
    type,
  }
}

/**
 * Shared state and logic for the tag/topic list of a procedure.
 * Used by the core TagsList and by project-specific variants that only differ in markup.
 *
 * Requires the `Tag` and `TagTopic` api stores to be registered.
 *
 * @example
 * import { useTagsList } from '@DpJs/composables/useTagsList'
 *
 * export default {
 *   setup () {
 *     return useTagsList()
 *   },
 * }
 */
export function useTagsList () {
  const store = useStore()

  const dataIsRequested = ref(false)
  const isInEditState = ref('')

  const tags = computed(() => store.state[TAG].items)
  const tagTopics = computed(() => store.state[TAG_TOPIC].items)

  const transformedCategories = computed(() => Object.values(tagTopics.value)
    .sort(compareTitles)
    .map(toTreeBranch))

  const itemsByType = {
    [TAG]: tags,
    [TAG_TOPIC]: tagTopics,
  }

  const setItem = (type, item) => store.commit(`${type}/setItem`, item)

  const setTopicTags = (topic, tagsData) => {
    setItem(TAG_TOPIC, {
      id: topic.id,
      type: TAG_TOPIC,
      attributes: topic.attributes,
      relationships: { ...topic.relationships, tags: { data: tagsData } },
    })
  }

  // Apply tagList.reorder RPC response — sync sortIndex in the Tag store for every changed tag
  const applyReorderResponse = (response) => {
    const result = response.data[0].result

    for (const [id, { sortIndex }] of Object.entries(result)) {
      const tag = tags.value[id]

      if (tag) {
        setItem(TAG, {
          ...tag,
          attributes: { ...tag.attributes, sortIndex },
        })
      }
    }

    dplan.notify.confirm(Translator.trans('confirm.saved'))
  }

  /**
   * Call tagList.reorder; on failure run rollback and notify the user
   */
  const persistReorder = (params, rollback) => {
    dpRpc('tagList.reorder', params)
      .then(applyReorderResponse)
      .catch(error => {
        console.error(error)
        rollback()
        dplan.notify.error(Translator.trans('error.changes.not.saved'))
      })
  }

  const closeEditForm = () => {
    isInEditState.value = ''
  }

  const setEditState = ({ id }) => {
    isInEditState.value = id
  }

  const isBranch = ({ node }) => node.type === TAG_TOPIC

  // Persist cross-topic move — optimistic update on both topics + rollback
  const crossTopicReorder = (tagId, newIndex, targetTopicId) => {
    const newParent = tagTopics.value[targetTopicId]
    const oldParent = Object.values(tagTopics.value).find(topic =>
      topic.relationships?.tags.data.some(tag => tag.id === tagId),
    )

    if (!newParent || !oldParent) {
      return
    }

    const oldSourceData = [...oldParent.relationships.tags.data]
    const oldTargetData = [...(newParent.relationships?.tags?.data || [])]

    const newTargetData = [...oldTargetData]
    newTargetData.splice(newIndex, 0, { type: TAG, id: tagId })

    setTopicTags(oldParent, oldSourceData.filter(tag => tag.id !== tagId))
    setTopicTags(newParent, newTargetData)

    persistReorder({ tagId, topicId: targetTopicId, newIndex }, () => {
      setTopicTags(oldParent, oldSourceData)
      setTopicTags(newParent, oldTargetData)
    })
  }

  // Persist new tag order within a topic — optimistic update + rollback
  const reorderTagInTopic = (parentId, tagId, newIndex) => {
    const topic = tagTopics.value[parentId]

    if (!topic) {
      return
    }

    const oldTagsData = [...(topic.relationships?.tags?.data || [])]
    const newTagsData = oldTagsData.filter(tag => tag.id !== tagId)
    newTagsData.splice(newIndex, 0, { type: TAG, id: tagId })

    setTopicTags(topic, newTagsData)

    persistReorder({ tagId, topicId: parentId, newIndex }, () => {
      setTopicTags(topic, oldTagsData)
    })
  }

  const handleTagReorder = (event, item, parentId) => {
    if (event.oldIndex === event.newIndex && event.from === event.to) {
      return
    }

    if (event.from === event.to) {
      reorderTagInTopic(parentId, item.id, event.newIndex)
    } else {
      crossTopicReorder(item.id, event.newIndex, event.to.id)
    }
  }

  const loadTagsAndTopics = () => {
    if (dataIsRequested.value) {
      return
    }

    dataIsRequested.value = true

    store.dispatch(`${TAG_TOPIC}/list`, {
      fields: {
        Tag: ['boilerplate', 'sortIndex', 'title'].join(),
        TagTopic: ['title', 'tags'].join(),
        Boilerplate: ['title'].join(),
      },
      include: 'tags,tags.boilerplate',
      sort: 'title',
    }).then(() => {
      dataIsRequested.value = false
    })
  }

  const deleteItem = (item) => {
    dpRpc('bulk.delete.tags.and.topics', { ids: [item] })
      .then(loadTagsAndTopics)
  }

  const save = ({ id, attributes, type, isTitleChanged }) => {
    if (!id || !isTitleChanged || !itemsByType[type]) {
      closeEditForm()

      return
    }

    setItem(type, {
      attributes,
      id,
      relationships: itemsByType[type].value[id]?.relationships,
      type,
    })

    store.dispatch(`${type}/save`, id)
      .then(closeEditForm)
  }

  onMounted(loadTagsAndTopics)

  return {
    closeEditForm,
    dataIsRequested,
    deleteItem,
    handleTagReorder,
    isBranch,
    isInEditState,
    loadTagsAndTopics,
    save,
    setEditState,
    transformedCategories,
  }
}
