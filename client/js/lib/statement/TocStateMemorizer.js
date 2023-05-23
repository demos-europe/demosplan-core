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

export default class TocStateMemorizer {
  constructor (parentEl) {
    this.el = parentEl
    this.toggleBtns = Array.from(parentEl.querySelectorAll('[data-toggle]'))
    this.active = []
    this.procedureId = window.dplan.procedureId
    this.elementId = parentEl.getAttribute('data-element-id')
    this.updateState().restoreState().registerHandlers()
  }

  saveState () {
    const state = JSON.parse(sessionStorage.getItem('tocStates'))
    state[this.procedureId][this.elementId] = this.active
    sessionStorage.setItem('tocStates', JSON.stringify(state))
  }

  updateState () {
    const pid = this.procedureId
    const eid = this.elementId
    const state = JSON.parse(sessionStorage.getItem('tocStates'))
    if (!state || !state[pid]) {
      sessionStorage.setItem('tocStates', JSON.stringify({ [pid]: { [eid]: [] } }))
    } else {
      if (!state[pid][eid]) {
        state[pid][eid] = []
        sessionStorage.setItem('tocStates', JSON.stringify(state))
      } else {
        this.active = state[pid][eid]
      }
    }
    return this
  }

  restoreState () {
    this.active.forEach(i => this.toggleBtns[i].click())
    return this
  }

  toggle (i) {
    const active = this.active

    if (active.includes(i)) {
      active.splice(active.indexOf(i), 1)
    } else {
      active.push(i)
    }
    return this
  }

  registerHandlers () {
    this.toggleBtns.forEach((toggler, i) => {
      (i => {
        toggler.addEventListener('click', e => this.toggle(i).saveState(), false)
      })(i)
    })
  }
}
