# Content Access Simple

This module uses the Content Access module functions to add a simpler interface for managing view access of specific nodes. Instead of a full screen of checkboxes, content editors are given a simpler list of only the roles who can view the node.

This is only enabled when "Per content node access control settings" are enabled as part of the Content Access module and only appear for roles who have the "Grant content access simple View and modify content access simple for any nodes" (`grant content access simple`) permissions.

## Installation

1. Enable the module.
2. Enable "Per content node access control settings" via Content Access module for a specific content type.
3. A new "Access and Permissions" section will be added to the node edit page.
4. To move this new section, use "Manage form display".

## Hidden Roles

This module also allows to hide roles from the list as well. Currently this is done only in config via `content_access_simple.settings.yml`. A future update will allow these to be configured in the UI.

## Complex permissions

If either of these cases are detected, "complex" permissions are triggered and content editors are not allowed to modify access control:

* If "view own" permissions on the node itself differs from the default "view own" permissions for the content type.
* For hidden roles, if "view all" permissions differs from the default "view all" permissions for the content type.

To fix, check these permissions using the regular Content Access form at `/node/{nid}/access`
