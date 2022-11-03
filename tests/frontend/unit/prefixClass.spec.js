/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { prefixClass } from 'demosplan-ui/lib'

window.dplan = {
  settings: {
    publicCSSClassPrefix: 'dp-'
  }
}

describe('prefixClass', () => {
  it('should return a list of classes prefixed', () => {
    expect(prefixClass('test foo bar')).toEqual('dp-test dp-foo dp-bar')
  })

  it('should return a quereyselector with prefixed classes', () => {
    expect(prefixClass('#test .foo .bar')).toEqual('#test .dp-foo .dp-bar')
    expect(prefixClass('[data-some-stuff="its all right"] > .a-class > label')).toEqual('[data-some-stuff="its all right"] > .dp-a-class > label')
  })

  it('should return the same sting if there are no points at the beginning of a word', () => {
    expect(prefixClass('[data-some-stuff="its all right"] > a:hover > label')).toEqual('[data-some-stuff="its all right"] > a:hover > label')
  })
})
