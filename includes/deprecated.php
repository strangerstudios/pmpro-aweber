<?php

// The following functions are all being replaced by the new pmproaw_pmpro_after_all_membership_level_changes() function.

/**
 * @deprecated TBD
 */
function pmproaw_pmpro_after_change_membership_level($level_id, $user_id)
{	
	_deprecated_function( __FUNCTION__, 'TBD', 'pmproaw_pmpro_after_all_membership_level_changes' );

	$pmproaw_levels = pmproaw_getPMProLevels();
	if ( empty( $pmproaw_levels ) ) {
		return;
	}	
	
	clean_user_cache($user_id);	
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

/**
 * @deprecated TBD
 */
function pmproaw_wp()
{
	_deprecated_function( __FUNCTION__, 'TBD' );
	if(is_admin())
		return;
		
	global $post;
	if(!empty($post->post_content) && strpos($post->post_content, "[pmpro_checkout]") !== false)
	{
		remove_action("pmpro_after_change_membership_level", "pmproaw_pmpro_after_change_membership_level", 15);
		add_action("pmpro_after_checkout", "pmproaw_pmpro_after_checkout", 15);	
	}
}

/**
 * @deprecated TBD
 */
function pmproaw_pmpro_after_checkout($user_id)
{
	_deprecated_function( __FUNCTION__, 'TBD', 'pmproaw_pmpro_after_all_membership_level_changes' );
	pmproaw_pmpro_after_change_membership_level(intval($_REQUEST['level']), $user_id);
}