# Changelog

**Versioning Scheme:**
- **Minor Version**: Incremented for each release.
- **Patch Version**: Incremented for bug fixes.

## UNRELEASED
- Allow flag on external links to indicate this URL should only be shown for user(roles) with a specific permission
- Extend safelist for purge css to include all plyr classes

### Features
- Institution tag management: Add search field and filters to institution list
- Procedure basic settings: Move procedure location up under the "internal" section
- TagsListEditForm: add a confirmation message before deleting the Tag or Topic

### Further changes
- Segments list: Use DpSearchField for custom search
- DpInlineNotification: Set margin from outside the component (instead of inside)
- Rework Tags-Lists: Now works with APi2.0 and is extendable by addons.
- TagsList: trigger an update request only when the title is modified

## 2.20.0

### Features
- Admin institution list: Institutions can now be tagged filtered by categories

### Fixes
- Several bug fixes

### Further changes
- Addons can now be installed automatically, when listed in the `addons.yml` file
- Several major dependencies have been updated
