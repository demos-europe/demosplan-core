/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { checkResponse, dpApi } from '@demos-europe/demosplan-ui'

/**
 * Validates if a string is a valid UUID v4 format
 * @param {string} uuid - The UUID string to validate
 * @returns {boolean} True if valid UUID format
 */
export function isValidUUID (uuid) {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
  return uuidRegex.test(uuid)
}

/**
 * Composable for segment navigation with pagination
 * Handles URL-based navigation to specific segments with automatic pagination
 *
 * @param {string} statementId - The parent statement ID
 * @param {string} storageKey - LocalStorage key for pagination persistence
 * @param {Object} paginationRef - Reactive pagination object reference
 * @param {Object} defaultPagination - Default pagination settings
 * @returns {Object} Methods for segment navigation
 */
export function useSegmentNavigation (statementId, storageKey, paginationRef, defaultPagination) {
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
        statementId: statementId
      })

      return await dpApi.get(url)
        .then(checkResponse)
    } catch (error) {
      console.error('Failed to get segment position:', error)
      return null
    }
  }

  /**
   * Calculates the correct page for a segment and updates pagination
   * Should be called once during component initialization if segment param exists
   *
   * @param {Object} context - Context object
   * @param {boolean} context.hasNavigatedToSegment - Flag to prevent re-calculation
   * @returns {Promise<Object>} Object with calculatedPage and shouldCalculate flag
   */
  async function calculatePageForSegment (context) {
    const queryParams = new URLSearchParams(window.location.search)
    const targetSegmentId = queryParams.get('segment')

    // Only calculate if we have a segment param and haven't navigated yet
    if (!targetSegmentId || context.hasNavigatedToSegment) {
      return {
        shouldCalculate: false,
        calculatedPage: null
      }
    }

    // Get segment position to calculate correct page
    const positionData = await getSegmentPosition(targetSegmentId)

    if (!positionData || !positionData.position) {
      return {
        shouldCalculate: false,
        calculatedPage: null
      }
    }

    const perPage = paginationRef.perPage || defaultPagination.perPage
    const calculatedPage = Math.ceil(positionData.position / perPage)

    // Update localStorage with calculated page
    const paginationData = { currentPage: calculatedPage, perPage: perPage }
    window.localStorage.setItem(storageKey, JSON.stringify(paginationData))

    // Update pagination object
    paginationRef.currentPage = calculatedPage
    paginationRef.perPage = perPage

    return {
      shouldCalculate: true,
      calculatedPage: calculatedPage
    }
  }

  /**
   * Initializes pagination for components with segment navigation
   * Handles URL parameter detection and localStorage interaction
   *
   * @param {Function} initPaginationCallback - Component's initPagination method
   * @returns {Object} Initial pagination state
   */
  function initializeSegmentPagination (initPaginationCallback) {
    const queryParams = new URLSearchParams(window.location.search)
    const hasSegmentParam = queryParams.has('segment')

    if (hasSegmentParam) {
      // Initialize with default pagination, ignoring localStorage for page number
      // The correct page will be calculated in fetchSegments
      const pagination = {
        currentPage: 1,
        perPage: defaultPagination.perPage
      }

      // Check localStorage only for perPage setting
      if (window.localStorage.getItem(storageKey)) {
        const stored = JSON.parse(window.localStorage.getItem(storageKey))
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
    getSegmentPosition,
    calculatePageForSegment,
    initializeSegmentPagination,
    isValidUUID
  }
}
