/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * Fills empty `<dp-boilerplate boilerplate-id="…">` tags with the boilerplate's current text,
 * for display in the editor. The backend stores and sends these tags empty — undo with
 * `stripBoilerplateContent` before saving, or the text gets copied into the database.
 *
 * Falls back to an empty paragraph if the boilerplate can't be found, since the node's schema
 * requires at least one block child.
 *
 * @param {String} tagFormHtml Recommendation HTML with empty boilerplate tags
 * @param {Object} boilerplatesById Boilerplates store state, keyed by id
 * @returns {String} Recommendation HTML with each boilerplate tag's current text inside
 */
export function embedBoilerplateContent (tagFormHtml, boilerplatesById) {
  const wrapper = document.createElement('div')

  wrapper.innerHTML = tagFormHtml

  wrapper.querySelectorAll('dp-boilerplate').forEach(element => {
    const boilerplateId = element.getAttribute('boilerplate-id')

    element.innerHTML = boilerplatesById[boilerplateId]?.attributes?.text || '<p></p>'
  })

  return wrapper.innerHTML
}

/**
 * Empties `<dp-boilerplate>` tags again, removing what `embedBoilerplateContent` put there.
 * Inverse of that function — must run before saving, so the link stays a reference instead of
 * a permanent copy of the boilerplate's text.
 *
 * DOM-based rather than a regex, since the content is real paragraphs and lists, not flat text.
 *
 * @param {String} contentFormHtml Recommendation HTML as the editor produces it
 * @returns {String} The same HTML with every boilerplate tag emptied
 */
export function stripBoilerplateContent (contentFormHtml) {
  const wrapper = document.createElement('div')

  wrapper.innerHTML = contentFormHtml

  wrapper.querySelectorAll('dp-boilerplate').forEach(element => {
    element.innerHTML = ''
  })

  return wrapper.innerHTML
}
