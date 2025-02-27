/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list.html.twig
 */

import DpReportListing from '@DpJs/components/report/DpReportListing'
import { hasPermission } from '@demos-europe/demosplan-ui'
import { initialize } from '@DpJs/InitVue'

const stores = {}
const components = { DpReportListing }

const presetModules = ['general', 'public_phase', 'invitations', 'register_invitations', 'final_mails', 'statements', 'elements', 'single_documents', 'paragraphs']
  .filter(name => hasPermission('feature_procedure_report_' + name))
  .map(name => {
    const camelName = name.replace(/_([a-z])/g, (match, p1) => p1.toUpperCase())
    return {
      name: camelName,
      defaultQuery: {
        group: camelName
      }
    }
  })

initialize(components, stores, [], { report: presetModules })
