/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for public_index.html.twig
 * where we don't have any map
 */
import { initialize } from '@DpJs/InitVue'

/*
 *  Values for combined filters
 *  all in participation
 */
const allInParticipationExternal = ' participation externalphase2 externalphase3 externalphase4'
const allInParticipationInternal = ' participation internalphase2 internalphase3 internalphase4'
//  All in participation and preparation
const allInParticipationPreparationExternal = ' participation externalphase2 externalphase3 externalphase4 preparation'
const allInParticipationPreparationInternal = ' participation internalphase2 internalphase3 internalphase4 preparation'

/*
 *  Results for combined filters
 *  all in participation
 */
const externalParticipationPhases = document.querySelectorAll('[data-phase-key="participation"], [data-phase-key="externalphase2"], [data-phase-key="externalphase3"]')
const internalParticipationPhases = document.querySelectorAll('[data-phase-key="participation"], [data-phase-key="internalphase2"], [data-phase-key="internalphase3"]')
//  Results for preparation
const preparationPhase = document.querySelectorAll('[data-phase-key="preparation"]')

/**
 * Sets 'all in participation and preparation' as default selected option. When javascript is disabled, 'all' option is selected instead
 * If 'all in participation and preparation' has no results, 'all in participation' is set as default selected option instead
 * If 'all in participation' has no results either, 'all' is set as default selected option instead
 */
const setSelectedOption = function () {
  const combinedParticipationPreparation = document.getElementById('combinedParticipationPreparation')
  const combinedFilterOption = document.getElementById('combinedFilter')
  const allOption = document.getElementById('all-option')
  const filterPhasesSelectEl = document.getElementById('filterPhases')

  // Allow projects to opt out of default filtering
  if (filterPhasesSelectEl?.dataset?.defaultSelected === 'all') {
    return
  }

  //  If there are results for any of the filters in the 'all in participation and preparation' option
  if ((combinedParticipationPreparation.value === allInParticipationPreparationInternal &&
    (internalParticipationPhases.length !== 0 || preparationPhase.length !== 0)) ||
    (combinedParticipationPreparation.value === allInParticipationPreparationExternal &&
      (externalParticipationPhases.length !== 0 || preparationPhase.length !== 0))) {
    //  Select 'all in participation and preparation'
    combinedParticipationPreparation.selected = true
    //  Unselect 'all'
    allOption.selected = false
    filterProceduresByPhase()
  } else if ((combinedFilterOption.value === allInParticipationInternal && internalParticipationPhases.length !== 0) ||
    (combinedFilterOption.value === allInParticipationExternal && externalParticipationPhases.length !== 0)) {
    /*
     *  If there are results for any of the filters in the 'all in participation' option
     *  Select 'all in participation'
     */
    combinedFilterOption.selected = true
    //  Unselect 'all'
    allOption.selected = false
    filterProceduresByPhase()
  } else {
    /*
     *  If both 'all in participation and preparation' and 'all in participation' have no results, select 'all'
     *  select all
     */
    allOption.selected = true
    filterProceduresByPhase()
  }
}

/**
 * Loops over procedure list elements and checks if their current phase matches the selected filter option
 * If not, they are hidden
 */
const filterProceduresByPhase = function () {
  // Get selected filter option
  const filterPhasesSelectEl = document.getElementById('filterPhases')
  if (filterPhasesSelectEl !== null) {
    const selectedPhasesToFilter = filterPhasesSelectEl.value.split(/(\s+)/).filter(function (e) {
      return e.trim().length > 0
    })
    // Get list elements in procedurelist
    const listElements = document.getElementsByClassName('c-procedurelist__item')

    let visibleElementsCount = 0

    const allOptionValue = document.getElementById('all-option').value

    // Loop over them
    for (let i = 0; i < listElements.length; i++) {
      // Get phase of procedure in procedurelist
      const phase = listElements[i].getElementsByClassName('phase')[0]
      let phaseExt
      if (listElements[i].getElementsByClassName('phaseExt')[0] !== null) {
        phaseExt = listElements[i].getElementsByClassName('phaseExt')[0]
      }

      // Check if phase of procedure matches selected filter option
      let showElement = selectedPhasesToFilter[0] === allOptionValue
      for (let j = 0; j < selectedPhasesToFilter.length && showElement === false; j++) {
        showElement = phase.innerHTML === selectedPhasesToFilter[j]
        if (typeof phaseExt !== 'undefined') {
          showElement = phase.innerHTML === selectedPhasesToFilter[j] || phaseExt.innerHTML === selectedPhasesToFilter[j]
        }
      }

      // If phase of procedure matches selected filter option, show it
      if (showElement) {
        if (listElements[i].classList.contains('hidden')) {
          listElements[i].classList.remove('hidden')
          listElements[i].classList.add('block')
        }
        visibleElementsCount++
      } else if (showElement === false) {
        // If phase of procedure does not match selected filter option, hide it
        if (listElements[i].classList.contains('block')) {
          listElements[i].classList.remove('block')
        }
        listElements[i].classList.add('hidden')
      }
    }

    //  No results
    const noResults = document.getElementById('no-results')

    //  If there are no results
    if (visibleElementsCount === 0) {
      //  If 'all in participation' is selected, show 'all' instead
      if (selectedPhasesToFilter.length === 3) {
        filterPhasesSelectEl.value = ['all']
      } else {
        noResults.style.display = 'block'
      }

      // If there are results
    } else {
      noResults.style.display = 'none'
    }

    //  Hide options without results
    const options = filterPhasesSelectEl.getElementsByTagName('option')

    for (let i = 0; i < options.length; i++) {
      //  For all options except 'all'
      if (options[i].value !== 'all') {
        //  Check if there are results for the option

        //  if 'all in participation' for external users
        if (options[i].value === allInParticipationExternal) {
          if (externalParticipationPhases.length === 0) {
            options[i].style.display = 'none'
          }
        } else if (options[i].value === allInParticipationInternal) {
          //  If 'all in participation' for internal users
          if (internalParticipationPhases.length === 0) {
            options[i].style.display = 'none'
          }
        } else if (options[i].value === allInParticipationPreparationExternal) {
          //  If 'all in participation and preparation' for external users
          if (externalParticipationPhases.length === 0 && document.querySelectorAll('[data-phase-key="preparation"]').length === 0) {
            options[i].style.display = 'none'
          }
        } else if (options[i].value === allInParticipationPreparationInternal) {
          //  If 'all in participation and preparation' for internal users
          if (internalParticipationPhases.length === 0 && document.querySelectorAll('[data-phase-key="preparation"]').length === 0) {
            options[i].style.display = 'none'
          }
        } else {
          //  If any other option value
          if (document.querySelectorAll('[data-phase-key="' + options[i].value + '"]').length === 0) {
            options[i].style.display = 'none'
          }
        }
      }
    }
  }
}

initialize().then(() => {
  setSelectedOption()
  document.getElementById('filterPhases').addEventListener('change', filterProceduresByPhase)
})
