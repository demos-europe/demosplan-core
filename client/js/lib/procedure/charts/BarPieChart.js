/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { arc, pie } from 'd3-shape'
import { axisBottom, axisLeft } from 'd3-axis'
import { max, sum } from 'd3-array'
import { scaleBand, scaleLinear } from 'd3-scale'
import { format } from 'd3-format'
import { interpolate } from 'd3-interpolate'
import Legend from './Legend'
import { select } from 'd3-selection'

/**
 *  Stelle ein Dashboard mit Barchart und abhängigem Piechart dar
 *  Basiert auf http://bl.ocks.org/NPashaP/96447623ef4d342ee09b
 *
 *  fData hat die Form
 *  [
 *   {Category:'Stimme zu',count:20,freq:{bvwp_bund:2,info_sbv:1,kriterien:4,schema:3,autobahn_neu:20,autobahn_ausbau:15,bstrasse:2}},
 *   {Category:'Stimme teilweise zu',count:16,freq:{bvwp_bund:1,info_sbv:2,kriterien:3,schema:4,autobahn_neu:10,autobahn_ausbau:8,bstrasse:12}},
 *  ]
 *
 *  die dazugehörige Kategoriedefinition z.B.
 *  [
 *   {key:"bvwp_bund", label:'Aufstellung des BVWP Teil Straße durch den Bund'},
 *   {key:"info_sbv", label:'Bereitstellung von Informationen durch die Straßenbauverwaltung'},
 *  ]
 *
 *  und die Farben z.B.
 *  {bar: ["#cccccc", "#999999"], pie: ["#ff0000", "#00ff00", "#0000ff"]}
 * (bar -> basis und hover // pie: Farben der Kategorien. sollten min. Anz. Kategorien entsprechen )
 *
 * @param id CSS-Selektor des Elementes, in das das Dashboard eingefügt werden soll
 * @param fData
 * @param categoryDefinition
 * @param colors
 */

export default class BarPieChart {
  constructor (options) {
    const defaults = {
      /* Default options here */
      fData: [],
      categoryDefinition: [],
      targetClasses: {},
      colors: {
        bar: ['#cccccc', '#999999'],
        pie: ['#ff0000', '#00ff00', '#0000ff'],
        active: ['#ff0000']
      },
      texts: {
        'no-data-fallback': Translator.trans('statements.none'),
        'data-names': Translator.trans('statements'),
        'data-name': Translator.trans('statement'),
        pie: {
          'legend-headline': 'statements.grouped.priority',
          'data-names': Translator.trans('statements'),
          'data-name': Translator.trans('statement')
        },
        bar: {
          'legend-headline': 'statements.grouped.status',
          'data-names': Translator.trans('statements'),
          'data-name': Translator.trans('statement')
        }
      }
    }

    Object.assign(this, { ...defaults, ...options })
    this.fData = this.fData.map((el, idx) => ({ ...el, index: idx }))

    // Calculate total frequency by segment for all state.
    this.tF = this.categoryDefinition.map((d, idx) => ({
      type: d.key,
      label: d.label,
      freq: sum(this.fData.map(t => t.freq[d.key])),
      index: idx
    }))

    // Store overall total
    this.total = this.sumData(this.fData)

    if (this.total > 0) {
      // Regular case

      // calculate total frequency by state for all segment.
      this.sF = this.fData.map((d, idx) => ({ key: d.Category, count: d.count, index: idx }))

      // Prepare data for legend
      this.statementLegendData = this.fData.map(item => {
        const legData = { key: item.Category, value: item.count, index: item.index }

        // Only return an url-field if an url field is specified in item
        return item.url ? Object.assign({}, legData, { url: item.url }) : legData
      })

      //  Generate charts and legend
      this.hG = this.histoGram(this.sF) // Create the histogram.
      this.pC = this.pieChart(this.tF) // Create the pie-chart.

      /**
       *
       * @TODO normalize Data for legend refs: legend.js:83 et. al. -> legend data slightly more normal? (this.statementLegendData)
       *
       *
       */
      this.hLeg = new Legend({
        data: this.statementLegendData,
        target: this.targetClasses.chartLegend,
        parentTarget: this.targetClasses.bar,
        colors: this.colors.bar,
        activeColor: this.colors.active,
        texts: this.texts.bar,
        total: this.total
      })
      this.pLeg = new Legend({
        data: this.tF,
        target: this.targetClasses.pieLegend,
        parentTarget: this.targetClasses.pie,
        colors: this.colors.pie,
        activeColor: this.colors.active,
        texts: this.texts.pie,
        total: this.total
      })
    } else {
      // Display a message if no data is found to be displayed.
      select(this.targetClasses.bar).append('p').text(this.texts['no-data-fallback'])
    }
  }

  /**
   *
   * @param data
   * @returns {number}
   */
  sumData (data) {
    return this.fData.reduce((acc, data) => {
      if (typeof data.count !== 'undefined') {
        return acc + data.count
      } else if (typeof data.freq !== 'undefined') {
        return acc + data.freq
      } else {
        return acc
      }
    }, 0)
  }

  /**
   *
   * @param c
   * @param chartType
   * @returns {string | *}
   */
  setColor (c, chartType) {
    if (typeof this.colors[chartType][c] !== 'undefined') return this.colors[chartType][c]
    return '#cccccc'
  }

  /**
   * Function to handle histogram
   *
   * @param fD
   * @returns {{update: function(*=, *=)}}
   */
  histoGram (fD) {
    const hgBasisWidth = 400
    const hgBasisHeight = 250
    const transSpeed = 200
    const hGDim = { t: 25, r: 0, b: 10, l: 50 }

    hGDim.w = hgBasisWidth - hGDim.l - hGDim.r
    hGDim.h = hgBasisHeight - hGDim.t - hGDim.b

    const mouseover = (ev, d) => {
      /*
       * Utility function to be called on mouseover.
       * filter for selected state.
       */
      const elemSet = hGsvg
      const i = d.index

      select(elemSet[i]).transition().duration(transSpeed).attr('fill', this.setColor(0, 'active'))

      const st = this.fData.filter((s) => { return s.Category === d.key })[0]
      const nD = Object.keys(st.freq).map((s) => { return { type: s, freq: st.freq[s] } })

      // Call update functions of pie-chart and legend.
      this.pC.update(nD)
      this.hLeg.highlightRow(i)
      this.pLeg.update(nD, st)
    }

    const mouseout = (ev, d) => {
      /*
       * Utility function to be called on mouseout.
       * reset the pie-chart and legend.
       */
      const elemSet = hGsvg
      const i = d.index

      select(elemSet[i]).transition().duration(transSpeed).attr('fill', this.setColor(i, 'bar'))

      this.pC.update(this.tF)
      this.hLeg.resetHighlightedRow(i)
      this.pLeg.update(this.tF, { Category: 'gesamt', count: this.total })
    }

    // Create svg for histogram.
    const hGsvg = select(this.targetClasses.bar).append('svg')
      .attr('width', '100%')
      .attr('height', '100%')
      .attr('viewBox', '0 0 ' + hgBasisWidth + ' ' + hgBasisHeight)
      .append('g')
      .attr('transform', 'translate(' + hGDim.l + ',' + hGDim.t + ')')

    /* AXIS-mapping */
    const x = scaleBand()
      .rangeRound([0, hGDim.w])
      .padding(0.1)
      .domain(fD.map(d => d.key))
    const y = scaleLinear()
      .rangeRound([hGDim.h, 0])
      .domain([0, max(fD, d => d.count)])
    const xAxis = axisBottom(x)

    // Add x-axis to the histogram svg.
    hGsvg.selectAll('.tick text')
      .enter()
      .attr('overflow', 'visible !important')
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
      .attr('x', d => x(d.key))
      .attr('y', d => y(d.count))
      .attr('width', x.bandwidth())
      .attr('height', d => (hGDim.h - (y(d.count))))
      .attr('data-selector', 'colorChanger')
      .attr('fill', (d, i) => this.setColor(i, 'bar'))
      .on('mouseover', mouseover) // Mouseover is defined above.
      .on('mouseout', mouseout) // Mouseout is defined above.

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
      .text(d => format(',')(d.count))
      .attr('x', d => x(d.key) + x.bandwidth() / 2)
      .attr('y', d => y(d.count) - 5)
      .attr('text-anchor', 'middle')

    // Create function to update the bars. This will be used by pie-chart.
    const update = nD => {
      // Attach the new data to the bars.
      const bars = hGsvg.selectAll('[data-selector=bar]').data(nD)

      // Transition the height and color of rectangles.
      bars.select('rect').transition().duration(transSpeed)
        .attr('data-selector', 'colorChanger')
        .attr('y', d => y(d.count))
        .attr('height', d => hGDim.h - y(d.count))

      // Transition the frequency labels location and change value.
      bars.select('[data-selector=bar-value]').transition().duration(transSpeed)
        .text(d => format(',')(d.count))
        .attr('y', d => y(d.count) - 5)
    }

    return { update }
  }

  /**
   * Function to handle pieChart.
   *
   * @param pD
   * @returns {{update: function(*)}}
   */
  pieChart (pD) {
    const radius = 100
    const pieRadius = radius * 0.5
    const labelRadius = pieRadius * 1.2
    const transSpeed = 200

    // Create function to draw the arcs of the pie slices.
    const pieArc = arc().outerRadius(pieRadius).innerRadius(0)
    const labelArc = arc().outerRadius(labelRadius).innerRadius(labelRadius)
    const labelLineArc = arc().outerRadius(labelRadius).innerRadius(labelRadius)
    // Create a function to compute the pie slice angles.
    const pieChart = pie().sort(null).value(d => d.freq)

    const midAngle = d => (d.startAngle + (d.endAngle - d.startAngle) / 2)
    // Minimum angle (from 2*PI ) to detect if Label overlap
    const minRad = 0.4
    // Offset to move label out
    const labelOffset = 0.4
    // Helper to know how many Tiles before have been too small
    let lastPiece = 0
    // Check if offeset for not fitting Label has to be reseted
    const lastPieceCounter = (d, last) => {
      lastPiece = last
      let counter = 0

      if (d.endAngle - d.startAngle >= minRad || d.startAngle === 0) {
        lastPiece = -1
      }

      if (d.endAngle - d.startAngle < minRad) {
        lastPiece++
      }

      if (lastPiece < 0) {
        counter = 0
      } else {
        counter = lastPiece
      }

      return counter
    }

    // Utility function to be called on mouseover a pie slice.
    const mouseover = (ev, d) => {
      // Call the update function of histogram with new data.
      const elemSet = piesvg
      const i = d.data.index

      const st = this.fData.map((v, idx) => ({ key: v.Category, count: v.freq[d.data.type], index: idx }))
      const nD = { key: d.data.label, count: d.data.freq, index: i }

      // Add a Highlight-Class to the related Row in the legend
      select(elemSet[i]).transition().duration(transSpeed).attr('fill', this.setColor(0, 'active'))
      // Update histoGram
      this.hG.update(st, this.colors.active[0])
      this.pLeg.highlightRow(i)
      this.hLeg.update(st, nD)
    }

    // Utility function to be called on mouseout a pie slice.
    const mouseout = (ev, d) => {
      // Call the update function of histogram with all data.
      const elemSet = piesvg
      const i = d.data.index

      const st = this.fData.map((v, idx) => ({ key: v.Category, count: v.count, index: idx }))
      const nD = { key: 'gesamt', count: this.total, index: i }

      // Add a Highlight-Class to the related Row in the legend
      select(elemSet[i]).transition().duration(transSpeed).attr('fill', this.setColor(i, 'pie'))
      // Update histoGram
      this.hG.update(st, this.colors.bar[i])
      this.pLeg.resetHighlightedRow(i)
      this.hLeg.update(st, nD)
    }

    /*
     * Animating the pie-slice requiring a custom function which specifies
     * how the intermediate paths should be drawn.
     */
    const arcTween = (a) => {
      const i = interpolate(this._current, a)
      this._current = i(0)
      return t => pieArc(i(t))
    }

    const labelOpacity = a => (a.value > 0) ? 1 : 0

    const textPosTween = (d) => {
      this._current = this._current || d
      const interpol = interpolate(this._current, d)
      this._current = interpol(0)

      // Check if offset for not fitting label has to be reset
      const offset = lastPieceCounter(d, lastPiece)

      return (t) => {
        const d2 = interpol(t)
        const pos = labelArc.centroid(d2)
        pos[0] = pieRadius * (midAngle(d2) < Math.PI ? 1 : -1)
        pos[0] = pos[0] * (1.2 + (labelOffset * offset))
        pos[1] = pos[1] * (1 + (labelOffset * offset))

        return 'translate(' + pos + ')'
      }
    }

    const textAnchorDetection = (d) => {
      this._current = this._current || d

      const interpol = interpolate(this._current, d)
      this._current = interpol(0)
      return (t) => {
        const d2 = interpol(t)
        return midAngle(d2) < Math.PI ? 'start' : 'end'
      }
    }

    const labelLineTween = (d) => {
      this._current = this._current || d
      const interpol = interpolate(this._current, d)
      this._current = interpol(0)

      const offset = lastPieceCounter(d, lastPiece)

      return (t) => {
        // Outer point from label-radius ( incl offset calc)
        const d2 = interpol(t)
        let outerRadiusPoint = labelLineArc.centroid(d2)
        const currentOffset = 1 + (labelOffset * offset)
        const labelOffesetArc = arc().outerRadius(labelRadius * currentOffset).innerRadius(labelRadius * currentOffset)
        outerRadiusPoint = labelOffesetArc.centroid(d2)

        // Horizontal line from outer point
        const pos = labelLineArc.centroid(d2)
        pos[0] = pieRadius * (midAngle(d2) < Math.PI ? 1 : -1)
        pos[0] = pos[0] * currentOffset
        pos[1] = pos[1] * currentOffset

        return [pieArc.centroid(d2), outerRadiusPoint, pos]
      }
    }

    /**
     * DRAW CHART
     */

    // create svg for pie chart.
    const piesvg = select(this.targetClasses.pie)
      .append('svg')
      .style('overflow', 'visible')
      .attr('width', '100%')
      .attr('height', '100%')
      .attr('overflow', 'visible !important')
      .attr('viewBox', '0 0 ' + radius * 4 + ' ' + radius * 2.5)
      .append('g')
      .attr('transform', 'translate(' + radius * 2 + ',' + radius * 1.25 + ')')

    // Draw the pie slices.
    const g = piesvg
      .append('g')
      .attr('data-selector', 'chartElements')
      .selectAll('path')
      .data(pieChart(pD))
      .enter()
      .append('g')
      .attr('data-selector', 'chartEl')

    g.append('path')
      .attr('data-selector', 'colorChanger')
      .attr('d', pieArc)
      .attr('fill', (d) => {
        return this.setColor(d.index, 'pie')
      })
      .on('mouseover', mouseover).on('mouseout', mouseout)

    const text = g.append('text')
      .attr('transform', (d) => {
        // Check if offeset for not fitting Label has to be reseted
        const offset = lastPieceCounter(d, lastPiece)

        const pos = labelArc.centroid(d)
        pos[0] = pieRadius * (midAngle(d) < Math.PI ? 1 : -1)
        pos[0] = pos[0] * (1.2 + (labelOffset * offset))
        pos[1] = pos[1] * (1 + (labelOffset * offset))

        return 'translate(' + pos + ')'
      })
      .attr('dy', '0.35em')
      .attr('opacity', labelOpacity)
      .style('text-anchor', (d) => {
        return midAngle(d) < Math.PI ? 'start' : 'end'
      })
      .text((d) => {
        for (let i = 0, len = this.categoryDefinition.length; i < len; i++) {
          if (this.categoryDefinition[i].key === d.data.type && d.data.freq > 0) {
            const length = 10
            let label = this.categoryDefinition[i].label
            if (label.length > length) {
              label = label.substring(0, length) + '...'
            }

            return ' ' + label + ' (' + d.data.freq + ') '
          }
        }
        return ''
      })

    text.transition()
      .duration(transSpeed)
      .attrTween('transform', textPosTween)
      .attr('opacity', labelOpacity)
      .style('text-anchor', textAnchorDetection)

    // Draw Line from Chart to Label
    const polyline = piesvg.selectAll('polyline')
      .data(pieChart(pD))
      .enter()
      .append('polyline')
      .attr('fill', 'none')
      .attr('opacity', (a) => {
        return (a.value === 0) ? 0 : 1
      })
      .attr('stroke', '#000')
      .attr('pointer-events', 'none')
      .transition()
      .duration(transSpeed)

    polyline
      .attr('points', (d) => {
        let outerRadiusPoint = labelLineArc.centroid(d)
        // Prevent overlapping Labels
        const pos = labelLineArc.centroid(d)

        // Check if offeset for not fitting Label has to be reseted
        const offset = lastPieceCounter(d, lastPiece)
        const currentOffset = (1 + ((labelOffset - 0.1) * offset))

        const labelOffesetArc = arc().outerRadius(labelRadius * currentOffset).innerRadius(labelRadius * currentOffset)
        outerRadiusPoint = labelOffesetArc.centroid(d)

        pos[0] = pos[0] * currentOffset
        pos[0] = pieRadius * (midAngle(d) < Math.PI ? 1 : -1)
        pos[1] = pos[1] * currentOffset

        return [pieArc.centroid(d), outerRadiusPoint, pos]
      })

    // Create function to update pie-chart. This will be used by histogram.
    const update = (nD) => {
      piesvg.selectAll('path')
        .data(pieChart(nD))
        .transition()
        .duration(transSpeed)
        .attrTween('d', arcTween)

      piesvg.selectAll('text')
        .data(pieChart(nD))
        .attr('opacity', labelOpacity)
        .text((d) => {
          for (let i = 0, len = this.categoryDefinition.length; i < len; i++) {
            if (this.categoryDefinition[i].key === d.data.type && d.data.freq > 0) {
              let label = this.categoryDefinition[i].label
              const length = 10
              if (label.length > length) {
                label = label.substring(0, length) + '...'
              }

              return label + ' (' + d.data.freq + ') '
            }
          }
          return ''
        })
        .transition()
        .duration(transSpeed)
        .attrTween('transform', textPosTween)
        .styleTween('text-anchor', textAnchorDetection)

      piesvg.selectAll('polyline')
        .data(pieChart(nD))
        .attr('opacity', labelOpacity)
        .transition()
        .duration(transSpeed)
        .attrTween('points', labelLineTween)
    }

    return { update }
  }
}
