<?php
/*
Plugin Name: Ask It
Plugin URI: 
Description: Ask It enables wordpress users to ask administrators questions and have them answered via the dashboard. Email & TXT alerts available.
Version: 1.2.1
Author: Evan Weible
Author URI: http://evanweible.com
License: GPL2
*/


/*  Copyright 2011  Evan Weible  (email : ekweible@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('ASKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ));

// Make sure we don't expose any info if called directly (Taken from Akismet plugin)
if ( !function_exists( 'add_action' ) ) {
	echo "Hi there! I'm just a plugin, not much I can do when called directly.";
	exit;
}

// Internationalization
load_plugin_textdomain('askit', false, basename( dirname( __FILE__ ) ) . '/languages' );

// Initialize Ask It plugin
add_action( 'init', 'askit_init' );

// Ask It initialization function
function askit_init() {
	if ( ! isset( $askit ) )
		$askit = new Askit();	
}

// Ask It class
class Askit {
	
	function __construct() {
		$this->init();
		$this->get_settings();
		$this->update_settings();
		$this->register_custom_post_type();
		$this->setup_settings_page();
		$this->setup_dashboard_widgets();
		$this->setup_notifications();
		$this->setup_additional_pages();
		$this->register_scripts();
		$this->register_styles();
	}
	
	private function reset_settings() {
		update_option( 'askit_settings', $this->defaults );
	}
	
	/**
	 * Initialize default settings, and the carriers vars.
	 *
	 * @since 1.0
	 */
	public function init() {
		$this->defaults = array(
			'capability_to_see_questions' => '',
			'capability_to_ask_questions' => '',
			'capability_to_edit_settings' => 'manage_options',
			'capability_to_answer_questions' => 'manage_options',
			'custom_post_singular' => 'Question',
			'custom_post_plural' => 'Questions',
			'answered_questions_dashboard_widget_title' => 'Answered Questions',
			'my_questions_dashboard_widget_title' => 'My Questions',
			'answered_questions_limit' => -1,
			'question_form_dashboard_widget_title' => 'Question? Ask it here!',
			'my_questions_limit' => -1,
			'offset' => get_option('gmt_offset'),
			'notification_question_asked_email' => true,
			'notification_question_asked_text' => false,
			'notification_question_answered_email' => true,
			'question_asked_email_to' => get_option( 'admin_email' ),
			'question_asked_email_subject' => '[AskIt] Question Asked!',
			'question_asked_text_number' => '',
			'question_asked_text_carrier' => '',
			'question_asked_text_subject' => '[AskIt Question]',
			'question_answered_email_subject' => '[AskIt] Question Answered!',
			'question_answered_email_from' => 'AskItQuestions',
			'remove_dashboard_answered_questions' => false,
			'remove_dashboard_my_questions' => false,
			'remove_dashboard_question_form' => false,
			'dashboard_cleanup' => array(
				'dashboard_right_now' => array(
					'remove' => false,
					'name' => 'Right Now',
				),
				'dashboard_recent_comments' => array(
					'remove' => false,
					'name' => 'Recent Comments',
				),
				'dashboard_incoming_links' => array(
					'remove' => false,
					'name' => 'Incoming Links',
				),
				'dashboard_plugins' => array(
					'remove' => false,
					'name' => 'Plugins',
				),
				'dashboard_quick_press' => array(
					'remove' => false,
					'name' => 'Quick Press',
				),
				'dashboard_recent_drafts' => array(
					'remove' => false,
					'name' => 'Recent Drafts',
				),
				'dashboard_primary' => array(
					'remove' => false,
					'name' => 'WordPress Blog',
				),
				'dashboard_secondary' => array(
					'remove' => false,
					'name' => 'Other WordPress News',
				)
			)
		);
		
		// Used in sending text notifications
		$this->carriers = array(
			'verizon' => 'Verizon',
			'uscellular' => 'US Cellular',
			'sprint' => 'Sprint',
			'att' => 'AT&T',
			'cingular' => 'Cingular',
			'qwest' => 'Qwest',
			'nextel' => 'Nextel',
			'tmobile' => 'T-Mobile',
			'vmobile' => 'Virgin Mobile'
		);
		$this->carrier_domains = array(
			'verizon' => 'vtext.com',
			'cingular' => 'cingularme.com',
			'sprint' => 'messaging.sprintpcs.com',
			'uscellular' => 'email.uscc.net',
			'att' => 'txt.att.net',
			'qwest' => 'qwestmp.com',
			'nextel' => 'messaging.nextel.com',
			'tmobile' => 'tmomail.net',
			'vmobile' => 'vmobl.com'
		);
	}
	
	/**
	 * Retrieves settings from WordPress option,
	 * uses defaults if this option has not been set yet.
	 *
	 * @since 1.0
	 */
	public function get_settings() {
		$this->settings = get_option( 'askit_settings', $this->defaults );
	}
	
	/**
	 * Runs if the settings form has been submitted.
	 *
	 * @since 1.0
	 */
	public function update_settings() {
		// Flag used to display the 'updated' notification
		$this->settings_updated = false;
		
		if ( isset( $_POST['askit_settings'] ) ) :
			// Cycle through the POST vars and use esc_attr() to validate them
			foreach ( $_POST as $key => $val ) {
				$_POST[$key] = esc_attr( $val );
			}
		
			// We can't let the current user set the capability required to edit settings
			// to a capability that he or she doesn't possess.. that would just be silly
			$capability_to_edit_settings = ( current_user_can( $_POST['capability-to-edit-settings'] ) ) ? $_POST['capability-to-edit-settings'] : $this->settings['capability_to_edit_settings'];
			
			// Same deal with the capability required to ask, answer & see questions
			$capability_to_see_questions = ( '' === trim( $_POST['capability-to-see-questions'] ) || current_user_can( $_POST['capability-to-see-questions'] ) ) ? $_POST['capability-to-see-questions'] : $this->settings['capability_to_see_questions'];
			$capability_to_ask_questions = ( '' === trim( $_POST['capability-to-ask-questions'] ) || current_user_can( $_POST['capability-to-ask-questions'] ) ) ? $_POST['capability-to-ask-questions'] : $this->settings['capability_to_ask_questions'];
			$capability_to_answer_questions = ( current_user_can( $_POST['capability-to-answer-questions'] ) ) ? $_POST['capability-to-answer-questions'] : $this->settings['capability_to_answer_questions'];
			
			// These following settings cannot be blank, if they are, we stay with the current setting
			$custom_post_singular = ( "" != $_POST['custom-post-singular'] ) ? $_POST['custom-post-singular'] : $this->settings['custom_post_singular'];
			$custom_post_plural = ( "" != $_POST['custom-post-plural'] ) ? $_POST['custom-post-plural'] : $this->settings['custom_post_plural'];
			$answered_questions_dashboard_widget_title = ( "" != $_POST['answered-questions-dashboard-widget-title'] ) ? $_POST['answered-questions-dashboard-widget-title'] : $this->settings['answered_questions_dashboard_widget_title'];
			$my_questions_dashboard_widget_title = ( "" != $_POST['my-questions-dashboard-widget-title'] ) ? $_POST['my-questions-dashboard-widget-title'] : $this->settings['my_questions_dashboard_widget_title'];
			$question_form_dashboard_widget_title = ( "" != $_POST['question-form-dashboard-widget-title'] ) ? $_POST['question-form-dashboard-widget-title'] : $this->settings['question_form_dashboard_widget_title'];
			
			// My Questions limit must be an integer	
			$answered_questions_limit = round( (int) $_POST['answered-questions-limit'] );		
			$my_questions_limit = round( (int) $_POST['my-questions-limit'] );
			
			$settings = array(
				'capability_to_see_questions' => $capability_to_see_questions,
				'capability_to_ask_questions' => $capability_to_ask_questions,
				'capability_to_edit_settings' => $capability_to_edit_settings,
				'capability_to_answer_questions' => $capability_to_answer_questions,
				'custom_post_singular' => $custom_post_singular,
				'custom_post_plural' => $custom_post_plural,
				'answered_questions_dashboard_widget_title' => $answered_questions_dashboard_widget_title,
				'answered_questions_limit' => $answered_questions_limit,
				'my_questions_dashboard_widget_title' => $my_questions_dashboard_widget_title,
				'question_form_dashboard_widget_title' => $question_form_dashboard_widget_title,
				'my_questions_limit' => $my_questions_limit,
				'offset' => get_option('gmt_offset'),
				'notification_question_asked_email' => isset( $_POST['notification-question-asked-email'] ),
				'notification_question_asked_text' => isset( $_POST['notification-question-asked-text'] ),
				'notification_question_answered_email' => isset( $_POST['notification-question-answered-email'] ),
				'question_asked_email_to' => $_POST['question-asked-email-to'],
				'question_asked_email_subject' => $_POST['question-asked-email-subject'],
				'question_asked_text_number' => $_POST['question-asked-text-number'],
				'question_asked_text_carrier' => $_POST['question-asked-text-carrier'],
				'question_asked_text_subject' => $_POST['question-asked-text-subject'],
				'question_answered_email_subject' => $_POST['question-answered-email-subject'],
				'question_answered_email_from' => $_POST['question-answered-email-from'],
				'remove_dashboard_answered_questions' => isset( $_POST['remove_dashboard_answered_questions'] ),
				'remove_dashboard_my_questions' => isset( $_POST['remove_dashboard_my_questions'] ),
				'remove_dashboard_question_form' => isset( $_POST['remove_dashboard_question_form'] ),
				'dashboard_cleanup' => array(
					'dashboard_right_now' => array(
						'remove' => isset( $_POST['dashboard_right_now'] ),
						'name' => 'Right Now',
					),
					'dashboard_recent_comments' => array(
						'remove' => isset( $_POST['dashboard_recent_comments'] ),
						'name' => 'Recent Comments',
					),
					'dashboard_incoming_links' => array(
						'remove' => isset( $_POST['dashboard_incoming_links'] ),
						'name' => 'Incoming Links',
					),
					'dashboard_plugins' => array(
						'remove' => isset( $_POST['dashboard_plugins'] ),
						'name' => 'Plugins',
					),
					'dashboard_quick_press' => array(
						'remove' => isset( $_POST['dashboard_quick_press'] ),
						'name' => 'Quick Press',
					),
					'dashboard_recent_drafts' => array(
						'remove' => isset( $_POST['dashboard_recent_drafts'] ),
						'name' => 'Recent Drafts',
					),
					'dashboard_primary' => array(
						'remove' => isset( $_POST['dashboard_primary'] ),
						'name' => 'WordPress Blog',
					),
					'dashboard_secondary' => array(
						'remove' => isset( $_POST['dashboard_secondary'] ),
						'name' => 'Other WordPress News',
					)
				)
			);
			
			$this->settings_updated = add_option( 'askit_settings', $settings ) or update_option( 'askit_settings', $settings );
			$this->get_settings();
		endif;
	}
	
	/**
	 * Hooks into `admin_menu` to add Ask It settings page.
	 *
	 * @since 1.0
	 */
	public function setup_settings_page() {
		add_action( 'admin_menu', array( &$this, 'add_settings_page' ) );
	}
	
	/**
	 * Adds Ask It settings page to menu.
	 *
	 * @since 1.0
	 */
	public function add_settings_page() {
		add_submenu_page( 'options-general.php', 'Ask It Settings', 'Ask It', $this->settings['capability_to_edit_settings'], 'ask-it-settings', array( &$this, 'settings_page' ) );
	}
	
	/**
	 * Displays Ask It settings page.
	 *
	 * @since 1.0
	 */
	public function settings_page() {
		?>
        <div class="wrap askit">
            <div id="icon-options-general" class="icon32"><br /></div>
            <h2>Ask It Settings</h2>
			<?php
			if ( $this->settings_updated || isset( $_GET['settings-updated'] ) ) :
				$this->get_settings();
				?>
				<div class="updated"><p>Ask It settings have been updated.</p></div>
				<?php
			endif;
            ?>
        	<br />
            
            <div id="askit-settings-menu">
            	<a href="#askit-general" class="askit-nav current">General</a>
                <span>|</span>
                <a href="#askit-capabilities" class="askit-nav">Capability Requirements</a>
                <span>|</span>
                <a href="#askit-dashboard" class="askit-nav">Dashboard Customization</a>
                <span>|</span>
                <a href="#askit-notifications" class="askit-nav">Notifications (Email & Text)</a>
            </div>
            
            <form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=ask-it-settings&settings-updated' ) ); ?>">
            
                <?php wp_nonce_field( 'askit_settings' ); ?>
                <input type="hidden" name="askit_settings" value="true" />
                
                <div id="askit-general" class="askit-settings-pane current">
                
                    <h3>General</h3>
                    <table class="form-table">
                        <tbody>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="custom-post-singular">Custom Post Type Singular</label>
                                </th>
                                <td>
                                    <input type="text" id="custom-post-singular" name="custom-post-singular" class="regular-text" value="<?php $this->s( 'custom_post_singular' ); ?>" />
                                    <span class="description">This will change the term you see in the admin menu and when editing the questions (default: 'Question').</span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="custom-post-plural">Custom Post Type Plural</label>
                                </th>
                                <td>
                                    <input type="text" id="custom-post-plural" name="custom-post-plural" class="regular-text" value="<?php $this->s( 'custom_post_plural' ); ?>" />
                                    <span class="description">Same effect as above, just the plural form (default: 'Questions').</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                </div>
                
                <div id="askit-capabilities" class="askit-settings-pane">
                    
                    <h3>Capability Requirements</h3>
                    <table class="form-table">
                        <tbody>
                        	<tr valign="top">
                            	<th scope="row">
                                	<label for="capability-to-see-questions">To See Answered Questions</label>
                                </th>
                                <td>
                                	<input type="text" id="capability-to-see-questions" name="capability-to-see-questions" class="regular-text" value="<?php $this->s( 'capability_to_see_questions' ); ?>" />
                                </td>
                            </tr>
                        	<tr valign="top">
                            	<th scope="row">
                                	<label for="capability-to-ask-questions">To Ask Questions</label>
                                </th>
                                <td>
                                	<input type="text" id="capability-to-ask-questions" name="capability-to-ask-questions" class="regular-text" value="<?php $this->s( 'capability_to_ask_questions' ); ?>" />
                                    <span class="description">This will be in addition to the required capabilities of `edit_posts` and `publish_posts`.</span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="capability-to-answer-questions">To Answer Questions</label>
                                </th>
                                <td>
                                    <input type="text" id="capability-to-answer-questions" name="capability-to-answer-questions" class="regular-text" value="<?php $this->s( 'capability_to_answer_questions' ); ?>" />
                                    <span class="description">If the current user does not have this capability, the '<?php $this->s( 'custom_post_plural' ); ?>' menu tab will be hidden (default: 'manage_options').</span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="capability-to-edit-settings">To Edit Ask It Settings</label>
                                </th>
                                <td>
                                    <input type="text" id="capability-to-edit-settings" name="capability-to-edit-settings" class="regular-text" value="<?php $this->s( 'capability_to_edit_settings' ); ?>" />
                                    <span class="description">(default: 'manage_options')</span>
                                </td>
                            </tr>
                        </tbody>               
                    </table>
                    
                </div>
                
                <div id="askit-dashboard" class="askit-settings-pane">
                    
                    <h3>Dashboard Customization</h3>
                    <h4>Answered Questions Dashboard Widget</h4>
                    <span class="description">This is where all questions that are made 'public' will be displayed to every user.</span>
                    <table class="form-table">
                    	<tbody>
                        	<tr valign="top">
                            	<th scope="row">
                                	<label for="answered-questions-dashboard-widget-title">Title</label>
                                </th>
                                <td>
                                	<input type="text" id="answered-questions-dashboard-widget-title" name="answered-questions-dashboard-widget-title" class="regular-text" value="<?php $this->s( 'answered_questions_dashboard_widget_title' ); ?>" />
                                </td>
                            </tr>
                            <tr valign="top">
                            	<th scope="row">
                                	<label for="answered-questions-limit"># of Questions to Display</label>
                                </th>
                                <td>
                                	<input type="text" id="answered-questions-limit" name="answered-questions-limit" class="regular-text" value="<?php $this->s( 'answered_questions_limit' ); ?>" />
                                    <span class="description">Use -1 to display all questions (default: -1).</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>My Questions Dashboard Widget</h4>
                    <span class="description">Only questions asked by the logged in user will be displayed in this widget.</span>
                    <table class="form-table">
                        <tbody>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="my-questions-dashboard-widget-title">Title</label>
                                </th>
                                <td>
                                    <input type="text" id="my-questions-dashboard-widget-title" name="my-questions-dashboard-widget-title" class="regular-text" value="<?php $this->s( 'my_questions_dashboard_widget_title' ); ?>" />
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <label for=""># of Questions to Display</label>
                                </th>
                                <td>
                                    <input type="text" id="my-questions-limit" name="my-questions-limit" class="regular-text" value="<?php $this->s( 'my_questions_limit' ); ?>" />
                                    <span class="description">Use -1 to display all questions (default: -1).</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Question Form Dashboard Widget</h4>
                    <table class="form-table">
                        <tbody>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="question-form-dashboard-widget-title">Title</label>
                                </th>
                                <td>
                                    <input type="text" id="question-form-dashboard-widget-title" name="question-form-dashboard-widget-title" class="regular-text" value="<?php $this->s( 'question_form_dashboard_widget_title' ); ?>" />
                                </td>
                            </tr>                    
                        </tbody>
                    </table>
                    
                    <h4>Clean Up the Dashboard</h4>
                    <table class="form-table">
                        <tbody>
                        	<tr valign="top">
                            	<th scope="row">
                               		<?php $this->s( 'answered_questions_dashboard_widget_title' ); ?>
                                </th>
                                <td>
                                	<label for="remove_dashboard_answered_questions"><input type="checkbox" id="remove_dashboard_answered_questions" name="remove_dashboard_answered_questions"<?php $this->s( 'remove_dashboard_answered_questions' ); ?> /> Remove</label>
                                </td>
                             </tr>
                             <tr valign="top">
                             	<th scope="row">
                                	<?php $this->s( 'my_questions_dashboard_widget_title' ); ?>
                                </th>
                              	<td>
                                	<label for="remove_dashboard_my_questions"><input type="checkbox" id="remove_dashboard_my_questions" name="remove_dashboard_my_questions"<?php $this->s( 'remove_dashboard_my_questions' ); ?> /> Remove</label>
                                </td>
                            </tr>
                            <tr valign="top">
                            	<th scope="row">
                                	<?php $this->s( 'question_form_dashboard_widget_title' ); ?>
                                </th>
                               	<td>
                                	<label for="remove_dashboard_question_form"><input type="checkbox" id="remove_dashboard_question_form" name="remove_dashboard_question_form"<?php $this->s( 'remove_dashboard_question_form' ); ?> /> Remove</label>
                                </td>
                            </tr>
                            <?php $this->display_dashboard_meta_box_options(); ?>                    
                        </tbody>
                    </table>
                    
                </div>
                
                <div id="askit-notifications" class="askit-settings-pane">
                    
                    <h3>Notifications (Email & Text)</h3>
                    <table class="form-table">
                        <tbody>
                        	<tr valign="top">
                            	<th scope="row">
                                	When a Question is Asked
                                </th>
                                <td>
                               		<label for="notification-question-asked-email"><input type="checkbox" id="notification-question-asked-email" name="notification-question-asked-email"<?php $this->s( 'notification_question_asked_email' ); ?> /> Send Email Notification</label><br />
                                    <label for="notification-question-asked-text"><input type="checkbox" id="notification-question-asked-text" name="notification-question-asked-text"<?php $this->s( 'notification_question_asked_text' ); ?> /> Send Text Notification</label>
                                </td>
                            </tr>
                            <tr valign="top">
                            	<th scope="row">
                                	When a Question is Answered
                                </th>
                                <td>
                                	<label for="notification-question-answered-email"><input type="checkbox" id="notification-question-answered-email" name="notification-question-answered-email"<?php $this->s( 'notification_question_answered_email' ); ?> /> Send Email Notification to Asker</label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div id="askit-question-asked-email-notification"<?php if( ! $this->settings['notification_question_asked_email'] ) : ?> class="askit-hidden"<?php endif; ?>>
                        <h4>Email Notification When a Question is Asked</h4>
                        <table class="form-table">
                        	<tbody>
                            	<tr valign="top">
                                	<th scope="row">
                                    	<label for="question-asked-email-to">Send Email to</label>
                                    </th>
                                    <td>
                                    	<input type="text" id="question-asked-email-to" name="question-asked-email-to" class="regular-text" value="<?php $this->s( 'question_asked_email_to' ); ?>" />
                                        <span class="description">Separate multiple emails with a comma (,)</span>
                                    </td>
                                </tr>
                            	<tr valign="top">
                                	<th scope="row">
                                    	<label for="question-asked-email-subject">Email Subject</label>
                                    </th>
                                    <td>
                                    	<input type="text" id="question-asked-email-subject" name="question-asked-email-subject" class="regular-text" value="<?php $this->s( 'question_asked_email_subject' ); ?>" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="askit-question-asked-text-notification"<?php if( ! $this->settings['notification_question_asked_text'] ) : ?> class="askit-hidden"<?php endif; ?>>
                    	<h4>Text Notification When a Question is Asked</h4>
                        <table class="form-table">
                        	<tbody>
                            	<tr valign="top">
                                	<th scope="row">
                                    	<label for="question-asked-text-number">Mobile Number</label>
                                    </th>
                                    <td>
                                    	<input type="text" id="question-asked-text-number" name="question-asked-text-number" class="regular-text" maxlength="10" value="<?php $this->s( 'question_asked_text_number' ); ?>" />
                                        <span class="description">Ex: 5551234567</span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                	<th scope="row">
                                    	<label for="question-asked-text-carrier">Wireless Carrier</label>
                                    </th>
                                    <td>
                                    	<select id="question-asked-text-carrier" name="question-asked-text-carrier">
                                        	<option value="">Choose one...</option>
                                        	<?php $this->print_carrier_list( $this->settings['question_asked_text_carrier'] ); ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr valign="top">
                                	<th scope="row">
                                    	<label for="question-asked-text-subject">Subject</label>
                                    </th>
                                    <td>
                                    	<input type="text" id="question-asked-text-subject" name="question-asked-text-subject" class="regular-text" value="<?php $this->s( 'question_asked_text_subject' ); ?>" />
                                    </td>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="askit-question-answered-email-notification"<?php if( ! $this->settings['notification_question_answered_email'] ) : ?> class="askit-hidden"<?php endif; ?>>
                    	<h4>Email Notification When a Question is Answered</h4>
                        <table class="form-table">
                        	<tbody>
                            	<tr valign="top">
                                	<th scope="row">
                                    	<label for="question-answered-email-subject">Email Subject</label>
                                    </th>
                                    <td>
                                    	<input type="text" id="question-answered-email-subject" name="question-answered-email-subject" class="regular-text" value="<?php $this->s( 'question_answered_email_subject' ); ?>" />
                                    </td>
                                </tr>
                                <tr valign="top">
                                	<th scope="row">
                                    	<label for="question-answered-email-from">From</label>
                                    </th>
                                    <td>
                                    	<input type="text" id="question-answered-email-from" name="question-answered-email-from" class="regular-text" value="<?php $this->s( 'question_answered_email_from' ); ?>" />
                                        <span class="description">When the email notification is received by the asker, this is who it will be "from".</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                </div>
                
                <p class="submit">
                	<input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes" />
                </p>
            
            </form>
        
        </div>
        <?php		
	}
	
	/**
	 * Quick way to echo the value of a setting.
	 *
	 * @since 1.0
	 *
	 * @param mixed $key
	 */
	public function s( $key ) {
		// @since 1.2.1 - Fixes Notices that are thrown when trying to echo a setting that isn't set.
		if ( isset( $this->settings[$key] ) ) {
			// Will function differently based on the type of the setting
			switch ( gettype( $this->settings[$key] ) ) {
				case 'boolean' :
					// If it's a boolean, then we're dealing with a checkbox,
					// and need to echo checked if true
					echo ( $this->settings[$key] ) ? ' checked' : '';
					break;
				case 'string' :
				case 'integer' :
				case 'double' :
					// In most other cases, we just need to echo the value
					echo ( isset( $this->settings[$key] ) ) ? $this->settings[$key] : '';
					break;
			}
		}
	}
	
	/**
	 * Displays checkboxes for each default dashboard widget.
	 * Used on the Ask It settings page.
	 *
	 * @since 1.0
	 */
	public function display_dashboard_meta_box_options() {		
		  foreach ( $this->settings['dashboard_cleanup'] as $key => $widget ) :
			$checked = $widget['remove'] ? ' checked' : '';
			?>
            <tr valign="top">
            	<th scope="row">
                	<?php echo $widget['name']; ?>
                </th>
                <td>
                	<fieldset>
                    	<label for="<?php echo $key; ?>">
                        	<input type="checkbox" id="<?php echo $key; ?>" name="<?php echo $key; ?>"<?php echo $checked; ?> />
                        	Remove
                        </label>
                    </fieldset>
                </td>
            </tr>
            <?php		
		endforeach;			
	}
		
	/**
	 * Registers the `Questions` custom post type.
	 * Also adds several hooks for meta boxes, saving meta info, and custom columns.
	 *
	 * @since 1.0
	 */
	public function register_custom_post_type() {
		$labels = array(
			'name' => $this->settings['custom_post_plural'],
			'singular_name' => $this->settings['custom_post_singular'],
			'add_new' => 'Add ' . $this->settings['custom_post_singular'],
			'add_new_item' => 'New ' . $this->settings['custom_post_singular'],
			'edit_item' => 'Edit ' . $this->settings['custom_post_singular'],
			'new_item' => 'New ' . $this->settings['custom_post_singular'],
			'view_item' => null,
			'search_items' => 'Search ' . $this->settings['custom_post_plural'],
			'not_found' =>  'No ' . $this->settings['custom_post_plural'] . ' found',
			'not_found_in_trash' => 'No ' . $this->settings['custom_post_plural'] . ' found in Trash', 
			'parent_item_colon' => '',
			'menu_name' => $this->settings['custom_post_plural']
		);
		$args = array(
			'labels' => $labels,
			'public' => false,
			'publicly_queryable' => true,
			'exclude_from_search' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => false,
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 25,
			'supports' => array( 'title', 'editor' )
		); 
		register_post_type( 'askit_question', $args );
		
		// Add necessary meta boxes
		add_action( 'add_meta_boxes', array( &$this, 'add_question_meta_boxes' ) );
		// Add hooks for saving post meta information
		add_action( 'save_post', array( &$this, 'save_question_meta_box' ) );
		add_action( 'save_post', array( &$this, 'save_question_status_meta' ) );
		add_action( 'save_post', array( &$this, 'save_question_public_meta' ) );
		
		// Add custom columns
		add_filter( 'manage_edit-askit_question_columns', array( &$this, 'edit_question_columns' ) );
		add_action( 'manage_posts_custom_column', array( &$this, 'question_custom_columns' ) );
		
		// Can't seem to make this custom column sortable by its meta value...
		//add_filter( 'manage_edit-askit_question_sortable_columns', array( &$this, 'question_custom_column_sortable' ) );
		//add_filter( 'query_vars', array( &$this, 'question_status_column_orderby' ) );
		
		// Within the settings, there is an option to specify the capability required to
		// answer questions. If the current user doesn't have that capbility, we need to
		// hide this menu link.
		if ( ! current_user_can( $this->settings['capability_to_answer_questions'] ) )
			add_action( 'admin_menu', array( &$this, 'hide_questions_cpt' ) );
	}
	
	/**
	 * Filter: Custom columns for Ask It custom post type.
	 *
	 * @since 1.0
	 *
	 * @param array $columns current columns
	 * @return array $new_columns new columns
	 */
	public function edit_question_columns( $columns ) {
		// Add a 'Status' column that will indicate whether a question has been 'asked' or 'answered'.
        $new_columns = array(
			'cb' => $columns['cb'],
			'title' => $columns['title'],
			'status' => 'Status',
			'date' => $columns['date']
		);
		
        return $new_columns;
	}
	
	/**
	 * Displays question status in custom column.
	 *
	 * @since 1.0
	 *
	 * @param string $column current column
	 */
	public function question_custom_columns( $column ) {
        global $post;
 
        switch ( $column ) {
            case 'status' :
				$status = get_post_meta( $post->ID, 'askit_question_status', true );
				echo ( 'Asked' === $status ) ? '<strong>' . $status . '</strong>' : $status;
            break;
        }
	}
	
	/**
	 * NOTE: Not currently used. Haven't quite figured out sorting custom columns.
	 * Filter: Adds our custom column to the sortable columns array.
	 *
	 * @since 1.0
	 *
	 * @param array $columns sortable columns
	 * @return array $columns
	 */
	public function question_custom_column_sortable( $columns ) {
		$columns['status'] = 'askit_question_status';
		
		return $columns;
	}
	
	/**
	 * NOTE: Not currently used. Haven't quite figured out sorting custom columns.
	 * Filter: Adjusts the query vars when sorting by our custom column 'Status'.
	 *
	 * @since 1.0
	 *
	 * @param array $vars current query vars
	 * @return array $vars
	 */
	public function question_status_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'askit_question_status' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'orderyby' => 'meta_value',
				'meta_key' => 'askit_question_answered'
			) );
		}
	 
		return $vars;
	}
	
	/**
	 * Hides Ask It's custom post type menu link.
	 *
	 * @since 1.0
	 */
	public function hide_questions_cpt() {
		remove_menu_page( 'edit.php?post_type=askit_question' );
	}
	
	/**
	 * Adds custom meta boxes to Ask It's custom post type.
	 *
	 * @since 1.0
	 *
	 * @param array $vars current query vars
	 * @return array $vars
	 */
	public function add_question_meta_boxes() {
		add_meta_box( 'askit_question', 'Question', array( &$this, 'question_meta_box' ), 'askit_question', 'normal', 'high' );
		add_meta_box( 'askit_public', 'Public', array( &$this, 'public_meta_box' ), 'askit_question', 'side', 'core' );
	}
	
	/**
	 * Displays Question meta box.
	 *
	 * @since 1.0
	 *
	 * @param object $post current post
	 */
	public function question_meta_box( $post ) {
		wp_nonce_field( 'askit_question_meta_box', 'askit_question_nonce' );
		?>
        <textarea id="the_askit_question" name="askit_question" rows="3" cols="40"><?php echo get_post_meta( $post->ID, 'askit_question', true ); ?></textarea>
        <?php		
	}
	
	/**
	 * Displays 'Make Public' meta box.
	 *
	 * @since 1.0
	 *
	 * @param object $post current post
	 */
	public function public_meta_box( $post ) {
		// Only allow the question to be made public if it has been answered
		if ( '' != $post->post_content ) :
			wp_nonce_field( 'askit_public_meta_box', 'askit_public_nonce' );
			?>
			<label for="askit_public"><input type="checkbox" id="askit_public" name="askit_public"<?php if ( get_post_meta( $post->ID, 'askit_public', true ) ) : ?> checked<?php endif; ?> /> Make Public</label>
			<?php
		else :
			?>
            <p>You must answer this question before making it public.</p>
            <?php
		endif;
	}
	
	/**
	 * Save custom post meta - 'Question'
	 *
	 * @since 1.0
	 *
	 * @param int $post_id current post id
	 */
	public function save_question_meta_box( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;
		
		// Ask It hijacks the built in Quick Press feature to create posts from the dashboard,
		// which means we need to check the global $action to see if that's what is happening.
		// Normally, we would check the nonce to verify the saving action, but if the call is
		// coming from this Quick Press feature, the nonce will never have been set, so we bypass it.
		global $action;
		if ( ! ( 0 === strpos( $action, 'post-quickpress' ) ) ) {

			if ( ! isset( $_POST['askit_question_nonce'] ) )
				return;
			
			if ( ! wp_verify_nonce( $_POST['askit_question_nonce'], 'askit_question_meta_box' ) )
				return;
		}
		
		// Only save post meta if we're dealing with the right post type
		// and the current user can edit this post
		if ( 'askit_question' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		} else {
			return;
		}

		$question = $_POST['askit_question'];
		add_post_meta( $post_id, 'askit_question', $question, true ) or update_post_meta( $post_id, 'askit_question', $question );
		
		return $question;
	}
	
	/**
	 * Save custom post meta - 'Public'
	 *
	 * @since 1.0
	 *
	 * @param int $post_id current post id
	 */
	public function save_question_public_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;
		
		// Verify nonce
		if ( ! isset( $_POST['askit_public_nonce'] ) )
			return;
		if ( ! wp_verify_nonce( $_POST['askit_public_nonce'], 'askit_public_meta_box' ) )
			return;
		
		// Only save post meta if we're dealing with the right post type
		// and the current user can edit this post
		if ( 'askit_question' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		} else {
			return;
		}

		$public = isset( $_POST['askit_public'] );
		add_post_meta( $post_id, 'askit_public', $public, true ) or update_post_meta( $post_id, 'askit_public', $public );
		
		return;
	}
	
	/**
	 * Save custom post meta - 'Question Status'
	 * This post meta is not attached to a custom meta box,
	 * it is just updated automatically and used as a flag
	 *
	 * @since 1.0
	 *
	 * @param int $post_id current post id
	 */
	public function save_question_status_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;
		
		// Only save post meta if we're dealing with the right post type
		// and the current user can edit this post
		if ( isset( $_POST['post_type'] ) && 'askit_question' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		} else {
			return;
		}
		
		$status = 'Asked';
		$questions = new WP_Query( array( 'post_type' => 'askit_question', 'p' => $post_id, 'posts_per_page' => 1 ) );
		
		if ( $questions->have_posts() ) : while ( $questions->have_posts() ) : $questions->the_post();
			$status = ( get_the_content() != "" ) ? 'Answered' : 'Asked';
		endwhile; endif;
		
		wp_reset_query();
		
		add_post_meta( $post_id, 'askit_question_status', $status, true ) or update_post_meta( $post_id, 'askit_question_status', $status );
		
		return;
	}
	
	/**
	 * Adds hooks to remove and add dashboard widgets.
	 *
	 * @since 1.0
	 */
	public function setup_dashboard_widgets() {
		add_action( 'admin_menu', array( &$this, 'remove_dashboard_widgets' ) );
		add_action( 'wp_dashboard_setup', array( &$this, 'add_dashboard_widgets' ) );	
	}
	
	/**
	 * Removes selected default dashboard widgets.
	 *
	 * @since 1.0
	 */
	public function remove_dashboard_widgets() {
		foreach ( $this->settings['dashboard_cleanup'] as $key => $c ) {
			if ( $c['remove'] ) {
				remove_meta_box( $key, 'dashboard', 'core' );
			}
		}
	}
	
	/**
	 * Adds Ask It dashboard widgets (unless specified otherwise in settings).
	 *
	 * @since 1.0
	 */
	public function add_dashboard_widgets() {
		if ( ! $this->settings['remove_dashboard_answered_questions'] )
			wp_add_dashboard_widget( 'askit_answered_questions', $this->settings['answered_questions_dashboard_widget_title'], array( &$this, 'dashboard_answered_questions' ) );
			
		if ( $this->can_ask_question() ) {
			if ( ! $this->settings['remove_dashboard_my_questions'] )
				wp_add_dashboard_widget( 'askit_my_questions', $this->settings['my_questions_dashboard_widget_title'], array( &$this, 'dashboard_my_questions' ) );
				
			if ( ! $this->settings['remove_dashboard_question_form'] )
				wp_add_dashboard_widget( 'askit_question_form', $this->settings['question_form_dashboard_widget_title'], array( &$this, 'dashboard_question_form' ) );
		}
	}
	
	/**
	 * Adds hooks to setup email & text notifications
	 *
	 * @since 1.0
	 */
	public function setup_notifications() {
		add_action( 'admin_menu', array( &$this, 'add_alert_page' ) );
		add_action( 'save_post', array( &$this, 'notify_asker' ) );
	}
	
	/**
	 * Adds Ask It alert page for when questions are asked.
	 *
	 * @since 1.0
	 */
	public function add_alert_page() {
		// We add this page and then instantly remove it so that it never actually shows up in the menu.
		// We just want to use it as a function page that will be called via ajax when the Question Form is submited via the dashboard.
		add_menu_page( 'Askit Alert', 'Askit Alert', 'manage_options', 'askit-alert', array( &$this, 'question_asked_notifications' ) );
		remove_menu_page( 'askit-alert' );
	}
	
	/**
	 * Registers & enqueues Ask It scripts
	 *
	 * @since 1.0
	 */
	public function register_scripts() {
		wp_register_script( 'askit-js', ASKIT_PLUGIN_URL . 'ask-it.js', array( 'jquery' ) );
		wp_enqueue_script( 'askit-js' );
	}
	
	/**
	 * Registers & enqueues Ask It styles
	 *
	 * @since 1.0
	 */
	public function register_styles() {
		wp_register_style( 'askit-css', ASKIT_PLUGIN_URL . 'ask-it.css' );
		wp_enqueue_style( 'askit-css' );
	}
	
	/**
	 * Dashboard Widget: displays answered questions that have been 'made public'.
	 *
	 * @since 1.0
	 */
	public function dashboard_answered_questions() {
		$args = array(
			'post_type' => 'askit_question',
			'posts_per_page' => $this->settings['answered_questions_limit'],
			'meta_key' => 'askit_public',
			'meta_value' => true
		);
		$questions = new WP_Query( $args );
		
		if ( $questions->have_posts() ) : while ( $questions->have_posts() ) : $questions->the_post();
			?>
			<div class="askit-q">
				<a href="#" class="askit-toggle"><span class="title"><?php the_title(); ?></span></a>
                
                <br class="askit-clear" />
				
				<div class="askit-q-inside">
                                        
					<div class="askit-question"><p><?php $this->the_question(); ?><p></div>
					
					<div class="askit-answer">
						<h4>Answer:</h4>
                        <div class="askit-content">
							<?php the_content(); ?>
                        </div>
					</div>
				</div>
			
			</div>
			
			<div class="askit-sep"></div>
			<?php
		endwhile; endif;		
	}
	
	/**
	 * Dashboard Widget: displays questions asked by current user.
	 *
	 * @since 1.0
	 */
	public function dashboard_my_questions() {
		global $current_user;
		get_currentuserinfo();
			
		$args = array(
			'post_type' => 'askit_question',
			'author' => $current_user->ID,
			'posts_per_page' => $this->settings['my_questions_limit'],
			'orderby' => 'modified'
		);
		$questions = new WP_Query( $args );
		
		if ( $questions->have_posts() ) : while ( $questions->have_posts() ) : $questions->the_post();
			$removed = get_post_meta( get_the_ID(), 'askit_removed', true );
			if ( $removed )
				continue;
			?>
			<div class="askit-q">
				<a href="#" class="askit-toggle<?php if ( ! $this->is_answered() ) : ?> askit-unanswered<?php endif; ?>"><span class="title"><?php the_title(); ?></span><span class="meta"><?php $this->the_question_status(); ?></span></a>
                
                <br class="askit-clear" />
				
				<div class="askit-q-inside">
                    <div class="askit-response"></div>
                	<div class="askit-q-controls">
                    	<?php $this->the_make_public_link(); ?>
                        <?php $this->the_remove_link(); ?>
                    </div>
                                        
					<div class="askit-question"><p><?php $this->the_question(); ?><p></div>
					
					<?php if( get_the_content() != "" ) : ?>
					<div class="askit-answer">
						<h4>Answer:</h4>
                        <div class="askit-content">
							<?php the_content(); ?>
                        </div>
					</div>
					<?php endif; ?>
				</div>
			
			</div>
			
			<div class="askit-sep"></div>
			<?php
		endwhile; endif;
		
		wp_reset_postdata();
	}
	
	/**
	 * Dashboard Widget: displays the question form.
	 * This essentially mimics/hijacks the Quick Press functionality,
	 * but does not conflict with it at all.
	 *
	 * @since 1.0
	 */
	public function dashboard_question_form() {
		?>    
		<form name="askit-question" action="<?php echo esc_url( admin_url( 'post.php?post_type=askit_question' ) ); ?>" method="post" id="askit-question">
		
			<p class="askit-response">
			</p>
		
			<h4><label for="askit-title">Subject</label></h4>
			<div class="input-text-wrap">
				<input type="text" id="askit-title" name="post_title" tabindex="7" autocomplete="off" />
			</div>
			
			<br class="askit-clear" />
			<br />
			
			<h4><label for="askit-question">Question</label></h4>
			<div class="textarea-wrap">
				<textarea name="askit_question" id="askit-question" rows="5" cols="15" tabindex="8"></textarea>
			</div>
			
			<br class="askit-clear" />
			<br />
			
			<h4><label for="askit-asker">Asker</label></h4>
			<div class="input-text-wrap">
				<em><?php $this->the_current_asker( 'both' ); ?></em>
				<input type="hidden" id="askit-asker" name="askit-asker" value="<?php $this->the_current_asker(); ?>" />
                <input type="hidden" id="askit-asker-email" name="askit-asker-email" value="<?php $this->the_current_asker( 'email' ); ?>" />
			</div>
			
			<br class="askit-clear" />   
			
			<p class="askit-submit">
				<input type="hidden" name="askit-action" id="askit-action" value="<?php echo esc_url( admin_url( 'admin.php?page=askit-alert' ) ); ?>" />
				<input type="hidden" name="action" id="askit-quickpost-action" value="post-quickpress-publish" />
				<input type="hidden" name="quickpress_post_ID" value="0" />
				<input type="hidden" name="post_type" value="askit_question" />
				<?php wp_nonce_field('add-askit_question'); ?>
				<input type="reset" value="<?php esc_attr_e( 'Reset' ); ?>" class="button" />
				<span class="askit-publishing-action">
					<input type="submit" name="publish" tabindex="9" class="button-primary" value="Ask Question" />
					<img class="waiting" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" />
				</span>
				<br class="askit-clear" />
			</p>
			
		</form>
		<?php
	}
	
	/**
	 * Called via ajax when a question is asked.
	 * Notifies the administrator via email & text depending on settings.
	 *
	 * @since 1.0
	 */
	public function question_asked_notifications() {
		if ( isset( $_POST['askitQuestionAsked'] ) ) {
			$title = $_POST['title'];
			$asker = $_POST['asker'];
			$asker_email = $_POST['askerEmail'];
			$headers = "From: " . $asker_email . "\r\n";
			
			// Email notification
			if ( $this->settings['notification_question_asked_email'] && ( '' != $this->settings['question_asked_email_to'] ) ) {
				mail( $this->settings['question_asked_email_to'], $this->settings['question_asked_email_subject'], $asker . ' has asked you a question on ' . get_bloginfo( 'home' ), $headers );
			}
			
			// Text notification
			if ( $this->settings['notification_question_asked_text'] && ( '' != $this->settings['question_asked_text_number'] ) && ( '' != $this->settings['question_asked_text_carrier'] ) ) {
				// We send a text notification by sending an email to the number @ carrier_domain
				// More info here: @link http://www.makeuseof.com/tag/email-to-sms/
				// And here: @link http://www.venture-ware.com/kevin/web-development/email-to-sms/
				$to = $this->settings['question_asked_text_number'] . '@' . $this->carrier_domains[$this->settings['question_asked_text_carrier']];
				$message = $this->settings['question_asked_text_subject'] . ' Question asked on ' . get_bloginfo( 'home' ) . " by " . $asker;
				mail( $to, '', $message );
			}
		}
	}
	
	/**
	 * Called on `save_post`. Notifies the asker if their question was answered.
	 *
	 * @since 1.0
	 *
	 * @param int $id post id
	 */
	public function notify_asker( $id ) {
		if ( '' == $this->settings['notification_question_answered_email'] )
			return;
			
		if ( get_post_type( $id ) !== 'askit_question' )
			return;
			
		// This will only be set if the asker has already been notified.
		// This prevents multiple notifications in case an administrator makes minor changes later on.
		if ( get_post_meta( $id, 'askit_asker_notified', true ) )
			return;
			
		$args = array(
			'post_type' => 'askit_question',
			'p' => $id,
			'posts_per_page' => 1
		);
		$question = new WP_Query( $args );
		
		// Notify asker by email
		if( $question->have_posts() ) : while( $question->have_posts() ) : $question->the_post();
			if( get_the_content() != "" ){				
				global $current_user;
				get_currentuserinfo();
				$to = $current_user->display_name . " <" . $current_user->user_email . ">";
				$subject = $this->settings['question_answered_email_subject'];
				
				$message = "<html><body>";
				$message .= "<h3>Question</h3>";
				$message .= $this->get_the_question();
				$message .= "<br><br><h3>Answer</h3>";
				$message .= get_the_content();
				$message .= '<br><br><a href="' . admin_url() . '" target="_blank">Login Here to View Your Questions & Answers.</a>';
						
				$headers = "From: " . $this->settings['question_answered_email_from'] . "\r\n";
				$headers .= "MIME-Version: 1.0\r\n";
				$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
				
				mail($to, $subject, $message, $headers);
				
				// Set flag to prevent multiple notifications
				add_post_meta( $id, 'askit_asker_notified', true );
			}
		endwhile; endif;
	}
	
	/**
	 * Add hook to add additional function pages.
	 *
	 * @since 1.0
	 */
	public function setup_additional_pages() {
		add_action( 'admin_menu', array( &$this, 'add_additional_pages' ) );
	}
	
	/**
	 * Add additional function pages.
	 *
	 * @since 1.0
	 */
	public function add_additional_pages() {
		add_menu_page( 'Askit - Make Public', 'Askit - Make Public', 'manage_options', 'askit-make-public', array( &$this, 'make_question_public' ) );
		add_menu_page( 'Askit - Remove', 'Askit - Remove', 'manage_options', 'askit-remove', array( &$this, 'remove_question' ) );
		
		// Remove them right after adding them so that they don't actually show up in the menu.
		// We just want to use them for functionality.
		remove_menu_page( 'askit-make-public' );
		remove_menu_page( 'askit-remove' );
	}
	
	/**
	 * Makes a question public. Called via ajax.
	 *
	 * @since 1.0
	 */
	public function make_question_public() {
		if ( isset( $_POST['askit'] ) && isset ( $_POST['id'] ) ) {
			$id = $_POST['id'];
			
			add_post_meta( $id, 'askit_public', true, true ) or update_post_meta( $id, 'askit_public', true );
		}
	}
	
	/**
	 * Removes a question from this user's 'My Questions' list. Called via ajax.
	 *
	 * @since 1.0
	 */
	public function remove_question() {
		if ( isset( $_POST['askit'] ) && isset ( $_POST['id'] ) ) {
			$id = $_POST['id'];
			
			add_post_meta( $id, 'askit_removed', true, true ) or update_post_meta( $id, 'askit_removed', true );
		}
	}
		
	/**
	 * Echoes the current asker (user) info.
	 *
	 * @since 1.0
	 *
	 * @param string $details 'email', 'both', or 'name'
	 */
	public function the_current_asker( $details = 'name' ) {
		global $current_user;
		get_currentuserinfo();
		
		switch ( $details ) {
			case 'email' :
				echo $current_user->user_email;
				break;
			case 'both' :
				echo $current_user->display_name . " (" . $current_user->user_email . ")";
				break;
			case 'name' :
			default :
				echo $current_user->display_name;
		}
	}
	
	/**
	 * Returns true if current question has been answered.
	 *
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public function is_answered() {
		return ( get_the_content() != "" );
	}
	
	/**
	 * Returns true if current user has all required capabilities to ask a question.
	 *
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public function can_ask_question() {
		$return = ( current_user_can( 'edit_posts' ) && current_user_can( 'publish_posts' ) );
		if ( '' !== $this->settings['capability_to_ask_questions'] && $return ) {
			$return = current_user_can( $this->settings['capability_to_ask_questions'] );
		}
		
		return $return;
	}
	
	/**
	 * Echoes link to make a question public.
	 *
	 * @since 1.0
	 */
	public function the_make_public_link() {
		// Can only make a question public if it's been answered
		if ( ! $this->is_answered() )
			return;
			
		$public = get_post_meta( get_the_ID(), 'askit_public', true );
		if ( $public ) {
			echo '<span class="askit-public">Public</span>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=askit-make-public' ) ) . '" data-id="' . get_the_ID() . '" class="askit-make-public">Make Public</a>';
		}
	}
	
	/**
	 * Echoes link to remove a question from the 'My Questions' list.
	 *
	 * @since 1.0
	 */
	public function the_remove_link() {
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=askit-remove' ) ) . '" data-id="' . get_the_ID() . '" class="askit-remove">Remove</a>';
	}
	
	/**
	 * Echoes the question.
	 *
	 * @since 1.0
	 */
	public function the_question() {
		echo $this->get_the_question();
	}
	
	/**
	 * Returns the question (stored in post meta 'askit_question').
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_the_question() {
		global $post;
		return get_post_meta( $post->ID, 'askit_question', true );
	}
	
	/**
	 * Echoes the question status along with a relative timestamp.
	 * Ex: "Asked 5 hours ago."
	 *
	 * @since 1.0
	 */
	public function the_question_status() {
		if( get_the_content() != "" ) {
			echo "Answered ";
			$this->the_relative_time( 'modified' );
		} else {
			echo "Asked ";
			$this->the_relative_time();
		}
	}
	
	/**
	 * Echoes the relative time from it's published timestamp or modified timestamp.
	 *
	 * @since 1.0
	 *
	 * @param string $from 'asked' refers to published, 'modified' refers to modified
	 * @param int $offset retrieved from WordPress settings
	 */
	public function the_relative_time( $from = 'asked', $offset = NULL ) {
		if ( NULL === $offset )
			$offset = (int) $this->settings['offset'];
			
		switch ( $from ) {
			case 'modified' :
				echo $this->how_long_ago( get_the_modified_time( 'U' ), $offset );
				break;
			case 'asked' :
			default :
				echo $this->how_long_ago( get_the_time( 'U' ) , $offset );
		}
	}
	
	/**
	 * Returns a verbal relative timestamp.
	 * Credits to Terri Swallow - @link http://terriswallow.com/weblog/2008/relative-dates-in-wordpress-templates/
	 *
	 * @since 1.0
	 * 
	 * @param int $timestamp
	 * @param int $offset
	 * @return string $r relative timestamp (how long ago)
	 */
	public function how_long_ago( $timestamp, $offset = 0 ) {
		$difference = ( time() + ( $offset * 60 * 60 ) ) - $timestamp;

		if ( $difference >= 60 * 60 * 24 * 365 ) {			// if more than a year ago
			$int = intval( $difference / ( 60 * 60 * 24 * 365 ) );
			$s = ( $int > 1 ) ? 's' : '';
			$r = $int . ' year' . $s . ' ago';
		} elseif( $difference >= 60 * 60 * 24 * 7 * 5 ) {	// if more than five weeks ago
			$int = intval( $difference / ( 60 * 60 * 24 * 30 ) );
			$s = ( $int > 1 ) ? 's' : '';
			$r = $int . ' month' . $s . ' ago';
		} elseif ( $difference >= 60 * 60 * 24 * 7 ) {		// if more than a week ago
			$int = intval( $difference / (60*60*24*7));
			$s = ($int > 1) ? 's' : '';
			$r = $int . ' week' . $s . ' ago';
		} elseif ( $difference >= 60 * 60 * 24 ) {			// if more than a day ago
			$int = intval( $difference / ( 60 * 60 * 24 ) );
			$s = ( $int > 1 ) ? 's' : '';
			$r = $int . ' day' . $s . ' ago';
		} elseif ( $difference >= 60 * 60 ) {				// if more than an hour ago
			$int = intval( $difference / ( 60 * 60 ) );
			$s = ( $int > 1 ) ? 's' : '';
			$r = $int . ' hour' . $s . ' ago';
		} elseif ( $difference >= 60 ) {					// if more than a minute ago
			$int = intval( $difference / ( 60 ) );
			$s = ( $int > 1 ) ? 's' : '';
			$r = $int . ' minute' . $s . ' ago';
		} else {											// if less than a minute ago
			$r = 'moments ago';
		}

		return $r;
	}
	
	/**
	 * Displays the <option>'s in a <select> for a wireless carrier dropdown box.
	 * Generated from the $this->carriers and $this->carrier_domains arrays.
	 *
	 * @since 1.0
	 * 
	 * @param mixed $compare used to determine selected option
	 */
	public function print_carrier_list( $compare = NULL ) {
		foreach ( $this->carriers as $key => $carrier ) :
			?>
            <option value="<?php echo $key; ?>"<?php if ( isset( $compare ) && $compare === $key ) : ?> selected<?php endif; ?>><?php echo $carrier; ?></option>
            <?php
		endforeach;
	}
	
}

?>