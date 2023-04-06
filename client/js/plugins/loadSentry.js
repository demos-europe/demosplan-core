import * as Sentry from '@sentry/browser'
import { BrowserTracing } from '@sentry/tracing'

export default function loadSentry () {
  if (window.dplan.sentryDsn !== '') {
    Sentry.init({
      dsn: window.dplan.sentryDsn,
      integrations: [new BrowserTracing({
        attachProps: true,
        tracing: true,
        tracingOptions: {
          trackComponents: true
        }
      })]
    })
  }
}
