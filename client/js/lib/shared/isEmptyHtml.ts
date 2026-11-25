/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

// Empty editor output like `<p></p>` counts as empty, content with an image does not
export function isEmptyHtml (html: string | null | undefined): boolean {
  if (!html) {
    return true
  }

  const body = new DOMParser().parseFromString(html, 'text/html').body

  return !body.querySelector('img') && (body.textContent ?? '').trim() === ''
}
