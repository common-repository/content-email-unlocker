<?php
if ( ! class_exists( 'Content_Email_Unlocker_Core' ) ) {
	
	class Content_Email_Unlocker_Core { 
		/** Refers to a single instance of this class. */
		private $instance = null;
		private $version = '1.0';
		
		protected $cookie_live = 365;
		
		public $mailpoet = false;
		public $mymail = false;

		public function __construct( $file ) {
			$this->file = $file;
			if( class_exists( 'WYSIJA' ) ) $this->mailpoet = true;
			if( function_exists( 'mymail' ) ) $this->mymail = true;
			
			add_action( 'wp_enqueue_scripts', array( $this, 'load_resources' ) );
			
			add_shortcode( 'emailunlock', array( $this, 'display_after_email' ) );

			add_action( 'wp_ajax_view_content', array( $this, 'view_content' ) );
			add_action( 'wp_ajax_nopriv_view_content', array( $this, 'view_content' ) );
			
			add_action( 'wp', array( $this, 'mymail_confirm_target' ), 10, 2 );
			add_action( 'wp', array( $this, 'wysija_confirm_target' ), 10, 2 );
			
			$this->options = get_option( 'content_email_unlocker' );
		}
		
		public function oval( $name, $value = null ) {
			if( isset( $this->options[ $name ] ) AND !empty( $this->options[ $name ] ) ) {
				return $this->options[ $name ];
			}
			return $value;
		}
		
		public function mymail_confirm_target() {
			global $wp_query;
			if( !$this->mymail ) return false;
			// Get hash form URL
			$hash = isset( $wp_query->query['_mymail_hash'] ) ? $wp_query->query['_mymail_hash'] : false;
			$url = isset( $_COOKIE['ceu_url'] ) ? esc_attr( $_COOKIE['ceu_url'] ) : false;
			// Return if hash or url not exists
			if( !$hash OR !$url ) return;
			$user = mymail('subscribers')->get_by_hash( $hash );
			$this->send_cookie( 'mymail', $user->hash );
			
			if ( $url AND filter_var($url, FILTER_VALIDATE_URL) ) {
				wp_redirect( $url, 301 );
				exit();
			}
		}
		
		public function wysija_confirm_target() {
			if( !$this->mailpoet ) return false;
			$controller = isset( $_GET['controller'] ) ? esc_attr( $_GET['controller'] ) : 0;
			// Get hash form URL
			$hash = isset( $_GET['wysija-key'] ) ? esc_attr( $_GET['wysija-key'] ) : 0;
			$url = isset( $_COOKIE['ceu_url'] ) ? esc_attr( $_COOKIE['ceu_url'] ) : 0;
			if( !$url ) return;
			if( $controller == 'confirm' AND $hash ) {
				$model_user=WYSIJA::get('user','model');
				$user = $model_user->getOne( false, array( 'keyuser'=>$hash ) );
				$this->send_cookie( 'mailpoet', $user['keyuser'] );
				if ( $url AND filter_var($url, FILTER_VALIDATE_URL) ) {
					wp_redirect( $url, 301 );
					exit();
				}
			}
		}

		public function load_resources( $hook ) {
			wp_register_script( 'content-email-unlock', plugins_url('/assets/js/content-email-unlock.js', $this->file), array('jquery') );
			wp_localize_script( 'content-email-unlock', 'ajax_options', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
			wp_enqueue_script( 'content-email-unlock' );
		}

		public function display_after_email( $atts, $content ) {
			global $post;
			$atts = shortcode_atts( array(	'message' => __('Enter your email to display a full content', 'ceutext') ), $atts, 'lock_email' );
			set_transient( '_cue_' . $post->ID, $content, 12 * HOUR_IN_SECONDS );
			
			$validate = $this->validate_cookie_user();
			if( !$validate ) {
				$form = '<div class="cue-wrapper" id="cue-wrapper"><form method="post">
				<div class="description">'.$atts['message'].'</div>
				<label for="ceu_email">E-mail</label>
				<div class="form">
					<input type="email" name="ceu_email" id="ceu_email" class="ceu-email" placeholder="Enter a valid email address" />
					<input type="hidden" name="ceu_postid" value="'.$post->ID.'" />
					<input type="hidden" name="ceu_permalink" value="'.get_permalink().'" />
					<input type="submit" class="ceu-send" value="'. __('Display content now', 'ceu') .'" name="display_content" />
				</div>
				</form></div>'.PHP_EOL;
				return $form;
			}
			// If cookie exists display all content
			if( $validate ) {
				return $content;
			} 
			return false;
		}

		public function set_content_cookie() {
			global $post;
			if( isset( $_POST['ceu_email'] ) AND filter_var( $_POST['ceu_email'], FILTER_VALIDATE_EMAIL ) ) {
				$post_id = (int) $_POST['ceu_postid'];
				$emails = (array) get_option( 'lockemails' );
				$setemails = array_merge( $emails, array( $_POST['ceu_email'] ) );
				update_option( 'lockemails', $setemails, false );
				wp_redirect($post->guid, 301);
				exit();
			}
		}

		public function view_content() {
			
			$mail_system = $this->oval('mail_system', null );
			$this->confirm = $this->oval('mail_confirm', false);
			
			$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
			$email = isset( $_POST['email'] ) ? $_POST['email'] : 0;
			$permalink = isset( $_POST['permalink'] ) ? $_POST['permalink'] : '';

			switch( $mail_system ) {
				case 'wysija': $send = $this->wysija_add_user( $email ); break;
				case 'mymail': $send = $this->mymail_add_user( $email ); break;
				case 'newsletter': $send = $this->wysija_add_user( $email ); break;
				default: $send = false; break;
			}
			
			if( !$send ) {
				echo '<p><strong>'.__('Select newsletter system in admin settings', 'ceutext').'</strong></p>'; wp_die();
			}
			
			if( $this->confirm AND $send['status'] != 1  ) {
				setcookie( "ceu_url", $permalink, time() + 86400 * $this->cookie_live, "/" );
				echo '<p><strong>'.$this->oval('mail_confirm_text', __('You must activate your e-mail to display content', 'ceutext')).'</strong></p>';
			} else {
				$this->send_cookie( $send['system'], $send['hash'] );
				if( $post_id AND $send ) echo get_transient( '_cue_' . $post_id );
			}
			wp_die();
		}
			
		/**
		 * Get cookie and set variables
		 *
		 * return array
		 */
		public function get_cookie() {
			if( !isset( $_COOKIE['_unlocked'] ) OR empty( $_COOKIE['_unlocked'] ) ) return false;
			$cookie = explode( '.', $_COOKIE['_unlocked'] );
			$system = isset( $cookie[0] ) ? $cookie[0] : null;
			$hash = isset( $cookie[1] ) ? $cookie[1] : null;
			if( !$system OR !$hash ) return false;
			return array( 'system'=>$system, 'hash'=>$hash );
		}
		
		public function send_cookie( $system = '1', $hash = '#fsc' ) {
			setcookie( '_unlocked', $system.'.'.$hash, time() + 86400 * $this->cookie_live, "/" );
			return 2;
		}
			
		/**
		 * Validate cookie
		 *
		 * return bool
		 */
		 
		public function validate_cookie_user() {
			// Check is cookie exists
			$cookie = $this->get_cookie();
			if( !$cookie ) return false;
			// Extract values from array
			extract( $cookie );	
			
			switch( $system ) {
				case 'mailpoet':
					if( !$this->mailpoet ) return false;
					$model_user=WYSIJA::get('user','model');
					$user = $model_user->getOne( false, array( 'keyuser'=>$hash ) );
					if( !isset( $user['user_id'] ) ) return false;
					
				break;
				case 'mymail':
					if( !$this->mymail ) return false;
					$user = mymail('subscribers')->get_by_hash( $hash );
					if( !isset( $user->hash ) ) return false;
				break;
				default: return false; break;
			}
			return true;
		}
		
		public function wysija_add_user( $email ) {
			if( !$this->mailpoet ) return false;
			$status = $this->confirm ? 0 : 1;
			
			$model_user = WYSIJA::get( 'user', 'model' );
			$helper = WYSIJA::get( 'user', 'helper' );
			
			// Get user, check if exists
			$user = $model_user->getOne( false, array( 'email'=>$email ) );
			if( !$user ) {
				// Add user
				$model_user->noCheck=true;
				$user_id = $model_user->insert( array( 'email'=>$email, 'wpuser_id'=>0, 'firstname'=>'', 'lastname'=>'', 'status'=>$status ) );
				$helper->addToLists( array( $this->options['wysija_list'] ), $user_id, 0);
				// Get new user
				$user = $model_user->getOne( false, array( 'user_id'=>$user_id ) );
			}
			// Send confirmation email when user exist or not
			if( !$status AND $user['status'] == 0 ) {
				$helper->sendConfirmationEmail( $user['user_id'], true, array( $this->options['wysija_list'] ) );
			}
			return array( 'hash'=> $user['keyuser'], 'system'=>'mymail', 'id'=>$user['user_id'], 'status'=>$user['status'] );
		}
		
		public function mymail_add_user( $email ) {
			if( !$this->mymail ) return false;
			$status = $this->confirm ? 0 : 1;
			// Get user, check is exists
			$user = mymail('subscribers')->get_by_mail($email);
			if( !isset( $user->hash ) ) {
				// Add new user
				$ID = mymail('subscribers')->add( array( 'email'=>$email, 'status'=>$status ) );
				if( $ID ) {
					// Add user to lists
					$add_to_list = mymail('subscribers')->assign_lists($ID, $this->options['mymail_list'] );
					// Get new user
					$user = mymail('subscribers')->get($ID);
				}
			}
			// Send confirmation email when user exists
			if( !isset( $ID ) AND ( !$status AND $user->status == 0 ) ) { 
				mymail('subscribers')->send_confirmations( $user->ID, true, true );
			}
			return array( 'hash'=> $user->hash, 'system'=>'mymail', 'id'=>$user->ID, 'status'=>$user->status );
		}
	}
}
