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
 * Checks whether a tag or topic title is already used in the procedure, before the request is sent.
 *
 */
export function useTagTitleCheck () {
  const store = useStore()

  const tags = computed(() => store.state[TAG].items)
  const tagTopics = computed(() => store.state[TAG_TOPIC].items)

  /**
   * @param {String} title
   * @param {String} excludeId  ID of the tag being edited, so its own title does not count as duplicate
   */
  const isTagTitleTaken = (title, excludeId = '') => Object.values(tags.value)
    .some(({ attributes, id }) => id !== excludeId && attributes.title.trim() === title.trim())

  /**
   * @param {String} title
   * @param {String} excludeId  ID of the topic being edited, so its own title does not count as duplicate
   */
  const isTopicTitleTaken = (title, excludeId = '') => Object.values(tagTopics.value)
    .some(({ attributes, id }) => id !== excludeId && attributes.title.trim().localeCompare(title.trim(), undefined, { sensitivity: 'accent' }) === 0)

  return {
    isTagTitleTaken,
    isTopicTitleTaken,
  }
}
