/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { inlineImageAnchors } from '@DpJs/lib/shared/inlineImageAnchors'

describe('inlineImageAnchors', () => {
  it('replaces a pdf_importer_image anchor with an img tag', () => {
    const html = '<p><a class="pdf_importer_image" href="http://example.com/hash.jpg">Label</a></p>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('<img')
    expect(result).toContain('src="http://example.com/hash.jpg"')
    expect(result).toContain('alt="Label"')
    expect(result).toContain('loading="lazy"')
    expect(result).not.toContain('<a')
  })

  it('preserves anchors without the target class', () => {
    const html = '<a class="other-class" href="http://example.com">keep me</a>'

    expect(inlineImageAnchors(html)).toBe(html)
  })

  it('handles anchors with multiple attributes (rel, target)', () => {
    const html = '<a class="pdf_importer_image" href="http://example.com/img.jpg" rel="noopener noreferrer nofollow" target="_blank">L</a>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('<img')
    expect(result).toContain('src="http://example.com/img.jpg"')
    expect(result).toContain('alt="L"')
  })

  it('trims the alt value from anchor text content', () => {
    const html = '<a class="pdf_importer_image" href="x">  spaced label  </a>'

    expect(inlineImageAnchors(html)).toContain('alt="spaced label"')
  })

  it('replaces multiple anchors in one pass', () => {
    const html = '<a class="pdf_importer_image" href="a">A</a><a class="pdf_importer_image" href="b">B</a>'
    const result = inlineImageAnchors(html)

    const matches = result.match(/<img/g)

    expect(matches).toHaveLength(2)
    expect(result).not.toContain('<a')
  })

  it('mixes pdf_importer_image anchors and unrelated anchors correctly', () => {
    const html = '<a class="pdf_importer_image" href="x">img</a><a href="y">link</a>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('<img')
    expect(result).toContain('<a href="y">link</a>')
  })

  it('returns the input unchanged when the class is absent', () => {
    const html = '<p>no images here</p>'

    expect(inlineImageAnchors(html)).toBe(html)
  })

  it('returns non-string input unchanged', () => {
    expect(inlineImageAnchors(null)).toBeNull()
    expect(inlineImageAnchors(undefined)).toBeUndefined()
  })

  it('respects a custom className', () => {
    const html = '<a class="my-class" href="x">L</a>'
    const result = inlineImageAnchors(html, 'my-class')

    expect(result).toContain('<img')
    expect(result).toContain('src="x"')
  })
})
