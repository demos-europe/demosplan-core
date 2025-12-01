/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'

/**
 * Validates if a string is a valid UUID v4 format
 * @param {string} uuid - The UUID string to validate
 * @returns {boolean} True if valid UUID format
 */
function isValidUUID (uuid) {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
  return uuidRegex.test(uuid)
}

/**
 * Removes the segment parameter from the URL without reloading the page
 */
function removeSegmentParameter () {
  const url = new URL(globalThis.location)
  url.searchParams.delete('segment')
  globalThis.history.replaceState({}, '', url)
}

/**
 * Utility for segment navigation with pagination
 * Handles URL-based navigation to specific segments with automatic pagination
 *
 * @param {Object} options - Data needed for handling segment navigation
 * @param {string} options.statementId - The parent statement ID
 * @param {string} options.storageKey - LocalStorage key for persisting pagination
 * @param {number} options.currentPerPage - Current items per page setting
 * @param {Object} options.defaultPagination - Default pagination settings
 * @returns {Object} Methods for segment navigation
 */
export function handleSegmentNavigation ({ statementId, storageKey, currentPerPage, defaultPagination }) {
  /**
   * Fetches the position of a segment within its statement
   * @param {string} segmentId - The segment ID to get position for
   * @returns {Promise<Object|null>} Position data or null on error
   */
  async function getSegmentPosition (segmentId) {
    // Validate UUID format before making API call
    if (!isValidUUID(segmentId)) {
      console.error('Invalid segment ID format:', segmentId)
      return null
    }

    try {
      const url = Routing.generate('dplan_segment_position', {
        segmentId: segmentId,
        statementId: statementId,
      })

      return await dpApi.get(url)
    } catch (error) {
      console.error('Failed to get segment position:', error)
      return null
    }
  }

  /**
   * Calculates the correct page for a segment
   * Returns updated pagination values without mutating component state
   * Does NOT remove segment parameter - that should happen after scrolling completes
   *
   * @returns {Promise<Object>} Object with pagination updates and segmentId, or all null if no calculation needed
   */
  async function calculatePageForSegment () {
    const queryParams = new URLSearchParams(globalThis.location.search)
    const targetSegmentId = queryParams.get('segment')

    // Only calculate if we have a segment parameter in the URL
    if (!targetSegmentId) {
      return {
        calculatedPage: null,
        perPage: null,
        segmentId: null,
      }
    }

    // Get segment position to calculate correct page
    const positionData = await getSegmentPosition(targetSegmentId)

    if (!positionData?.data?.position) {
      // Remove invalid segment parameter from URL
      removeSegmentParameter()

      return {
        calculatedPage: null,
        perPage: null,
        segmentId: null,
      }
    }

    const perPage = currentPerPage || defaultPagination.perPage
    const calculatedPage = Math.ceil(positionData.data.position / perPage)

    // Update localStorage with calculated page
    const paginationData = { currentPage: calculatedPage, perPage }
    globalThis.localStorage.setItem(storageKey, JSON.stringify(paginationData))

    // Return segment ID so component can use it for scrolling before cleanup
    return {
      calculatedPage,
      perPage,
      segmentId: targetSegmentId,
    }
  }

  /**
   * Initializes pagination for components with segment navigation
   * Handles URL parameter detection and localStorage interaction
   *
   * @param {Function} initPaginationCallback - Component's initPagination method
   * @returns {Object|null} Initial pagination state or null to use component's default
   */
  function initializeSegmentPagination (initPaginationCallback) {
    const queryParams = new URLSearchParams(globalThis.location.search)
    const hasSegmentParam = queryParams.has('segment')

    // Only override initialization if we have a segment parameter
    if (hasSegmentParam) {
      /*
       * Initialize with default pagination, ignoring localStorage for page number
       * The correct page will be calculated in fetchSegments
       */
      const pagination = {
        currentPage: 1,
        perPage: defaultPagination.perPage,
      }

      // Check localStorage only for perPage setting
      if (globalThis.localStorage.getItem(storageKey)) {
        const stored = JSON.parse(globalThis.localStorage.getItem(storageKey))
        pagination.perPage = stored.perPage || defaultPagination.perPage
      }

      return pagination
    } else {
      // Normal flow - use component's initPagination method
      initPaginationCallback()
      return null // Indicates to use component's existing pagination
    }
  }

  return {
    calculatePageForSegment,
    initializeSegmentPagination,
    removeSegmentParameter,
  }
}
