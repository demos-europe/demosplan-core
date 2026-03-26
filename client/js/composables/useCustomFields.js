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
 * Shared Map for batch-fetched custom field values per procedure.
 * Key: 'resourceType:definitionSourceId' (e.g. 'OriginalStatement:uuid-of-procedure')
 * Value: Map<resourceId, Array> of custom field values indexed by resource ID
 */
const cachedBatchValues = new Map()

/**
 * Shared Map for pending batch fetch promises.
 * Prevents duplicate batch requests when multiple components mount simultaneously.
 * Key: 'resourceType:definitionSourceId:filterPath'
 * Value: Promise resolving to Map<resourceId, Array>
 */
const pendingBatchFetches = new Map()

/**
 * Shared Map for cached individual value fetches.
 * Key: 'resourceType:resourceId'
 * Value: Array of custom field values
 */
const cachedIndividualValues = new Map()

/**
 * Shared Map for pending individual fetch promises.
 * Prevents duplicate requests for the same resource.
 * Key: 'resourceType:resourceId'
 * Value: Promise resolving to Array
 */
const pendingIndividualFetches = new Map()

function fetchIndividualValues (resourceType, resourceId) {
  const cacheKey = `${resourceType}:${resourceId}`

  if (cachedIndividualValues.has(cacheKey)) {
    return Promise.resolve(cachedIndividualValues.get(cacheKey))
  }

  if (pendingIndividualFetches.has(cacheKey)) {
    return pendingIndividualFetches.get(cacheKey)
  }

  const url = Routing.generate('api_resource_get', { resourceType, resourceId })
  const params = {
    fields: {
      [resourceType]: ['customFields'].join(),
    },
  }

  const doFetch = () => dpApi.get(url, params)
    .then(response => response.data.data?.attributes?.customFields || [])

  let executionPromise

  if (activeValueFetches < MAX_CONCURRENT_VALUE_FETCHES) {
    activeValueFetches++
    executionPromise = doFetch().finally(() => {
      activeValueFetches--
      processValueQueue()
    })
  } else {
    executionPromise = new Promise((resolve, reject) => {
      valueRequestQueue.push({ fn: doFetch, resolve, reject })
    })
  }

  const cachedPromise = executionPromise
    .then(values => {
      cachedIndividualValues.set(cacheKey, values)
      return values
    })
    .finally(() => {
      pendingIndividualFetches.delete(cacheKey)
    })

  pendingIndividualFetches.set(cacheKey, cachedPromise)
  return cachedPromise
}

/*
 * Fetches all custom field values for a procedure in a single request.
 * Results are cached in cachedBatchValues under the given cacheKey.
 */
function fetchBatchValues (resourceType, definitionSourceId, cacheKey, filterPath) {
  const url = Routing.generate('api_resource_list', { resourceType })
  const params = {
    fields: {
      [resourceType]: ['customFields'].join(),
    },
    filter: {
      procedureId: {
        condition: {
          path: filterPath,
          value: definitionSourceId,
        },
      },
    },
  }

  return dpApi.get(url, params)
    .then(response => {
      const items = response.data.data || []
      const batchCache = new Map(
        items.map(item => [item.id, item.attributes?.customFields || []]),
      )
      cachedBatchValues.set(cacheKey, batchCache)
      pendingBatchFetches.delete(cacheKey)
      return batchCache
    })
    .catch(err => {
      pendingBatchFetches.delete(cacheKey)
      throw err
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
  /**
   * Clear custom field definitions for a specific procedure or all procedures
   * Useful for forcing a refresh after updates
   * Also clears any pending fetches
   *
   * @param {string|null} definitionSourceId - Procedure ID to clear, or null to clear all
   */
  const clearCustomFieldsDefinitions = (definitionSourceId = null) => {
    if (definitionSourceId) {
      /*
       * Batch cache keys use 'resourceType:definitionSourceId:filterPath', so we
       * match on ':definitionSourceId:' to avoid false positives at the start or end.
       */
      const definitionIdCacheKey = `:${definitionSourceId}:`
      customFieldsDefinitions.delete(definitionSourceId)
      pendingFetches.delete(definitionSourceId)
      for (const key of cachedBatchValues.keys()) {
        if (key.includes(definitionIdCacheKey)) {
          cachedBatchValues.delete(key)
        }
      }
      for (const key of pendingBatchFetches.keys()) {
        if (key.includes(definitionIdCacheKey)) {
          pendingBatchFetches.delete(key)
        }
      }
      cachedIndividualValues.clear()
      pendingIndividualFetches.clear()
    } else {
      customFieldsDefinitions.clear()
      pendingFetches.clear()
      cachedBatchValues.clear()
      pendingBatchFetches.clear()
      cachedIndividualValues.clear()
      pendingIndividualFetches.clear()
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

    const fetchPromise = dpApi.get(url, params)
      .then(response => {
        const customFields = response.data.data || []
        customFieldsDefinitions.set(definitionSourceId, customFields)
        pendingFetches.delete(definitionSourceId)
        return customFields
      })
      .catch(err => {
        pendingFetches.delete(definitionSourceId)
        dplan.notify.notify('error', Translator.trans('custom.fields.error.loading'))
        throw err
      })

    pendingFetches.set(definitionSourceId, fetchPromise)

    return fetchPromise
  }

  /**
   * Fetch custom field values for a specific resource.
   * When batchFilterPath is provided, all values for the procedure are fetched
   * in a single batch request and cached. Concurrent calls for the same procedure
   * share the same pending promise, preventing duplicate requests.
   * Falls back to an individual request if the resource is not found in the batch.
   * When batchFilterPath is null, an individual request is made for the single resource.
   * Individual requests are also cached per resource ID.
   *
   * @param {string} resourceType - Resource type (e.g., 'Statement', 'OriginalStatement')
   * @param {string} resourceId - ID of the resource
   * @param {string|null} definitionSourceId - Procedure ID used as filter value in batch fetch
   * @param {string|null} batchFilterPath - JSON:API filter path for batch fetch (e.g. 'procedure.id').
   *   When null, individual fetch is used regardless of definitionSourceId.
   * @returns {Promise<Array>} Promise resolving to array of { id, value } objects
   */
  const fetchCustomFieldValues = (resourceType, resourceId, definitionSourceId = null, batchFilterPath = null) => {
    if (batchFilterPath === null) {
      return fetchIndividualValues(resourceType, resourceId)
    }

    const cacheKey = `${resourceType}:${definitionSourceId}:${batchFilterPath}`

    if (cachedBatchValues.has(cacheKey)) {
      const batchCache = cachedBatchValues.get(cacheKey)
      return batchCache.has(resourceId) ?
        Promise.resolve(batchCache.get(resourceId)) :
        fetchIndividualValues(resourceType, resourceId)
    }

    if (pendingBatchFetches.has(cacheKey)) {
      return pendingBatchFetches.get(cacheKey).then(batchCache =>
        batchCache.has(resourceId) ?
          batchCache.get(resourceId) :
          fetchIndividualValues(resourceType, resourceId),
      )
    }

    const batchPromise = fetchBatchValues(resourceType, definitionSourceId, cacheKey, batchFilterPath)
    pendingBatchFetches.set(cacheKey, batchPromise)

    return batchPromise.then(batchCache =>
      batchCache.has(resourceId) ?
        batchCache.get(resourceId) :
        fetchIndividualValues(resourceType, resourceId),
    )
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
   * Check synchronously whether individual values are already cached for a resource.
   * Used to skip the loading state when data is available immediately.
   *
   * @param {string} resourceType - Resource type (e.g. 'DraftStatement')
   * @param {string} resourceId - ID of the resource
   * @returns {boolean} True if values are in cache
   */
  const hasCachedValues = (resourceType, resourceId) => {
    return cachedIndividualValues.has(`${resourceType}:${resourceId}`)
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
    }).then(response => {
      cachedIndividualValues.delete(`${resourceType}:${resourceId}`)
      return response
    })
  }

  return {
    clearCustomFieldsDefinitions,
    fetchCustomFields,
    fetchCustomFieldValues,
    getCustomFieldsDefinitions,
    hasCachedValues,
    updateCustomFields,
  }
}
