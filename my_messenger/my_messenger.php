<?php
/**
 * My Messenger
 * 
 * @package           My Messenger Package
 * @author            Marioio
 * @copyright         2024
 * @license           GPL-2.0-or-llater
 * 
 * Plugin Name:       My Messenger
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Send Messages of the day
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Marioio
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       my-messenger-slug
 * Domain Path:       /languages
 */
// Avoid execute plugin from direction input in browser
defined('ABSPATH') or die("You shouldn't be here");
 
    class MyMessenger {
        // Declare shortcodes
        function __construct() {
            add_shortcode('mmm_show_message', array($this, 'mmm_show_message'));
        }
        
        // Execute action in order to create a new custom post type
        function mmm_execute_actions() {
            // Register CPT for message
            add_action('init', array($this, 'mmm_register_custom_message'));
            // Enqueue style css and js
            add_action('wp_enqueue_scripts', array($this, 'mmm_enqueue_front_css'));
            // Add a metabox
            add_action('add_meta_boxes', array($this, 'mmm_add_metabox'));
            // Save custom post fields in DDBB
            add_action('save_post', array($this, 'mmm_save_message_metabox'));
        } 
        
        function mmm_register_custom_message() {
            $supports = array(
                'title',
                'editor',
                'thumbnail',
            );
            $labels = array(
                'name' => _x('Messages', 'plural'),
                'singular_name' => _x('Message', 'singular'),
                'menu_name' => _x('Messages', 'admin menu'),
                'menu_admin_bar' => _x('Message', 'admin bar'),
                'add_new' => _x('Add New Message', 'add_new'),
                'all_items' => __('Messages'),
                'add_new_item' => __('Add new Message'),
                'view_item' => __('View Message'),
                'search' => __('Search Message'),
                'not_found' => __('No Message found...'), 
            );
            $args = array(
                'supports' => $supports,
                'labels' => $labels,
                'query_var' => true,     // Access to query vars with CPT
                'show_in_rest' => true,  // We are using the Gutenberg editor
                'show_in_menu' => true,  // Show CPT option in admin-bar
                'show_in_menu' => true,  // Show CPT option in admin-bar
                'menu_position' => 7,
                'menu_icon' => 'dashicons-testimonial',       // Dashicons icon css class
                'public' => true,        // Custom-post-type can be viewed in front-end
                'exclude_from_search' => true,
            );
            register_post_type('mmm_message', $args);
            
            // Add author roles as categories new taxonomy
            register_taxonomy(
                'to',
                'mmm_message',
                array(
                    'label' => 'To',
                    'rewrite' => array('slug' => 'to'),
                    'show_admin_column' => true,
                    'query_var' => true,
                    'hierarchical' => true,
                    'show_in_rest' => true,
                ),
            );
            
            $terms = array('All Users', 'Administrator', 'Editor', 'Author', 'Colaborator', 'Subscriptor');
            
            foreach($terms as $term){
                wp_insert_term($term, 'to');
            }
            
            flush_rewrite_rules();
        }
        

        /**
         * Show message shortcode
         * */
        function mmm_show_message($attr){
            $authorid = shortcode_atts(array('id' => 0), $attr);
            $author_id = $authorid['id'];
            
            // Get user role
            $role = trim(implode('', get_userdata($author_id)->roles));
            
            // Make sure the user is logged in
            if(is_user_logged_in()){
                // Query to determine if there is a message for that role
                $args = array(
                    'post_per_page' => 1,
                    'post_type' => array('mmm_message'),
                    'tax_query' => array(
                        array(
                            // Searching taxonomy slug
                            'taxonomy' => 'to',
                            // Field Type
                            'field' => 'slug',
                            // Searching value
                            'terms' => $role,
                            // Action INCLUDE or NOT INCLUDE in the query
                            'operator' => 'IN',
                        ),
                    ),
                );
                
                $msg = new WP_Query($args);
                // Make message HTML structure
                if($msg->have_posts()):
                    $msg->the_post();
                    
                    $post_id = get_the_id();
                    $expired_date = new DateTime(get_post_meta($post_id, 'mmm-expired-date', true), new DateTimeZone('+1'));
                    $today = new DateTime("now", new DateTimeZone('+1'));
                    
                    // var_dump($today > $expired_date);
                    $interval = $today->diff($expired_date);
                    // var_dump($interval->format('%R'));
                    
                    // Thumbnail img
                    if(has_post_thumbnail()){
                      $thumb_url = get_the_post_thumbnail_url();
                    } else { // Imagen por defecto
                      $thumb_url = get_template_directory_uri().'/assets/img/pulpo.png';
                    }
                    
                    if($interval->format('%R') == "+"):
                    // if($today < $expired_date):
                        ?>
                        <div class="motd-box row">
                            <div class="message-pic col-md-3" style="background-image: url(<?php echo $thumb_url; ?>); background-position: center;
                            background-size: contain; background-repeat: no-repeat;width: 100px;height:100px;"></div>
                            <div class="message-content col-md-9">
                                <h3><?php the_title(); ?></h3>
                                <p><?php the_content(); ?></p>
                            </div>
                        </div>
                        <?php
                    endif;
                else:
                    echo '<h2>There is no message for '.$role.' :(</h2>';
                endif;
                wp_reset_postdata();
            }
        }
        
        function mmm_enqueue_front_css() {
            wp_register_style('mmm_front_css', plugins_url('/my_messenger/mmm-front.css'), __FILE__);
            wp_enqueue_style('mmm_front_css');
        }
        
        function mmm_add_metabox($screens){
            $screens = array('mmm_message');
            foreach($screens as $screen){
                add_meta_box('mmm-message', 'Message Details', array($this, 'mmm_draw_metabox'), $screen, 'advanced');
            }
        }
        
        function mmm_draw_metabox($post) {
            wp_nonce_field(basename(__FILE__), 'mmm_message_nonce');
            $expired_date = get_post_meta($post->ID, 'mmm-expired-date', true);
            
            // It doesn't have expiration date
            $today = new DateTime("now", new DateTimeZone('+1'));
            if(!$expired_date){
                // Set +24 h as expiration date
                $today->modify("+1 day");
                $expired_date = $today->format('Y-m-d\TH:i');
            }
            ?>
            <div class="custom-field">
                <label for="mmm-expired-date">Expiration Date</label>
                <input type="datetime-local" id="mmm-expired-date" name="mmm-expired-date" value="<?php echo $expired_date; ?>">
            </div>
            <?php
        } 
        
        function mmm_save_message_metabox($post_id) {
            // Check if we are in an autosave
            $is_autosave = wp_is_post_autosave($post_id);
            // Check if we are in revision
            $is_revision = wp_is_post_revision($post_id);
            // Check if the nonce field is valid
            $is_valid_nonce = wp_verify_nonce( $_POST['mmm_message_nonce'], basename(__FILE__));
            
            if($is_autosave || $is_revision || !$is_valid_nonce){
                return;
            }
            
            // Check if user have the capabilities to save posts
            if(!current_user_can('edit_post', $post_id)) {
              return;
            }
            
            // Sanitize fields to avoid code injections
            $expired_date = sanitize_text_field($_POST['mmm-expired-date']);
            
            // Update custom post fields
            update_post_meta($post_id, 'mmm-expired-date', $expired_date);
        } 
         
    } // End Class
    
    if(class_exists('MyMessenger')){
        $myMessage = new MyMessenger();
        $myMessage->mmm_execute_actions();
    }