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
  if (window.dplan.sentryDsn !== '') {
    Sentry.init({
      dsn: window.dplan.sentryDsn,
      integrations: [browserTracingIntegration({
        attachProps: true,
        tracing: true,
        tracingOptions: {
          trackComponents: true
        }
      })]
    })
  }
}
