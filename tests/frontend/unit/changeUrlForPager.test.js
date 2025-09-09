/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import changeUrlforPager from '@DpJs/components/statement/assessmentTable/utils/changeUrlforPager'

describe('changeUrlforPager', () => {
  let defineProp
  let updatedUrl
  const urlContent = {
    count: 3,
    currentPage: 3
  }
  const urlParts = {
    part1: 'https://dummy.com',
    part2: `r_limit=${urlContent.count}&page=${urlContent.currentPage}`
  }
  beforeAll(() => {
    global.window = Object.create(window)
    defineProp = (url) => {
      Object.defineProperty(window, 'location', {
        value: {
          href: url
        }
      })
      updatedUrl = changeUrlforPager({
        count: urlContent.count,
        current_page: urlContent.currentPage
      })
      return updatedUrl
    }
  })

  it('gives values if no values exists', () => {
    const url = changeUrlforPager({
      count: urlContent.count,
      current_page: urlContent.currentPage
    })
    expect(url[1]).toBe(urlParts.part2)
  })

  it('overwrites given values', () => {
    const url = `${urlParts.part1}?r_limit=2&page=5`
    defineProp(url)
    expect(updatedUrl[0]).toBe(urlParts.part1)
    expect(updatedUrl[1]).toBe(urlParts.part2)
  })

  it.skip('overwrites the limit value if it is the only param', () => {
    defineProp(`${urlParts.part1}?r_limit=2`)
    expect(updatedUrl[0]).toBe(urlParts.part1)
    expect(updatedUrl[1]).toBe(urlParts.part2)
  })

  it('overwrites the page value if it is the only param', () => {
    defineProp(`${urlParts.part1}?page=2`)
    expect(updatedUrl[0]).toBe(urlParts.part1)
    expect(updatedUrl[1]).toBe(urlParts.part2)
  })

  it.skip('sets params if they are undefined', () => {
    defineProp(`${urlParts.part1}?page=&r_limit=`)
    expect(updatedUrl[0]).toBe(urlParts.part1)
    expect(updatedUrl[1]).toBe(urlParts.part2)
  })

  it('works even if there are other params or the order is mixed', () => {
    defineProp(`${urlParts.part1}?test=tete&page=${urlContent.currentPage}&y=2&r_limit=${urlContent.count}`)
    expect(updatedUrl[0]).toBe(urlParts.part1)
    expect(updatedUrl[1]).toContain(`r_limit=${urlContent.count}`)
    expect(updatedUrl[1]).toContain(`page=${urlContent.currentPage}`)
  })
})
