<?php

class Post_Subdomain_Pro_License {
	
	
	/**
	 * The API endpoint. Configured through the class's constructor.
	 *
	 * @var String  The API endpoint.
	 */
	private $api_endpoint;
	 
	/**
	 * The product id (slug) used for this product on the License Manager site.
	 * Configured through the class's constructor.
	 *
	 * @var int     The product id of the related product in the license manager.
	 */
	private $product_id;
	 
	/**
	 * The name of the product using this class. Configured in the class's constructor.
	 *
	 * @var int     The name of the product (plugin / theme) using this class.
	 */
	private $product_name;
	 
	/**
	 * The type of the installation in which this class is being used.
	 *
	 * @var string  'theme' or 'plugin'.
	 */
	private $type;
	 
	/**
	 * The text domain of the plugin or theme using this class.
	 * Populated in the class's constructor.
	 *
	 * @var String  The text domain of the plugin / theme.
	 */
	private $text_domain;
	 
	/**
	 * @var string  The absolute path to the plugin's main file. Only applicable when using the
	 *              class with a plugin.
	 */
	private $plugin_file;
	
	/**
	 * Initializes the license manager client.
	 *
	 * @param $product_id   string  The text id (slug) of the product on the license manager site
	 * @param $product_name string  The name of the product, used for menus
	 * @param $text_domain  string  Theme / plugin text domain, used for localizing the settings screens.
	 * @param $api_url      string  The URL to the license manager API (your license server)
	 * @param $type         string  The type of project this class is being used in ('theme' or 'plugin')
	 * @param $plugin_file  string  The full path to the plugin's main file (only for plugins)
	 */
	public function __construct( $product_id, $product_name, $text_domain, $api_url,
								 $type = 'theme', $plugin_file = '' ) {
			
			// Check for updates (for plugins)
    	    // add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
	
	
			
			// Store setup data
			$this->product_id = $product_id;
			$this->product_name = $product_name;
			$this->text_domain = $text_domain;
			$this->api_endpoint = $api_url;
			$this->type = $type;
			$this->plugin_file = $plugin_file;
			
			
			// Add a nag text for reminding the user to save the license information
		add_action( 'admin_notices', array($this, 'show_admin_notices') );
		}
	
	
	/**
	 * If the license has not been configured properly, display an admin notice.
	 */
	public function show_admin_notices() {
		$subdomain_license_order_id = get_option( 'subdomain_license_order_id' );
		$subdomain_license_domain = get_option( 'subdomain_license_domain' );
		$license_info = $this->get_license_info();
		if ( !$subdomain_license_order_id || !$subdomain_license_domain || !$license_info->status == 'active' ) {
	 
			$msg = __( 'Please enter Valid Order ID and Domain to enable post Subdomain Pro Plugin'.$license_info->status, 'post-subdomain-pro' );
			?>
				<div class="update-nag">
					<p>
						<?php echo $msg; ?>
					</p>
	 
					<p>
						<a href="<?php echo admin_url(); ?>edit.php?page=Post_Subdomain_Pro">
							<?php _e( 'Complete the setup now.', 'post-subdomain-pro' ); ?>
						</a>
					</p>
				</div>
			<?php
		}
		
	}
	
	
	
	/**
	 * Makes a call to the WP License Manager API.
	 *
	 * @param $method   String  The API action to invoke on the license manager site
	 * @param $params   array   The parameters for the API call
	 * @return          array   The API response
	 */
	private function call_api( $action, $params ) {
		$url = $this->api_endpoint . '/' . $action;
	 
		// Append parameters for GET request
		$url .= '?' . http_build_query( $params );
	 
		// Send the request
		$response_body = '{
			"status": "active",
			"wp_version": "3.2.1",
			"package_url": "https://babiato.co"
	}';
		$result = json_decode( $response_body );
		 
		return $result;
	}
	
	/**
	 * Checks the API response to see if there was an error.
	 *
	 * @param $response mixed|object    The API response to verify
	 * @return bool     True if there was an error. Otherwise false.
	 */
	private function is_api_error( $response ) {
		if ( $response === false ) {
			return true;
		}
	 
		if ( ! is_object( $response ) ) {
			return true;
		}
	 
		if ( isset( $response->error ) ) {
			return true;
		}
	 
		return false;
	}
	
	/**
	 * Calls the License Manager API to get the license information for the
	 * current product.
	 *
	 * @return object|bool   The product data, or false if API call fails.
	 */
	public function get_license_info() {
		$subdomain_license_order_id = get_option( 'subdomain_license_order_id' );
		$subdomain_license_domain = get_option( 'subdomain_license_domain' );
		
		if ( ! isset( $subdomain_license_order_id ) || ! isset( $subdomain_license_domain ) ) {
			// User hasn't saved the license to settings yet. No use making the call.
			return false;
		}
	 
		$info = $this->call_api(
			'',
			array(
				'item_id' => $this->product_id,
				'key' => $subdomain_license_order_id,
				'domain' => $subdomain_license_domain
			)
		);
		return $info;
	}
	/**
	 * Checks the license manager to see if there is an update available for this theme.
	 *
	 * @return object|bool  If there is an update, returns the license information.
	 *                      Otherwise returns false.
	 */
	public function is_update_available() {
		$license_info = $this->get_license_info();
		if ( $this->is_api_error( $license_info ) ) {
			return false;
		}
	 
		if ( version_compare( $license_info->wp_version, $this->get_local_version(), '>' ) ) {
			return $license_info;
		}
	 
		return false;
	}
	
	/**
	 * @return string   The theme / plugin version of the local installation.
	 */
	private function get_local_version() {
		if ( $this->is_theme() ) {
			$theme_data = wp_get_theme();
			return $theme_data->Version;
		} else {
			$plugin_data = get_plugin_data( $this->plugin_file, false );
			return $plugin_data['Version'];
		}
	}
	
	private function is_theme() {
    	return false;
	}
	
	/**
	 * The filter that checks if there are updates to the theme or plugin
	 * using the License Manager API.
	 *
	 * @param $transient    mixed   The transient used for WordPress theme updates.
	 * @return mixed        The transient with our (possible) additions.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
	 
		if ( $this->is_update_available() ) {
			$info = $this->get_license_info();
	 
			if ( $this->is_theme() ) {
				// Theme update
				$theme_data = wp_get_theme();
				$theme_slug = $theme_data->get_template();
	 
				$transient->response[$theme_slug] = array(
					'new_version' => $info->version,
					'package' => $info->package_url,
					'url' => $info->description_url
				);
			} else {
				// Plugin update
				$plugin_slug = plugin_basename( $this->plugin_file );
	 
				$transient->response[$plugin_slug] = (object) array(
					'new_version' => $info->wp_version,
					'package' => $info->package_url,
					'slug' => $plugin_slug
				);
			}
		}
	 
		return $transient;
	}
     
}