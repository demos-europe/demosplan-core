/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getResolutionsFromScales, getScalesAndResolutions } from '@DpJs/components/map/map/utils/utils'
import ResponseAttributes from './__mocks__/dplan_api_map_options_admin.json'

describe('DpOlMap/utils', () => {
  /*
   *  Fake window.matchMedia
   *  See https://stackoverflow.com/questions/39830580/jest-test-fails-typeerror-window-matchmedia-is-not-a-function
   */
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      value: jest.fn((query) => {
        const a = 96
        const number = query.match(/\d+/)[0]
        if (number <= a) {
          return {
            matches: true
          }
        } else if (number > a) {
          return {
            matches: false
          }
        }
      })
    })
  })

  it('`getResolutionsFromScales` should return a descending sorted array', () => {
    const resolutions = getResolutionsFromScales(ResponseAttributes.scales, 'm', 96)
    expect(typeof resolutions).toBe('object')
    expect(resolutions).toHaveLength(13)
    expect(resolutions[0]).toBeGreaterThan(resolutions[resolutions.length - 1])
  })

  it('`getScalesAndResolutions` should return a descending sorted array of objects with keys scale + resolution', () => {
    const resolutions = [
      243.4171535009737,
      132.2919312505292,
      66.1459656252646,
      39.687579375158755,
      18.520870375074086,
      9.260435187537043,
      3.9687579375158757,
      1.3229193125052918,
      1.0583354500042335,
      0.5291677250021167,
      0.26458386250105836,
      0.13229193125052918,
      0.06614596562526459
    ]
    const scalesAndResolutions = getScalesAndResolutions(resolutions, 'm', 96)
    expect(typeof scalesAndResolutions).toBe('object')
    expect(scalesAndResolutions).toHaveLength(13)
    expect(typeof scalesAndResolutions[4]).toBe('object')
    expect(Object.hasOwn(scalesAndResolutions[4], 'resolution')).toBe(true)
    expect(Object.hasOwn(scalesAndResolutions[4], 'scale')).toBe(true)
    expect(scalesAndResolutions[0].resolution).toBeGreaterThan(scalesAndResolutions[scalesAndResolutions.length - 1].resolution)
  })
})
