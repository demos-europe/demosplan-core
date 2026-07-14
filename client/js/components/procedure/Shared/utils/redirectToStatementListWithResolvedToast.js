/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import lscache from 'lscache'

/*
 * Both dissolving a group and detaching its last member end the same way: the group's
 * head detail page no longer exists, so send the user back to the statement list. The
 * externId travels via lscache so the list can show the "group resolved" toast on mount
 * (URL stays clean).
 *
 * When the redirect is triggered by detaching the last member (rather than the explicit
 * "dissolve group" action), detachedStatementExternId carries that member's externId so
 * the list can also show the "statement detached" toast, shown before the "group resolved"
 * one — a plain location.href change would otherwise unload the page before a toast fired
 * here could ever be seen.
 */
export function redirectToStatementListWithResolvedToast (procedureId, externId, detachedStatementExternId = null) {
  if (null !== detachedStatementExternId) {
    lscache.set(`${procedureId}:clusterElementDetached`, JSON.stringify({ statementId: detachedStatementExternId, clusterId: externId }))
  }

  lscache.set(`${procedureId}:clusterResolved`, externId)
  globalThis.location.href = Routing.generate('dplan_procedure_statement_list', { procedureId })
}
