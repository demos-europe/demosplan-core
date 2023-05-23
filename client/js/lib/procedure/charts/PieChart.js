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

// @improve use only needed modules -> https://github.com/d3/d3/blob/master/API.md
import * as d3 from 'd3'

/**
 *
 * @param options {data: [{name, link, value}], radius: int, innerRadius: int, colors: [], target: cssSelector }
 */
export default class PieChart {
  constructor (options) {
    const defaults = {
      data: [{ name: 'Apple', link: 'haha', value: 5 },
        { name: 'Banana', link: 'blabla', value: 8 },
        { name: 'Cherry', link: 'blubb', value: 3 }],
      radius: 100,
      innerRadius: 0, // If not 0 Pie goes Donut
      colors: ['#bbb', '#999', '#666'],
      target: 'body'
    }

    Object.assign(this, { ...defaults, ...options })

    if (typeof this.labelRadius === 'undefined') {
      this.labelRadius = (this.radius * 2 + this.innerRadius) / 3
    }

    this.caliber = this.radius * 2

    this.draw()
  }

  draw () {
    const pie = d3.pie()
      .sort(null)
      .value((d) => {
        return d.value
      })

    const svg = d3.select(this.target).append('svg')
      .attr('style', 'width: 100%; height: 100%;')
      .attr('viewBox', '0 0 ' + this.caliber + ' ' + this.caliber)
      .append('g')

    const arc = d3.arc()
      .outerRadius(this.radius - 10)
      .innerRadius(this.innerRadius)

    const labelArc = d3.arc()
      .outerRadius(this.labelRadius)
      .innerRadius(this.labelRadius)

    const color = d3.scaleOrdinal(this.colors)

    /**
     * DRAW CHART
     */
    const g = svg.selectAll('.arc')

      .data(pie(this.data))
      .enter()
      .append('g')
      .attr('class', 'arc')
      .attr('style', 'transform: translate(' + this.radius + 'px,' + this.radius + 'px)')

    g.append('path')
      .attr('d', arc)
      .style('fill', (d) => {
        return color(d.data.name)
      })

    g.append('text')
      .attr('transform', (d) => {
        return 'translate(' + labelArc.centroid(d) + ')'
      })
      .attr('dy', '0.35em')
      .html((d) => {
        return '<a href="' + d.data.link + '">' + d.data.name + '</a>'
      })
  }
}
