/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Formerly known as js__statement.js
 */

import { hasOwnProp, prefixClass } from '@demos-europe/demosplan-ui'

export default function StatementForm () {
  //  Statement Form Behavior
  if ($(prefixClass('.js__statementForm')).length > 0 || $('#dp-map').length > 0) {
    /*
     *  #########################################################
     * get jQuery toggleClass() function to trigger custom event
     *  this way the statement form can check changes in sessionStorage every time it is opened
     *
     *  http://stackoverflow.com/q/2157963/6234391
     */
    (function () {
      const ev = new $.Event('toggleClass')
      const orig = $.fn.toggleClass
      $.fn.toggleClass = function () {
        $(this).trigger(ev)
        return orig.apply(this, arguments)
      }
    })()

    /*
     *  #########################################################
     *  functions needed for statement workflow
     */

    const saveStateFieldsToSessionStorage = function (fields) {
      let data = window.getUserdataSession('publicStatement')

      if (!data) {
        data = {}
      }

      if (!hasOwnProp(data, 'state')) {
        data.state = {}
      }

      for (const field in fields) {
        if (hasOwnProp(fields, field)) {
          data.state[field] = fields[field]
        }
      }

      //  Write current input values to sessionStorage
      window.addSessionStorageData('publicStatement', data)
    }

    const getStateFromSessionStorage = function (state) {
      const userData = window.getUserdataSession('publicStatement')

      if (userData) {
        if (userData.state[state]) {
          return userData.state[state]
        } else {
          return null
        }
      } else {
        return null
      }
    }

    const getFieldFromSessionStorage = function (field) {
      const userData = window.getUserdataSession('publicStatement')
      let userDataProcedure = null

      if (userData) {
        userDataProcedure = userData[dplan.procedureIdKey]
      }

      if (!!userDataProcedure && !!userDataProcedure[dplan.currentStatementId]) {
        return userDataProcedure[dplan.currentStatementId][field]
      }
      return null
    }

    const activateQueryAreaButton = function (el, hideOnly) {
      if (!!hideOnly || el.hasClass(prefixClass('is-activated'))) {
        el.html('Potenzialfläche auswählen')
        el.removeClass(prefixClass('is-activated'))
        el.next().find(prefixClass('.fa-long-arrow-right')).remove()
      } else {
        el.html('Potenzialfläche auswählen...')
        el.addClass(prefixClass('is-activated'))
        el.next().append('<i class="' + prefixClass('fa fa-2x fa-long-arrow-right c-actionbox__arrow') + '" aria-hidden="true"></i>')
      }
    }

    const showMapButtonState = function (id) {
      //  Drawing tools button in actionbox
      console.warn('deprecated: dear dev. Please refactor showMapButtonState()', id)
      if (id === 'saveStatementButton') {
        return window.dplan.statement.labels[id].buttons
      } else {
        /*
         *  Buttons in map popup
         * return showPopupButton()
         */
      }
    }

    const setDplanStatementMethods = function () {
      //  New 'statement' property in dplan obj
      window.dplan.statement = { labels: {} }

      //  Labels for buttons / headings
      window.dplan.statement.labels = {
        priorityAreaPopup: {
          headings: {
            negative: Translator.trans('priorityArea.rejected'),
            positive: Translator.trans('priorityArea')
          },
          buttons: Translator.trans('statement.new')
        },
        markLocationPopup: {
          buttons: Translator.trans('statement.new')
        },
        saveStatementButton: {
          states: {
            visible: {
              button: Translator.trans('statement.map.draw_to_map'),
              title: Translator.trans('statement.map.draw.no_drawing_warning')
            },
            active: {
              button: Translator.trans('statement.continue'),
              title: Translator.trans('statement.map.draw.drawing_complete')
            }

          }
        }
      }

      //  Assign functions to methods of 'statement' obj to be reusable by map scripts
      window.dplan.statement.activateQueryAreaButton = activateQueryAreaButton
      window.dplan.statement.saveStateFieldsToSessionStorage = saveStateFieldsToSessionStorage
      window.dplan.statement.getFieldFromSessionStorage = getFieldFromSessionStorage
      window.dplan.statement.getStateFromSessionStorage = getStateFromSessionStorage
      window.dplan.statement.showMapButtonState = showMapButtonState
    }

    /*
     *  #########################################################
     *  variables needed for statement functions
     */

    //  set methods that go into the global dplan obj
    setDplanStatementMethods()

    /*
     *  #################################################################
     *  commands that run on click
     */

    $(document).on('click', prefixClass('.js__statementForm'), function (event) {
      //  Get reference to clicked element
      const el = $(this)

      // Get current action from data attr
      const action = typeof el.data('statement-action') !== 'undefined' ? el.data('statement-action') : false

      // Will be executed by default when any .js__statementForm element is clicked
      saveStateFieldsToSessionStorage({ latest: action })

      event.preventDefault()

      // Functions which are special to elements are triggered by data attr
      switch (action) {
        case 'closeHint':
          saveStateFieldsToSessionStorage({ closeHint: true })
          break
        default:
          break
      }
    })
  }
};

/**
 * Speichere Werte in dem sessionStorage. Überschreibe dabei existierende Werte
 * @param key
 * @param data
 */
window.addSessionStorageData = function (key, data) {
  // Besorge die bestehenden Werte
  let existingData = window.getUserdataSession(key)
  if (existingData === null) {
    existingData = {}
  }
  // Ersetze die bestehenden Werte
  for (const dataItem in data) {
    if (hasOwnProp(data, dataItem)) {
      existingData[dataItem] = data[dataItem]
    }
  }
  window.setUserdataSession(key, existingData)
}

/**
 * Save Value in sessionStorage if applicable
 * @param key
 * @param value
 */
window.setUserdataSession = function (key, value) {
  if (sessionStorage) {
    sessionStorage.setItem(key, JSON.stringify(value))
  }
}

/**
 * Get Value from sessionStorage
 * @param key
 */
window.getUserdataSession = function (key) {
  if (sessionStorage) {
    return JSON.parse(sessionStorage.getItem(key))
  }

  return null
}

/**
 * Remove from sessionStorage
 * @param key
 */
window.removeUserdataSession = function (key) {
  if (sessionStorage) {
    sessionStorage.removeItem(key)
  }
}

/**
 * Clear sessionStorage
 */
window.clearUserdataSession = function () {
  if (sessionStorage) {
    sessionStorage.clear()
  }
}
