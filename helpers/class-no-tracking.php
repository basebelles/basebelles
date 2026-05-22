<?php

class BaseBelles_Block_Tracking {

	protected static $version;

	public function __construct() {

		self::$version = '1.0';

		// Block WP.org tracking _everything_
		add_filter( 'core_version_check_query_args', array( $this, 'block_org_core_args' ) );
		add_filter( 'pre_http_request', array( $this, 'block_happyness' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'block_extension_tracking' ), 0, 2 );
	}

	/**
	 * Filter the core update check arguments.
	 *
	 * Stop tracking things we don't need.
	 *
	 * @param array $args Array of update check arguments.
	 * @return array
	 */
	public function block_org_core_args( $args ) {
		$remove_tags = array( 'blogs', 'users', 'mysql', 'multisite_enabled', 'initial_db_version', 'local_package', 'extensions', 'platform_flags' );
		foreach ( $remove_tags as $tag ) {
			if ( isset( $args[ $tag ] ) ) {
				unset( $args[ $tag ] );
			}
		}

		return $args;
	}

	/**
	 * Block requests to the Happyness plugin.
	 *
	 * @param mixed $return
	 * @param array $request
	 * @param string $url
	 *
	 * @return array
	 */
	public function block_happyness( $return, array $request, string $url ) {
		if ( preg_match( '!^https?://api\.wordpress\.org/core/.*?-happy/!i', $url ) ) {
			return new \WP_Error( 'http_request_failed', sprintf( 'Request to %s is not allowed.', $url ) );
		}

		return $return;
	}

	/**
	 * Block requests to the extension tracking.
	 *
	 * @param array  $params
	 * @param string $url
	 *
	 * @return array
	 */
	public function block_extension_tracking( array $params, string $url ) {
		$wordpress_jetpack_urls = array(
			'api.wordpress.org',
			// 'jetpack.wordpress.com',
			// 'public-api.wordpress.com'
		);

		$wordpress_jetpack_urls = apply_filters( 'api_privacy_wp_urls', $wordpress_jetpack_urls );

		$is_wordpress_org_api = false;
		foreach ( $wordpress_jetpack_urls as $wp_org_url ) {
			if ( strpos( $url, $wp_org_url ) !== false ) {
				$is_wordpress_org_api = true;
				break;
			}
		}

		// remove the version of WP from the user-agent
		if ( ! empty( $params['user-agent'] ) ) {
			$params['user-agent'] = str_replace( get_bloginfo( 'version' ), 'Private', $params['user-agent'] );
		}

		// remove the URL from the user-agent
		if ( $is_wordpress_org_api ) {
			$params['user-agent'] = $this->remove_url_from_user_agent( $params['user-agent'] );
		}

		// Stop tracking plugins and themes
		if ( strpos( $url, 'wordpress.org/plugins/update-check/' ) !== false ) {
			// Don't ping .org for plugins hosted off .org
			$params['body']['plugins'] = $this->block_plugins( $params['body']['plugins'] );
		} elseif ( strpos( $url, 'wordpress.org/themes/update-check/' ) !== false ) {
			// Don't ping .org for themes hosted off .org
			$params['body']['themes'] = $this->block_themes( $params['body']['themes'] );
		} if ( strpos( $url, 'api.wordpress.org/core/version-check' ) !== false ) {
			// Don't track details about the site when checking for core updates
			if ( isset( $params['headers'] ) ) {
				if ( isset( $params['headers']['wp_install'] ) ) {
					unset( $params['headers']['wp_install'] );
				}

				if ( isset( $params['headers']['wp_blog'] ) ) {
					unset( $params['headers']['wp_blog'] );
				}
			}
		}

		return $params;
	}

	/**
	 * Remove tracking of themes hosted off-site.
	 *
	 * @param  string $themes
	 * @return string json_encoded string
	 */
	private function block_themes( $themes ) {
		$decoded_json = json_decode( $themes );
		if ( $decoded_json ) {
			// check for theme info
			if ( $decoded_json->themes ) {
				$to_remove = array();
				foreach ( $decoded_json->themes as $name => $theme ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					if ( isset( $theme->UpdateURI ) && ! empty( $theme->UpdateURI ) ) {
						// don't remove ones hosted on wordpress.org
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						if ( strpos( $theme->UpdateURI, 'wordpress.org' ) === false ) {
							$to_remove[] = $name;
						}
					}
				}

				foreach ( $to_remove as $remove ) {
					unset( $decoded_json->themes->$remove );
				}
			}
			$themes = wp_json_encode( $decoded_json );
		}

		return $themes;
	}

	/**
	 * Remove tracking of plugins hosted off-site.
	 *
	 * @param  string $plugins
	 * @return string json_encoded string
	 */
	private function block_plugins( $plugins ) {
		$decoded_json = json_decode( $plugins );
		if ( $decoded_json ) {
			// check for plugin info
			if ( $decoded_json->plugins ) {
				$to_remove = array();
				$to_keep   = array();
				foreach ( $decoded_json->plugins as $name => $plugin ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					if ( isset( $plugin->UpdateURI ) && ! empty( $plugin->UpdateURI ) ) {
						// don't remove ones hosted on wordpress.org
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						if ( strpos( $plugin->UpdateURI, 'wordpress.org' ) === false ) {
							$to_remove[] = $name;
							continue;
						}
					}

					$to_keep[] = $name;
				}

				foreach ( $to_remove as $remove ) {
					unset( $decoded_json->plugins->$remove );
				}

				$decoded_json->active = $to_keep;
			}
			$plugins = wp_json_encode( $decoded_json );
		}

		return $plugins;
	}

	/**
	 * Remove the URL from the user-agent string.
	 *
	 * @param string $str
	 * @return string
	 */
	private function remove_url_from_user_agent( $str ) {
		if ( strpos( $str, ';' ) !== false ) {
			$split = explode( ';', $str );

			return $split[0];
		}
	}
}

new BaseBelles_Block_Tracking();
