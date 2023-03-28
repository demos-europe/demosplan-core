/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import DpBulkEditStatement from '@DpJs/components/statement/assessmentTable/DpBulkEditStatement'

describe('DpBulkEditStatement', () => {
  it('should be an object', () => {
    expect(typeof DpBulkEditStatement).toBe('object')
  })

  it('should be named DpBulkEdit', () => {
    expect(DpBulkEditStatement.name).toBe('DpBulkEditStatement')
  })
})
