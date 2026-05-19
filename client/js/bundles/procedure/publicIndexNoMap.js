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

/**
 * Returns the count of visible procedure list elements matching any of the given phase ids.
 */
const countMatchingProcedures = function (phaseIds) {
  const listElements = document.getElementsByClassName('c-procedurelist__item')
  let count = 0
  for (const listElement of listElements) {
    const phaseSpan = listElement.getElementsByClassName('phase')[0]
    const phaseExtSpan = listElement.getElementsByClassName('phaseExt')[0]
    const phaseValue = phaseSpan ? phaseSpan.innerHTML.trim() : ''
    const phaseExtValue = phaseExtSpan ? phaseExtSpan.innerHTML.trim() : ''
    if (phaseIds.includes(phaseValue) || phaseIds.includes(phaseExtValue)) {
      count++
    }
  }
  return count
}

/**
 * Splits a space-separated option value into individual phase ids.
 */
const splitOptionValue = function (value) {
  return value.split(/\s+/).filter(v => v.trim().length > 0)
}

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

  const participationPreparationIds = splitOptionValue(combinedParticipationPreparation.value)
  const participationIds = splitOptionValue(combinedFilterOption.value)

  if (participationPreparationIds.length > 0 && countMatchingProcedures(participationPreparationIds) > 0) {
    combinedParticipationPreparation.selected = true
    allOption.selected = false
    filterProceduresByPhase()
  } else if (participationIds.length > 0 && countMatchingProcedures(participationIds) > 0) {
    combinedFilterOption.selected = true
    allOption.selected = false
    filterProceduresByPhase()
  } else {
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
    const selectedPhasesToFilter = splitOptionValue(filterPhasesSelectEl.value)
    // Get list elements in procedurelist
    const listElements = document.getElementsByClassName('c-procedurelist__item')

    let visibleElementsCount = 0

    const allOptionValue = document.getElementById('all-option').value

    // Loop over them
    for (const listElement of listElements) {
      // Get phase of procedure in procedurelist
      const phase = listElement.getElementsByClassName('phase')[0]
      let phaseExt
      if (listElement.getElementsByClassName('phaseExt')[0] !== null) {
        phaseExt = listElement.getElementsByClassName('phaseExt')[0]
      }

      // Check if phase of procedure matches selected filter option
      let showElement = selectedPhasesToFilter[0] === allOptionValue
      for (let j = 0; j < selectedPhasesToFilter.length && showElement === false; j++) {
        showElement = phase.innerHTML.trim() === selectedPhasesToFilter[j]
        if (typeof phaseExt !== 'undefined') {
          showElement = phase.innerHTML.trim() === selectedPhasesToFilter[j] || phaseExt.innerHTML.trim() === selectedPhasesToFilter[j]
        }
      }

      // If phase of procedure matches selected filter option, show it
      if (showElement) {
        if (listElement.classList.contains('hidden')) {
          listElement.classList.remove('hidden')
          listElement.classList.add('block')
        }
        visibleElementsCount++
      } else if (showElement === false) {
        // If phase of procedure does not match selected filter option, hide it
        if (listElement.classList.contains('block')) {
          listElement.classList.remove('block')
        }
        listElement.classList.add('hidden')
      }
    }

    //  No results
    const noResults = document.getElementById('no-results')

    //  If there are no results
    if (visibleElementsCount === 0) {
      //  If a combined filter is selected, show 'all' instead
      const selectedOptionId = filterPhasesSelectEl.selectedOptions[0]?.id
      if (selectedOptionId === 'combinedFilter' || selectedOptionId === 'combinedParticipationPreparation') {
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

    for (const option of options) {
      //  For all options except 'all'
      if (option.value !== 'all') {
        const phaseIds = splitOptionValue(option.value)
        if (countMatchingProcedures(phaseIds) === 0) {
          option.style.display = 'none'
        }
      }
    }
  }
}

initialize().then(() => {
  setSelectedOption()
  document.getElementById('filterPhases').addEventListener('change', filterProceduresByPhase)
})
