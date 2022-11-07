/**
 *
 * Copyright 2016 Google Inc. All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

'use strict'

import Detabinator from './Detabinator'

class SideNav {
  constructor () {
    //  Only init when sidenav is present
    if (!document.querySelector('[data-slidebar]')) {
      return
    }

    this.showButtonElements = Array.from(document.querySelectorAll('[data-slidebar-show]'))
    this.hideButtonElements = Array.from(document.querySelectorAll('[data-slidebar-hide]'))
    this.sideNavEl = document.querySelector('[data-slidebar]')

    this.sideNavContainerEl = document.querySelector('[data-slidebar-container]')
    this.orientation = this.sideNavEl.getAttribute('data-slidebar') // Is SideNav aligned to right or left?

    this.sideNavClassVisible = 'is-visible'
    this.sideNavClassAnimatable = 'is-animatable'

    /*
     * Control whether the container's children can be focused
     * Set initial state to inert since the drawer is offscreen
     */
    this.detabinator = new Detabinator(this.sideNavContainerEl)
    this.detabinator.inert = true

    this.showSideNav = this.showSideNav.bind(this)
    this.hideSideNav = this.hideSideNav.bind(this)
    this.blockClicks = this.blockClicks.bind(this)
    this.onTouchStart = this.onTouchStart.bind(this)
    this.onTouchMove = this.onTouchMove.bind(this)
    this.onTouchEnd = this.onTouchEnd.bind(this)
    this.onTransitionEnd = this.onTransitionEnd.bind(this)
    this.update = this.update.bind(this)

    const d = document
    const e = d.documentElement
    const g = d.getElementsByTagName('body')[0]
    this.innerWidth = window.innerWidth || e.clientWidth || g.clientWidth
    this.initialTranslateX = this.getTranslateX(this.sideNavContainerEl)

    this.startX = this.orientation === 'right' ? this.innerWidth : 0
    this.currentX = this.orientation === 'right' ? this.innerWidth : 0
    this.touchingSideNav = false

    this.transitionEndProperty = null
    this.transitionEndTime = 0

    this.supportsPassive = undefined
    this.addEventListeners()
  }

  // Apply passive event listening if it's supported
  applyPassive () {
    if (this.supportsPassive !== undefined) {
      return this.supportsPassive ? { passive: true } : false
    }
    // Permission detect
    let isSupported = false
    try {
      document.addEventListener('test', null, {
        get passive () {
          isSupported = true
        }
      })
    } catch (e) { }
    this.supportsPassive = isSupported
    return this.applyPassive()
  }

  addEventListeners () {
    this.showButtonElements.forEach((el) => {
      el.addEventListener('click', this.showSideNav)
    })
    this.hideButtonElements.forEach((el) => {
      el.addEventListener('click', this.hideSideNav)
    })
    this.sideNavEl.addEventListener('click', this.hideSideNav)
    this.sideNavContainerEl.addEventListener('click', this.blockClicks)

    this.sideNavEl.addEventListener('touchstart', this.onTouchStart, this.applyPassive())
    this.sideNavEl.addEventListener('touchmove', this.onTouchMove, this.applyPassive())
    this.sideNavEl.addEventListener('touchend', this.onTouchEnd)
  }

  onTouchStart (evt) {
    if (!this.sideNavEl.classList.contains(this.sideNavClassVisible)) { return }

    this.startX = evt.touches[0].pageX
    this.currentX = this.startX

    this.touchingSideNav = true
    requestAnimationFrame(this.update)
  }

  onTouchMove (evt) {
    if (!this.touchingSideNav) { return }

    this.currentX = evt.touches[0].pageX
  }

  onTouchEnd (evt) {
    if (!this.touchingSideNav) { return }

    const whenToHideSideNav = this.orientation === 'right' ? this.initialTranslateX : 0

    this.touchingSideNav = false
    this.sideNavContainerEl.style.transform = ''

    if (this.updateTranslateX() < whenToHideSideNav) {
      this.hideSideNav()
    }
  }

  update () {
    if (!this.touchingSideNav) { return }

    requestAnimationFrame(this.update)

    this.sideNavContainerEl.style.transform = `translateX(${this.updateTranslateX()}px)`
  }

  blockClicks (evt) {
    evt.stopPropagation()
  }

  onTransitionEnd (evt) {
    if (evt.propertyName !== this.transitionEndProperty && evt.elapsedTime !== this.transitionEndTime) {
      return
    }

    this.transitionEndProperty = null
    this.transitionEndTime = 0

    this.sideNavEl.classList.remove(this.sideNavClassAnimatable)
    this.sideNavEl.removeEventListener('transitionend', this.onTransitionEnd)
  }

  showSideNav () {
    this.sideNavEl.classList.add(this.sideNavClassAnimatable)
    this.sideNavEl.classList.add(this.sideNavClassVisible)
    this.detabinator.inert = false

    this.transitionEndProperty = 'transform'
    // The duration of transition (make unique to distinguish transitions )
    this.transitionEndTime = 0.33

    this.sideNavEl.addEventListener('transitionend', this.onTransitionEnd)
  }

  hideSideNav () {
    this.sideNavEl.classList.add(this.sideNavClassAnimatable)
    this.sideNavEl.classList.remove(this.sideNavClassVisible)
    this.detabinator.inert = true

    this.transitionEndProperty = 'transform'
    this.transitionEndTime = 0.13

    this.sideNavEl.addEventListener('transitionend', this.onTransitionEnd)
  }

  getTranslateX (element) {
    if (!window.getComputedStyle) { return }

    const transArr = []
    const style = getComputedStyle(element)
    const transform = style.transform || style.mozTransform || style.msTransform
    let mat = transform.match(/^matrix3d\((.+)\)$/)

    if (mat) {
      return parseFloat(mat[1].split(', ')[13])
    }

    mat = transform.match(/^matrix\((.+)\)$/)
    mat ? transArr.push(parseFloat(mat[1].split(', ')[4])) : transArr.push(0)
    mat ? transArr.push(parseFloat(mat[1].split(', ')[5])) : transArr.push(0)

    return transArr[0]
  }

  updateTranslateX () {
    if (this.orientation === 'right') {
      return Math.max(0, this.currentX - this.startX)
    } else {
      return Math.min(0, this.currentX - this.startX)
    }
  }
}

export default SideNav
