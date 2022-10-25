/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import axios from 'axios'
import hasOwnProp from '@DpJs/lib/utils/hasOwnProp'
import { stringify } from 'qs'
import { v4 as uuid } from 'uuid'

let currentProcedureId = null
let jwtToken = null

if (typeof dplan !== 'undefined') {
  if (hasOwnProp(dplan, 'procedureId')) {
    currentProcedureId = dplan.procedureId
  }
  if (hasOwnProp(dplan, 'jwtToken')) {
    jwtToken = dplan.jwtToken
  }
}

const apiDefaultHeaders = {
  'X-JWT-Authorization': 'Bearer ' + jwtToken
}

const api2defaultHeaders = {
  Accept: 'application/vnd.api+json',
  'Content-Type': 'application/vnd.api+json',
  'X-JWT-Authorization': 'Bearer ' + jwtToken
}

const getHeaders = function (params) {
  const headers = params.url.includes('api/2.0/')
    ? { ...api2defaultHeaders, ...params.headers }
    : { ...apiDefaultHeaders, ...params.headers }

  // Add current procedure id only if set
  if (currentProcedureId !== null) {
    headers['X-Demosplan-Procedure-Id'] = currentProcedureId
  }
  return headers
}

const doRequest = (params) => {
  return axios({
    ...{ data: {} },
    ...params,
    headers: getHeaders(params)
  })
}

const dpApi = doRequest
dpApi.post = (url, params = {}, data = {}, options = {}) => doRequest({ method: 'post', url, data, params, options })
dpApi.get = (url, params = {}, options = {}) => {
  if (options.serialize === true) {
    const config = {
      paramsSerializer: (params) => stringify(params, { encodeValuesOnly: true, arrayFormat: 'brackets' }),
      headers: getHeaders({ ...params, url })
    }
    delete options.serialize
    return axios.create(config).request({ method: 'get', data: {}, url, params, ...options })
  } else {
    return doRequest({ method: 'get', url, data: {}, params, options })
  }
}
dpApi.put = (url, params = {}, data = {}, options = {}) => doRequest({ method: 'put', url, data, params, options })
dpApi.patch = (url, params = {}, data = {}, options = {}) => doRequest({ method: 'patch', url, data, params, options })
dpApi.delete = (url, params = {}, data = {}, options = {}) => doRequest({ method: 'delete', url, params, options })

/**
 * Do a JsonRpc call.
 *
 * Id is optional and defaults to a UUID v4.
 *
 * @param {string} method
 * @param {object} parameters
 * @param {string} id
 * @return {Promise}
 */
const dpRpc = function (method, parameters, id = null) {
  const data = {
    jsonrpc: '2.0',
    method: method,
    id: id === null ? uuid() : id,
    params: parameters
  }

  return doRequest({
    method: 'post',
    url: Routing.generate('rpc_generic_post'),
    data
  })
}

/**
 * Turn messages into notifications
 *
 * @param responseMeta
 * @return {boolean}
 */
const handleResponseMessages = function (responseMeta) {
  if (hasOwnProp(responseMeta, 'messages')) {
    for (const type in responseMeta.messages) {
      for (const message in responseMeta.messages[type]) {
        dplan.notify.notify(type, Translator.trans(responseMeta.messages[type][message]))
      }
    }
  }
}

/**
 * Perform rudimentary response validation, handle response messages.
 *
 * @param {Object} response
 *                    The axios wrapper around the XMLHttpRequest instance.
 *                    See https://github.com/axios/axios#response-schema for documentation.
 * @param {Object} [messages]
 *                    Define messages to display with response codes that are expected to be returned
 *                    from a certain endpoint.
 * @param {Object} messages[(200|201|202|204)]
 *                    The response status code this particular message should be displayed with is represented
 *                    by the key of the respective property.
 * @param {String} messages[(200|201|202|204)].text
 *                    The actual text of the message.
 * @param {String} messages[(200|201|202|204)].type
 *                    The message type (may be 'confirm', 'error', 'info', or 'warning').
 * @return {Promise<any>}
 */
const checkResponse = function (response, messages) {
  return new Promise((resolve, reject) => {
    if (hasOwnProp(response, 'data') && response.data && hasOwnProp(response.data, 'meta')) {
      handleResponseMessages(response.data.meta)
      /*
       * Sometimes we get response with status 400 and to display the error messages hidden there we have
       * to pass response.meta (and not response.data.meta as above) to the messages handler
       */
    } else if (dplan !== undefined && dplan.debug && hasOwnProp(response, 'errors') && hasOwnProp(response, 'meta') && hasOwnProp(response.meta, 'messages')) {
      handleResponseMessages(response.meta)
    } else if (messages && hasOwnProp(messages, response.status)) {
      /*
       * The generic api (/api/2.0/{resourceType}) does not specify success messages.
       * Instead, custom messages passed by the calling component are displayed here.
       */
      dplan.notify.notify(messages[response.status].type, Translator.trans(messages[response.status].text))
    }

    if (response.status >= 400) {
      // @improve handle 404, 500 specially?
      reject(response.data)
    } else if (response.status === 200) {
      // Got data!
      resolve(response.data)
    } else {
      // Got no data
      resolve(null)
    }
  })
}

function makeFormPost (payload, url) {
  const postData = new FormData()

  for (const [key, value] of Object.entries(payload)) {
    if (Array.isArray(value)) {
      value.forEach(el => postData.append(key + '[]', el))
    } else {
      postData.append(key, value)
    }
  }

  return dpApi({
    method: 'post',
    url: url,
    data: postData,
    headers: { 'Content-Type': 'multipart/form-data' }
  })
}

export { dpApi, handleResponseMessages, checkResponse, dpRpc, makeFormPost }
