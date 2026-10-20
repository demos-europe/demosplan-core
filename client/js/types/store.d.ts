/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import type { Bookmark } from './bookmarks'
import type { Store } from 'vuex'

export interface FilterOption {
  id: string
  label: string
}

export interface FilterGroup {
  options?: FilterOption[]
}

export interface Slidebar {
  showTab: string
  isOpen: boolean
  externId: string
  segmentId: string
}

export interface SegmentsBookmarksState {
  allBookmarks: Bookmark[]
  currentBookmark: Bookmark | null
  currentBookmarkId: string | null
  isBookmarkLoading: boolean
}

export interface SegmentSlidebarState {
  slidebar: Slidebar
  currentQueryHash: string
}

export interface FilterFlyoutState {
  filterQuery: Record<string, any>
  initialFlyoutFilterIds: Record<string, string[]>
  isExpanded: Record<string, boolean>
  groupedOptions: Record<string, FilterGroup[]>
  isLoading: Record<string, boolean>
  lastAppliedFilterQuery: Record<string, any>
  ungroupedOptions: Record<string, FilterOption[]>
}

export interface RootState {
  SegmentsBookmarks: SegmentsBookmarksState
  SegmentSlidebar: SegmentSlidebarState
  FilterFlyout?: FilterFlyoutState
  Bookmark?: {
    items: Record<string, Bookmark>
  }
  [key: string]: any
}

declare module 'vuex' {
  export function useStore(): Store<RootState>
}
