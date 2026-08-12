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
    const results = [buildStatement('statement-1', 'procedure-a'), buildStatement('statement-2', 'procedure-a')]

    const groups = buildProcedureGroups(results, [buildProcedure('procedure-a', 'Testverfahren')])

    expect(groups).toHaveLength(1)
    expect(groups[0].statements.map(statement => statement.id)).toEqual(['statement-1', 'statement-2'])
  })

  it('separates statements of different procedures', () => {
    const results = [buildStatement('statement-1', 'procedure-a'), buildStatement('statement-2', 'procedure-b')]
    const included = [buildProcedure('procedure-a', 'Verfahren A'), buildProcedure('procedure-b', 'Verfahren B')]

    const groups = buildProcedureGroups(results, included)

    expect(groups.map(group => group.procedureId)).toEqual(['procedure-a', 'procedure-b'])
    expect(groups.map(group => group.procedureName)).toEqual(['Verfahren A', 'Verfahren B'])
  })

  it('falls back to a generic name when the procedure is missing in included', () => {
    const groups = buildProcedureGroups([buildStatement('statement-1', 'procedure-a')], [])

    expect(groups[0].procedureName).toBe('procedure')
  })

  it('flattens the statement attributes into the group item', () => {
    const results = [buildStatement('statement-1', 'procedure-a', { authorName: 'Max Musterperson', status: 'new' })]

    const groups = buildProcedureGroups(results, [buildProcedure('procedure-a', 'Testverfahren')])

    expect(groups[0].statements[0]).toEqual({
      id: 'statement-1',
      externId: 'Mstatement-1',
      authorName: 'Max Musterperson',
      status: 'new',
    })
  })
})
