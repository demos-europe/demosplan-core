/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'
import { ref } from 'vue'

/**
 * Shared "unlock a segment" behaviour for the segment list components.
 * Owns the unlock modal template ref, the segment currently being unlocked
 * and the JSON:API update request.
 *
 * Bind the returned `unlockModal` via `ref="unlockModal"` on the SegmentUnlockModal,
 * wire `@unlock` to `unlockSegment`, and pass a component-specific refetch as `onSuccess`:
 *   @unlock="payload => unlockSegment(payload, () => fetchSegments(...))"
 *
 * @returns {Object} {
 *   unlockModal,       // Template ref for the SegmentUnlockModal; bind via ref="unlockModal"
 *   openUnlockModal,   // (segment) => void — remembers the segment and opens the modal
 *   unlockSegment,     // (payload, onSuccess?) => Promise
 * }
 */
export function useSegmentUnlock () {
  const segmentToUnlock = ref(null)
  const unlockModal = ref(null)

  function openUnlockModal (segment) {
    segmentToUnlock.value = segment
    unlockModal.value?.toggle()
  }

  /**
   * Receives the SegmentUnlockModal `@unlock` event payload `{ assignee, place }` (the values chosen in the modal).
   * Moves the remembered segment to `place` (this is what unlocks it), sets `assignee`, then runs `onSuccess`.
   */
  function unlockSegment ({ assignee, place }, onSuccess) {
    const assigneeRel = assignee.id === 'noAssigneeId' ?
      { data: null } :
      { data: { id: assignee.id, type: 'AssignableUser' } }

    const payload = {
      data: {
        id: segmentToUnlock.value.id,
        type: 'StatementSegment',
        relationships: {
          assignee: assigneeRel,
          place: { data: { id: place.id, type: 'Place' } },
        },
      },
    }

    return dpApi.patch(
      Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: segmentToUnlock.value.id }),
      {},
      payload,
    )
      .then(() => {
        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        onSuccess?.()
      })
      .catch((err) => {
        console.error(err)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))
      })
  }

  return { unlockModal, openUnlockModal, unlockSegment }
}
