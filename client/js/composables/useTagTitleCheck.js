/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { computed } from 'vue'
import { useStore } from 'vuex'

const TAG = 'Tag'
const TAG_TOPIC = 'TagTopic'

/**
 * Checks whether a tag title is already used in its topic or a topic title is already used in the procedure,
 * before the request is sent. Titles are compared ignoring case and leading/trailing spaces.
 */
export function useTagTitleCheck () {
  const store = useStore()

  const tags = computed(() => store.state[TAG].items)
  const tagTopics = computed(() => store.state[TAG_TOPIC].items)

  const normalizeTitle = title => title.trim().toLowerCase()

  /**
   * @param {String} tagId
   * @return {String} ID of the topic containing the tag, empty if not found
   */
  const getTopicIdOfTag = tagId => Object.values(tagTopics.value)
    .find(({ relationships }) => relationships?.tags?.data.some(({ id }) => id === tagId))?.id ?? ''

  /**
   * @param {String} title
   * @param {String} topicId
   * @param {String} excludeId  ID of the tag being edited, so its own title does not count as duplicate
   */
  const isTagTitleTaken = (title, topicId, excludeId = '') => {
    const tagIdsOfTopic = (tagTopics.value[topicId]?.relationships?.tags?.data ?? []).map(({ id }) => id)

    return tagIdsOfTopic.some(id => id !== excludeId &&
      tags.value[id] &&
      normalizeTitle(tags.value[id].attributes.title) === normalizeTitle(title))
  }

  /**
   * @param {String} title
   * @param {String} excludeId  ID of the topic being edited, so its own title does not count as duplicate
   */
  const isTopicTitleTaken = (title, excludeId = '') => Object.values(tagTopics.value)
    .some(({ attributes, id }) => id !== excludeId && normalizeTitle(attributes.title) === normalizeTitle(title))

  return {
    getTopicIdOfTag,
    isTagTitleTaken,
    isTopicTitleTaken,
  }
}
