/** Structure
 *
 * - #PatchChatMessenger
 *   - #PatchChatList
 *     - #PatchChatInit
 *     - .PatchChatListItem
 *   - #PatchChatBoxes
 *     - #PatchChatInitBox
 *     - .PatchChatBox
 *       - .PatchChatHeader
 *       - .PatchChatComments
 *       - .PatchChatForm
 *
 */

var PatchChatMessenger = React.createClass( {

	timeOutID : null,

	// TODO: Don't play on init or page reload
	playReceiveSound: function( newChats ) {

		if ( this.state.chats.length < newChats.length ) {
			new Audio( patchchat.getsound ).play();
			return;
		}

		if ( this.state.chats.length == newChats.length ) {

			for ( var i = 0; i < this.state.chats.length; i++ ) {

				if ( this.state.chats[i].comments.length < newChats[i].comments.length ) {
					new Audio( patchchat.getsound ).play();
					return;
				}

			}

		}

	},

	loadCommentsFromServer: function() {

		var ajaxdata = {
			'action'  : 'patchchat_get',
			'method'  : 'get_user_state'
		};

		if ( patchchat.debug == 'true' ) console.log( 'before ' + ajaxdata.method, ajaxdata );

		jQuery.ajax({
			method  : 'POST',
			url     : patchchat.ajaxurl,
			data    : ajaxdata,
			success : function ( response ) {

				if ( patchchat.debug == 'true' ) console.log( 'response get_user_chats: ', response );

				if ( response.success ) {

					this.playReceiveSound( response.data );

					this.setState( { chats : response.data } );

					clearTimeout( this.timeOutID );
					this.timeOutID = setTimeout( this.loadCommentsFromServer, this.props.pulse );

				} else {
					if ( patchchat.debug == 'true' ) console.log( 'error response get_user_chats: ', response );
				}

			}.bind(this),
			error   : function ( response ) {
				if ( patchchat.debug == 'true' ) console.error( 'error response get_user_chats: ', response );
			}.bind(this)
		});
	},

	submit: function(chat) {

		patchchat.spinner.show();

		chat.action = 'patchchat_post';

		if ( patchchat.debug == 'true' ) console.log( 'before ' + chat.method, chat );

		jQuery.ajax({
			method  : 'POST',
			url     : patchchat.ajaxurl,
			data    : chat,
			success : function ( response ) {

				if ( patchchat.debug == 'true' ) console.log( 'response create/update: ', response );

				patchchat.spinner.hide();

				this.setState( { chats : response.data } );

				clearTimeout( this.timeOutID );
				this.timeOutID = setTimeout( this.loadCommentsFromServer, this.props.pulse );

				var audio = new Audio( patchchat.postsound ).play();

			}.bind( this ),
			error   : function ( response ) {
				if ( patchchat.debug == 'true' ) console.error( 'error response create/update: ', response );
			}.bind( this )
		});
	},
	getInitialState: function() {
		return { chats: new Array(0) }
	},
	componentDidMount: function() {
		patchchat.spinner = jQuery( '.spinner' );
		this.loadCommentsFromServer();
	},
	render: function() {
		return (
			<div id="patchchatmessenger">
				<PatchChatList  chats={this.state.chats} />
				<PatchChatBoxes chats={this.state.chats} submit={this.submit} />
			</div>
		);
	}
} );