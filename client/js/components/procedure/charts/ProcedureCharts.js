/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { initBarChart, initBarPieChart, initDonutChart } from '@DpJs/lib/procedure/charts/helpers/init'
import { getColorFromCSS } from '@DpJs/lib/procedure/charts/helpers/getColorsFromCSS'
import SankeyDiagram from '@DpJs/lib/procedure/charts/SankeyDiagram'

export default class ProcedureCharts {
  constructor () {
    return {
      BarPieChart: initBarPieChart('#barChart', '#pieChart', '#chartLegend', '#pieLegend'),
      StatusChart: initBarChart('#statusChart', '#statusChartLegend'),
      VoteChart: initBarChart('#voteChart', '#voteChartLegend'),
      MovedStatements: this.initSankeyDiagram('#movedStatements'),
      StatementNewCountDonut: initDonutChart('#statementNewCount', '#statementNewCountLegend'),
      StatementProcessingCountDonut: initDonutChart('#statementProcessingCount', '#statementProcessingCount'),
      StatementCompletedCountDonut: initDonutChart('#statementCompletedCount', '#statementCompletedCountLegend')
    }
  }

  initSankeyDiagram (elementId) {
    const element = document.querySelector(elementId)

    if (element) {
      const movedStatements = JSON.parse(element.getAttribute('data-moved-statements'))
      const currentProcedureId = element.getAttribute('data-current-procedure-id')
      const currentProcedureTitle = element.getAttribute('data-current-procedure-title')
      const currentProcedureStatementsTotal = element.getAttribute('data-current-procedure-statements-total')
      const cssColorClasses = JSON.parse(element.getAttribute('data-colors'))

      const colors = {}
      for (const color in cssColorClasses) {
        colors[color] = getColorFromCSS(cssColorClasses[color])
      }

      /*
       * Set the current Procedure and a copy of it.
       * (we need the copy (fake-node) to have a box instead of a line)
       */
      const data = {}
      data.nodes = [{
        title: currentProcedureTitle,
        id: currentProcedureId
      },
      {
        title: ' ',
        id: 'fake-node'
      }]
      data.links = [{
        source: currentProcedureId,
        target: 'fake-node',
        value: currentProcedureStatementsTotal,
        linkTitle: Translator.trans('statement.sum', { count: currentProcedureStatementsTotal }),
        color: colors.current
      }]

      /*
       * Add all moved-from procedures to the node-list
       * and link them to the current procedure
       */
      movedStatements.toThisProcedure.procedures.forEach(el => {
        if (el.title.length > 20) {
          el.title = el.title.slice(0, 8) + ' ... ' + el.title.slice(-8) + ' (' + el.value + ')'
        } else {
          el.title = el.title + ' (' + el.value + ')'
        }
        data.nodes.push(el)

        const link = {
          source: el.id,
          target: currentProcedureId,
          value: el.value,
          direction: 'r',
          linkTitle: el.title + ' --> ' + currentProcedureTitle,
          color: colors.toThisProcedure
        }

        data.links.push(link)
      })

      /*
       * Add all moved-to procedures to the node-list
       * and link them to the copy current procedure
       */
      movedStatements.fromThisProcedure.procedures.forEach(el => {
        if (el.title.length > 20) {
          el.title = el.title.slice(0, 8) + ' ... ' + el.title.slice(-8) + ' (' + el.value + ')'
        } else {
          el.title = el.title + ' (' + el.value + ')'
        }
        data.nodes.push(el)

        const link = {
          source: 'fake-node',
          target: el.id,
          value: el.value,
          direction: 'l',
          linkTitle: currentProcedureTitle + ' --> ' + el.title,
          color: colors.fromThisProcedure
        }

        data.links.push(link)
      })

      let diagramHeight = 200
      const diagramWidth = 400
      const heightMultiplier = 20
      const trasholdHeight = diagramHeight / heightMultiplier
      if (movedStatements.fromThisProcedure.total > trasholdHeight || movedStatements.toThisProcedure.total > trasholdHeight) {
        if (movedStatements.fromThisProcedure.total > movedStatements.toThisProcedure.total) {
          diagramHeight = movedStatements.fromThisProcedure.total * heightMultiplier
        } else {
          diagramHeight = movedStatements.toThisProcedure.total * heightMultiplier
        }
      }

      const diagramDimensions = [diagramWidth, diagramHeight]

      return new SankeyDiagram({
        target: elementId,
        data,
        dimensions: diagramDimensions,
        colors
      })
    }
  }
}
