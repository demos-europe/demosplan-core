/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import DpResizeHandle from '../DpResizeHandle'

let namedFunc
let currentHandle
let nextEl
let cursorStart = 0
let dragStart = false
let resize
let resizeWidth
let nextWidth

const initResize = (e, idx) => {
  resize = document.querySelector(`th[data-col-idx='${idx}']`)

  currentHandle = resize.getElementsByClassName('c-data-table__resize-handle')[0]
  currentHandle.classList.add('is-active')
  nextEl = document.querySelector(`th[data-col-idx='${idx + 1}']`)
  dragStart = true
  cursorStart = e.pageX
  const resizeBound = resize.getBoundingClientRect()
  resizeWidth = resizeBound.width
  const nextBound = nextEl.getBoundingClientRect()
  nextWidth = nextBound.width
  namedFunc = (e) => resizeEl(e, idx)
  const bodyEl = document.getElementsByTagName('body')[0]
  bodyEl.classList.add('resizing')
  bodyEl.addEventListener('mousemove', namedFunc)
  bodyEl.addEventListener('mouseup', stopResize)
}

const resizeEl = (e, idx) => {
  if (dragStart) {
    const cursorPos = e.pageX
    const mouseMoved = cursorPos - cursorStart
    const newWidth = resizeWidth + mouseMoved
    const newNextWidth = nextWidth - mouseMoved
    if (newWidth > 25 && newNextWidth > 25) {
      resize.style.width = newWidth + 'px'
      nextEl.style.width = newNextWidth + 'px'
    }
  }
}

const stopResize = (e) => {
  currentHandle.classList.remove('is-active')
  dragStart = false
  document.getElementsByTagName('body')[0].removeEventListener('mousemove', namedFunc)
  document.getElementsByTagName('body')[0].removeEventListener('mouseup', stopResize)
  document.getElementsByTagName('body')[0].classList.remove('resizing')
}

const renderResizeWrapper = (h, wrapperContent, idx, isLast, resizeable, label, tooltip) => {
  let headerClass = ''
  if (resizeable) {
    headerClass = 'c-data-table__resizable'
  }
  if (isLast) {
    headerClass += ' u-pr-0'
  }
  return [h('th', {
    attrs: {
      'data-col-idx': idx,
      class: headerClass
    },
    directives: [
      {
        name: 'tooltip',
        value: tooltip || label
      }
    ]
  }, [wrapperContent, ...isLast ? [] : [h(DpResizeHandle, { props: { displayIcon: resizeable }, on: { mousedown: (e) => initResize(e, idx) } })]])]
}

export { renderResizeWrapper }
