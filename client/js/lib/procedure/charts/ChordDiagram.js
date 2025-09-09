/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import * as d3 from 'd3'

const matrix = [
  [0, 500, 500, 300],
  [500, 0, 100, 200],
  [500, 100, 0, 100],
  [300, 200, 100, 0]
]

/*
 *Var matrix = [
 *    [ 1,1, 1,2, 1,3, 1.4],
 *    [ 2,1, 2,2, 2,3, 2.4],
 *    [ 3,1, 3,2, 3,3, 3.4],
 *    [ 4,1, 4,2, 4,3, 4.4],
 *];
 */

/*
 *
 *var matrix = [
 *
 *    [0,  500, 100, 100],
 *    [ 500, 0, 100, 500],
 *    [ 500, 500, 0, 800],
 *    [ 100, 500,  500, 0]
 *];
 *
 */

const data = [{ name: 'appel', value: 5 },
  { name: 'Melon', value: 17 },
  { name: 'Banana', value: 13 },
  { name: 'Pie', value: 25 }]

const svg = d3.select('svg')
const width = +svg.attr('width')
const height = +svg.attr('height')
const outerRadius = Math.min(width, height) * 0.5 - 40
const innerRadius = outerRadius - 30

const chord = d3.chord()
  .padAngle(0.05)
  .sortSubgroups(d3.descending)

const arc = d3.arc()
  .innerRadius(innerRadius)
  .outerRadius(outerRadius)

const ribbon = d3.ribbon()
  .radius(innerRadius)

const color = d3.scaleOrdinal()
  .domain(d3.range(4))
  .range(['#ccff00', '#00ff33', '#ff00ff', '#0000ff'])

const g = svg.append('g')
  .attr('transform', `translate(${width / 2},${height / 2})`)
  .datum(chord(matrix))

const group = g.append('g')
  .attr('class', 'groups')
  .selectAll('g')
  .data(chords => chords.groups)
  .enter().append('g')

group.append('path')
  .style('fill', d => color(d.index))
  .style('stroke', d => d3.rgb(color(d.index)).darker())
  .attr('d', arc)

const groupTick = group.selectAll('.group-tick')
  .data(d => groupTicks(d, 1e3, data))
  .enter().append('g')
  .attr('class', 'group-tick')
  .attr('transform', d => `rotate(${(d.angle * 180 / Math.PI - 90)}) translate(${outerRadius},0)`)

groupTick.append('line')
  .attr('x2', 6)

groupTick
  .filter(d => d.value)
  .append('text')
  .attr('x', 8)
  .attr('dy', '.35em')
  .attr('transform', d => d.angle > Math.PI ? 'rotate(180) translate(-16)' : null)
  .style('text-anchor', d => d.angle > Math.PI ? 'end' : null)
  .text(d => d.value)

g.append('g')
  .attr('class', 'ribbons')
  .selectAll('path')
  .data(function (chords) {
    return chords
  })
  .enter().append('path')
  .attr('d', ribbon)
  .on('mouseout', () => fade(1))
  .on('mouseover', () => fade(0))
  .style('fill', d => color(d.target.index))
  .style('stroke', d => d3.rgb(color(d.target.index)).darker())

// Returns an array of tick angles and values for a given group and step.
function groupTicks (d, step, data) {
  return d3.range(0, d.value, step).map(value => ({ value: data[d.index].name, angle: value + d.startAngle }))
}

// Returns an event handler for fading a given chord group.
function fade (opacity) {
  return function (g, i) {
    svg.selectAll('.ribbons path')
      .filter(d => (d.source.index !== i && d.target.index !== i))
      .transition()
      .style('opacity', opacity)
  }
}
