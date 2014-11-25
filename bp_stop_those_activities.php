<?php

/**
 * Nifty function to get the name of the directory where the  plugin is installed in.
 * @author  Stergatu Eleni
 * @version 1, 14/11/2014
 * @since 0.6
 */
function ls_bp_activity_block_dir() {
    if ( stristr( __FILE__, '/' ) )
	$bp_gr_dir = explode( '/plugins/', dirname( __FILE__ ) );
    else
	$bp_gr_dir = explode( '\\plugins\\', dirname( __FILE__ ) );
    return str_replace( '\\', '/', end( $bp_gr_dir ) ); //takes care of MS slashes
}

/**
 *  Add settings link on plugin page
 *  @param type $links
 * @param type $file
 * @return array
 * @since version 0.6
 * version 1, 14/11/2014 stergtu
 *
 */
function ls_bp_activity_block_link( $links, $file ) {
    $plugindir = ls_bp_activity_block_dir();
    $thisfile = $plugindir . '/loader.php';
    // Return normal links if not BuddyPress
    if ( $thisfile != $file )
	return $links;

    // Main settings page
    $settings_page = bp_core_do_network_admin() ? 'settings.php' : 'options-general.php';
    // Add a few links to the existing links array
    return array_merge( $links, array(
	'settings' => '<a href="' . add_query_arg( array( 'page' => 'bp-settings#ls-bp-stop-record-activity' ), $settings_page ) . '">' . esc_html__( 'Settings', 'buddypress' ) . '</a>',
	    ) );

    return $links;
}

/// Add link to settings page
add_filter( 'plugin_action_links', 'ls_bp_activity_block_link', 10, 2 );
add_filter( 'network_admin_plugin_action_links', 'ls_bp_activity_block_link', 10, 2 );



add_action( 'bp_register_admin_settings', 'ls_bp_activity_block_admin_register_settings', 99 );

function ls_bp_activity_block_admin_register_settings() {
    // Add the main section
    add_settings_section( 'ls-bp-stop-record-activity', __( 'BuddyPress Stop Record those Activity Types', 'bp-activity-block' ), 'ls_bp_activity_block_admin_section', 'buddypress' );

    ls_bp_activity_block_callback_activity_types();
}

function ls_bp_activity_block_admin_section( $args ) {
    ?><span class="description" id="<?php echo $args['id']; ?>"><?php
	_e( 'Useful for large communities, in order '
		. 'to reduce the data volume in bp_activity database table.<br/> '
		. 'WARNING: No records of the selected actions will be stored. The email notifications will be still send out, unless another plugin has unset them.', 'bp-activity-block' );
	echo '<br/>';
	_e( 'It is advised NOT to block activity_comment and activity_update activities (will cause errors in buddypress)', 'bp-activity-block' );
	?></span>
        <br/><br/><?php _e( 'Select which activity types should not be recorded:', 'bp-activity-block' ); ?>
    <?php
}

/**
 * @version 1, stergatu, 13/11/2014
 */
function ls_bp_activity_block_callback_activity_types() {
    $bp = buddypress();

    /** stergatu, use array ($dont_mess_these_activities) with activity types which the user shouldn't block */
    $dont_mess_these_activities = array( 'last_activity', 'activity_update', 'activity_comment' );
    // Get the actions
    $activity_actions = buddypress()->activity->actions;
    ?>
	<?php foreach ( $activity_actions as $component => $actions ) { ?>
	    <?php
	    foreach ( $actions as $action_key => $action_values ) {
	    $field_id = 'bp-disable-' . esc_attr( $action_key );
	    // Skip the incorrectly named pre-1.6 action
	    if ( 'friends_register_activity_action' !== $action_key ) {
		if ( ! in_array( esc_attr( $action_key ), $dont_mess_these_activities ) ) {
		    add_settings_field( $field_id, __( ucfirst( $component ), 'buddypress' ) . '- ' . esc_html( $action_values['value'] ), 'ls_bp_activity_block_settings_fields', 'buddypress', 'etivite-bp-activity-block', $field_id );
		    register_setting( 'buddypress', $field_id, 'intval' );
		} else {
		    add_settings_field( $field_id, '<del>' . __( ucfirst( $component ), 'buddypress' ) . ' - ' . esc_html( $action_values['value'] ) . '</del>', 'ls_bp_activity_block_settings_fields_not_available', 'buddypress', 'etivite-bp-activity-block', $field_id );
		}
	    }
	}
    }
}

/**
 * Callable for create settings fields
 * @param string $key
 * @param string $value
 * @version 1, stergatu, 13/11/2014
 */
function ls_bp_activity_block_settings_fields( $key ) {
    ?><input id="<?php echo $key; ?>" name="<?php echo $key; ?>" type="checkbox" value="1"  <?php checked( ls_bp_activity_block_fields_status( $key ) ); ?> />
	<?php
    }

/**
 *
 * @param type $key
 * @version 1, stergatu, 13/11/2014
 */
function ls_bp_activity_block_settings_fields_not_available( $key ) {
    ?>
            <input id="<?php echo $key; ?>" name="<?php echo $key; ?>" type="checkbox" value="0" disabled />
        <!--<label class="description"  for="bp-disable-<?php echo $key; ?>"><?php echo esc_html( $action_values['value'] ); ?></label></del><br/>-->
	<?php
    }

/**
 *
 * @param type $key
 * @param type $default
 * @return type
 * @version 1, stergatu, 13/11/2014
 */
function ls_bp_activity_block_fields_status( $key ) {

    return ( bool ) apply_filters( $key, ( bool ) bp_get_option( $key ) );
}

/**
 * Checks if the activity type should not be recorded
 * @param type $activity
 * @return boolean
 * @version 1, stergatu 14/11/2014
 */
function ls_bp_activity_block_activity_recording( $activity ) {
    /* Is activity update, don't do a check */
    if ( $activity->id ) {
	return $activity;
    }

    $option_name = 'bp-disable-' . esc_attr( $activity->type );
    if ( ls_bp_activity_block_fields_status( $option_name ) ) {
	$activity->type = false;
    }
    return $activity;
}

add_action( 'bp_activity_before_save', 'ls_bp_activity_block_activity_recording' );

/**
 * Remove the blocked types from filtering select element
 * @param array $filters
 * @return type
 * @since version 0.6
 * @author stergatu
 */
function ls_bp_activity_block_remove_types_from_filtering_select( $filters ) {
    if ( is_array( $filters ) ) {
	foreach ( $filters as $key => $filter ) {
	$keys = explode( ',', $key ); //the explode is needed in order to comply with the "hack" for friendships in the  bp_get_activity_show_filters() bp-activity/bp-activity-template.php,
	foreach ( $keys as $newkey ) {
	    $option_name = 'bp-disable-' . esc_attr( $newkey );
	    if ( ls_bp_activity_block_fields_status( $option_name ) ) {
		unset( $filters[$key] );
	    }
	}
    }
    }
    return $filters;
}


add_filter( 'bp_get_activity_show_filters_options', 'ls_bp_activity_block_remove_types_from_filtering_select', 999 );
//for BP versions < 2.2
add_filter( 'bp_get_activity_show_filters', 'ls_bp_activity_block_remove_types_from_filtering_select', 999 );
