/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default function AdministrationMaster () {
  /*
   *  These are the js functions taken from the administration_edit_master and administration_new_master (Blaupausen),
   *  because after adding a vue component for CC e-mails the js functions are overwritten by vue and the event listeners
   *  have to be assigned again after vue mount
   */

  // gets its jquery from window

  // *********FROM ADMINISTRATION_EDIT_MASTER*********

  if (hasPermission('feature_statement_notify_counties')) {
    //  If permission is enabled, enable editing of email addresses only with checked r_sendMailsToCounties
    const $sendMailsToCounties = $('[name="r_sendMailsToCounties"]')
    const $receiverFields = $('[name^="r_receiver"]')
    const change = function (event) {
      if ($(this).is(':checked')) {
        $receiverFields.prop('disabled', false)
      } else {
        $receiverFields.attr('disabled', 'disabled')
        //  Remove error classes since email fields are not saved with unchecked r_sendMailsToCounties
        $receiverFields.removeClass('is-required-error')
      }
    }
    $sendMailsToCounties.on('change', change)
    change()
  }

  // *********FROM ADMINISTRATION_EDIT*********

  function updatePermissionsetdescription (element) {
    const $element = $(element)
    const permissionset = $element.find(':selected').data('permissionset')
    let permissionsetVerbose

    switch (permissionset) {
      case 'hidden':
        permissionsetVerbose = Translator.trans('permissionset.hidden')
        break
      case 'read':
        permissionsetVerbose = Translator.trans('permissionset.read')
        break
      case 'write':
        permissionsetVerbose = Translator.trans('permissionset.write')
        break
      default:
        break
    }
    // Update der Beschreibung der Phase
    $element.next('div').find('[data-procedure-permission-set]').text(permissionsetVerbose)
  }

  // Ausgabe der Rechte der Institutionen in der Phase
  const internalPermissionElement = $('select[name="r_phase"]')
  // Ausgabe beim Laden der Seite
  updatePermissionsetdescription(internalPermissionElement)
  // Ausgabe beim Verändern
  $(internalPermissionElement).on('change', function () {
    updatePermissionsetdescription($(this))
  })

  //  Dito Phase der Öffentlichkeitsbeteiligung
  if (hasPermission('area_public_participation')) {
    // Ausgabe der Rechte der Öffentlichkeit in der Phase
    const externalPermissionElement = $('select[name="r_publicParticipationPhase"]')
    // Ausgabe beim Laden der Seite
    updatePermissionsetdescription(externalPermissionElement)
    // Ausgabe beim Verändern
    $(externalPermissionElement).on('change', function () {
      updatePermissionsetdescription($(this))
    })
  }

  // Atm broken (was not reimplemented after removing jQuery autocomplete)
  if (dplan.settings.useOpenGeoDb === true && document.querySelector('.js__locationName') !== null) {
    // Autocomplete Ort
    $('.js__locationName').autocomplete({
      serviceUrl: Routing.generate('core_suggest_location_json', { maxResults: 12 }),
      minChars: 3,
      onSelect: function (suggestion) {
        $('input[name="r_locationPostCode"]').val(suggestion.data.postcode)
        $('input[name="r_locationName"]').val(suggestion.data.city)
        $('input[name="r_municipalCode"]').val(suggestion.data.municipalCode.substr(0, 5))
      },
      width: 350,
      zIndex: 10000
    })
  }

  if (hasPermission('feature_short_url')) {
    // Zeige die resultierende URL im Beschreibungstext an
    const shortUrlInput = $('input[name="r_shortUrl"]')
    // Falls die URL ungültig war, steht schon was in dem Inputfeld
    if (typeof shortUrlInput.val() !== 'undefined' && shortUrlInput.val().length > 0) {
      $('#js--shortUrl').html(encodeURI(shortUrlInput.val()))
    }
    // Schreibe den Input urlencodiert in den Beschreibungstext
    $(shortUrlInput).on('keyup', function () {
      $('#js--shortUrl').html(encodeURI($(this).val()))
    })
  }
}
