/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const HTML_ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }

/**
 * Escapes text for places that insert their content as HTML, e.g. the v-tooltip directive,
 * so that it is displayed literally.
 */
export const escapeHtml = (text: string): string => String(text ?? '').replace(/[&<>"']/g, character => HTML_ESCAPES[character])
