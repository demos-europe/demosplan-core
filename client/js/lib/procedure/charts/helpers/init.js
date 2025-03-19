/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getColorFromCSS, getColorsFromCSS } from './getColorsFromCSS'
import BarChart from '../BarChart'
import BarPieChart from '../BarPieChart'
import DonutChart from '../DonutChart'
import LineChart from '../LineChart'

function initBarChart (elementId, elementLegendId) {
  const element = document.querySelector(elementId)

  if (element) {
    const data = JSON.parse(element.getAttribute('data-status'))
    const cssColorClasses = JSON.parse(element.getAttribute('data-colors'))
    const texts = JSON.parse(element.getAttribute('data-texts'))

    return new BarChart({
      target: elementId,
      legendTarget: elementLegendId,
      data,
      texts,
      colors: getColorsFromCSS(cssColorClasses),
      activeColor: [getColorFromCSS('c-chart__color-active')]
    })
  }
}

function initBarPieChart (barId, pieId, barLegendId, pieLegendId) {
  const barChartElement = document.querySelector(barId)
  if (barChartElement) {
    const fData = JSON.parse(barChartElement.getAttribute('data-status'))
    const categoryDefinition = JSON.parse(barChartElement.getAttribute('data-categories'))
    const targetClasses = { bar: barId, pie: pieId, pieLegend: pieLegendId, chartLegend: barLegendId }
    const cssColorClasses = JSON.parse(barChartElement.getAttribute('data-colors'))
    const colors = {}
    for (const colorArray in cssColorClasses) {
      colors[colorArray] = getColorsFromCSS(cssColorClasses[colorArray])
    }

    return new BarPieChart({ targetClasses, fData, categoryDefinition, colors })
  }
}

function initDonutChart (donutId, donutLegendId) {
  const donutElement = document.querySelector(donutId)

  if (donutElement) {
    const cssColorClass = JSON.parse(donutElement.getAttribute('data-color'))
    return new DonutChart({
      categoryDefinition: JSON.parse(donutElement.getAttribute('data-categories')),
      color: getColorFromCSS(cssColorClass),
      data: JSON.parse(donutElement.getAttribute('data-items')),
      target: donutId,
      targetClasses: { donut: donutId, donutLegend: donutLegendId },
      texts: JSON.parse(donutElement.getAttribute('data-texts'))
    })
  }
}

function initLineChart (lineId, lineLegendId) {
  const lineElement = document.querySelector(lineId)

  if (lineElement) {
    const cssColorClass = JSON.parse(lineElement.getAttribute('data-color'))
    return new LineChart({
      activeColor: [getColorFromCSS('c-chart__color-active')],
      color: getColorFromCSS(cssColorClass),
      data: JSON.parse(lineElement.getAttribute('data-items')),
      legendData: JSON.parse(lineElement.getAttribute('data-legend')),
      legendTarget: lineLegendId,
      target: lineId,
      targetClasses: { donut: lineId, donutLegend: lineLegendId },
      texts: JSON.parse(lineElement.getAttribute('data-texts'))
    })
  }
}

export { initBarChart, initBarPieChart, initDonutChart, initLineChart }
