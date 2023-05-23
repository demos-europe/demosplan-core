/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import $ from 'jquery'
/**
 * FE behaviour for New Statement, Statement Detail, Cluster Detail
 *
 * - paragraph selection
 * - toggle orga/citizen fields
 * - show procedure-phase options based on r_role
 * - copy-fragment-considerations
 * - Tag-Selector
 *
 * @improve
 *  - rename this file to <Statement|Assessment>Detail.js or something that correctly expresses its scope
 *  - change js hooks to data-whatever or better, use dedicated vue component for whole logic
 *
 *
 */
export default function AssessmentStatement () {
  //  Paragraph selection
  const $elementSelect = $('#elementSelect')
  const elementSelectValue = $elementSelect.val()
  $elementSelect.on('change', function () {
    $('.js-paragraph').hide()
    $('#js-paragraph_' + $(this).val()).show()

    $('.js-document')
      .hide()
    $('#js-document_' + $(this).val())
      .show()
  })

  $('.js-paragraph').hide()
  $('#js-paragraph_' + elementSelectValue).show()

  $('.js-document')
    .hide()
  $('#js-document_' + elementSelectValue)
    .show()
}
