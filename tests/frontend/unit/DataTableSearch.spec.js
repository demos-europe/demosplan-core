/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DataTableSearch from '@DemosPlanCoreBundle/components/DpDataTable/DataTableSearch'

describe('DataTableSearch a utility function to perform full-text search in strings', () => {
  it('should escape all regex special chars', () => {
    const itemsToSearch = [
      { text: 'Hello, this is some text.' }
    ]
    const fields = ['text']
    const searchTerm = 'he*l+l.l?o( t)h[i]s^ is $some|'

    expect(DataTableSearch(searchTerm, itemsToSearch, fields)).toEqual([])
  })

  it('should properly match text containing special chars', () => {
    const itemsToSearch = [
      { text: 'Hello? import * from ./../stars.js' }
    ]
    const fields = ['text']
    const searchTerm = 'Hello? import * from ./../stars.js'

    expect(DataTableSearch(searchTerm, itemsToSearch, fields)).toEqual(itemsToSearch)
  })

  it('should match even if number of whitespaces differ', () => {
    const itemsToSearch = [
      { text: 'Hello, this is some text.' }
    ]
    const fields = ['text']
    const searchTerm = 'Hello,  this is some text.'

    expect(DataTableSearch(searchTerm, itemsToSearch, fields)).toEqual(itemsToSearch)
  })
})
