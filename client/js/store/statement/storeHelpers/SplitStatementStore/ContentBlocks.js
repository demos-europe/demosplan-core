/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { hasOwnProp } from '@demos-europe/demosplan-ui'

/**
 * Normalizes a segment's `place` into `{ id, name }`.
 *
 * The frontend's own writes always send `{ id, name }`. The backend's read-side transformer
 * (for an already-segmented statement's live-generated contentBlocks payload) sends a bare
 * place id string, or `null` when no place was set.
 */
function normalizePlace (place, availablePlaces) {
  if (place === null || place === undefined) {
    return availablePlaces.length > 0 ?
      { id: availablePlaces[0].value, name: availablePlaces[0].label } :
      { id: '', name: '' }
  }

  const id = typeof place === 'string' ? place : place.id
  const knownPlace = availablePlaces.find(availablePlace => availablePlace.value === id)

  return { id, name: knownPlace?.label ?? place.name ?? '' }
}

/**
 * Derives store state from the legacy `textualReference` + `segments` shape.
 */
function deriveFromLegacy (attributes, availablePlaces) {
  const segments = attributes.segments

  // This should not be neccessary once the BE always sends a place
  segments.forEach((segment, idx) => {
    if (hasOwnProp(segment, 'place') === false) {
      segments[idx].place = availablePlaces.length > 0 ?
        { id: availablePlaces[0].value, name: availablePlaces[0].label } :
        { id: '', name: '' }
    }
  })

  return {
    segments,
    initText: attributes.textualReference,
    contentBlocks: [],
  }
}

/**
 * Derives store state from the order-based `contentBlocks` shape.
 *
 * Rebuilds `initText` by wrapping each `segment` block's HTML in a `<segment-mark>` element
 * (reusing the existing, unchanged ProseMirror parsing pipeline) and leaving `textSection`
 * blocks unwrapped, so leftover text renders as plain, non-taggable content. `segments` is
 * built exclusively from `segment` blocks - `textSection` blocks never become segments.
 */
function deriveFromContentBlocks (contentBlocks, availablePlaces) {
  const sortedBlocks = [...contentBlocks].sort((a, b) => a.order - b.order)

  const initText = sortedBlocks.map(block => {
    const html = block.textRaw ?? block.text

    if (block.type !== 'segment') {
      return html
    }

    const isConfirmed = block.status === 'confirmed'

    return `<segment-mark data-segment-id="${block.id}" data-range-confirmed="${isConfirmed}">${html}</segment-mark>`
  }).join('')

  const segments = sortedBlocks
    .filter(block => block.type === 'segment')
    .map(block => ({
      id: block.id,
      status: block.status ?? 'confirmed',
      tags: block.tags ?? [],
      place: normalizePlace(block.place, availablePlaces),
      ...(block.assigneeId ? { assigneeId: block.assigneeId } : {}),
      ...(block.deadline ? { deadline: block.deadline } : {}),
    }))

  return { segments, initText, contentBlocks: sortedBlocks }
}

export { deriveFromContentBlocks, deriveFromLegacy, normalizePlace }
