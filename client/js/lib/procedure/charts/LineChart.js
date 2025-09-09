/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { axisBottom, axisLeft } from 'd3-axis'
import { scaleBand, scaleLinear } from 'd3'
import Legend from './Legend'
import { line } from 'd3-shape'
import { max } from 'd3-array'
import { select } from 'd3-selection'
import { timeFormatLocale } from 'd3-time-format'

export default class LineChart {
  constructor (options) {
    const defaults = {
      activeColor: '',
      // Defined in data-items attribute
      data: [],
      // { key: 'labelName', value: 4 }
      legendData: [],
      height: 200,
      width: 800,
      // Defined in data-color attribute
      color: '#fc8d59',
      targetClasses: {},
      // Defined in data-texts attribute
      texts: {
        'legend-headline': '',
        'no-data-fallback': '',
        'data-names': '',
        'data-name': ''
      },
      target: 'body',
      legendTarget: null,

      /*
       * Whitespace around the chart graphic to separate it from other elements.
       * As long as we do not maintain a universal scale to be imported, the values
       * have to be hardcoded here.
       * https://observablehq.com/@d3/margin-convention
       */
      margin: {
        top: 16,
        right: 24,
        bottom: 24,
        left: 32
      }
    }

    Object.assign(this, { ...defaults, ...options })

    const locale = timeFormatLocale({
      dateTime: '%a %b %e %X %Y',
      date: '%b-%y',
      time: '%H:%M:%S',
      periods: ['AM', 'PM'],
      days: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
      shortDays: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
      months: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
      shortMonths: ['Jan', 'Feb', 'Mrz', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
    })

    const parseDate = locale.parse('%Y-%m')
    const formatDate = locale.format('%b %y')

    let chartData = []

    chartData = this.data.map(el => {
      return {
        ...el,
        x: formatDate(parseDate(el.x))
      }
    })

    if (this.data.length > 12) {
      const itemsPerChunk = 3
      const chunkedItems = chartData.reduce((acc, curr, idx) => {
        const chunkIndex = Math.floor(idx / itemsPerChunk)

        if (!acc[chunkIndex]) {
          acc[chunkIndex] = []
        }

        acc[chunkIndex].push(curr)

        return acc
      }, [])

      chartData = chunkedItems.map((chunk, chunkIdx) => {
        return chunk.reduce((res, item) => {
          if (typeof res.index === 'undefined') {
            const datePtOne = chunk[0].x.slice(0, chunk[0].x.length - 3)
            let datePtTwo
            if (chunk[2]) {
              datePtTwo = ` - ${chunk[2].x}`
            } else if (chunk[1]) {
              datePtTwo = ` - ${chunk[1].x}`
            } else {
              datePtTwo = ''
            }
            res = {
              index: chunkIdx,
              x: `${datePtOne}${datePtTwo}`
            }
          }

          res = {
            ...res,
            y: res.y ? res.y + item.y : item.y
          }

          return res
        }, {})
      })
    }

    this.line(chartData)

    // Create the legend.
    if (this.legendTarget !== null) {
      this.leg = new Legend({
        activeColor: this.activeColor,
        colors: [this.color],
        data: this.legendData.map((el, idx) => ({ ...el, index: idx })),
        target: this.legendTarget,
        parentTarget: this.target,
        showPercentages: false,
        texts: this.texts
      })
    }
  }

  line (data) {
    // Create svg element.
    const svg = select(this.target)
      .append('svg')
      .attr('width', this.width)
      .attr('height', this.height)
      .attr('style', 'width: 100%; height: auto;')
      .attr('viewBox', `0 0 ${this.width} ${this.height}`)

    const transSpeed = 200

    /**
     * Utility function to be called on mouseover
     * highlight line that is being hovered
     */
    const mouseover = (ev, d) => {
      svg.select('[data-selector=chartElements] > :nth-child(1) [data-selector=colorChanger]')
        .transition()
        .duration(transSpeed)
        .attr('stroke', this.activeColor)

      svg.selectAll('circle')
        .transition()
        .duration(transSpeed)
        .attr('fill', this.activeColor)
      // Call update-row functions of the legend.
      this.leg.highlightRow(0)
    }

    /**
     * Utility function to be called on mouseout
     */
    const mouseout = (ev, d) => {
      svg.select('[data-selector=chartElements] > :nth-child(1) [data-selector=colorChanger]')
        .transition()
        .duration(transSpeed)
        .attr('stroke', this.color)

      svg.selectAll('circle')
        .transition()
        .duration(transSpeed)
        .attr('fill', this.color)

      // Call update-row functions of the legend.
      this.leg.resetHighlightedRow(0)
    }

    // Create x scale
    const xScale = scaleBand()
      .rangeRound([this.margin.left, this.width - this.margin.right])
      .domain(data.map((d) => {
        return d.x
      }))
      .paddingOuter(0)

    svg
      .style('overflow', 'visible')
      .append('g')
      .style('font-size', '14px')
      .attr('transform', 'translate(0,' + (this.height - this.margin.bottom) + ')')
      .attr('class', 'xAxis')
      .call(axisBottom(xScale))
      .selectAll('text')
      .style('text-anchor', 'end')
      .attr('dx', '-.8em')
      .attr('dy', '.15em')
      .attr('transform', 'rotate(-65)')

    /*
     * After having rendered the xAxis, get its height to add it to the height of the outer svg.
     * This way the outer dimensions of the svg always correspond to the current form of xAxis.
     */
    const xAxisHeight = svg.select('.xAxis').node().getBBox().height
    const heightWithXAxis = Math.round(this.height + xAxisHeight)
    svg.attr('height', heightWithXAxis).attr('viewBox', `0 0 ${this.width} ${heightWithXAxis}`)

    // Add y scale
    const yScale = scaleLinear()
      .rangeRound([this.height - this.margin.bottom, this.margin.top])
      .domain([0, max(data, d => {
        return d.y
      })])

    svg
      .append('g')
      .call(axisLeft(yScale).ticks(2))
      .style('font-size', '14px')
      .attr('transform', 'translate(' + this.margin.left + ', 0)')

    const lineFunc = line()
      .x((d) => xScale(d.x))
      .y((d) => yScale(d.y))

    const lineOffset = (this.width - this.margin.left - this.margin.right) / data.length / 2
    svg
      .append('g')
      .attr('data-selector', 'chartElements')
      .attr('transform', 'translate(' + lineOffset + ', 0)')
      .append('g')
      .append('path')
      .attr('d', lineFunc(data))
      .attr('stroke', this.color)
      .style('stroke-width', 4)
      .attr('fill', 'none')
      .attr('data-selector', 'colorChanger')
      .on('mouseover', mouseover)
      .on('mouseout', mouseout)

    svg.selectAll('dataPoints')
      .data(data)
      .enter()
      .append('circle')
      .attr('data-selector', 'dataPoints')
      .attr('fill', this.color)
      .attr('cx', d => xScale(d.x))
      .attr('cy', d => yScale(d.y))
      .attr('r', 5)
      .attr('transform', 'translate(' + lineOffset + ', 0)')
      .on('mouseover', mouseover)
      .on('mouseout', mouseout)
  }
}
