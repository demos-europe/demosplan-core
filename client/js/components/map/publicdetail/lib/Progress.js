/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

class Progress {
  /**
   * Renders a progress bar.
   * @param {Element} el The target element.
   * @constructor
   */
  constructor (el) {
    this.el = el
    this.loading = 0
    this.loaded = 0
  }

  /**
   * Increment the count of loading tiles.
   */
  addLoading () {
    if (this.loading === 0) {
      this.show()
    }
    ++this.loading
    this.update()
  }

  /**
   * Increment the count of loaded tiles.
   */
  addLoaded () {
    const this_ = this
    setTimeout(function () {
      ++this_.loaded
      this_.update()
    }, 100)
  }

  /**
   * Update the progress bar.
   */
  update () {
    this.el.style.width = (this.loaded / this.loading * 100).toFixed(1) + '%'
    if (this.loading === this.loaded) {
      this.loading = 0
      this.loaded = 0
      const this_ = this
      setTimeout(function () {
        this_.hide()
      }, 500)
    }
  }

  /**
   * Show the progress bar.
   */
  show () {
    this.el.style.visibility = 'visible'
  }

  /**
   * Hide the progress bar.
   */
  hide () {
    if (this.loading === this.loaded) {
      this.el.style.visibility = 'hidden'
      this.el.style.width = 0
    }
  }
}

export default Progress
