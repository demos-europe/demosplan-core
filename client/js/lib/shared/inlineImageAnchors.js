const DEFAULT_CLASS = 'pdf_importer_image'

/**
 * Replace `<a class="<className>" href="…">label</a>` anchors with `<img>` tags
 * so image links render inline. Display-time only — stored HTML is untouched.
 */
export function inlineImageAnchors (html, className = DEFAULT_CLASS) {
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
