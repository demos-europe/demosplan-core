/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { Circle, Fill, Stroke, Style } from 'ol/style'

/**
 * Get drawStyle for openLayers Map
 *
 * @params fillColor
 * @params strokeColor
 * @params imageColor
 * @params strokeLineDash
 * @params strokeLineWidth
 *
 * @return new Style()
 */
export default function drawStyle ({ fillColor, strokeColor, imageColor, strokeLineDash, strokeLineWidth }) {
  return new Style({
    fill: new Fill({
      color: fillColor
    }),
    stroke: new Stroke({
      color: strokeColor,
      width: strokeLineWidth || 1,
      lineDash: strokeLineDash || 0
    }),
    image: new Circle({
      radius: 5,
      fill: new Fill({
        color: imageColor
      })
    })
  })
}
