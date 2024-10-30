<?php
class Content_Email_Unlocker_Settings
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
		
        $this->active_plugins = get_option( 'active_plugins' );
		$this->supported_plugins = array(
			'mymail' => 'myMail/myMail.php',
			'newsletter' => 'newsletter/plugin.php',
			'wysija' => 'wysija-newsletters/index.php'
		);
		$this->plugins_url = plugins_url();
		
		$this->options = get_option( 'content_email_unlocker' );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            __('Content Email Unlocker', 'ceutext'),
            __('Content Email Unlocker', 'ceutext'),
            'manage_options', 
            'ceu-settings', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        ?>
        <div class="wrap">
            <h2><?php  _e('Content Email Unlocker', 'ceutext'); ?></h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'my_option_group' );   
                do_settings_sections( 'my-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'my_option_group', // Option group
            'content_email_unlocker', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            __('General settings', 'ceutext'), // Title
            array( $this, 'print_section_info' ), // Callback
            'my-setting-admin' // Page
        );    

        add_settings_field(
            'mail_system',
            'Newsletter system', 
            array( $this, 'newsletter_system_callback' ), 
            'my-setting-admin', 
            'setting_section_id'
        );
		
		add_settings_field(
            'mail_confirm', 
            __('Mail Confirm', 'ceutext'),
            array( $this, 'mail_confirm_callback' ), 
            'my-setting-admin', 
            'setting_section_id',
			array( 'description' => 'Check this option if you want to send subsription confirm e-mail' )
        );
		
		add_settings_field(
            'mail_confirm_text', 
            __('Mail Confirm Text', 'ceutext'),
            array( $this, 'mail_confirm_text_callback' ), 
            'my-setting-admin', 
            'setting_section_id'
        );
    
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();

        if( isset( $input['mail_system'] ) )
            $new_input['mail_system'] = sanitize_key( $input['mail_system'] );
        
		if( isset( $input['mymail_list'] ) )
            $new_input['mymail_list'] = absint( $input['mymail_list'] ); 
		
		if( isset( $input['wysija_list'] ) )
            $new_input['wysija_list'] = absint( $input['wysija_list'] );
		
		if( isset( $input['mail_confirm'] ) )
            $new_input['mail_confirm'] = absint( $input['mail_confirm'] );
        
		if( isset( $input['mail_confirm_text'] ) )
            $new_input['mail_confirm_text'] = esc_attr( $input['mail_confirm_text'] );     

        return $new_input;
    }
	
	protected function oval( $index, $value = '' ) {
		if( !isset( $this->options[ $index ] ) ) {
			return $value;
		}
		return $this->options[ $index ];
	}

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print '';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function newsletter_system_callback()
    {
		$output = '';
		$is_system = false;
		foreach(   $this->supported_plugins as $slug=>$name ) {
			
			if( in_array( $name, $this->active_plugins ) ) {
				$is_system = true;
				$output .= "<p>";
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $name );
				$plugin_name = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '';
				$output .= '<input id="'.sanitize_text_field( $plugin_name ).'" '.checked( $this->oval('mail_system'), $slug, false).' type="radio" id="mail_system" name="content_email_unlocker[mail_system]" value="'.$slug.'" />';
				$output .= '<label for="'.sanitize_text_field( $plugin_name ).'">'.$plugin_name.'</label>';
				if( $slug == 'wysija' ) {
					$model_lists = WYSIJA::get( 'list', 'model' );
					$lists = $model_lists->getLists();
					
					if( $lists ) {
						$output .= '<div><label for="wysija_list">Email list</label> <select id="wysija_list" name="content_email_unlocker[wysija_list]">';
						foreach( $lists as $form ) {
							$output .= '<option '.selected( $this->oval('wysija_list'), $form['list_id'], false).' value="'.$form['list_id'].'">'.$form['name'].'</option>';
						}
						$output .= '</select></div>';
					}
				} else if( $slug == 'mymail' ) {
					global $mymail;
					$mymail_forms = $mymail->lists()->get();
					if( $mymail_forms ) {
						$output .= '<div><label for="mymail_list">Email list</label> <select id="mymail_list" name="content_email_unlocker[mymail_list]">';
						foreach( $mymail_forms as $myform ) {
							$output .= '<option '.selected( $this->oval('mymail_list'), $myform->ID, false).' value="'.$myform->ID.'">'.$myform->name.'</option>';
						}
						$output .= '</select></div>';
					}
					
				} else {
					
					
				}
				$output .= "</p>";
			}
		}
		if( !$is_system ) {
			$output = '<p>'.__('Plugin requires one of these plugins: MyMail, MailPoet (Wysija)', 'ceutext').'</p>';
		}
		echo $output;
    }  
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function mail_confirm_callback( $args = '' )
    {
        echo '<input '.checked( $this->oval('mail_confirm'), 1, false).' type="checkbox" id="mail_confirm" name="content_email_unlocker[mail_confirm]" value="1" />';
		if( isset( $args['description'] ) )
			echo '<p class="description">'. $args['description'] .'</p>';
    }
	
	/** 
     * Get the settings option array and print one of its values
     */
    public function mail_confirm_text_callback()
    {
        printf(
            '<input type="text" id="mail_confirm_text" name="content_email_unlocker[mail_confirm_text]" value="%s" />',
            $this->oval('mail_confirm_text')
        );
    }

}