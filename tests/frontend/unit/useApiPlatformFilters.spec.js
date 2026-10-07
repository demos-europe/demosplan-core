/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { useApiPlatformFilters } from '@DpJs/composables/useApiPlatformFilters'

describe('useApiPlatformFilters', () => {
  let transformFiltersToApiPlatform

  const createFilter = (path, value, operator = '=') => ({
    condition: {
      path,
      value,
      operator,
    },
  })

  beforeEach(() => {
    ({ transformFiltersToApiPlatform } = useApiPlatformFilters())
  })

  describe('transformFiltersToApiPlatform', () => {
    it('returns an empty object for empty filters', () => {
      expect(transformFiltersToApiPlatform({})).toEqual({})
    })

    it('transforms a value filter to API Platform format', () => {
      const filters = {
        filter1: createFilter('assignee', 'assignee-uuid-123'),
      }

      expect(transformFiltersToApiPlatform(filters)).toEqual({
        'assignee.id': ['assignee-uuid-123'],
      })
    })

    it('transforms IS NULL to exists[path]=false', () => {
      const filters = {
        filter1: createFilter('assignee', null, 'IS NULL'),
      }

      expect(transformFiltersToApiPlatform(filters)).toEqual({
        'exists[assignee]': false,
      })
    })

    it('accumulates multiple values for the same path', () => {
      const filters = {
        filter1: createFilter('tag', 'tag-uuid-1'),
        filter2: createFilter('tag', 'tag-uuid-2'),
        filter3: createFilter('tag', 'tag-uuid-3'),
      }

      expect(transformFiltersToApiPlatform(filters)).toEqual({
        'tag.id': ['tag-uuid-1', 'tag-uuid-2', 'tag-uuid-3'],
      })
    })

    it('handles filters with different paths', () => {
      const filters = {
        filter1: createFilter('assignee', 'assignee-uuid-123'),
        filter2: createFilter('tag', 'tag-uuid-456'),
        filter3: createFilter('place', 'place-uuid-123'),
      }

      expect(transformFiltersToApiPlatform(filters)).toEqual({
        'assignee.id': ['assignee-uuid-123'],
        'tag.id': ['tag-uuid-456'],
        'place.id': ['place-uuid-123'],
      })
    })

    it('handles value and IS NULL filters together', () => {
      const filters = {
        filter1: createFilter('assignee', null, 'IS NULL'),
        filter2: createFilter('tag', 'tag-uuid-123'),
      }

      expect(transformFiltersToApiPlatform(filters)).toEqual({
        'exists[assignee]': false,
        'tag.id': ['tag-uuid-123'],
      })
    })

    it.each([
      ['missing', {}],
      ['null', { condition: null }],
      ['undefined', { condition: undefined }],
    ])('skips filters with %s condition', (_, invalidFilter) => {
      const filters = {
        filter1: createFilter('tag', 'tag-uuid-123'),
        filter2: invalidFilter,
      }

      expect(transformFiltersToApiPlatform(filters)).toEqual({
        'tag.id': ['tag-uuid-123'],
      })
    })

    describe('custom fields', () => {
      const FIELD_A = '3f2a1c4e-5b6d-4e8f-9a0b-1c2d3e4f5a6b'
      const FIELD_B = '9d8c7b6a-5f4e-4d3c-8b2a-1f0e9d8c7b6a'
      // The path has no hyphens, see customFieldFilterPath.js
      const pathOf = (fieldId) => `customField_${fieldId.replaceAll('-', '')}`

      it('sends the selected options of a custom field as customField[fieldId][]', () => {
        const filters = {
          option1: createFilter(pathOf(FIELD_A), 'option-a'),
          option2: createFilter(pathOf(FIELD_A), 'option-b'),
        }

        expect(transformFiltersToApiPlatform(filters)).toEqual({
          customField: { [FIELD_A]: ['option-a', 'option-b'] },
        })
      })

      it('keeps the selections of several custom fields apart', () => {
        const filters = {
          option1: createFilter(pathOf(FIELD_A), 'option-a'),
          option2: createFilter(pathOf(FIELD_B), 'option-x'),
        }

        expect(transformFiltersToApiPlatform(filters)).toEqual({
          customField: {
            [FIELD_A]: ['option-a'],
            [FIELD_B]: ['option-x'],
          },
        })
      })

      it('combines custom fields with the other filters', () => {
        const filters = {
          filter1: createFilter('tag', 'tag-uuid-123'),
          option1: createFilter(pathOf(FIELD_A), 'option-a'),
        }

        expect(transformFiltersToApiPlatform(filters)).toEqual({
          'tag.id': ['tag-uuid-123'],
          customField: { [FIELD_A]: ['option-a'] },
        })
      })
    })
  })
})
