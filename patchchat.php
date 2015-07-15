<?php
/*
Plugin Name: PatchChat
Version: 0.1-alpha
Description: A high quality chat plugin made just for WordPress
Author: PatchWorks
Author URI: https://patch.works/
Plugin URI: https://patch.works/plugin/patchchat
Text Domain: patchworks
Domain Path: /languages
*/

include 'patchchatadmin.php';

class PatchChat {

	function __construct() {

		if ( is_admin() ) {
		
			add_action( 'admin_menu', 'PatchChatAdmin::register_menu' );
		
		} else {


		}



	}

}

new PatchChat();