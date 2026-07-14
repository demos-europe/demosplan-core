const DEFAULT_CLASS = 'pdf_importer_image'
const DEFAULT_TARGET = '_blank'
const DEFAULT_REL = 'noopener noreferrer'
const WRAPPER_CLASS = 'pdf-importer-image-wrapper'
const WRAPPER_UTILITY_CLASSES = `${WRAPPER_CLASS} inline-block text-center`
const IMAGE_UTILITY_CLASSES = 'block'
const LINK_UTILITY_CLASSES = 'pdf-importer-image-link block mt-1'
const DEFAULT_FALLBACK_LABEL_KEY = 'image.open'

const filenameFromSrc = (src) => {
  if (typeof src !== 'string' || src === '' || src.startsWith('data:')) {
    return ''
  }

  try {
    const path = src.split(/[?#]/)[0]
    const slashIndex = path.lastIndexOf('/')
    const basename = slashIndex === -1 ? path : path.substring(slashIndex + 1)

    if (basename === '' || !basename.includes('.')) {
      return ''
    }

    return decodeURIComponent(basename)
  } catch (error) {
    console.error('filenameFromSrc: failed to decode src', error)

    return ''
  }
}

export const resolveLinkLabel = (altText, src, fallback) => {
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

/**
 * Resolve the translated default link label. Falls back to the bare key when no
 * `Translator` global is available (e.g. isolated unit usage) so callers never
 * hit a ReferenceError.
 */
export const defaultInlineImageLabel = () =>
  (typeof Translator === 'undefined') ? DEFAULT_FALLBACK_LABEL_KEY : Translator.trans(DEFAULT_FALLBACK_LABEL_KEY)

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

/**
 * Build the canonical inline-image structure shared by the display transform
 * and the segmentation editor: a wrapper holding the image and a visible link.
 *
 * Pass `label: null` to leave the link text empty so a ProseMirror markView can
 * supply the marked document text via the returned `link` as its `contentDOM`.
 *
 * @returns {{ wrapper: HTMLElement, img: HTMLElement, link: HTMLElement }}
 */
export function buildInlineImageFigure (doc, { src, alt = '', href = src, label = null, target = DEFAULT_TARGET, rel = DEFAULT_REL }) {
  const wrapper = createWrapper(doc)
  const img = createImg(doc, { src, alt })
  const link = createLink(doc, { href, target, rel, label: label ?? '' })

  wrapper.appendChild(img)
  wrapper.appendChild(link)

  return { wrapper, img, link }
}

const isInsideWrapper = (element) => {
  return element.closest(`.${WRAPPER_CLASS}`) !== null
}

const wrapImporterAnchors = (root, doc, className) => {
  root.querySelectorAll(`a.${className}[href]`).forEach((anchor) => {
    if (isInsideWrapper(anchor)) {
      return
    }

    const href = anchor.getAttribute('href')
    const label = anchor.textContent.trim()
    const target = anchor.getAttribute('target') || DEFAULT_TARGET
    const rel = anchor.getAttribute('rel') || DEFAULT_REL
    const { wrapper } = buildInlineImageFigure(doc, { src: href, alt: label, href, target, rel, label })

    anchor.replaceWith(wrapper)
  })
}

const wrapBareImages = (root, doc, fallbackLabel) => {
  /*
   * Run after wrapImporterAnchors so that importer <img> nodes produced by
   * that pass are already inside a wrapper and skipped by isInsideWrapper.
   */
  root.querySelectorAll('img').forEach((img) => {
    if (isInsideWrapper(img)) {
      return
    }

    const src = img.getAttribute('src')

    if (!src) {
      return
    }

    const label = resolveLinkLabel(img.getAttribute('alt'), src, fallbackLabel)
    const wrapper = createWrapper(doc)

    img.replaceWith(wrapper)
    ensureBlockClass(img)
    wrapper.appendChild(img)
    wrapper.appendChild(createLink(doc, { href: src, target: DEFAULT_TARGET, rel: DEFAULT_REL, label }))
  })
}

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
export function inlineImageAnchors (html, className = DEFAULT_CLASS, fallbackLabel = null) {
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

  wrapImporterAnchors(root, doc, className)
  wrapBareImages(root, doc, fallbackLabel ?? defaultInlineImageLabel())

  return root.innerHTML
}

/**
 * Editable-context variant: convert PDF-importer anchors to plain `<img>` tags
 * so an editor (e.g. DpEditor) renders them as images. Unlike
 * {@link inlineImageAnchors}, this adds no wrapper and no visible link, keeping
 * editor content — and the HTML persisted from it — free of display-only markup.
 */
export function inlineImageAnchorsForEditing (html, className = DEFAULT_CLASS) {
  if (typeof html !== 'string' || !html.includes(className)) {
    return html
  }

  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  const root = doc.body.firstElementChild

  if (!root) {
    return html
  }

  root.querySelectorAll(`a.${className}[href]`).forEach((anchor) => {
    const img = doc.createElement('img')

    img.setAttribute('src', anchor.getAttribute('href'))
    img.setAttribute('alt', anchor.textContent.trim())
    img.setAttribute('loading', 'lazy')
    anchor.replaceWith(img)
  })

  return root.innerHTML
}

/**
 * Text-only variant for truncated previews: replace image references with their
 * label as plain text so no image or link renders. Importer anchors become their
 * inner text; bare `<img>` tags become their `alt`.
 */
export function stripInlineImageAnchors (html, className = DEFAULT_CLASS) {
  if (typeof html !== 'string' || (!html.includes(className) && !html.includes('<img'))) {
    return html
  }

  const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html')
  const root = doc.body.firstElementChild

  if (!root) {
    return html
  }

  root.querySelectorAll(`a.${className}[href]`).forEach((anchor) => {
    anchor.replaceWith(doc.createTextNode(anchor.textContent ?? ''))
  })

  root.querySelectorAll('img').forEach((img) => {
    img.replaceWith(doc.createTextNode(img.getAttribute('alt') ?? ''))
  })

  return root.innerHTML
}
