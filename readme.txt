=== PMPro AWeber ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, aweber, email marketing
Requires at least: 3.4
Tested up to: 3.8.1
Stable tag: .4.1

Sync your WordPress users and members with AWeber lists.

If Paid Memberships Pro is installed you can sync users by membership level, otherwise all users can be synced to one or more lists.

== Description ==

Sync your WordPress users and members with AWeber lists.

If Paid Memberships Pro is installed you can sync users by membership level, otherwise all users can be synced to one or more lists.


== Installation ==

1. Upload the `pmpro-aweber` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. The settings page is at Settings --> PMPro AWeber in the WP dashboard.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-aweber/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==
= 1.0 =
* Released to WordPress repository.

= .4.1 =
* Fixed bug that occurred when deleting users. FYI, deleted users are added to any All Users lists per the PMPro AWeber settings, since they have any membership level removed first before they are deleted. We may change this behavior in the future.

= .4 =
* Fixed bug introduced in .3 where you had to set something in the pmpro_aweber_custom_fields filter or subscribers weren't being added.
* Fixed bug where we were checking for the MCAPI class instead of the AWeberAPI class. (Thanks, pabbate22 and ppcuban on GitHub)

= .3 =
* Added "pmpro_aweber_custom_fields" filter. Example: https://gist.github.com/strangerstudios/8931605

= .2 =
* Fixed unsubscribe code that was unsubscribing random members of a list.

= .1 =
* Initial version based on the pmpro-mailchimp plugin with alterations.