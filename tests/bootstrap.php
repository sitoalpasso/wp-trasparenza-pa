<?php
/**
 * Avvio della suite di test sulla suite di test di WordPress.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

$trasparenza_pa_polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

if ( file_exists( $trasparenza_pa_polyfills ) ) {
	require_once $trasparenza_pa_polyfills;
}

$trasparenza_pa_radice_test = getenv( 'WP_TESTS_DIR' );

if ( false === $trasparenza_pa_radice_test || '' === $trasparenza_pa_radice_test ) {
	$trasparenza_pa_radice_test = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $trasparenza_pa_radice_test . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		'Suite di test di WordPress non trovata in ' . $trasparenza_pa_radice_test . PHP_EOL
		. 'Impostare WP_TESTS_DIR oppure avviare i test dentro wp-env.' . PHP_EOL
	);
	exit( 1 );
}

require_once $trasparenza_pa_radice_test . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/trasparenza-pa.php';
	}
);

require $trasparenza_pa_radice_test . '/includes/bootstrap.php';
