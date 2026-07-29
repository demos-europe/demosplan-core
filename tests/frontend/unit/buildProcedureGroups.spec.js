/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import buildProcedureGroups from '@DpJs/components/procedure/utils/buildProcedureGroups'

describe('buildProcedureGroups', () => {
  const buildStatement = (id, procedureId, attributes = {}) => ({
    id,
    type: 'AdminStatementCrossProcedureSearch',
    attributes: { externId: `M${id}`, ...attributes },
    relationships: { procedure: { data: { id: procedureId, type: 'Procedure' } } },
  })

  const buildProcedure = (id, name) => ({
    id,
    type: 'Procedure',
    attributes: { name },
  })

  beforeEach(() => {
    global.Translator = { trans: jest.fn(key => key) }
  })

  it('returns an empty array when there are no results', () => {
    expect(buildProcedureGroups([], [])).toEqual([])
  })

  it('collects statements of the same procedure in one group', () => {
    const results = [buildStatement('1', 'p1'), buildStatement('2', 'p1')]

    const groups = buildProcedureGroups(results, [buildProcedure('p1', 'Testverfahren')])

    expect(groups).toHaveLength(1)
    expect(groups[0].statements.map(statement => statement.id)).toEqual(['1', '2'])
  })

  it('separates statements of different procedures', () => {
    const results = [buildStatement('1', 'p1'), buildStatement('2', 'p2')]
    const included = [buildProcedure('p1', 'Verfahren A'), buildProcedure('p2', 'Verfahren B')]

    const groups = buildProcedureGroups(results, included)

    expect(groups.map(group => group.procedureId)).toEqual(['p1', 'p2'])
    expect(groups.map(group => group.procedureName)).toEqual(['Verfahren A', 'Verfahren B'])
  })

  it('falls back to a generic name when the procedure is missing in included', () => {
    const groups = buildProcedureGroups([buildStatement('1', 'p1')], [])

    expect(groups[0].procedureName).toBe('procedure')
  })

  it('flattens the statement attributes into the group item', () => {
    const results = [buildStatement('1', 'p1', { authorName: 'Max Musterperson', status: 'new' })]

    const groups = buildProcedureGroups(results, [buildProcedure('p1', 'Testverfahren')])

    expect(groups[0].statements[0]).toEqual({
      id: '1',
      externId: 'M1',
      authorName: 'Max Musterperson',
      status: 'new',
    })
  })
})
