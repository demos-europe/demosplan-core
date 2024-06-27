import AdminLayerPlanDrawing from '@DpJs/components/map/admin/AdminLayerPlanDrawing'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'

describe('AdminLayerPlanDrawing.vue', () => {
  let wrapper

  beforeEach(() => {
    global.dplan.path = {
      tusEndpoint: 'localhost'
    }
  })

  it("canEditMapHint returns true when isMaster is false and hasPermission returns true", () => {

    wrapper = shallowMountWithGlobalMocks(AdminLayerPlanDrawing, {
      propsData: {
        isMaster: false,
        procedureId: 'my-fancy-id-xx-vv'
      },
    })

    expect(wrapper.vm.canEditMapHint).toBe(true)
  })

  it("canEditMapHint returns false when isMaster is true", () => {

    wrapper = shallowMountWithGlobalMocks(AdminLayerPlanDrawing, {
      propsData: {
        isMaster: true,
        procedureId: 'my-fancy-id-xx-vv'
      },
    })

    expect(wrapper.vm.canEditMapHint).toBe(false)
  })

  it("canEditMapHint returns false when hasPermission returns false", () => {
    jest.spyOn(global, 'hasPermission').mockImplementation(() => false)

    wrapper = shallowMountWithGlobalMocks(AdminLayerPlanDrawing, {
      propsData: {
        isMaster: false,
        procedureId: 'my-fancy-id-xx-vv'
      }
    })

    expect(wrapper.vm.canEditMapHint).toBe(false)
  })
})
