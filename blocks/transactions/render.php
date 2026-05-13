<?php
/**
 * ACF Block: Latest Guardians transactions (MLB Stats API, last 30 days).
 *
 * @package Base*Belles
 *
 * @var array $block Block settings and attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_field' ) ) {
	return;
}

if ( ! class_exists( 'Basebelles_API' ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-transactions placeholder"><p>Transactions API integration is unavailable.</p></div>';
	}

	return;
}

$raw_limit = get_field( 'number_of_transactions' );
$limit     = is_numeric( $raw_limit ) ? (int) $raw_limit : 5;
if ( $limit < 1 ) {
	$limit = 1;
}
if ( $limit > Basebelles_API::TRANSACTIONS_DISPLAY_MAX ) {
	$limit = Basebelles_API::TRANSACTIONS_DISPLAY_MAX;
}

$api          = Basebelles_API::get_instance();
$transactions = $api->get_guardians_recent_transactions( $limit );

if ( is_wp_error( $transactions ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-transactions placeholder">';
		echo '<h3>Latest Transactions</h3>';
		echo '<p>Transaction data is unavailable right now.</p>';
		echo '</div>';
	}

	return;
}

if ( empty( $transactions ) ) {
	if ( is_admin() ) {
		echo '<div class="basebelles-transactions placeholder">';
		echo '<h3>Latest Transactions</h3>';
		echo '<p>No transactions in the last 30 days, or data is still loading.</p>';
		echo '</div>';
	}

	return;
}

echo '<ul class="basebelles-transactions">';

foreach ( $transactions as $row ) {
	$person = isset( $row['person'] ) && is_array( $row['person'] ) ? $row['person'] : array();
	$name   = isset( $person['fullName'] ) ? (string) $person['fullName'] : '';
	if ( '' === $name ) {
		$name = __( 'Unknown', 'basebelles' );
	}

	$effective = isset( $row['effectiveDate'] ) ? (string) $row['effectiveDate'] : ( isset( $row['date'] ) ? (string) $row['date'] : '' );
	$dmy       = '';
	if ( '' !== $effective && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $effective, $m ) ) {
		$dmy = $m[3] . '/' . $m[2];
	}

	$desc = isset( $row['description'] ) ? (string) $row['description'] : '';

	$line = $name;
	if ( '' !== $dmy ) {
		$line .= ' (' . $dmy . ')';
	}
	if ( '' !== $desc ) {
		$line .= ': ' . $desc;
	}

	echo '<li>' . esc_html( $line ) . '</li>';
}

echo '</ul>';
