/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/*
 * import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
 * import shallowMountWithGlobalMocks from '@DemosPlanCoreBundle/VueConfigLocal'
 */

describe.skip('DpUploadFiles', () => {
  it('should be an object', () => {
    expect(typeof DpUploadFiles).toBe('object')
  })

  it('should be named DpUploadedFiles', () => {
    expect(DpUploadFiles.name).toBe('DpUploadFiles')
  })

  it('should update Files-list after adding a file', () => {
    const File = {
      hash: 'xxx-qqq-sss'
    }

    const instance = shallowMountWithGlobalMocks(DpUploadFiles, {
      propsData: {
        allowedFileTypes: 'pdf'
      }
    })

    const comp = instance.vm
    comp.addFile(File)
    expect(comp.uploadedFiles.length).toBe(1)
    expect(comp.uploadedFiles[0].hash).toBe(File.hash)
    expect(comp.fileHashes.length).toBe(0)
  })

  it('should update filterhash-list if we need a hidden input', () => {
    const File = {
      hash: 'xxx-qqq-sss'
    }

    const instance = shallowMountWithGlobalMocks(DpUploadFiles, {
      propsData: {
        allowedFileTypes: 'pdf',
        needsHiddenInput: true
      }
    })

    const comp = instance.vm
    comp.addFile(File)
    expect(comp.uploadedFiles.length).toBe(1)
    expect(comp.uploadedFiles[0].hash).toBe(File.hash)
    expect(comp.fileHashes.length).toBe(1)
    expect(comp.fileHashes[0]).toBe(File.hash)
  })

  it('should reset the files and filehash-list after calling the clear-list method', () => {
    const File = {
      hash: 'xxx-qqq-sss'
    }

    const File2 = {
      hash: 'yyy-qqq-sss'
    }

    const instance = shallowMountWithGlobalMocks(DpUploadFiles, {
      propsData: {
        allowedFileTypes: 'pdf',
        needsHiddenInput: true
      }
    })

    const comp = instance.vm
    comp.addFile(File)
    comp.addFile(File2)

    comp.clearFilesList()
    expect(comp.uploadedFiles.length).toBe(0)
    expect(comp.fileHashes.length).toBe(0)
  })
})
