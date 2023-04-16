=== Paid Memberships Pro - AWeber Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, aweber, email marketing
Requires at least: 4
Tested up to: 6.2
Stable tag: 1.3.2

Add users and members to AWeber lists based on their membership level.

== Description ==

Add users and members to AWeber lists based on their membership level.

This plugin offers extended functionality for [membership websites using the Paid Memberships Pro plugin](https://wordpress.org/plugins/paid-memberships-pro/) available for free in the WordPress plugin repository. 

With Paid Memberships Pro installed, you can specify unique lists for each membership level.

The settings page allows the site admin to specify which lists to assign for all users and members to plus additional features you may wish to adjust. The first step is to connect your website to AWeber using your account's authorization code.

== Installation ==
This plugin works with and without Paid Memberships Pro installed.

= Download, Install and Activate! =
1. Upload the `pmpro-aweber` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Navigate to Settings > PMPro Aweber to proceed with setup.

= Configuration and Settings =

**Authorize your app with AWeber:** Your Authorization Code will be provided once the app is authorized with your AWeber account. Navigate to Settings > PMPro AWeber in the WordPress dashboard to authorize your app with AWeber.

After entering your Authorization Code, continue with the setup by assigning All User Lists, Membership Level Lists, and review the additional settings.

For full documentation on all settings, please visit the [AWeber Integration Add On documentation page at Paid Memberships Pro](https://www.paidmembershipspro.com/add-ons/pmpro-aweber-integration/). 

Filter hooks are available for developers that need to customize specific aspects of the integration. [Please explore the plugin's action and filter hooks here](https://www.paidmembershipspro.com/add-ons/pmpro-aweber-integration/#hooks).

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-aweber/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Screenshots ==

1. General settings for all members/subscribers list.
2. Membership-level specific list subscription settings.

== Changelog ==
= 1.3.2 - 2020-11-04 =
* BUG FIX: Fixed issue where the incorrect subscriber in AWeber may be updated when a user changes their email address in WordPress.

= 1.3.1 =
* BUG FIX: Fixed issues with selecting and unselecting lists on the settings page.

= 1.3 =
* NOTE: All users will have to reauthenticate PMPro AWeber after updating.
* NOTE: If you are wondering, v1.2 was live on our update server for about 30m. There is no harm if you updated to v1.2, but please update to v1.3 now.
* BUG/ENHANCEMENT: Now fetching a new consumer key/secret pair.
* ENHANCEMENT: Making sure we don't forget your list settings when reauthenticating with AWeber.
* ENHANCEMENT: Added an admin notice that checks no more than once per day if the AWeber API is working and if not shows a link to the settings page.

= 1.2 =
* ENHANCEMENT: Improved messaging to make it more clear how to authorize the app at AWeber. Compatible for the Jan 16, 2017 API updates.
* BUG: Fixed some warnings.
* BUG/ENHANCEMENT: Will now fetch > 100 lists if you have that many. (Thanks, Fabio on GitHub)

= 1.1.2 =
* BUG: Fixed pmproaw_getAccount() error resulting in lists not being found

= 1.1.1 =
* BUG: Wrapping API calls to avoid fatal errors.

= 1.1
* FEATURE: Added option to the settings page to choose how users are unsubscribed from other lists when changing levels.

= 1.0.1 =
* Updates to name, description, tags. Added link to support and settings on plugins page.

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
