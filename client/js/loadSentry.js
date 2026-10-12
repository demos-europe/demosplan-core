/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import * as Sentry from '@sentry/browser'
import { browserTracingIntegration } from '@sentry/browser'

export default function loadSentry () {
  if (window.dplan.sentryDsn === '') {
    return
  }

  const tracesSampleRate = Number(window.dplan.sentryTracesSampleRate) || 0

  Sentry.init({
    dsn: window.dplan.sentryDsn,
    tracesSampleRate,
    /*
     * Tracing is off unless a rate is configured, and browserTracingIntegration
     * collects nothing without it. It carries the interaction metrics (INP,
     * long animation frames), not just pageload timings.
     */
    integrations: tracesSampleRate > 0 ? [browserTracingIntegration()] : [],
  })
}
