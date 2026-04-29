/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'

/*
 * Module-level shared Map for custom field definitions.
 * Key: definitionSourceId (string)
 * Value: Array of custom field definitions
 */
const customFieldsDefinitions = new Map()

/*
 * Module-level shared Map for pending fetch promises.
 * Prevents race conditions when multiple components fetch simultaneously.
 * Key: definitionSourceId (string)
 * Value: Promise resolving to custom field definitions
 */
const pendingFetches = new Map()

/**
 * Composable for managing custom field definitions (CRUD on field configs).
 * All instances share the same module-level cache.
 *
 * Note: clearDefinitionsCache clears only the definition Maps. To also clear
 * value caches after a definition mutation, use clearCustomFieldsDefinitions
 * from useCustomFields instead.
 *
 * @returns {Object} {
 *   clearDefinitionsCache,
 *   createCustomFieldDefinition,
 *   deleteCustomFieldDefinition,
 *   fetchCustomFields,
 *   getCustomFieldsDefinitions,
 *   updateCustomFieldDefinition,
 * }
 */
export function useCustomFieldDefinitions () {
  /**
   * Clear the definition cache for a specific procedure or all procedures.
   * Clears all cached variants (all targetEntity/sourceEntity combinations) for the given procedure.
   *
   * @param {string|null} definitionSourceId - Procedure ID to clear, or null to clear all
   */
  const clearDefinitionsCache = (definitionSourceId = null) => {
    if (definitionSourceId) {
      const prefix = `${definitionSourceId}:`
      for (const key of customFieldsDefinitions.keys()) {
        if (key.startsWith(prefix)) {
          customFieldsDefinitions.delete(key)
        }
      }
      for (const key of pendingFetches.keys()) {
        if (key.startsWith(prefix)) {
          pendingFetches.delete(key)
        }
      }
    } else {
      customFieldsDefinitions.clear()
      pendingFetches.clear()
    }
  }

  /**
   * Fetch custom field definitions for a given procedure ID.
   * Supports optional server-side filtering by targetEntity and sourceEntity.
   * Uses a composite cache key so filtered and unfiltered results are cached independently.
   *
   * @param {string} definitionSourceId - The procedure ID to fetch custom fields for
   * @param {Object} [options={}] - Optional server-side filter options
   * @param {string|null} [options.targetEntity=null] - Filter by target entity (e.g. 'STATEMENT', 'SEGMENT')
   * @param {string|null} [options.sourceEntity=null] - Filter by source entity (e.g. 'PROCEDURE', 'PROCEDURE_TEMPLATE')
   * @returns {Promise<Array>} Promise resolving to array of custom field definitions
   */
  const fetchCustomFields = (definitionSourceId, { targetEntity = null, sourceEntity = null } = {}) => {
    const cacheKey = `${definitionSourceId}:${targetEntity ?? ''}:${sourceEntity ?? ''}`

    if (customFieldsDefinitions.has(cacheKey)) {
      return Promise.resolve(customFieldsDefinitions.get(cacheKey))
    }

    if (pendingFetches.has(cacheKey)) {
      return pendingFetches.get(cacheKey)
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
        ...(targetEntity && {
          targetEntity: {
            condition: {
              path: 'targetEntity',
              value: targetEntity,
            },
          },
        }),
        ...(sourceEntity && {
          sourceEntity: {
            condition: {
              path: 'sourceEntity',
              value: sourceEntity,
            },
          },
        }),
      },
    }

    const fetchPromise = dpApi.get(url, params)
      .then(response => {
        const customFields = response.data.data || []
        customFieldsDefinitions.set(cacheKey, customFields)
        pendingFetches.delete(cacheKey)

        return customFields
      })
      .catch(err => {
        pendingFetches.delete(cacheKey)
        dplan.notify.notify('error', Translator.trans('custom.fields.error.loading'))

        throw err
      })

    pendingFetches.set(cacheKey, fetchPromise)

    return fetchPromise
  }

  /**
   * Get custom field definitions for a procedure (synchronous).
   * Returns immediately if definitions are already loaded.
   * Must be called with the same options as the corresponding fetchCustomFields call.
   *
   * @param {string} definitionSourceId - The procedure ID
   * @param {Object} [options={}] - Same filter options used in fetchCustomFields
   * @param {string|null} [options.targetEntity=null]
   * @param {string|null} [options.sourceEntity=null]
   * @returns {Array|undefined} Custom field definitions or undefined if not loaded
   */
  const getCustomFieldsDefinitions = (definitionSourceId, { targetEntity = null, sourceEntity = null } = {}) => {
    const cacheKey = `${definitionSourceId}:${targetEntity ?? ''}:${sourceEntity ?? ''}`

    return customFieldsDefinitions.get(cacheKey)
  }

  /**
   * Create a new custom field definition.
   * Invalidates the definition cache for the procedure after creation.
   *
   * @param {Object} attributes - Custom field attributes (name, description, fieldType, options, ...)
   * @param {string} sourceId - The procedure ID (used for cache invalidation)
   * @returns {Promise} Promise resolving to the API response
   */
  const createCustomFieldDefinition = (attributes, sourceId) => {
    const url = Routing.generate('api_resource_create', { resourceType: 'CustomField' })

    return dpApi({
      method: 'POST',
      url,
      data: { data: { type: 'CustomField', attributes } },
      headers: { 'X-CSRF-Token': dplan.csrfToken },
    }).then(response => {
      clearDefinitionsCache(sourceId)

      return response
    })
  }

  /**
   * Delete a custom field definition.
   * Invalidates the definition cache for the procedure after deletion.
   *
   * @param {string} id - ID of the custom field to delete
   * @param {string} sourceId - The procedure ID (used for cache invalidation)
   * @returns {Promise} Promise resolving to the API response
   */
  const deleteCustomFieldDefinition = (id, sourceId) => {
    const url = Routing.generate('api_resource_delete', { resourceType: 'CustomField', resourceId: id })

    return dpApi({
      method: 'DELETE',
      url,
      headers: { 'X-CSRF-Token': dplan.csrfToken },
    }).then(response => {
      clearDefinitionsCache(sourceId)

      return response
    })
  }

  /**
   * Update a custom field definition.
   * Invalidates the definition cache for the procedure after the update.
   *
   * @param {string} id - ID of the custom field to update
   * @param {Object} payload - JSON:API payload including type, id, and updatable attributes
   * @param {string} sourceId - The procedure ID (used for cache invalidation)
   * @returns {Promise} Promise resolving to the API response
   */
  const updateCustomFieldDefinition = (id, payload, sourceId) => {
    const url = Routing.generate('api_resource_update', { resourceType: 'CustomField', resourceId: id })

    return dpApi({
      method: 'PATCH',
      url,
      data: { data: payload },
      headers: { 'X-CSRF-Token': dplan.csrfToken },
    }).then(response => {
      clearDefinitionsCache(sourceId)

      return response
    })
  }

  return {
    clearDefinitionsCache,
    createCustomFieldDefinition,
    deleteCustomFieldDefinition,
    fetchCustomFields,
    getCustomFieldsDefinitions,
    updateCustomFieldDefinition,
  }
}
