/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
import ClientSideTagFilter from '@DpJs/components/procedure/admin/InstitutionTagManagement/ClientSideTagFilter.vue'

const { getCategoryTags, groupTagsByCategory } = ClientSideTagFilter.methods

const tag = (id, name, categoryId) => ({
  id,
  type: 'InstitutionTag',
  attributes: { name },
  relationships: { category: { data: { id: categoryId, type: 'InstitutionTagCategory' } } },
})

describe('ClientSideTagFilter', () => {
  describe('groupTagsByCategory', () => {
    it('groups the tags by the id of their category and keeps the order of the response', () => {
      const first = tag('tag-1', 'first', 'category-b')
      const second = tag('tag-2', 'second', 'category-a')
      const third = tag('tag-3', 'third', 'category-a')

      expect(groupTagsByCategory([third, first, second])).toEqual({
        'category-a': [third, second],
        'category-b': [first],
      })
    })

    it('ignores tags without a category', () => {
      const withoutCategory = { id: 'tag-1', attributes: { name: 'lonely' }, relationships: {} }

      expect(groupTagsByCategory([withoutCategory])).toEqual({})
    })

    it('returns an empty object without tags', () => {
      expect(groupTagsByCategory(undefined)).toEqual({})
    })
  })

  describe('getCategoryTags', () => {
    it('returns the grouped tags of the category when the tags are loaded separately', () => {
      const first = tag('tag-1', 'first', 'category-a')
      const context = { loadTagsSeparately: true, tagsByCategory: { 'category-a': [first] } }

      expect(getCategoryTags.call(context, 'category-a')).toEqual([first])
      expect(getCategoryTags.call(context, 'unknown')).toEqual([])
    })

    it('reads the tags from the relationship of the category when they are not loaded separately', () => {
      const tags = { 'tag-1': tag('tag-1', 'first', 'category-a') }
      const context = {
        loadTagsSeparately: false,
        institutionTagCategoriesCopy: {
          'category-a': { relationships: { tags: { data: [{ id: 'tag-1' }], list: () => tags } } },
        },
      }

      expect(getCategoryTags.call(context, 'category-a')).toEqual(tags)
    })
  })
})
