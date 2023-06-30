/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import CharCount from '../../lib/core/libs/CharCount'

/**
 * Toggle input fields for captions.
 * @param fieldName
 * @param show
 */
function toggleCaptionInput (fieldName, show) {
  let captionId

  // Mapping of file upload fieldNames and input fields for the respective caption
  if (fieldName === 'r_picture') {
    captionId = 'r_pictitle'
  } else if (fieldName === 'r_pdf') {
    captionId = 'r_pdftitle'
  }

  const selectCaptionId = document.getElementById(captionId)
  if (selectCaptionId) {
    if (show) {
      selectCaptionId.parentNode.classList.remove('hidden')
      selectCaptionId.setAttribute('required', 'required')
      /*
       * Since the input is hidden onLoad, the `CharCount()` that is fired after mounting Vue
       * in `loadLibs()` does not take effect here.
       */
      CharCount(selectCaptionId)
    } else {
      selectCaptionId.parentNode.classList.add('hidden')
      selectCaptionId.removeAttribute('required')
    }
  }
}

/**
 * Bind events to form elements
 */
export default function newsAdminInit () {
  // On paste remove line breaks from copied text, because in IE11 there are empty lines added and it is impossible to paste something to input fields (only empty line gets pasted)
  const inputs = []
  if (document.querySelector('[name="r_title"]')) {
    inputs.push(document.querySelector('[name="r_title"]'))
  }
  if (document.querySelector('[name="r_pictitle"]')) {
    inputs.push(document.querySelector('[name="r_pictitle"]'))
  }
  if (document.querySelector('[name="r_pdftitle"]')) {
    inputs.push(document.querySelector('[name="r_pdftitle"]'))
  }

  if (inputs.length > 0) {
    inputs.forEach(input => {
      input.addEventListener('paste', e => {
        e.preventDefault()
        const clipboardData = e.clipboardData || window.clipboardData || e.originalEvent.clipboardData
        const text = clipboardData.getData('text')
        const newText = text.replace(/[\r\n]/g, '')
        const initialValue = e.target.value
        e.target.value = initialValue.substring(0, input.selectionStart) + newText + initialValue.substring(input.selectionEnd)
      })
    })
  }

  // Hide captions at the beginning if no PDF was uploaded before and show it as required field if an PDF upload happened.
  if (document.getElementById('news_picture') === null) {
    toggleCaptionInput('r_picture', false)
  }
  if (document.getElementById('news_pdf') === null) {
    toggleCaptionInput('r_pdf', false)
  }

  // Show caption depending on which file was uploaded
  document.addEventListener('uploadSuccess', event => {
    toggleCaptionInput(event?.detail.fieldName, true)
  })
}
