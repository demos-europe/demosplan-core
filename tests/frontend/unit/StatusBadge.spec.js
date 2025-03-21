import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import StatusBadge from '@DpJs/components/procedure/Shared/StatusBadge'

describe('StatusBadge.vue', () => {
  it('renders correctly for each status', () => {
    const statuses = ['new', 'processing', 'completed']
    const colors = ['info', 'warning', 'confirm']

    statuses.forEach((status, index) => {
      const wrapper = shallowMountWithGlobalMocks(StatusBadge, {
        props: { status }
      })

      expect(wrapper.vm.color).toBe(colors[index])
    })
  })
})
