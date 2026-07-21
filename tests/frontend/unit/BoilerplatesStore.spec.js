/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import BoilerplatesStore from '@DpJs/store/procedure/Boilerplates'

describe('Boilerplates store', () => {
  beforeAll(() => {
    globalThis.Translator = {
      trans: jest.fn(key => key),
    }
  })

  const buildBoilerplate = (id, { verified, groupId = null }) => {
    return {
      id,
      attributes: {
        title: `Title ${id}`,
        text: `Text ${id}`,
        categoriesTitle: [],
        verified,
      },
      relationships: groupId ?
        { group: { data: { id: groupId } } } :
        {},
    }
  }

  describe('getGroupedBoilerplates', () => {
    it('returns an empty list if no boilerplates are present', () => {
      const state = { boilerplates: {}, groups: [] }

      expect(BoilerplatesStore.getters.getGroupedBoilerplates(state)).toEqual([])
    })

    it('maps the verified attribute for grouped and ungrouped boilerplates', () => {
      const state = {
        boilerplates: {
          bp1: buildBoilerplate('bp1', { verified: true, groupId: 'group1' }),
          bp2: buildBoilerplate('bp2', { verified: false, groupId: 'group1' }),
          bp3: buildBoilerplate('bp3', { verified: true }),
        },
        groups: [
          { id: 'group1', attributes: { title: 'Group 1' } },
        ],
      }

      const grouped = BoilerplatesStore.getters.getGroupedBoilerplates(state)

      const group = grouped.find(g => g.id === 'group1')

      expect(group.boilerplates).toEqual([
        expect.objectContaining({ id: 'bp1', verified: true }),
        expect.objectContaining({ id: 'bp2', verified: false }),
      ])

      const noGroup = grouped.find(g => g.id === 'withoutGroupID')

      expect(noGroup.boilerplates).toEqual([
        expect.objectContaining({ id: 'bp3', verified: true }),
      ])
    })
  })
})
