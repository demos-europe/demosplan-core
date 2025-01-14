/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { dpApi } from '@demos-europe/demosplan-ui'

const setWarningForUnsetBounds = () => {
  const statusBox = document.querySelector('#js__statusBox')

  // Fill error-data for initialExtend into hidden fields
  document.querySelector('input[name="r_mapExtent"]').setAttribute('value', '')
  statusBox.innerText = Translator.trans('map.import.bounds.warning')
  statusBox.classList.remove('sr-only')
  statusBox.classList.remove('flash-confirm')
  statusBox.classList.add('flash-warning')
  // Enable save-button
  document.getElementById('saveBtn').removeAttribute('disabled')
}

const setConfirmForBounds = function (data) {
  const statusBox = document.querySelector('#js__statusBox')

  // Fill data for initialExtend into hidden fields
  document.querySelector('input[name="r_mapExtent"]').setAttribute('value', data.procedure.bounds)
  statusBox.innerText = Translator.trans('map.import.bounds.success')
  statusBox.classList.remove('sr-only')
  statusBox.classList.remove('flash-warning')
  statusBox.classList.add('flash-confirm')
  // Enable save-button
  document.getElementById('saveBtn').removeAttribute('disabled')
}

function getXplanboxBounds (procedureName) {
  return dpApi({
    method: 'GET',
    url: Routing.generate('DemosPlan_xplanbox_get_bounds', { procedureName })
  })
    .then(data => {
      if (data.data.code === 100 && data.data.success === true) {
        setConfirmForBounds(data.data)
      } else {
        setWarningForUnsetBounds()
      }
    })
    .catch(() => {
      setWarningForUnsetBounds()
    })
}

export default function CreateProcedure () {
  const statusBox = document.getElementById('js__statusBox')
  const saveBtn = document.getElementById('saveBtn')

  /*
   * @improve T15008
   * disable save-button - user can only save if we have a valid  plis-id seleced
   */
  saveBtn.setAttribute('disabled', true)

  const planningCauseSelect = document.getElementById('js__plisPlanungsanlass')

  //  Get plis data from BE
  const plisSelect = document.querySelector('select[name="r_plisId"]')
  plisSelect.addEventListener('change', value => {
    saveBtn.setAttribute('disabled', true)
    // Fill hidden fields
    const selectedOption = plisSelect[value.currentTarget.selectedIndex]
    document.querySelector('input[name="r_name"]').setAttribute('value', selectedOption.text)
    // Hide status-box
    statusBox.classList.add('sr-only')
    // Ask BE about the selection - but only if selectedOption is not empty
    if (selectedOption.value !== '') {
      dpApi({
        url: Routing.generate('DemosPlan_plis_get_procedure', { uuid: selectedOption.value }),
        method: 'GET'
      })
        .then(data => {
          const dataResponse = JSON.parse(data.data)
          if (dataResponse.code === 100 && dataResponse.success === true) {
            planningCauseSelect.classList.remove('lbl__hint', 'flash-error', 'u-p-0_25', 'u-mt-0_25')
            const planningOccasionText = dataResponse.procedure.planungsanlass

            planningCauseSelect.innerHTML = planningOccasionText.replace(/\n/g, '<br>')
            document.querySelector('input[name="r_externalDesc"]').setAttribute('value', planningOccasionText)
            const elt = document.querySelector('select[name="r_plisId"]')
            getXplanboxBounds(elt.options[elt.selectedIndex].text)
          } else {
            planningCauseSelect.innerText = Translator.trans('error.plis.getplanningcause')
            planningCauseSelect.classList.add('flash-error', 'u-p-0_25', 'u-mt-0_25')
          }
        })
        .catch(() => {
          planningCauseSelect.innerText = Translator.trans('error.plis.getplanningcause')
          planningCauseSelect.classList.remove('lbl__hint')
          planningCauseSelect.classList.add('flash-error', 'u-p-0_25', 'u-mt-0_25')
        })
    } else {
      planningCauseSelect.innerText = Translator.trans('planningcause.select.hint')
      planningCauseSelect.classList.add('lbl__hint')
      planningCauseSelect.classList.remove('flash-error', 'u-p-0_25', 'u-mt-0_25')
    }
  })
}
