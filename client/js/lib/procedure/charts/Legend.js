/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { format } from 'd3-format'
import { hasOwnProp } from '@demos-europe/demosplan-ui'
import { select } from 'd3-selection'
import { sum } from 'd3-array'

export default class Legend {
  constructor (options) {
    const defaults = {
      target: '#chartLegend',
      data: [{ key: 'labelName', value: 5 }],
      colors: ['#cccccc', '#ebebeb', '#999999'],
      activeColor: '#ff0000',
      showPercentages: true,
      texts: {
        'no-data-fallback': Translator.trans('fragments.not.submitted'),
        'legend-headline': Translator.trans('fragments'),
        'data-names': Translator.trans('fragments'),
        'data-name': Translator.trans('fragment')
      },
      transSpeed: 200
    }

    Object.assign(this, { ...defaults, ...options })

    this.draw()
  }

  /**
   * Function to handle legend.
   *
   * @param data
   * @returns {{update: function(*=, *)}}
   */
  draw () {
    // Create table for legend.
    this.legend = select(this.target).append('table').attr('data-selector', 'legend').attr('class', 'c-chart__legend')

    // Utility function to be called on mouseover of a legend element.
    const mouseover = (ev, d) => {
      const i = d.index
      this.updateElementColor(this.getChartElement(i), this.activeColor)
      this.updateElementColor(this.getLegendElement(i), this.activeColor)

      const dataPoints = this.getDataPointElements()
      if (dataPoints.size() > 0) {
        this.updateElementColor(dataPoints, this.activeColor, true)
      }
    }

    // Utility function to be called on mouseout of a legend element.
    const mouseout = (ev, d) => {
      const i = d.index
      this.updateElementColor(this.getChartElement(i), this.colors[i])
      this.updateElementColor(this.getLegendElement(i), this.colors[i])
      const dataPoints = this.getDataPointElements()
      if (dataPoints.size() > 0) {
        this.updateElementColor(dataPoints, this.colors[i])
      }
    }

    /**
     * BUILD LEGEND TABLE
     */
    this.legend.append('thead')
      .append('tr')
      .append('th')
      .attr('colspan', 4)
      .attr('data-selector', 'tableHeading')
      .attr('class', 'c-chart__legend-headline')
      .text((d) => {
        return Translator.trans(this.texts['legend-headline'], { count: this.total })
      })

    // Create one row per segment.
    const tr = this.legend.append('tbody')
      .selectAll('[data-selector=tableContent]')
      .data(this.data)
      .enter()
      .append('tr')
      .attr('data-selector', 'tableContent')
      .attr('class', 'c-chart__legend-content')
      .on('mouseover', mouseover)
      .on('mouseout', mouseout)

    // Create the first column for each segment.
    tr.append('td')
      .append('svg')
      .attr('width', '16')
      .attr('height', '16')
      .append('rect')
      .attr('width', '16')
      .attr('height', '16')
      .transition()
      .duration(this.transSpeed)
      .attr('fill', (d, i) => {
        return this.colors[i]
      })

    // Create the second column for each segment.
    tr.append('td').html((d) => {
      let text = d.key || d.label

      if (typeof d === 'object' && hasOwnProp(d, 'url')) {
        text = `<a href="${d.url}">${text}</a>`
      }
      return text
    })

    if (this.showPercentages) {
      // Create the third column for each segment.
      tr.append('td')
        .attr('data-selector', 'legendFreq')
        .attr('class', 'is-hidden')
        .text((d) => {
          return format(',')(this.getValue(d))
        })
      // Create the fourth column for each segment.
      tr.append('td')
        .attr('data-selector', 'legendPerc')
        .text((d) => {
          return this.getPercentage(d, this.data)
        })
    } else {
      // Create the third column for each segment.
      tr.append('td')
        .attr('data-selector', 'legendFreq')
        .text((d) => {
          return format(',')(this.getValue(d))
        })
    }
  }

  // Utility function to be used to update the legend.
  update (nD, st) {
    // Update the data attached to the row elements.
    const l = this.legend.select('tbody').selectAll('tr').data(nD)

    // Update the frequencies.
    l.select('[data-selector=legendFreq]').text((d) => {
      let val = 0
      if (typeof d[1] !== 'undefined') {
        val = d[1]
      } else if (typeof d.freq !== 'undefined') {
        val = d.freq
      } else if (typeof d.count !== 'undefined') {
        val = d.count
      }

      return format(',')(val)
    })

    // Update the percentage column.
    l.select('[data-selector=legendPerc]').text((d) => {
      return this.getPercentage(d, nD)
    })

    const lh = this.legend.select('thead').selectAll('tr').data(nD)

    // Update the headline.
    lh.select('[data-selector=tableHeading]').text(() => {
      let headingPostfix = Translator.trans(this.texts['legend-headline'], { count: st.count })
      const entityName = st.count === 1 ? this.texts['data-name'] : this.texts['data-names']

      if (hasOwnProp(st, 'Category')) {
        headingPostfix = st.Category !== 'gesamt' ? entityName + ' ' + '"' + st.Category + '"' : headingPostfix
      }
      if (hasOwnProp(st, 'key')) {
        headingPostfix = st.key !== 'gesamt' ? entityName + ' ' + '"' + st.key + '"' : headingPostfix
      }
      return headingPostfix
    })
  }

  // Utility function to be used to highlight the hovered/selected row.
  highlightRow (idx) {
    this.legend.select('tbody').selectAll('[data-selector=tableContent]:nth-child(' + (idx + 1) + ')').classed('highlight-row', true)
    this.legend.select('tbody').selectAll('[data-selector=tableContent]:nth-child(' + (idx + 1) + ') rect').transition().duration(this.transSpeed).attr('fill', this.activeColor[0])
  }

  resetHighlightedRow (idx) {
    this.legend.select('tbody').selectAll('[data-selector=tableContent]:nth-child(' + (idx + 1) + ')').classed('highlight-row', false)
    this.legend.select('tbody').selectAll('[data-selector=tableContent]:nth-child(' + (idx + 1) + ') rect').transition().duration(this.transSpeed).attr('fill', this.colors[idx])
  }

  /**
   * Update the fill or stroke color of an element.
   *
   * The function tests if a "stroke" attr is set, in this case it changes
   * the stroke color. If not, it changes the fill color.
   * @param element
   * @param color
   */
  updateElementColor (element, color) {
    element.transition().duration(this.transSpeed)
    if (element.attr('stroke')) {
      element.attr('stroke', color)
    } else {
      element.attr('fill', color)
    }
  }

  /**
   * Get reference to a single element in a rendered chart (identified by `colorChanger` value).
   * @param i
   * @return {*}
   */
  getChartElement (i) {
    return select(this.parentTarget).selectAll('[data-selector=chartElements] > :nth-child(' + (i + 1) + ') [data-selector=colorChanger]')
  }

  getDataPointElements () {
    return select(this.parentTarget).selectAll('[data-selector=dataPoints]')
  }

  /**
   * Get reference to a single rect element in a rendered legend (identified by `tableContent` value).
   * @param i
   * @return {*}
   */
  getLegendElement (i) {
    return select(this.target).selectAll('[data-selector=tableContent]:nth-child(' + (i + 1) + ') rect')
  }

  // Utility function to handle different sorts of objects.
  getValue (obj) {
    const value = obj.value || obj.freq || obj.count

    // Return 0 if obj has no or a falsy value
    return typeof value !== 'undefined' || typeof obj[1] === 'number' ? value : 0
  }

  // Utility function to compute percentage.
  getPercentage (d, aD) {
    const val = this.getValue(d)

    return format('.1%')(val / sum(aD.map((v) => {
      return this.getValue(v)
    })))
  };
}
