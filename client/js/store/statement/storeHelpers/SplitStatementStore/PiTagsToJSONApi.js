/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

function transformPiToJsonApi (piTags) {
  const transformedTags = []
  piTags.forEach(tag => {
    const transformedTag = { attributes: {} }
    transformedTag.id = tag.id
    transformedTag.attributes.title = tag.tagName
    transformedTag.type = 'piTag'
    transformedTag.attributes.score = tag.score
    transformedTags.push(transformedTag)
  })
  return transformedTags
}

function transformJsonApiToPi (jsonApiTag) {
  let transformedTag = jsonApiTag
  if (jsonApiTag.type === 'piTag') {
    transformedTag = {
      tagName: jsonApiTag.attributes.title,
      ...jsonApiTag.id ? { id: jsonApiTag.id } : {},
      score: jsonApiTag.attributes.score
    }
  }
  return transformedTag
}

export { transformJsonApiToPi, transformPiToJsonApi }
