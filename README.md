# Content Access Simple

This module utilized the functionality of the Content Access module but adds a simpler interface for managing view access of specific nodes. Instead of a full screen of checkboxes, content editors are given a simpler list of only the roles who can view the node.

This is only enabled when "Per content node access control settings" are enabled as part of the Content Access module and only appear for roles who have the permissions set for this module (`grant content access simple` and/or `grant own content access simple`).

## Installation

1. Enable the module.
2. Visit the Content Access form for a specific content type at `/admin/structure/types/manage/{content_type}/access`.
3. Enable "Per content node access control settings".
4. A new "Access and Permissions" section will be added to the node edit page.
5. To move this new section, use "Manage form display".
6. Note there are permissions for this module specifically as well and can be changed under `/admin/users/permissions`.

## Hidden Roles

This module also allows to hide roles from the view list so content editors cannot change permissions for specific roles. Currently this is done only in config via `content_access_simple.settings.yml`. A future update will allow these to be configured in the UI.

## Complex permissions

If either of the following cases are detected, "complex" permissions are triggered and content editors are not allowed to modify access control via this module (but still can via the Content Access form if they have permissions):

* If "view own" permissions on the node itself differs from the default "view own" permissions for the content type.
* For hidden roles, if "view all" permissions differs from the default "view all" permissions for the content type.

To fix, check these permissions using the regular Content Access form at `/node/{nid}/access` as compared to the permissions on the content type at `/admin/structure/types/manage/{content_type}/access`
