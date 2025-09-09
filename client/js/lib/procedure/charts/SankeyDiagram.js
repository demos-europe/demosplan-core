/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import * as d3 from 'd3'
import * as d3Sankey from 'd3-sankey-diagram'

/**
 *
 * @param data, target
 */
export default class SankeyDiagram {
  constructor (options) {
    const defaults = {
      data: {
        nodes: [],
        links: []
      },
      target: 'body',
      dimensions: [400, 200]
    }

    Object.assign(this, { ...defaults, ...options })

    // Init Layout
    const layout = d3Sankey.sankey()
      .extent([[0, 0], this.dimensions])

    // Setup diagram
    const diagram = d3Sankey.sankeyDiagram()
      .linkColor(d => d.color)
      .nodeTitle(d => {
        return d.title
      })
      .linkTitle(d => {
        return d.linkTitle
      })
    // Create the Diagram
    d3.select(this.target).append('svg')
      .attr('class', 'sankey-diagram')
      .attr('width', '50%')
      .attr('height', '100%')
      .style('overflow', 'visible')
      .attr('viewBox', '0 0 ' + this.dimensions[0] + ' ' + this.dimensions[1])
      .datum(layout(this.data))
      .call(diagram)
  }
}
