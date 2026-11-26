/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { computed, ref } from 'vue'
import { useTagTitleCheck } from './useTagTitleCheck'

/**
 * Shared state and logic for a single row (tag or tag topic) of the tag list.
 * Used by the core TagListEditForm and by project-specific variants that only differ in markup.
 *
 * The component must declare the emits `abort`, `delete`, `edit`, `save`.
 *
 * @param {Object} props
 * @param {String} props.isInEditState  Id of the item currently in edit mode
 * @param {Object} props.nodeElement    Tag or TagTopic tree node
 * @param {Function} emit               Component emit function
 *
 * @example
 * import { useTagListEditForm } from '@DpJs/composables/useTagListEditForm'
 *
 * export default {
 *   emits: ['abort', 'delete', 'edit', 'save'],
 *   setup (props, { emit }) {
 *     return useTagListEditForm(props, emit)
 *   },
 * }
 */
export function useTagListEditForm (props, emit) {
  const unsavedItem = ref({ title: '' })

  const { getTopicIdOfTag, isTagTitleTaken, isTopicTitleTaken } = useTagTitleCheck()

  const isEditing = computed(() => props.isInEditState === props.nodeElement.id)

  const boilerplateTitle = computed(() => props.nodeElement.relationships?.boilerplate?.attributes?.title ?? '')

  const confirmMessage = computed(() => {
    const { type, attributes, children } = props.nodeElement

    if (type !== 'TagTopic') {
      return Translator.trans('check.tag.delete', { tag: attributes.title })
    }

    const topicConfirmMessage = children?.length === 0 ? 'check.topic.delete' : 'check.topic.delete.tags'

    return Translator.trans(topicConfirmMessage, { topic: attributes.title })
  })

  const abort = () => {
    emit('abort')
  }

  const confirmDelete = () => {
    const { id, type } = props.nodeElement

    emit('delete', { id, type })
  }

  const editItem = () => {
    unsavedItem.value = { ...props.nodeElement.attributes }
    emit('edit', { id: props.nodeElement.id, type: props.nodeElement.type })
  }

  const saveItem = () => {
    const { attributes, id, type } = props.nodeElement
    const isTitleChanged = attributes.title !== unsavedItem.value.title
    const isTopic = type === 'TagTopic'
    const isTitleTaken = isTopic ?
      isTopicTitleTaken(unsavedItem.value.title, id) :
      isTagTitleTaken(unsavedItem.value.title, getTopicIdOfTag(id), id)

    // The item itself is excluded, so saving an unchanged title is not reported as duplicate
    if (isTitleChanged && isTitleTaken) {
      const errorKey = isTopic ? 'tag.topic.title.duplicate.error' : 'tag.title.duplicate.in.topic.error'

      dplan.notify.error(Translator.trans(errorKey))

      return
    }

    emit('save', {
      id,
      attributes: unsavedItem.value,
      type,
      isTitleChanged,
    })
  }

  return {
    abort,
    boilerplateTitle,
    confirmDelete,
    confirmMessage,
    editItem,
    isEditing,
    saveItem,
    unsavedItem,
  }
}
