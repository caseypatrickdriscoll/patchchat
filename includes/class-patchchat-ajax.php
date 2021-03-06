<?php
/**
 * PatchChat AJAX
 *
 * Handles all ajax calls, sanitizes and directs to PatchChat_Controller, returns json
 *
 * There are only two class methods, get and post, reflecting HTTP methods
 *
 * Both function operate off giant switch statements which parse for the available actions.
 *
 * @author caseypatrickdriscoll
 * @created 2015-07-24 23:30:03
 */


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Class PatchChat_AJAX
 */
class PatchChat_AJAX {

	// TODO: Needs to be refactored
	//       - Should just handle ajax events
	//       - Conditions for transient manipulation should be handled elsewhere
	// TODO: Needs more security
	//       - Sanitize early
	//       - Escape late
	//       - Separate nopriv and admin functions?


	public static function init() {
		add_action( 'wp_ajax_change_chat_status',
			array( __CLASS__, 'change_chat_status' ) );

		add_action( 'wp_ajax_patchchat_post',
			array( __CLASS__, 'post' ) );
		add_action( 'wp_ajax_nopriv_patchchat_post',
			array( __CLASS__, 'post' ) );

		add_action( 'wp_ajax_nopriv_patchchat_get',
			array( __CLASS__, 'get' ) );
		add_action( 'wp_ajax_patchchat_get',
			array( __CLASS__, 'get' ) );
	}


	/**
	 * Get json from the server
	 *
	 * Previously, there were many types of things to GET
	 *
	 * Now, the app is built in a way that only needs to 'get' the current state,
	 *   an array of chats the user belongs to
	 *
	 * The controller handles what that is, depending on the user
	 *
	 * TODO: Is a nonce needed here? I'm not sure how it will help
	 *
	 * @author caseypatrickdriscoll
	 * 
	 * @edited 2015-08-03 14:47:59 - Adds logged in validation
	 * @edited 2015-08-03 14:52:01 - Adds current_user validation
	 * @edited 2015-08-20 15:34:17 - Refactors to clean up AJAX get
	 *
	 */
	public static function get() {

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$current_user = wp_get_current_user();

		if ( $current_user->ID == 0 ) {
			wp_send_json_error( 'Not a user' );
		}

		switch ( $_POST['method'] ) {
			case 'get_user_state' : // Return current chats for current user
				$chats = PatchChat_Controller::get_user_state();
				break;

			default:
				$chats = array( 'error' => 'No method with name ' . $_POST['method'] );
		}

		if ( isset( $chats['error'] ) )
			wp_send_json_error( $chats );
		else
			wp_send_json_success( $chats );

	}


	/**
	 * Sanitize the POST request and send to the correct controller method
	 *
	 * Return the user's chat state if successful
	 * Return an error if unsuccessful
	 *
	 * @author caseypatrickdriscoll
	 *
	 * @edited 2015-08-21 11:21:08 - Refactors to move validation to AJAX post, removes submit function
	 * @edited 2015-08-22 09:51:49 - Adds method validation
	 * @edited 2015-08-22 10:04:34 - Adds data sanitization
	 * @edited 2015-08-22 10:12:13 - Adds catching the honeypot
	 * @edited 2015-08-22 10:26:27 - Refactors preliminary validation
	 * @edited 2015-08-22 10:28:56 - Refactors to check for email_exists
	 * @edited 2015-08-22 10:31:56 - Refactors create validations
	 * @edited 2015-08-29 16:40:38 - Refactors to use PatchChat_Controller::update()
	 * 
	 */
	public static function post() {

		// TODO: Create test for each error case
		// TODO: Send email reminder if email already exists???
		// TODO: Handle username duplicates (iterate or validate?)
		// TODO: Allow title length to be set as option (currently hard coded to 40 char)

		$error = false;

		// Validate POST
		if ( empty( $_POST['method'] ) || ! empty( $_POST['honey'] ) ) {
			wp_send_json_error( 0 ); // No need to give info on this
		} 

		if ( $_POST['method'] == 'create' ) {

			if ( empty( $_POST['name'] ) ) {
				$error = __( 'Name is empty', 'patchchat' );
			} elseif ( empty( $_POST['email'] ) ) {
				$error = __( 'Email is empty', 'patchchat' );
			} elseif ( ! is_email( $_POST['email'] ) ) {
				$error = __( 'Email is not valid', 'patchchat' );
			} 

		}

		if ( empty( $_POST['text'] ) ) {
			$error = __( 'Text is empty', 'patchchat' );
		}

		if ( $error ) {
			wp_send_json_error( $error );
		}

		// Sanitize request
		if ( $_POST['method'] == 'create' ) {

			$chat = array(
				'name'   => sanitize_user( $_POST['name'] ),
				'email'  => sanitize_email( $_POST['email'] ),
				'text'   => sanitize_text_field( $_POST['text'] ),
				'method' => 'create',
			);

			if ( email_exists( $chat['email'] ) ) {
				wp_send_json_error(  __( 'Email exists', 'patchchat' ) );
			}

		} elseif ( $_POST['method'] == 'update' ) {

			$chat = array(
				'chat_id' => intval( $_POST['chat_id'] ),
				'text'    => sanitize_text_field( $_POST['text'] ),
				'method'  => 'update',
			);

		} else {
			wp_send_json_error( 0 );
		}

		// Switch based on request
		switch ( $chat['method'] ) {
			case 'create' : // Create a chat
				$chats = PatchChat_Controller::create( $chat );
				break;

			case 'update' : // Update a chat by adding a comment

				if ( is_user_logged_in() ) {
					$chats = PatchChat_Controller::update( $chat );
				} else {
					$chats = array( 'error' => 'User is not logged in' );
				}
				break;

			default:
				$chats = array( 'error' => 'No method with name ' . $chat['method'] );
		}


		if ( isset( $chats['error'] ) )
			wp_send_json_error( $chats );
		else
			wp_send_json_success( $chats );
	}


	/**
	 * The POST handler for status changes from the admin table
	 *
	 * @author caseypatrickdriscoll
	 *
	 * @created 2015-07-18 17:49:02
	 * @edited  2015-07-24 19:56:52 - Refactors to use move function
	 * @edited  2015-08-28 18:12:26 - Adds user validation on change_chat_status
	 *
	 * TODO: Validate and sanitize fields
	 *
	 */
	public static function change_chat_status() {

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in' );
		}

		$chat = array(
			'ID'          => $_POST['chat_id'],
			'prev_status' => $_POST['prev_status'],
			'post_status' => $_POST['status'],
		);

		$response = PatchChat_Controller::change_chat_status( $chat );

		if ( $response ) {
			wp_send_json_success( $response );
		} else {
			wp_send_json_error( 0 );
		}

	}


}