/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
const features = []
const hasPermission = jest.fn(feature => !!features[feature])

const Translator = {
  trans: jest.fn(key => key)
}

global.Translator = Translator
global.hasPermission = hasPermission

global.PROJECT = 'blp'
