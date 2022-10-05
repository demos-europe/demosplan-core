

import availableTranslations from '@DpJs/generated/translations.json'
import exposedRoutes from '@DpJs/generated/routes.json'
import { hasPermission } from '@DpJs/lib/utils/hasPermission'
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router'
import Translator from '../../vendor/willdurand/js-translation-bundle/Resources/js/translator'

const bootstrap = function () {
  Routing.setRoutes(exposedRoutes.routes)

  if (document.location.href.match('app_dev')) {
    Routing.setBaseUrl(Routing.getBaseUrl() + '/app_dev.php')
  }

  // eslint-disable-next-line no-undef
  if (URL_PATH_PREFIX) {
    // eslint-disable-next-line no-undef
    Routing.setBaseUrl(Routing.getBaseUrl() + URL_PATH_PREFIX)
  }

  Translator.fromJSON(availableTranslations)

  window.Routing = Routing
  window.Translator = Translator
  window.hasPermission = hasPermission
}

export { bootstrap }
