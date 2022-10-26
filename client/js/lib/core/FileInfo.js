/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { hasOwnProp } from 'demosplan-utils'

const fileTypes = {
  antragx: ['.antragx'],
  pdf: ['.pdf'],
  'pdf-img': ['.pdf', 'image/*'],
  'pdf-img-zip': ['.pdf', 'image/*', '.zip'],
  'pdf-zip': ['.pdf', '.zip'],
  img: ['image/*'],
  import: ['.docx'],
  all: ['.jpg', '.png', '.bmp', '.jpeg', '.zip', '.docx', '.pdf', '.bin', '.txt', '.tiff', '.tif'],
  zip: ['.zip'],
  csv: ['.csv'],
  'pdf-video': ['.pdf', 'video/*'],
  'pdf-zip-video': ['.pdf', 'video/*', '.zip'],
  xls: ['.xls', '.xlsx', '.ods']
}

const mimeTypes = {
  'text/plain': 'txt',
  'text/html': 'html',
  'text/css': 'css',
  'application/javascript': 'js',
  'application/json': 'json',
  'application/xml': 'xml',
  'application/x-shockwave-flash': 'swf',
  'application/octet-stream': 'binary',
  'video/x-flv': 'flv',
  // Images,
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/gif': 'gif',
  'image/bmp': 'bmp',
  'image/vnd.microsoft.icon': 'ico',
  'image/tiff': 'tiff',
  'image/svg+xml': 'svg',
  // Archives,
  'application/zip': 'zip',
  'application/x-rar-compressed': 'rar',
  'application/x-msdownload': 'exe',
  'application/vnd.ms-cab-compressed': 'cab',
  // Audio/video,
  'audio/mpeg': 'mp3',
  'video/quicktime': 'mov',
  // Adobe,
  'application/pdf': 'pdf',
  'application/x-pdf': 'pdf',
  'application/x-download': 'pdf',
  'image/vnd.adobe.photoshop': 'psd',
  'application/postscript': 'ps',
  // Ms office,
  'application/msword': 'doc',
  'application/vnd.openxmlformats-officedocument.wo:processingml.document': 'docx',
  'application/rtf': 'rtf',
  'application/vnd.ms-excel': 'xls',
  'application/vnd.ms-powerpoint': 'ppt',
  // Open office,
  'application/vnd.oasis.opendocument.text': 'odt',
  'application/vnd.oasis.opendocument.spreadsheet': 'ods'
}

/**
 * @param string $scale
 * @param string $value
 *
 * @return float
 */
const convertSize = function (scale, value) {
  let returnValue
  switch (scale) {
    case 'KB':
      returnValue = value / 1024
      break
    case 'MB':
      returnValue = value / 1048576
      break
    case 'GB':
      returnValue = value / 1073741824
      break
    case 'TB':
      returnValue = value / 1099511627776
      break
      /* This is the Default */
    /*
     * case 'B':
     *     returnValue = value;
     *     break;
     */
    default:
      returnValue = value
  }

  return Math.round(returnValue) + ' ' + scale
}

/**
 * Returns a readable MimeType
 * @param string $value
 *
 * @return mixed
 */
const convertMimeType = function (value) {
  // Wenn du den MimeType findest, ersetze ihn, ansonsten den technischen MimeType
  if (hasOwnProp(mimeTypes, value)) {
    return mimeTypes[value]
  }

  return value
}

/**
 *
 * Transforms String with File-data into a usable Object
 * As
 *
 *
 */
const getFileInfo = function (fileString = '', options = {}) {
  const defaults = {
    sizeScale: 'KB',
    separator: ':'
  }

  const mergedOptions = { ...defaults, ...options }

  const fileArray = fileString.split(mergedOptions.separator)
  let fileObject = {}

  if (fileArray.length > 0) {
    fileObject = {
      name: fileArray[0],
      hash: fileArray[1],
      size: convertSize(mergedOptions.sizeScale, fileArray[2]),
      mimeType: convertMimeType(fileArray[3]),
      id: fileArray[1] || ''
    }
  } else {
    fileObject = {
      name: fileArray[0],
      hash: fileArray[0],
      size: fileArray[0],
      mimeType: fileArray[0],
      id: fileArray[0]
    }
  }

  return fileObject
}

/**
 *
 * @param file-type-(group)-name  see: fileTyoes
 * @return {Array, String}        Array of mimetype extentions or input-string if not defined
 */
const getFileTypes = function (type) {
  if (hasOwnProp(fileTypes, type)) {
    return fileTypes[type]
  }

  return type
}

export { getFileInfo, getFileTypes, convertSize, mimeTypes }
