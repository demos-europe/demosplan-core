/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import { createStore } from 'vuex'
import { nextTick } from 'vue'
import shallowMountWithGlobalMocks from '@DpJs/VueConfigLocal'
import SideBar from '@DpJs/components/statement/splitStatement/SideBar'

const noAssignee = { id: 'noAssigneeId', name: 'not.assigned' }
const assignableUser = { id: 'user1', name: 'Jane Doe' }
const otherUser = { id: 'user2', name: 'John Smith' }

const tagWithDefaultAssignee = {
  id: 'tag1',
  attributes: { title: 'Tag with assignee' },
  relationships: {
    defaultAssignee: { data: { id: 'user1', type: 'AssignableUser' } },
    topic: { data: { id: 'topic1', type: 'TagTopic' } },
  },
}

const tagWithoutDefaultAssignee = {
  id: 'tag2',
  attributes: { title: 'Tag without assignee' },
  relationships: {
    defaultAssignee: { data: null },
    topic: { data: { id: 'topic1', type: 'TagTopic' } },
  },
}

const tagWithOtherDefaultAssignee = {
  id: 'tag3',
  attributes: { title: 'Tag with other assignee' },
  relationships: {
    defaultAssignee: { data: { id: 'user2', type: 'AssignableUser' } },
    topic: { data: { id: 'topic1', type: 'TagTopic' } },
  },
}

describe('SplitStatement SideBar', () => {
  let store
  let wrapper

  beforeEach(() => {
    store = createStore({
      modules: {
        SplitStatement: {
          namespaced: true,
          state: {
            assignableUsers: [noAssignee, assignableUser, otherUser],
            availablePlaces: [{ id: 'place1', name: 'Place 1' }],
            availableTags: [tagWithDefaultAssignee, tagWithoutDefaultAssignee, tagWithOtherDefaultAssignee],
            editingSegment: { id: 'segment1', tags: [] },
            editModeActive: true,
            initialSegments: [],
            isBusy: false,
            procedureId: 'procedure1',
            segments: [{ id: 'segment1', tags: [] }],
            tagTopics: [],
            uncategorizedTags: [],
          },
          getters: {
            assignableUsers: state => state.assignableUsers,
            availablePlaces: state => state.availablePlaces,
            availableTags: state => state.availableTags,
            editingSegment: state => state.editingSegment,
            editModeActive: state => state.editModeActive,
            initialSegments: state => state.initialSegments,
            isBusy: state => state.isBusy,
            procedureId: state => state.procedureId,
            segmentById: state => id => state.segments.find(segment => segment.id === id),
            tagTopics: state => state.tagTopics,
            uncategorizedTags: state => state.uncategorizedTags,
          },
          actions: {
            updateCurrentTags: jest.fn(),
          },
          mutations: {
            locallyUpdateSegments: jest.fn(),
            setProperty: jest.fn(),
          },
        },
      },
    })

    wrapper = shallowMountWithGlobalMocks(SideBar, {
      props: {
        offset: 0,
      },
      global: {
        plugins: [store],
      },
    })
  })

  it('preselects the default assignee of an added tag on an unassigned segment', async () => {
    expect(wrapper.vm.selectedAssignee.id).toBe('noAssigneeId')

    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')
  })

  it('does not change the assignee when the added tag has no default assignee', async () => {
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag2', tagName: 'Tag without assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('noAssigneeId')
  })

  it('overwrites an already selected assignee when a tag with a default assignee is added', async () => {
    wrapper.vm.selectedAssignee = otherUser
    await nextTick()

    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')
  })

  it('uses the first selected tag that has a default assignee, skipping tags without one', async () => {
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag2', tagName: 'Tag without assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('noAssigneeId')

    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [
        { id: 'tag2', tagName: 'Tag without assignee' },
        { id: 'tag1', tagName: 'Tag with assignee' },
      ],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')
  })

  it('keeps the first selected tag assignee when a later tag with a different assignee is added', async () => {
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')

    // Adding a second tag (kept after the first) must not change the assignee: the first wins.
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [
        { id: 'tag1', tagName: 'Tag with assignee' },
        { id: 'tag3', tagName: 'Tag with other assignee' },
      ],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')
  })

  it('updates the assignee when the segment is retagged with a tag that has a different default assignee', async () => {
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')

    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag3', tagName: 'Tag with other assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user2')
  })

  it('resets the assignee to not assigned when retagged with a tag that has no default assignee', async () => {
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')

    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag2', tagName: 'Tag without assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('noAssigneeId')
  })

  it('resets the assignee to not assigned when all tags are removed', async () => {
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')

    store.state.SplitStatement.editingSegment = { id: 'segment1', tags: [] }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('noAssigneeId')
  })

  it('overrules a manually selected assignee when the tags change afterwards', async () => {
    // The user picks an assignee by hand.
    wrapper.vm.selectedAssignee = otherUser
    await nextTick()

    // A subsequent tag change applies the first tag's default assignee.
    store.state.SplitStatement.editingSegment = {
      id: 'segment1',
      tags: [{ id: 'tag1', tagName: 'Tag with assignee' }],
    }
    await nextTick()

    expect(wrapper.vm.selectedAssignee.id).toBe('user1')
  })
})
