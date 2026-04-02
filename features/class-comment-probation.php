<?php
/*
Plugin Name: Comment Probation
Description: Puts a comment author "on probation," approving that comment, but not automatically approving future comments until one of their comments is approved without probation.
Version: 4.0
Author: Mika Epstein, Andrew Nacin

Fork of http://wordpress.org/extend/plugins/comment-probation/
Forked to update for WP 6.0 and onward.

This file is part of Comment Probation, a plugin for WordPress.

Comment Probation is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

Comment Probation is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WordPress.  If not, see <http://www.gnu.org/licenses/>.
*/

class HELF_Comment_Probation {

	const META_KEY = '_comment_probation';
	public static $instance;

	public function __construct() {
		self::$instance = $this;
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	public function plugins_loaded() {

		if ( ! get_option( 'comment_previously_approved' ) ) {
			return;
		}

		add_action( 'load-options-discussion.php', array( $this, 'load_discussion_page' ) );
		add_action( 'admin_head-edit-comments.php', array( $this, 'admin_head' ) );
		add_filter( 'pre_comment_approved', array( $this, 'pre_comment_approved' ), 10, 2 );
		add_filter( 'comment_row_actions', array( $this, 'comment_row_actions' ), 10, 2 );
		add_action( 'wp_set_comment_status', array( $this, 'wp_set_comment_status' ), 10, 2 );
	}

	public function load_discussion_page() {
		add_action( 'gettext', array( $this, 'gettext' ), 10, 3 );
	}

	public function gettext( $translated, $original, $domain ) {
		if ( 'Comment author must have a previously approved comment' !== $original ) {
			return $translated;
		}

		return sprintf( 'Comment author must have a previously approved comment (<a href="%s">and not be on probation</a>)', network_admin_url( 'plugins.php' ) . '#comment-probation' );
	}

	public function wp_set_comment_status( $comment_id, $status ) {
		global $wpdb;
		if ( '1' !== $status && 'approve' !== $status ) {
			return;
		}

		if ( ! empty( $_POST['probation'] ) ) { // phpcs:disable WordPress.Security.NonceVerification
			update_comment_meta( $comment_id, self::META_KEY, '1' );
		} else {
			$commentdata = get_comment( $comment_id );
			$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_author = %s AND comment_author_email = %s AND comment_approved = '1'", $commentdata->comment_author, $commentdata->comment_author_email ) );

			foreach ( $comment_ids as $comment_id ) {
				delete_comment_meta( $comment_id, self::META_KEY );
			}
		}
	}

	public function admin_head() {
		echo '<style>.comment-probation { display: none; } tr.unapproved .comment-probation { display: inline }</style>' . "\n";
	}

	public function pre_comment_approved( $approved, $commentdata ) {
		global $wpdb;

		// If we're not approving the comment, keep going.
		if ( 1 !== $approved ) {
			return $approved;
		}

		// Logged-in users get a pass.
		if ( is_user_logged_in() ) {
			return $approved;
		}

		// The only other situation is check_comment() returning true.
		$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_author = %s AND comment_author_email = %s AND comment_approved = '1'", $commentdata['comment_author'], $commentdata['comment_author_email'] ) );

		// This shouldn't happen...
		if ( ! $comment_ids ) {
			return 0; // or return $approved?
		}

		$comment_ids = implode( ', ', $comment_ids );
		$commentmeta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->commentmeta WHERE comment_id IN (%s) AND META_KEY = %s LIMIT 1", $comment_ids, self::META_KEY ) );

		// This user is on probation. Tsk tsk.
		if ( $commentmeta ) {
			add_action( 'comment_post', array( $this, 'comment_post' ) );
			return 0;
		} else {
			return 1;
		}
	}

	public function comment_post( $comment_id ) {
		update_comment_meta( $comment_id, self::META_KEY, '1' );
	}

	public function comment_row_actions( $actions, $comment ) {
		if ( ! isset( $actions['approve'] ) ) {
			return $actions;
		}

		$probation = str_replace( 'action=approvecomment', 'action=approvecomment&amp;probation=1', $actions['approve'] );
		preg_match( '/^(.*?>)/', $probation, $matches );
		$probation  = str_replace( array( ':new=approved', ' vim-a' ), array( ':new=approved&probation=1', '' ), $matches[1] );
		$probation .= 'Approve with Probation</a>';

		$actions['approve'] .= '<span class="comment-probation"> | ' . $probation . '</span>';

		return $actions;
	}
}

new HELF_Comment_Probation();