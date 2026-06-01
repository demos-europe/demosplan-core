const DEFAULT_CLASS = 'pdf_importer_image'
const DEFAULT_TARGET = '_blank'
const DEFAULT_REL = 'noopener noreferrer'
const WRAPPER_CLASS = 'pdf-importer-image-wrapper'
const WRAPPER_UTILITY_CLASSES = `${WRAPPER_CLASS} inline-block text-center`
const IMAGE_UTILITY_CLASSES = 'block'
const LINK_UTILITY_CLASSES = 'pdf-importer-image-link block mt-1'
const DEFAULT_FALLBACK_LABEL = Translator.trans('image.open')

const filenameFromSrc = (src) => {
  if (typeof src !== 'string' || src === '' || src.startsWith('data:')) {
    return ''
  }
  try {
    const path = src.split(/[?#]/)[0]
    const slashIndex = path.lastIndexOf('/')
    if (slashIndex === -1) {
      return ''
    }
    const segment = path.substring(slashIndex + 1)
    if (segment === '' || !segment.includes('.')) {
      return ''
    }
    return decodeURIComponent(segment)
  } catch (error) {
    console.error('filenameFromSrc: failed to decode src', error)
    return ''
  }
}

const resolveLinkLabel = (altText, src, fallback) => {
  const alt = (altText ?? '').trim()
  if (alt !== '') {
    return alt
  }
  const filename = filenameFromSrc(src)
  if (filename !== '') {
    return filename
  }
  return fallback
}

const ensureBlockClass = (img) => {
  const existingClasses = (img.getAttribute('class') || '').split(/\s+/).filter(Boolean)
  if (!existingClasses.includes('block')) {
    existingClasses.push('block')
  }
  img.setAttribute('class', existingClasses.join(' '))
}

const createWrapper = (doc) => {
  const wrapper = doc.createElement('span')
  wrapper.className = WRAPPER_UTILITY_CLASSES
  return wrapper
}

const createImg = (doc, { src, alt }) => {
  const img = doc.createElement('img')
  img.setAttribute('src', src)
  img.setAttribute('alt', alt)
  img.setAttribute('loading', 'lazy')
  img.className = IMAGE_UTILITY_CLASSES
  return img
}

const createLink = (doc, { href, target, rel, label }) => {
  const anchor = doc.createElement('a')
  anchor.className = LINK_UTILITY_CLASSES
  anchor.setAttribute('href', href)
  anchor.setAttribute('target', target)
  anchor.setAttribute('rel', rel)
  anchor.textContent = label
  return anchor
}

const isInsideWrapper = (element) =>
  element.closest(`.${WRAPPER_CLASS}`) !== null

/**
 * Replace image references with an inline wrapper that holds the image and a
 * visible link below it.
 *
 * Two input shapes are handled at display time:
 *   1. PDF importer: `<a class="pdf_importer_image" href="…">label</a>`.
 *   2. Manually entered: bare `<img src="…">` (e.g. from DpEditor's
 *      imageButton on caseworker / citizen forms).
 *
 * Stored HTML is never mutated — the transform runs on the display string
 * only. The result is idempotent: re-running on already-wrapped HTML is a
 * no-op.
 */
export function inlineImageAnchors (html, className = DEFAULT_CLASS, fallbackLabel = DEFAULT_FALLBACK_LABEL) {
  if (typeof html !== 'string') {
    return html
  }
  if (!html.includes(className) && !html.includes('<img')) {
    return html
  }

  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  const root = doc.body.firstElementChild

  if (!root) {
    return html
  }

  root.querySelectorAll(`a.${className}[href]`).forEach((anchor) => {
    if (isInsideWrapper(anchor)) {
      return
    }

    const href = anchor.getAttribute('href')
    const label = anchor.textContent.trim()
    const target = anchor.getAttribute('target') || DEFAULT_TARGET
    const rel = anchor.getAttribute('rel') || DEFAULT_REL

    const wrapper = createWrapper(doc)
    wrapper.appendChild(createImg(doc, { src: href, alt: label }))
    wrapper.appendChild(createLink(doc, { href, target, rel, label }))

    anchor.replaceWith(wrapper)
  })

  root.querySelectorAll('img').forEach((img) => {
    if (isInsideWrapper(img)) {
      return
    }
    const src = img.getAttribute('src')
    if (!src) {
      return
    }

    const sibling = img.nextElementSibling
    const orphanLink = (sibling?.tagName === 'A' && sibling?.getAttribute('href') === src)
      ? sibling
      : null

    const altLabel = resolveLinkLabel(img.getAttribute('alt'), src, fallbackLabel)
    const wrapper = createWrapper(doc)

    img.replaceWith(wrapper)
    ensureBlockClass(img)
    wrapper.appendChild(img)

    if (orphanLink) {
      orphanLink.remove()
      orphanLink.setAttribute('class', LINK_UTILITY_CLASSES)
      orphanLink.setAttribute('target', orphanLink.getAttribute('target') || DEFAULT_TARGET)
      orphanLink.setAttribute('rel', orphanLink.getAttribute('rel') || DEFAULT_REL)
      if (orphanLink.textContent.trim() === '') {
        orphanLink.textContent = altLabel
      }
      wrapper.appendChild(orphanLink)
    } else {
      wrapper.appendChild(createLink(doc, {
        href: src,
        target: DEFAULT_TARGET,
        rel: DEFAULT_REL,
        label: altLabel,
      }))
    }
  })

  return root.innerHTML
}
