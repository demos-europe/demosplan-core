/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import * as d3 from 'd3'
import { select } from 'd3-selection'

export default class DonutChart {
  constructor (options) {
    const defaults = {
      // [{ key: '', label: '' }], defined in data-categories attribute
      categoryDefinition: [],
      // [{ label: '', count: 0, percentage: 0 }], defined in data-items attribute
      data: [],
      radius: 0,
      innerRadius: 100,
      height: 500,
      width: 500,
      // Defined in data-color attribute
      color: '#006fd0',
      targetClasses: {},
      // Defined in data-texts attribute
      texts: {
        'no-data-fallback': Translator.trans('statements.none'),
        'data-names': Translator.trans('statements'),
        'data-name': Translator.trans('statement')
      },
      target: 'body'
    }

    Object.assign(this, { ...defaults, ...options })

    this.donut(this.data)
  }

  donut (data) {
    const width = 150
    const height = 140
    const radius = Math.min(width, height) / 2
    const color = d3.scaleOrdinal()
      .range(this.color)

    const svg = select(this.target)
      .append('svg')
      .attr('width', width)
      .attr('height', height)
      .attr('class', 'inline-block')
      .append('g')
      .attr('transform', 'translate(' + (width / 2) + ',' + (height / 2) + ')')

    const arc = d3.arc()
      .innerRadius(radius - 19)
      .outerRadius(radius - 1)

    const pie = d3.pie()
      .value((d) => d.count)
      .sort(null)

    // eslint-disable-next-line no-unused-vars
    const path = svg.selectAll('path')
      .data(pie(data))
      .enter()
      .append('path')
      .attr('d', arc)
      .attr('fill', (d, i) => {
        color(d.data.label)
        return this.color
      })
      .attr('transform', 'translate(0, 0)')

    const percentage = data[0].percentage / 100
    svg.append('path')
      .attr('d', d3.arc()
        .endAngle(Math.PI * 2)
        .startAngle(percentage * Math.PI * 2)
        .innerRadius(radius - 20)
        .outerRadius(radius)
      )
      .attr('fill', '#ebe9e9')

    // Create legend inside donut
    const legendRectSize = 13
    const legendSpacing = 7

    const chart = select(this.target)
    chart.append('div')
      .data(data)
      .text(d => d.label + ' (' + d.count + ')')
      .attr('class', 'u-mt-0_5')

    const legend = svg.selectAll('.legend')
      .data(color.domain())
      .enter()
      .append('g')
      .attr('transform', (d, i) => {
        const height = legendRectSize + legendSpacing
        const offset = height * color.domain().length / 2
        const horizontal = -2 * legendRectSize - 11
        const vertical = i * height - offset + 2
        return 'translate(' + horizontal + ',' + vertical + ')'
      })

    legend.append('text')
      .data(data)
      .attr('x', d => {
        const percL = Math.round(d.percentage).toString().length
        return percL === 3 ? 10 : percL === 2 ? 15 : 20
      })
      .attr('y', 15)
      .text(d => Math.round(d.percentage) + '%')
      .attr('font-size', 18)
      .attr('font-weight', 'bold')
      .attr('fill', '#4d4d4d')
  }
}
