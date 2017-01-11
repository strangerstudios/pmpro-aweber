<?php
/*
Plugin Name: Paid Memberships Pro - AWeber Add On
Plugin URI: http://www.paidmembershipspro.com/pmpro-aweber/
Description: Sync your WordPress users and members with AWeber lists.
Version: 1.3
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/
/*
	Copyright 2011	Stranger Studios	(email : jason@strangerstudios.com)
	GPLv2 Full license details in license.txt
*/

/*
	These keys are for the PMPro-AWeber App.
	Your user keys should be entered on the settings page at Settings --> PMPro AWeber in your WP dashboard.
*/
define('PMPROAW_APPID', '7f549046');

//init
function pmproaw_init()
{
	//include AWeberAPI Class if we don't have it already
	if(!class_exists("AWeberAPI"))
	{
		require_once(dirname(__FILE__) . "/includes/aweber_api/aweber_api.php");
	}
	
	//get options for below
	$options = get_option("pmproaw_options");
	
	//default new unsubscribe option to 'Yes (Only old lists.)'
	if(empty($options)) {
		$options = array('unsubscribe'=>'1');
		update_option('pmproaw_options', $options);
	} elseif(!isset($options['unsubscribe'])) {
		$options['unsubscribe'] = 1;
		update_option('pmproaw_options', $options);
	}
	
	//setup hooks for new users	
	if(!empty($options['users_lists']))
		add_action("user_register", "pmproaw_user_register");
	
	//setup hooks for PMPro levels
	pmproaw_getPMProLevels();
	global $pmproaw_levels;
	if(!empty($pmproaw_levels))
	{		
		add_action("pmpro_after_change_membership_level", "pmproaw_pmpro_after_change_membership_level", 15, 2);
	}		
}
add_action("init", "pmproaw_init", 30);

//use a different action if we are on the checkout page
function pmproaw_wp()
{
	if(is_admin())
		return;
		
	global $post;
	if(!empty($post->post_content) && strpos($post->post_content, "[pmpro_checkout]") !== false)
	{
		remove_action("pmpro_after_change_membership_level", "pmproaw_pmpro_after_change_membership_level", 15);
		add_action("pmpro_after_checkout", "pmproaw_pmpro_after_checkout", 15);	
	}
}
add_action("wp", "pmproaw_wp", 0);

/*
	Load the AWeber API
*/
function pmproaw_getAccount($force = false)
{
	global $pmproaw_aweber_api, $pmproaw_aweber_account;	
	
	if(empty($force) && empty($pmproaw_aweber_account)) {		
		$options = get_option("pmproaw_options");
		
		if(empty($options['consumer_key']) || empty($options['consumer_secret']) || empty($options['access_key']) || empty($options['access_secret']))
			return false;
		
		try {
			$pmproaw_aweber_api = new AWeberAPI($options['consumer_key'], $options['consumer_secret']);
			$pmproaw_aweber_account = $pmproaw_aweber_api->getAccount($options['access_key'], $options['access_secret']);
		} catch(AWeberAPIException $exc) {			
			global $pmproaw_exception;
			$pmproaw_exception = $exc;				
			
			return false;
		}
	}
	
	return $pmproaw_aweber_account;
}

//if the API is not working, show a notice and link to settings
function pmproaw_admin_notices() {
	//check transient so we don't check to often
	$pmproaw_api_squelch_notice = get_transient('pmproaw_api_squelch_notice');
	if($pmproaw_api_squelch_notice)
		return;
	
	//don't show on the options page
	if(!empty($_REQUEST['page']) && $_REQUEST['page'] == 'pmproaw_options')
		return;
		
	//haven't checked in a while so try to connect
	$account = pmproaw_getAccount();
	if(empty($account)) {
	?>
	<div class="notice notice-error is-dismissible">
		<p>Error connecting to the AWeber API. <a href="<?php echo admin_url('options-general.php?page=pmproaw_options');?>">Please check your PMPro AWeber settings and reauthorize if necessary.</a></p>
	</div>
	<?php
	} else {
		//API seems okay, let's disable the check for 24 hours
		set_transient('pmproaw_api_squelch_notice', true, 60*60*24);
	}
}
add_action('admin_notices', 'pmproaw_admin_notices');

//for when checking out
function pmproaw_pmpro_after_checkout($user_id)
{
	pmproaw_pmpro_after_change_membership_level(intval($_REQUEST['level']), $user_id);
}

//subscribe users when they register
function pmproaw_user_register($user_id)
{
	clean_user_cache($user_id);
	
	$options = get_option("pmproaw_options");
	
	//should we add them to any lists?
	if(!empty($options['users_lists']) && !empty($options['access_key']) && !empty($options['access_secret']))
	{
		//get user info
		$list_user = get_userdata($user_id);
		
		//subscribe to each list
		try {

			foreach($options['users_lists'] as $list_id)
			{	
				pmproaw_subscribe($list_id, $list_user);				
			}
		}
		catch(AWeberAPIException $exc) {
			//just catching errors so users don't see them			
		}
	}
}

//subscribe new members (PMPro) when they register
function pmproaw_pmpro_after_change_membership_level($level_id, $user_id)
{
	clean_user_cache($user_id);
	
	global $pmproaw_levels;
	$options = get_option("pmproaw_options");
	$all_lists = get_option("pmproaw_all_lists");	
		
	//should we add them to any lists?
	if(!empty($options['level_' . $level_id . '_lists']) && !empty($options['consumer_key']) && !empty($options['consumer_secret']) && !empty($options['access_key']) && !empty($options['access_secret']))
	{
		//get user info
		$list_user = get_userdata($user_id);		
		
		//subscribe to each list
		try {		
			
			foreach($options['level_' . $level_id . '_lists'] as $list_id)
			{					
				//echo "<hr />Trying to subscribe to " . $list_id . "...";
				pmproaw_subscribe($list_id, $list_user);							
			}
			
			foreach($all_lists as $list)
			{
				//Unsubscribe set to "No"
				if(!$options['unsubscribe'])
					return;
				
				//Unsubscribe set to "Yes"
				if($options['unsubscribe'] == "all")
				{
					if(!in_array($list['id'], $options['level_' . $level_id . '_lists']))
					{
						pmproaw_unsubscribe($list, $list_user);
					}
				}
				
				//Unsubscribe set to "Yes (Old Level Lists Only)
				else
				{
					//get their prevous level lists
					global $wpdb;
					
					if($level_id)
						$last_level = $wpdb->get_results("SELECT* FROM $wpdb->pmpro_memberships_users WHERE `user_id` = $user_id ORDER BY `id` DESC LIMIT 1,1");
					
					else
						$last_level = $wpdb->get_results("SELECT* FROM $wpdb->pmpro_memberships_users WHERE `user_id` = $user_id ORDER BY `id` DESC LIMIT 1");
		
					if($last_level)
					{			
						$last_level_id = $last_level[0]->membership_id;
						
						if(!empty($options['level_'.$last_level_id.'_lists']))
							$old_level_lists = $options['level_'.$last_level_id.'_lists'];
						else
							$old_level_lists = array();
					}
					else
						$old_level_lists = array();
					
					//if we find this list id in thier old level, then unsubscribe
					if(in_array($list['id'], $old_level_lists))
					{
						pmproaw_unsubscribe($list, $list_user);
					}
				}
			}
		}
		catch(AWeberAPIException $exc) {
			//just catching errors so users don't see them			
		}
	}
	elseif(!empty($options['consumer_key']) && !empty($options['consumer_secret']) && !empty($options['access_key']) && !empty($options['access_secret']))
	{		
		//now they are a normal user should we add them to any lists?
		if(!empty($options['users_lists']))
		{
			//get user info
			$list_user = get_userdata($user_id);
			
			//subscribe to each list
			try {

				foreach($options['users_lists'] as $list)
				{					
					if ( is_array($list) && isset($list['id']) )
						pmproaw_subscribe($list['id'], $list_user);
					else
						pmproaw_subscribe($list, $list_user);
				}
				
				//unsubscribe from any list not assigned to users
				foreach($all_lists as $list)
				{
					pmproaw_unsubscribe($list, $list_user);
				}
			}
			catch(AWeberAPIException $exc) {
				//just catching errors so users don't see them				
			}
		}
		else
		{			
			//some memberships are on lists. assuming the admin intends this level to be unsubscribed from everything
			if(is_array($all_lists))
			{
				//get user info
				$list_user = get_userdata($user_id);
				
				//unsubscribe to each list
				try {
				
					foreach($all_lists as $list)
					{
						pmproaw_unsubscribe($list, $list_user);
					}
				}
				catch(AWeberAPIException $exc) {
					//just catching errors to hide them from users					
				}
			}
		}
	}
}

function pmproaw_unsubscribe($list_id, $list_user)
{	
	//accept arrays as well
	if(is_array($list_id))
		$list_id = $list_id['id'];
	
	//get aweber account or fail
	$account = pmproaw_getAccount();	
	if(empty($account))
		return;
	
	//get list
	$listURL = "/accounts/{$account->id}/lists/{$list_id}";
	$aw_list = $account->loadFromUrl($listURL);

	//find subscriber
	$subscribers = $aw_list->subscribers;							
	$params = array('email' => $list_user->user_email);
	$found_subscribers = $subscribers->find($params);
												
	//unsubscribe
	foreach($found_subscribers as $subscriber)
	{							
		$subscriber->status = 'unsubscribed';
		$subscriber->save();							
	}
}

function pmproaw_subscribe($list_id, $list_user)
{
	//accept arrays as well
	if(is_array($list_id))
		$list_id = $list_id['id'];
	
	//get aweber account or fail
	$account = pmproaw_getAccount();	
	if(empty($account))
		return;
	
	$listURL = "/accounts/{$account->id}/lists/{$list_id}";
	$list = $account->loadFromUrl($listURL);
	$subscribers = $list->subscribers;
	if (!$custom_fields = apply_filters("pmpro_aweber_custom_fields", array(), $list_user))
	{
		$new_subscriber = $subscribers->create(array(
				'email' => $list_user->user_email,
				'name' => trim($list_user->first_name . " " . $list_user->last_name)));
	}
	
	else
	{
		$new_subscriber = $subscribers->create(array(
				'email' => $list_user->user_email,
				'name' => trim($list_user->first_name . " " . $list_user->last_name),
				'custom_fields' => $custom_fields));
	}
}

//change email in AWeber if a user's email is changed in WordPress
function pmproaw_profile_update($user_id, $old_user_data)
{
	//get aweber account or fail
	$account = pmproaw_getAccount();	
	if(empty($account))
		return;
	
	$new_user_data = get_userdata($user_id);
	if($new_user_data->user_email != $old_user_data->user_email)
	{			
		try {
			//get all lists
			$pmproaw_lists = $account->lists->data['entries'];
		
			if(!empty($pmproaw_lists))
			{
				foreach($pmproaw_lists as $list)
				{
					//get list
					$listURL = "/accounts/{$account->id}/lists/{$list['id']}";
					$aw_list = $account->loadFromUrl($listURL);

					//find subscriber
					$subscribers = $aw_list->subscribers;
				
					$params = array('status' => 'subscribed');
					$found_subscribers = $subscribers->find($params);
				
					//change email
					foreach($found_subscribers as $subscriber)
					{
						$subscriber->email = $new_user_data->user_email;
						$subscriber->save();							
					}
				}
			}
		}
		catch(AWeberAPIException $exc) {
			//just catching errors to hide them from users					
		}
	}
}
add_action("profile_update", "pmproaw_profile_update", 10, 2);

//admin init. registers settings
function pmproaw_admin_init()
{
	//setup settings
	register_setting('pmproaw_options', 'pmproaw_options', 'pmproaw_options_validate');	
	add_settings_section('pmproaw_section_general', 'General Settings', 'pmproaw_section_general', 'pmproaw_options');		
	add_settings_field('pmproaw_option_authorization_code', 'AWeber Authorization Code', 'pmproaw_option_authorization_code', 'pmproaw_options', 'pmproaw_section_general');		
	add_settings_field('pmproaw_option_consumer_key', 'AWeber Consumer Key', 'pmproaw_option_consumer_key', 'pmproaw_options', 'pmproaw_section_general');		
	add_settings_field('pmproaw_option_consumer_secret', 'AWeber Consumer Secret', 'pmproaw_option_consumer_secret', 'pmproaw_options', 'pmproaw_section_general');		
	add_settings_field('pmproaw_option_access_key', 'AWeber Access Key', 'pmproaw_option_access_key', 'pmproaw_options', 'pmproaw_section_general');		
	add_settings_field('pmproaw_option_access_secret', 'AWeber Access Secret', 'pmproaw_option_access_secret', 'pmproaw_options', 'pmproaw_section_general');		
	add_settings_field('pmproaw_option_users_lists', 'All Users List', 'pmproaw_option_users_lists', 'pmproaw_options', 'pmproaw_section_general');	
	//add_settings_field('pmproaw_option_double_opt_in', 'Require Double Opt-in?', 'pmproaw_option_double_opt_in', 'pmproaw_options', 'pmproaw_section_general');	
	add_settings_field('pmproaw_option_unsubscribe', 'Unsubscribe on Level Change?', 'pmproaw_option_unsubscribe', 'pmproaw_options', 'pmproaw_section_general');	

	//pmpro-related options	
	add_settings_section('pmproaw_section_levels', 'Membership Levels and Lists', 'pmproaw_section_levels', 'pmproaw_options');		
	
	//add options for levels
	pmproaw_getPMProLevels();
	global $pmproaw_levels;
	if(!empty($pmproaw_levels))
	{						
		foreach($pmproaw_levels as $level)
		{
			add_settings_field('pmproaw_option_memberships_lists_' . $level->id, $level->name, 'pmproaw_option_memberships_lists', 'pmproaw_options', 'pmproaw_section_levels', array($level));
		}
	}		
}
add_action("admin_init", "pmproaw_admin_init");

//set the pmproaw_levels array if PMPro is installed
function pmproaw_getPMProLevels()
{	
	global $pmproaw_levels, $wpdb;
	
	if ( isset( $wpdb->pmpro_membership_levels ) ) 
		$pmproaw_levels = $wpdb->get_results("SELECT * FROM $wpdb->pmpro_membership_levels ORDER BY id");			
	else
		$pmproaw_levels = array();
}

//options sections
function pmproaw_section_general()
{	
?>
<p></p>	
<?php
}

//options sections
function pmproaw_section_levels()
{	
	global $wpdb, $pmproaw_levels;
	
	//do we have PMPro installed?
	if(class_exists("MemberOrder"))
	{
	?>
		<p>PMPro is installed.</p>
	<?php
		//do we have levels?
		if(empty($pmproaw_levels))
		{
		?>
		<p>Once you've <a href="admin.php?page=pmpro-membershiplevels">created some levels in Paid Memberships Pro</a>, you will be able to assign AWeber lists to them here.</p>
		<?php
		}
		else
		{
		?>
		<p>For each level below, choose the lists which should be subscribed to when a new user registers.</p>
		<?php
		}
	}
	else
	{
		//just deactivated or needs to be installed?
		if(file_exists(dirname(__FILE__) . "/../paid-memberships-pro/paid-memberships-pro.php"))
		{
			//just deactivated
			?>
			<p><a href="plugins.php?plugin_status=inactive">Activate Paid Memberships Pro</a> to add membership functionality to your site and finer control over your AWeber lists.</p>
			<?php
		}
		else
		{
			//needs to be installed
			?>
			<p><a href="plugin-install.php?tab=search&type=term&s=paid+memberships+pro&plugin-search-input=Search+Plugins">Install Paid Memberships Pro</a> to add membership functionality to your site and finer control over your AWeber lists.</p>
			<?php
		}
	}
}


//options code
function pmproaw_option_authorization_code()
{
	global $pmproaw_exception;
	
	$options = get_option('pmproaw_options');		
	if(isset($options['authorization_code']))
		$authorization_code = $options['authorization_code'];
	else
		$authorization_code = "";
	
	echo "<textarea name='pmproaw_options[authorization_code]' cols='60' rows='3'>" . esc_textarea($authorization_code) . "</textarea>";	
	
	if(empty($authorization_code)){
		echo "<div class='updated inline'><p>To get started, you must authorize this website to access your AWeber account.<br /><a target='_blank' href='?page=pmproaw_options&oauth=1'>Click here to authorize this site to access your AWeber account</a>.<br />Then copy the 'authorization code' given into the field above and click 'Save Settings' to continue.</p></div>";
	} elseif(!empty($pmproaw_exception)) {
		echo "<div class='notice notice-error inline'><p>You must reauthorize the PMPro Aweber App at AWeber.<br /><a target='_blank' href='?page=pmproaw_options&oauth=1'>Click here to authorize this site to access your AWeber account</a>.<br />Then copy the new 'authorization code' given and replace it into the field above and click 'Save Settings' to continue.</p></div>";
	}
	
	//show an extra save settings button sometimes
	if(empty($authorization_code) || !empty($pmproaw_exception)) {
	?>
	<input type="submit" name="submit" class="button-primary" value="Save Settings">
	<?php
	}
}

function pmproaw_option_consumer_key()
{
	$options = get_option('pmproaw_options');		
	if(isset($options['consumer_key']))
		$consumer_key = $options['consumer_key'];
	else
		$consumer_key = "";
	
	?>
	<input id='pmproaw_consumer_key' name='pmproaw_options[consumer_key]' size='80' type='text' value='<?php echo esc_attr($consumer_key); ?>' readonly='readonly'/>
	<br /><small>This value is automatically generated when the Authorization Code above is set.</small>
	<script>
		//hide this row in the table
		jQuery('#pmproaw_consumer_key').closest('tr').hide();
	</script>
	<?php
}

function pmproaw_option_consumer_secret()
{
	$options = get_option('pmproaw_options');		
	if(isset($options['consumer_secret']))
		$consumer_secret = $options['consumer_secret'];
	else
		$consumer_secret = "";
	
	?>
	<input id='pmproaw_consumer_secret' name='pmproaw_options[consumer_secret]' size='80' type='text' value='<?php echo esc_attr($consumer_secret); ?>' readonly='readonly'/>
	<br /><small>This value is automatically generated when the Authorization Code above is set.</small>
	<script>
		//hide this row in the table
		jQuery('#pmproaw_consumer_secret').closest('tr').hide();
	</script>
	<?php
}

function pmproaw_option_access_key()
{
	$options = get_option('pmproaw_options');		
	if(isset($options['access_key']))
		$access_key = $options['access_key'];
	else
		$access_key = "";
	
	?>
	<input id='pmproaw_access_key' name='pmproaw_options[access_key]' size='80' type='text' value='<?php echo esc_attr($access_key); ?>' readonly='readonly'/>
	<br /><small>This value is automatically generated when the Authorization Code above is set.</small>
	<script>
		//hide this row in the table
		jQuery('#pmproaw_access_key').closest('tr').hide();
	</script>
	<?php
}

function pmproaw_option_access_secret()
{
	$options = get_option('pmproaw_options');		
	if(isset($options['access_secret']))
		$access_secret = $options['access_secret'];
	else
		$access_secret = "";
	
	?>
	<input id='pmproaw_access_secret' name='pmproaw_options[access_secret]' size='80' type='text' value='<?php echo esc_attr($access_secret); ?>' readonly='readonly'/>
	<br /><small>This value is automatically generated when the Authorization Code above is set.</small>
	<script>
		//hide this row in the table
		jQuery('#pmproaw_access_secret').closest('tr').hide();
	</script>
	<?php
}

function pmproaw_option_users_lists()
{	
	global $pmproaw_lists;
	$options = get_option('pmproaw_options');
		
	if(isset($options['users_lists']) && is_array($options['users_lists']))
		$selected_lists = $options['users_lists'];
	else
		$selected_lists = array();
	
	if(!empty($pmproaw_lists))
	{
		echo "<select multiple='yes' name=\"pmproaw_options[users_lists][]\">";
		foreach($pmproaw_lists as $list)
		{
			echo "<option value='" . $list['id'] . "' ";
			if(in_array($list['id'], $selected_lists))
				echo "selected='selected'";
			echo ">" . $list['name'] . "</option>";
		}
		echo "</select>";
	}
	else
	{
		echo "No lists found.";
	}	
}

function pmproaw_option_double_opt_in()
{
	$options = get_option('pmproaw_options');	
	?>
	<select name="pmproaw_options[double_opt_in]">
		<option value="0" <?php selected($options['double_opt_in'], 0);?>>No</option>
		<option value="1" <?php selected($options['double_opt_in'], 1);?>>Yes</option>		
	</select>
	<?php
}

function pmproaw_option_memberships_lists($level)
{	
	global $pmproaw_lists;
	$options = get_option('pmproaw_options');
	
	$level = $level[0];	//WP stores this in the first element of an array
		
	if(isset($options['level_' . $level->id . '_lists']) && is_array($options['level_' . $level->id . '_lists']))
		$selected_lists = $options['level_' . $level->id . '_lists'];
	else
		$selected_lists = array();
	
	if(!empty($pmproaw_lists))
	{
		echo "<select multiple='yes' name=\"pmproaw_options[level_" . $level->id . "_lists][]\">";
		foreach($pmproaw_lists as $list)
		{
			echo "<option value='" . $list['id'] . "' ";
			if(in_array($list['id'], $selected_lists))
				echo "selected='selected'";
			echo ">" . $list['name'] . "</option>";
		}
		echo "</select>";
	}
	else
	{
		echo "No lists found.";
	}	
}

// validate our options
function pmproaw_options_validate($input) 
{					
	global $pmproaw_levels;
	$options = get_option('pmproaw_options');
	
	//api key
	$newinput['authorization_code'] = trim(preg_replace("[^a-zA-Z0-9\-\|]", "", $input['authorization_code']));
	$newinput['consumer_key'] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['consumer_key']));
	$newinput['consumer_secret'] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['consumer_secret']));
	$newinput['access_key'] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['access_key']));
	$newinput['access_secret'] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['access_secret']));

	//clear access key and secret if authorization code was updated
	if($options['authorization_code'] != $newinput['authorization_code']) {		
		$newinput['consumer_key'] = '';
		$newinput['consumer_secret'] = '';
		$newinput['access_key'] = '';
		$newinput['access_secret'] = '';
	}
	
	//other settings
	//$newinput['double_opt_in'] = intval($input['double_opt_in']);	
	$newinput['unsubscribe'] = preg_replace("[^a-zA-Z0-9\-]", "", $input['unsubscribe']);
	
	//user lists	
	if(!isset($input['users_lists'])) {
		//no lists, probably needs to authenticate, keep old settings
		$newinput['users_lists'] = $options['users_lists'];
	} else {
		//grab data from input
		if(!empty($input['users_lists']) && is_array($input['users_lists']))
		{
			$count = count($input['users_lists']);
			for($i = 0; $i < $count; $i++)
				$newinput['users_lists'][] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['users_lists'][$i]));	;
		}
	}
	
	//membership lists	
	if(!empty($pmproaw_levels)) {
		foreach($pmproaw_levels as $level) {
			if(!isset($input['users_lists'])) {
				//no lists, probably needs to authenticate, keep old settings
				$newinput['level_' . $level->id . '_lists'] = $options['level_' . $level->id . '_lists'];
			} else {
				//grab data from input
				if(!empty($input['level_' . $level->id . '_lists']) && is_array($input['level_' . $level->id . '_lists'])) {
					$count = count($input['level_' . $level->id . '_lists']);
					for($i = 0; $i < $count; $i++)
						$newinput['level_' . $level->id . '_lists'][] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['level_' . $level->id . '_lists'][$i]));	;
				}
			}
		}
	}
	
	return $newinput;
}		

// add the admin options page	
function pmproaw_admin_add_page() 
{
	add_options_page('PMPro AWeber Options', 'PMPro AWeber', 'manage_options', 'pmproaw_options', 'pmproaw_options_page');
}
add_action('admin_menu', 'pmproaw_admin_add_page');

//oauth
function pmproaw_init_oauth()
{	
	if(is_admin())
	{
		if(!empty($_REQUEST['page']) && $_REQUEST['page'] == "pmproaw_options" && !empty($_REQUEST['oauth']))
		{
			// Redirect your user to the distributed app authorization URL
			$authorizationURL = "https://auth.aweber.com/1.0/oauth/authorize_app/" . PMPROAW_APPID;
			header("Location: $authorizationURL");
			exit();
		}
	}
}
add_action("init", "pmproaw_init_oauth");

//show exception error
function pmproaw_printAWeberAPIException($exc) {
	//don't print unless we're on the settings page
	if(!current_user_can('manage_options') || (is_admin() && !empty($_REQUEST['page']) && $_REQUEST['page'] != 'pmproaw_options'))
		return;
	
	print "<div class='notice notice-error'><h3>AWeberAPIException:</h3>";
	print " <li> Type: $exc->type              <br>";
	print " <li> Msg : $exc->message           <br>";
	print " <li> Docs: $exc->documentation_url <br>";
	print "</div>";
}

//html for options page
function pmproaw_options_page()
{
	global $pmproaw_lists;
	
	//check for a valid API key and get lists
	$options = get_option("pmproaw_options");	
	
	//get token if needed
	if(!empty($options['authorization_code']) && (empty($options['consumer_key']) || empty($options['consumer_secret']) || empty($options['access_key']) || empty($options['access_secret'])))
	{
		try {
			# set $authorization_code to the code that is given to you from
			# https://auth.aweber.com/1.0/oauth/authorize_app/YOUR_APP_ID			
			$auth = AWeberAPI::getDataFromAweberID($options['authorization_code']);			
			list($consumerKey, $consumerSecret, $accessKey, $accessSecret) = $auth;

			# Store the Consumer key/secret, as well as the AccessToken key/secret
			# in your app, these are the credentials you need to access the API.
			$options['consumer_key'] = $consumerKey;
			$options['consumer_secret'] = $consumerSecret;
			$options['access_key'] = $accessKey;
			$options['access_secret'] = $accessSecret;
			
			update_option('pmproaw_options', $options);			
		}
		catch(AWeberAPIException $exc) {
			global $pmproaw_exception;
			$pmproaw_exception = $exc;	
			pmproaw_printAWeberAPIException($exc);			
		}
	}
	
	//get lists
	if(!empty($options['consumer_key']) && !empty($options['consumer_secret']) && !empty($options['access_key']) && !empty($options['access_secret']))
	{
		//get aweber account or fail
		$account = pmproaw_getAccount();	
		if(!empty($account)) {
			try {								
				if($account->lists->data['total_size'] > 99) {
					//need to grab lists this way to get > 100
					$pmproaw_lists = array();					
					foreach($account->lists as $somelist) {
						$pmproaw_lists[] = $somelist->data;
					}
				} else {
					//just get the entries array which has first 100
					$pmproaw_lists = $account->lists->data['entries'];
				}
				
				$all_lists = array();
								
				//save all lists in an option
				$i = 0;	
				foreach ( $pmproaw_lists as $list ) {
					$all_lists[$i]['id'] = $list['id'];				
					$all_lists[$i]['account_id'] = $account->id;
					$all_lists[$i]['name'] = $list['name'];
					$i++;
				}
				
				/** Save all of our new data */
				update_option( "pmproaw_all_lists", $all_lists);
				
			} catch(AWeberAPIException $exc) {
				global $pmproaw_exception;
				$pmproaw_exception = $exc;
				pmproaw_printAWeberAPIException($exc);
			}
		} else {
			global $pmproaw_exception;
			if(!empty($pmproaw_exception)) {
				pmproaw_printAWeberAPIException($pmproaw_exception);
			}			
		}
	}
?>
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br></div>
	<h2>PMPro AWeber Integration Options</h2>		
	
	<?php if(!empty($msg)) { ?>
		<div class="message <?php echo $msgt; ?>"><p><?php echo $msg; ?></p></div>
	<?php } ?>
	
	<form action="options.php" method="post">
		
		<p>This plugin will integrate your site with AWeber. You can choose one or more AWeber lists to have users subscribed to when they signup for your site.</p>
		<p>If you have <a href="http://www.paidmembershipspro.com">Paid Memberships Pro</a> installed, you can also choose one or more AWeber lists to have members subscribed to for each membership level.</p>
		<p>Don't have a AWeber account? <a href="http://www.aweber.com/?422729" target="_blank">Get one here</a>.</p>
		
		<?php			
			settings_fields('pmproaw_options');
			do_settings_sections('pmproaw_options');			
		?>
		
		<p><br /></p>
						
		<div class="bottom-buttons">
			<input type="hidden" name="pmprot_options[set]" value="1" />
			<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e('Save Settings'); ?>">				
		</div>
		
	</form>
</div>
<?php
}

/*
Function to add links to the plugin action links
*/
function pmproaw_add_action_links($links) {
	
	$new_links = array(
			'<a href="' . get_admin_url(NULL, 'options-general.php?page=pmproaw_options') . '">Settings</a>',
	);
	return array_merge($new_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pmproaw_add_action_links');

/*
Function to add links to the plugin row meta
*/
function pmproaw_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-aweber.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/third-party-integration/pmpro-aweber-integration/') . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproaw_plugin_row_meta', 10, 2);

function pmproaw_option_unsubscribe()
{
	$options = get_option('pmproaw_options');	
	?>
	<select name="pmproaw_options[unsubscribe]">
		<option value="0" <?php selected($options['unsubscribe'], 0);?>>No</option>
		<option value="1" <?php selected($options['unsubscribe'], 1);?>>Yes (Only old level lists.)</option>
		<option value="all" <?php selected($options['unsubscribe'], "all");?>>Yes (All other lists.)</option>
	</select>
	<small>Recommended: Yes. However, if you manage multiple lists in Aweber and have users subscribe outside of WordPress, you may want to choose No so contacts aren't unsubscribed from other lists when they register on your site.</small>
	<?php
}
