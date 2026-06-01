/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

globalThis.Translator = { trans: jest.fn(key => key === 'image.open' ? 'Bild öffnen' : key) }

import { inlineImageAnchors } from '@DpJs/lib/shared/inlineImageAnchors'

describe('inlineImageAnchors', () => {
  it('replaces a pdf_importer_image anchor with an img and a visible link', () => {
    const html = '<p><a class="pdf_importer_image" href="http://example.com/hash.jpg">Label</a></p>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('<img')
    expect(result).toContain('src="http://example.com/hash.jpg"')
    expect(result).toContain('alt="Label"')
    expect(result).toContain('loading="lazy"')
    expect(result).toContain('class="pdf-importer-image-wrapper inline-block text-center"')
    expect(result).toContain('class="pdf-importer-image-link block mt-1"')
    expect(result).toMatch(/<a[^>]*href="http:\/\/example\.com\/hash\.jpg"[^>]*>Label<\/a>/)
  })

  it('preserves anchors without the target class', () => {
    const html = '<a class="other-class" href="http://example.com">keep me</a>'
    expect(inlineImageAnchors(html)).toBe(html)
  })

  it('preserves target and rel from the source anchor', () => {
    const html = '<a class="pdf_importer_image" href="http://example.com/img.jpg" rel="noopener noreferrer nofollow" target="_blank">L</a>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('<img')
    expect(result).toContain('src="http://example.com/img.jpg"')
    expect(result).toContain('alt="L"')
    expect(result).toContain('target="_blank"')
    expect(result).toContain('rel="noopener noreferrer nofollow"')
  })

  it('defaults target and rel when missing on the source anchor', () => {
    const html = '<a class="pdf_importer_image" href="http://example.com/img.jpg">L</a>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('target="_blank"')
    expect(result).toContain('rel="noopener noreferrer"')
  })

  it('trims the label used for alt and link text', () => {
    const html = '<a class="pdf_importer_image" href="x">  spaced label  </a>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('alt="spaced label"')
    expect(result).toMatch(/<a[^>]*>spaced label<\/a>/)
  })

  it('replaces multiple anchors in one pass', () => {
    const html = '<a class="pdf_importer_image" href="a">A</a><a class="pdf_importer_image" href="b">B</a>'
    const result = inlineImageAnchors(html)

    expect(result.match(/<img/g)).toHaveLength(2)
    expect(result.match(/pdf-importer-image-link/g)).toHaveLength(2)
  })

  it('mixes pdf_importer_image anchors and unrelated anchors correctly', () => {
    const html = '<a class="pdf_importer_image" href="x">img</a><a href="y">link</a>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('<img')
    expect(result).toContain('<a href="y">link</a>')
  })

  it('returns the input unchanged when there is nothing to transform', () => {
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
    expect(result).toMatch(/<a[^>]*href="x"[^>]*>L<\/a>/)
  })

  it('wraps a bare img tag with a link below using its alt as label', () => {
    const html = '<p><img src="http://example.com/photo.jpg" alt="My photo" loading="lazy"></p>'
    const result = inlineImageAnchors(html)

    expect(result).toContain('class="pdf-importer-image-wrapper inline-block text-center"')
    expect(result).toContain('<img')
    expect(result).toContain('src="http://example.com/photo.jpg"')
    expect(result).toContain('alt="My photo"')
    expect(result).toMatch(/<a[^>]*href="http:\/\/example\.com\/photo\.jpg"[^>]*>My photo<\/a>/)
    expect(result).toContain('target="_blank"')
    expect(result).toContain('rel="noopener noreferrer"')
  })

  it('falls back to filename when bare img has no alt', () => {
    const html = '<img src="https://files.example.com/folder/screenshot.png">'
    const result = inlineImageAnchors(html)

    expect(result).toMatch(/<a[^>]*>screenshot\.png<\/a>/)
  })

  it('falls back to the default label when alt and filename are unusable', () => {
    const html = '<img src="data:image/png;base64,AAAA" alt="">'
    const result = inlineImageAnchors(html)

    expect(result).toMatch(/<a[^>]*>Bild öffnen<\/a>/)
  })

  it('honours a custom fallback label', () => {
    const html = '<img src="x" alt="">'
    const result = inlineImageAnchors(html, 'pdf_importer_image', 'View image')

    expect(result).toMatch(/<a[^>]*>View image<\/a>/)
  })

  it('does not re-wrap content that already passed through the transform', () => {
    const first = inlineImageAnchors('<img src="http://example.com/a.jpg" alt="A">')
    const second = inlineImageAnchors(first)

    expect(second).toBe(first)
  })

  it('wraps anchor and bare img in the same input', () => {
    const html = '<a class="pdf_importer_image" href="a.jpg">A</a><img src="b.jpg" alt="B">'
    const result = inlineImageAnchors(html)

    expect(result.match(/pdf-importer-image-wrapper/g)).toHaveLength(2)
    expect(result.match(/pdf-importer-image-link/g)).toHaveLength(2)
    expect(result).toContain('src="a.jpg"')
    expect(result).toContain('src="b.jpg"')
  })

  it('absorbs an orphan sibling link with the same href instead of duplicating it', () => {
    const html = '<img src="http://example.com/a.jpg" alt="Darstellung_Stell_001" width="255" height="362"><a class="pdf-importer-image-link block mt-1" href="http://example.com/a.jpg" target="_blank" rel="noopener">Darstellung_Stell_001</a>'
    const result = inlineImageAnchors(html)

    expect(result.match(/pdf-importer-image-wrapper/g)).toHaveLength(1)
    expect(result.match(/pdf-importer-image-link/g)).toHaveLength(1)
    expect(result.match(/<a /g)).toHaveLength(1)
    expect(result).toMatch(/<span class="pdf-importer-image-wrapper[^"]*"><img[^>]*><a[^>]*>Darstellung_Stell_001<\/a><\/span>/)
  })

  it('still wraps a bare img when the next sibling link points elsewhere', () => {
    const html = '<img src="a.jpg" alt="A"><a href="b.jpg">other</a>'
    const result = inlineImageAnchors(html)

    expect(result.match(/pdf-importer-image-wrapper/g)).toHaveLength(1)
    expect(result).toContain('<a href="b.jpg">other</a>')
    expect(result).toMatch(/<span[^>]*><img[^>]*><a[^>]*>A<\/a><\/span><a href="b\.jpg">other<\/a>/)
  })

  it('skips bare img without a src attribute', () => {
    const html = '<img alt="broken">'
    const result = inlineImageAnchors(html)

    expect(result).not.toContain('pdf-importer-image-wrapper')
    expect(result).toContain('<img')
    expect(result).toContain('alt="broken"')
  })
})
