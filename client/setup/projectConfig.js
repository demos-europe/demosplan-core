/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

const chalk = require('chalk')
const spawnSync = require('child_process').spawnSync // To run php processes
const log = require('./webpack/log').log

/* eslint-disable dot-notation */

function projectConfig (mode, project) {
  const frontendIntegratorCommand = [
    `bin/${project}`,
    'dplan:frontend:integrator'
  ]

  if (mode === 'production') {
    frontendIntegratorCommand.push('-e', 'prod')
  }

  let beConfigOutput = null
  try {
    beConfigOutput = spawnSync('php', frontendIntegratorCommand, {
      windowsHide: true
    })

    if (beConfigOutput.status !== 0) {
      log(chalk.red('An error occurred during configuration loading'))
      log(beConfigOutput.stdout.toString())
      process.exit(0)
    }
  } catch (e) {
    log(chalk.red(e))
    process.exit(0)
  }

  const beConfigStr = beConfigOutput.stdout.toString()
  const beConfig = JSON.parse(beConfigStr)

  return {
    // Prefix must be class-ified for postcss, but as the BE uses it in `class` attributes it is dotless there.
    cssPrefix: '.' + beConfig['cssPrefix'],
    project: project,
    projectRoot: beConfig['projectDir'],
    publicPath: beConfig['projectDir'] + '/web/',
    stylesEntryPoint: beConfig['projectDir'] + '/app/Resources/DemosPlanCoreBundle/client/scss/style.scss',
    publicStylesEntryPoint: beConfig['projectDir'] + '/app/Resources/DemosPlanCoreBundle/client/scss/style-public.scss',
    urlPathPrefix: beConfig['urlPrefix']
  }
}

module.exports = { projectConfig }
