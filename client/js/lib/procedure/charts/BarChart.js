/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { axisBottom, axisLeft } from 'd3-axis'
import { scaleBand, scaleLinear } from 'd3-scale'
import { format } from 'd3-format'
import Legend from './Legend'
import { max } from 'd3-array'
import { select } from 'd3-selection'

/**
 *
 *
 * @param options
 *  options : { 'target' : '#barChart',
 *          'data' : [ {'key': 'labelName', 'value' : 5}],
 *          'colors' : ['#cccccc', '#ebebeb', '#999999'] }
 *
 */
export default class BarChart {
  constructor (options) {
    const defaults = {
      target: '#barChart',
      legendTarget: null,
      data: [{ key: 'labelName', value: 5, index: 0 }],
      colors: ['#cccccc', '#ebebeb', '#999999'],
      'active-color': ['#ff0000'],
      texts: {
        'no-data-fallback': Translator.trans('fragments.not.submitted'),
        'legend-headline': Translator.trans('fragments'),
        'data-names': Translator.trans('fragments'),
        'data-name': Translator.trans('fragment')
      }
    }

    Object.assign(this, { ...defaults, ...options })

    this.total = this.data.reduce((acc, data) => acc + data.value, 0)

    this.data = this.data.map((el, idx) => ({ ...el, index: idx }))

    if (this.total > 0) {
      // Regular case

      //  generate Chart
      this.hG = this.histoGram(this.data) // Create the histogram.
      if (this.legendTarget !== null) {
        this.leg = new Legend({
          data: this.data,
          target: this.legendTarget,
          parentTarget: this.target,
          colors: this.colors,
          activeColor: this.activeColor,
          texts: this.texts,
          total: this.total
        })
      } // Create the legend.
    } else {
      // Display a message if no data is found to be displayed.
      select(this.target).append('p').text(this.texts['no-data-fallback'])
    }
  }

  /**
   *
   * @param c
   * @returns {string | *}
   */
  setColor (c) {
    if (typeof this.colors[c] !== 'undefined') return this.colors[c]
    return ''
  }

  /**
   * Function to handle histogram
   *
   * @param fD
   */
  histoGram (fD) {
    const hgBasisWidth = 400
    const hgBasisHeight = 250
    const transSpeed = 200
    const hGDim = { t: 25, r: 0, b: 10, l: 25 }

    hGDim.w = hgBasisWidth - hGDim.l - hGDim.r
    hGDim.h = hgBasisHeight - hGDim.t - hGDim.b

    const mouseover = (ev, d) => {
      const i = d.index
      const elemSet = hGsvg
      /*
       * Utility function to be called on mouseover.
       * highlight selected segment
       */
      select(elemSet[i]).transition().duration(transSpeed).attr('fill', this.activeColor[0])
      // Call update-row functions of the legend.
      this.leg.highlightRow(i)
    }

    const mouseout = (ev, d) => {
      // Utility function to be called on mouseout.
      const i = d.index
      const elemSet = hGsvg
      // Reset the pie-chart and legend.
      select(elemSet[i]).transition().duration(transSpeed).attr('fill', this.colors[i])
      // Call update-row functions of the legend.
      this.leg.resetHighlightedRow(i)
      this.leg.resetHighlightedRow(i)
    }

    // Create svg for histogram.
    const hGsvg = select(this.target).append('svg')
      .attr('width', '100%')
      .attr('height', '100%')
      .style('overflow', 'visible')
      .attr('viewBox', '0 0 ' + hgBasisWidth + ' ' + hgBasisHeight)
      .append('g')
      .attr('transform', 'translate(' + hGDim.l + ',' + hGDim.t + ')')

    // /* AXIS-mapping */
    const x = scaleBand()
      .rangeRound([0, hGDim.w])
      .padding(0.1)
      .domain(fD.map((d) => {
        return d.key
      }))
    const y = scaleLinear()
      .rangeRound([hGDim.h, 0])
      .domain([0, max(fD, (d) => {
        return d.value
      })])

    // Add x-axis to the histogram svg.
    hGsvg.selectAll('.tick text')
      .enter()
      .append('g')
      .attr('data-selector', 'x-axis')
      .attr('transform', 'translate(0,' + hGDim.h + ')')
      .call(axisBottom(x))
      .append('text')

    hGsvg.append('g')
      .attr('data-selector', 'y-axis')
      .call(axisLeft(y).ticks(2))
      .append('text')
      .attr('transform', 'rotate(-90)')
      .attr('y', 6)
      .attr('dy', '0.71em')
      .attr('text-anchor', 'end')
      .text('Frequency')

    // Create bars for histogram to contain rectangles and freq labels.
    hGsvg.append('g')
      .attr('data-selector', 'chartElements')
      .selectAll('[data-selector=bar]')
      .data(fD)
      .enter()
      .append('g')
      .attr('data-selector', 'bar')
      .append('rect')
      .attr('x', (d, i) => {
        return x(d.key)
      })
      .attr('y', d => y(d.value))
      .attr('width', x.bandwidth())
      .attr('height', d => (hGDim.h - (y(d.value))))
      .attr('data-selector', 'colorChanger')
      .attr('fill', (d, i) => this.colors[i])
      .on('mouseover', mouseover)// Mouseover is defined below.
      .on('mouseout', mouseout)// Mouseout is defined below.

    const xAxis = axisBottom(x)

    // Create Describing labels below the rectangles.
    hGsvg.append('g')
      .attr('data-selector', 'bar-description')
      .attr('width', x.bandwidth())
      .attr('transform', 'translate(0,' + hGDim.h + ')')
      .call(xAxis)
      .selectAll('text')
      .remove()

    // Create the frequency labels above the rectangles.
    hGsvg.selectAll('[data-selector=bar]')
      .append('text')
      .attr('data-selector', 'bar-value')
      .text(d => format(',')(d.value))
      .attr('x', d => x(d.key) + x.bandwidth() / 2)
      .attr('y', d => y(d.value) - 5)
      .attr('text-anchor', 'middle')
  }
}
