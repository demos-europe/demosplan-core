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
 * Module-level shared Map for custom field definitions
 * Key: definitionSourceId (string)
 * Value: Array of custom field definitions
 */
const customFieldsDefinitions = new Map()

/**
 * Module-level shared Map for pending fetch promises
 * Prevents race conditions when multiple components fetch simultaneously
 * Key: definitionSourceId (string)
 * Value: Promise resolving to custom field definitions
 */
const pendingFetches = new Map()

/**
 * Module-level concurrency limiter for value fetches
 * Prevents 429 Too Many Requests when many components mount simultaneously (e.g., table rows)
 */
const MAX_CONCURRENT_VALUE_FETCHES = 5
let activeValueFetches = 0
const valueRequestQueue = []

function processValueQueue () {
  if (valueRequestQueue.length === 0 || activeValueFetches >= MAX_CONCURRENT_VALUE_FETCHES) {
    return
  }
  activeValueFetches++
  const { fn, resolve, reject } = valueRequestQueue.shift()
  fn()
    .then(resolve)
    .catch(reject)
    .finally(() => {
      activeValueFetches--
      processValueQueue()
    })
}

/**
 * Composable for managing custom field definitions and persistence
 * Provides methods to fetch field definitions and persist values via JSON:API
 * Each component instance gets its own loading/error state,
 * but all instances share the same cache Map.
 *
 * @returns {Object} {
 *   clearCustomFieldsDefinitions,
 *   fetchCustomFields,
 *   fetchCustomFieldValues,
 *   getCustomFieldsDefinitions,
 *   isLoading,
 *   updateCustomFields,
 * }
 *
 * @example Fetch definitions
 * const { fetchCustomFields } = useCustomFields()
 * fetchCustomFields(definitionSourceId)
 *   .then(definitions => console.log(definitions))
 *
 * @example Update values (batch update)
 * const { updateCustomFields } = useCustomFields()
 * updateCustomFields('Statement', statementId, [
 *   { id: 'field-1', value: ['option-1'] },
 *   { id: 'field-2', value: 'option-2' }
 * ])
 *   .then(() => console.log('Saved'))
 *   .catch(err => console.error(err))
 */
export function useCustomFields () {
  const isLoading = ref(false)

  /**
   * Clear custom field definitions for a specific procedure or all procedures
   * Useful for forcing a refresh after updates
   * Also clears any pending fetches
   *
   * @param {string|null} definitionSourceId - Procedure ID to clear, or null to clear all
   */
  const clearCustomFieldsDefinitions = (definitionSourceId = null) => {
    if (definitionSourceId) {
      customFieldsDefinitions.delete(definitionSourceId)
      pendingFetches.delete(definitionSourceId)
    } else {
      customFieldsDefinitions.clear()
      pendingFetches.clear()
    }
  }

  /**
   * Fetch custom fields for a given procedure ID
   * Uses shared Map for caching - only fetches once per procedure
   * Handles race conditions by caching pending promises
   *
   * @param {string} definitionSourceId - The procedure ID to fetch custom fields for
   * @returns {Promise<Array>} Promise resolving to array of custom field definitions
   */
  const fetchCustomFields = (definitionSourceId) => {
    // Return cached data if available
    if (customFieldsDefinitions.has(definitionSourceId)) {
      return Promise.resolve(customFieldsDefinitions.get(definitionSourceId))
    }

    /*
     * Return pending promise if fetch is already in progress
     * This prevents race conditions when multiple components mount simultaneously
     */
    if (pendingFetches.has(definitionSourceId)) {
      return pendingFetches.get(definitionSourceId)
    }

    isLoading.value = true

    const url = Routing.generate('api_resource_list', {
      resourceType: 'CustomField',
    })

    const params = {
      fields: {
        CustomField: ['name', 'description', 'options', 'fieldType', 'isRequired'].join(),
      },
      filter: {
        sourceEntityId: {
          condition: {
            path: 'sourceEntityId',
            value: definitionSourceId,
          },
        },
      },
    }

    // Create and cache the promise
    const fetchPromise = dpApi.get(url, params)
      .then(response => {
        const customFields = response.data.data || []

        // Cache the definitions in the shared Map
        customFieldsDefinitions.set(definitionSourceId, customFields)

        isLoading.value = false

        // Remove from pending fetches after successful completion
        pendingFetches.delete(definitionSourceId)

        return customFields
      })
      .catch(err => {
        isLoading.value = false

        // Remove from pending fetches on error to allow retry
        pendingFetches.delete(definitionSourceId)

        dplan.notify.notify('error', Translator.trans('custom.fields.error.loading'))
        throw err
      })

    // Cache the pending promise
    pendingFetches.set(definitionSourceId, fetchPromise)

    return fetchPromise
  }

  /**
   * Fetch custom field values for a specific resource
   * Uses module-level concurrency limiter to prevent rate limiting
   * when many components mount simultaneously (e.g., in table rows)
   *
   * @param {string} resourceType - Resource type (e.g., 'Statement')
   * @param {string} resourceId - ID of the resource
   * @returns {Promise<Array>} Promise resolving to array of { id, value } objects
   */
  const fetchCustomFieldValues = (resourceType, resourceId) => {
    const url = Routing.generate('api_resource_get', { resourceType, resourceId })
    const params = {
      fields: {
        [resourceType]: ['customFields'].join(),
      },
    }

    const doFetch = () => dpApi.get(url, params)
      .then(response => response.data.data?.attributes?.customFields || [])

    if (activeValueFetches < MAX_CONCURRENT_VALUE_FETCHES) {
      activeValueFetches++
      return doFetch().finally(() => {
        activeValueFetches--
        processValueQueue()
      })
    }

    return new Promise((resolve, reject) => {
      valueRequestQueue.push({ fn: doFetch, resolve, reject })
    })
  }

  /**
   * Get custom field definitions for a procedure (synchronous)
   * Returns immediately if definitions are already loaded
   *
   * @param {string} definitionSourceId - The procedure ID
   * @returns {Array|undefined} Custom field definitions or undefined if not loaded
   */
  const getCustomFieldsDefinitions = (definitionSourceId) => {
    return customFieldsDefinitions.get(definitionSourceId)
  }

  /**
   * Update custom field values via JSON:API
   * Supports both single and batch updates (multiple fields in one call)
   * Returns a Promise (use .then() for handling)
   *
   * @param {string} resourceType - Resource type (e.g., 'Statement', 'StatementSegment')
   * @param {string} resourceId - ID of the resource
   * @param {Array} customFieldValues - Array of { id, value } objects
   * @returns {Promise} Promise resolving on successful save
   *
   * @example Single field update
   * updateCustomFields('Statement', 'id-123', [
   *   { id: 'field-1', value: ['option-1'] }
   * ])
   *
   * @example Batch update (multiple fields in one call)
   * updateCustomFields('Statement', 'id-123', [
   *   { id: 'field-1', value: ['option-1', 'option-2'] },
   *   { id: 'field-2', value: 'option-3' }
   * ])
   */
  const updateCustomFields = (resourceType, resourceId, customFieldValues) => {
    const url = Routing.generate('api_resource_update', {
      resourceType,
      resourceId,
    })

    const payload = {
      data: {
        type: resourceType,
        id: resourceId,
        attributes: {
          customFields: customFieldValues,
        },
      },
    }
    // Use dpApi() directly to pass headers (dpApi.patch() doesn't support headers parameter)
    return dpApi({
      method: 'PATCH',
      url,
      data: payload,
      headers: {
        'X-CSRF-Token': dplan.csrfToken,
      },
    })
  }

  return {
    clearCustomFieldsDefinitions,
    fetchCustomFields,
    fetchCustomFieldValues,
    getCustomFieldsDefinitions,
    isLoading,
    updateCustomFields,
  }
}
