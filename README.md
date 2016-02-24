Group Lock
=================

This extension seeks to do what Author Roles extension does not cover in terms of permissions. 
That is handling of separate groups within one Symphony Install.

The extension assumes that you have one section which will determine the different groups. 
For example if you created a symphony install to manage workflow, you want users from Group A not to be able to see users from group B.
Creating a `group` section would allow you to determine which to which group the user belongs to.

## Installation

In order for this magic filtering to follow the below instructions.

1. Install the Extension

2. Proceed to the Preferences

  1. Here you have to first select the section you want to use for filtering.

  2. Save the Preferences

  3. The preferences panel will now load each section which has an `Association` with the main section. You have to select the field which is to be used for permission filtering ( SBL / Association Field etc)

  4. In case of dual nested relationships ex Groups is linked to Categories and categories to Articles. It is possible to use an `inherited` relationship as long as the field supports the `AssociationFiltering` delegate. At the time of writing this is only the latest release of the `AssociationField`

  5. Save the Preferences and you're ready to go

3. Go to the Author Section and start giving users access to the various groups as required.

## Use

This extension shows a `dropdown` in the top part of symphony showing which group is currently active, when a user has access to multiple groups.
The selected group would by default filter all the entries to the restricted group to simplify the view.
Changing the group would reload the page with the new filters.

Any new entries will by default take on the same group value as currently selected, and work as-if the value has been pre-filled. So in most cases you want the field to be hidden when pre-filled.

### Managers & Adding new Authors/Users

With the right preferences you can have Managers who are only able to add new users within their own groups. 
Adding of users which already exist but don't have access to the group is currently not catered for and will in most likely of cases throw an error.
Kindly proceed with caution or get in touch if it's a feature which you'd like to see working

## Author Filtering

Author Filtering by group requires a new delegte which is planned to be released in Symphony 2.7.0.
Should you need authors to be blocked from accessing other groups, and 2.7.0 is not yet available get in touch.