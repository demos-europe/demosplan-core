/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/* eslint-disable-next-line import/extensions */
// import 'swagger-ui-dist/swagger-ui.css' // This has to be commented out, to make the vue-compate run.
import { initialize } from '@DpJs/InitVue'
import { SwaggerUIBundle } from 'swagger-ui-dist'

initialize().then(() => {
  SwaggerUIBundle({
    domNode: document.querySelector('[data-swagger-ui]'),
    url: Routing.generate('dplan_api_openapi_json')
  })
})
