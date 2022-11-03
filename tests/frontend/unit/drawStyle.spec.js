/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// import drawStyle from '@DemosPlanMapBundle/components/map/utils/drawStyle'

const styles = {
  fillColor: 'rgba(212, 0, 75, 0.2)',
  strokeColor: 'rgb(164, 0, 76)',
  imageColor: 'rgb(164, 0, 76)'
}

//  Testing with openLayers is a little bit complicated...
//  read more in
//    * https://www.google.com/search?rlz=1C1GCEU_deDE821DE822&ei=VeU1XOfDG4SxgweEwL_QBA&q=jest+SyntaxError%3A+Unexpected+token+export+openlayers
//    * https://jestjs.io/docs/en/configuration.html#transformignorepatterns-array-string
//    * https://medium.com/@compatt84/how-to-test-open-layers-react-components-with-mocha-part-i-9a2ca0458ba1
//    * https://medium.com/@compatt84/how-to-test-open-layers-react-components-with-mocha-part-ii-d91d65145bce

describe('DpOlMap/drawStyle', () => {
  it('`drawStyle` should return an openLayers Style instance', () => {
    // so far not working :-(
    // const drawStyles = drawStyle(styles)
    // expect(typeof drawStyles).toBe('object')
  })
})
