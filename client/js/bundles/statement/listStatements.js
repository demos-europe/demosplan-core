/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_statements.html.twig
 */

import CvStatementList from '@DpJs/components/statement/CvStatementList'
import CvStatementSegmentContainer from '@DpJs/components/statement/CvStatementSegmentContainer'
import CvSegmentList from '@DpJs/components/statement/segments/CvSegmentList'
import { initialize } from '@DpJs/InitVue'
import ListOriginalStatements from '@DpJs/components/statement/listOriginalStatements/ListOriginalStatements'
import ListStatements from '@DpJs/components/statement/listStatements/ListStatements'

const components = {
  ListOriginalStatements,
  CvStatementList,
  CvStatementSegmentContainer,
  CvSegmentList,
  ListStatements
}
const apiStores = ['AssignableUser', 'Statement', 'StatementSegment', 'OriginalStatement']

initialize(components, {}, apiStores)
