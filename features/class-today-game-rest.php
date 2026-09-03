<?php
/**
 * REST endpoint that powers live-updating tabs on the today-game block.
 *
 * @package Base*Belles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Basebelles_Today_Game_Rest {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the today-game REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'basebelles/v1',
			'/today-game/(?P<game_pk>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'game_pk' => array(
						'validate_callback' => function ( $value ) {
							return is_numeric( $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Return the current phase and rendered panel HTML for one of today's games.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array|WP_Error
	 */
	public function handle_request( WP_REST_Request $request ) {
		if ( ! class_exists( 'Basebelles_API' ) || ! class_exists( 'Basebelles_Today_Game_Panels' ) ) {
			return new WP_Error( 'basebelles_today_game_unavailable', 'Today\'s game data is unavailable.', array( 'status' => 500 ) );
		}

		$game_pk  = (int) $request->get_param( 'game_pk' );
		$api      = Basebelles_API::get_instance();
		$schedule = $api->get_guardians_today_game();

		if ( is_wp_error( $schedule ) || empty( $schedule['games'] ) ) {
			return new WP_Error( 'basebelles_today_game_not_found', 'No game found for today.', array( 'status' => 404 ) );
		}

		$game = null;

		foreach ( $schedule['games'] as $candidate ) {
			if ( $game_pk === (int) $candidate['game_pk'] ) {
				$game = $candidate;
				break;
			}
		}

		if ( null === $game ) {
			return new WP_Error( 'basebelles_today_game_mismatch', 'That game is not scheduled for today.', array( 'status' => 404 ) );
		}

		// See the matching comment in blocks/today-game/render.php: lineups/plays are on MLB's own
		// schedule, not tied to the Score tab's 30-minute countdown threshold, so always fetch.
		$phase       = Basebelles_Today_Game_Panels::get_phase( $game );
		$live_feed   = array();
		$feed_result = $api->get_live_feed( $game_pk );

		if ( ! is_wp_error( $feed_result ) ) {
			$live_feed = $feed_result;
		}

		return array(
			'phase'       => $phase,
			'header_score' => Basebelles_Today_Game_Panels::render_header_score( $game, $live_feed ),
			'panels'      => array(
				'score'   => Basebelles_Today_Game_Panels::render_score_panel( $game, $phase, $live_feed ),
				'plays'   => Basebelles_Today_Game_Panels::render_plays_panel( $live_feed ),
				'stats'   => Basebelles_Today_Game_Panels::render_stats_panel( $game, $phase, $live_feed ),
				'players' => Basebelles_Today_Game_Panels::render_players_panel( $game, $live_feed ),
			),
		);
	}
}

new Basebelles_Today_Game_Rest();
