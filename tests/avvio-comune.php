<?php
/**
 * Parti comuni ai tre avvii della suite.
 *
 * Le prove dell'avvio hanno bisogno di tre ambienti diversi, e la differenza sta
 * tutta nel componente comune: presente e compatibile, assente, presente con una
 * versione di interfaccia incompatibile. La costante che dichiara quella versione
 * si definisce una volta sola per processo, quindi i tre ambienti sono tre avvii
 * separati che condividono questo file.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

/**
 * Percorso della suite di test di WordPress, o interrompe con una diagnosi.
 *
 * @return string
 */
function trasparenza_pa_dir_test(): string {
	$dir = getenv( 'WP_TESTS_DIR' );

	if ( ! $dir ) {
		$dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
	}

	if ( ! file_exists( $dir . '/includes/functions.php' ) ) {
		fwrite( STDERR, "Suite di test di WordPress non trovata in {$dir}.\n" );
		fwrite( STDERR, "Impostare WP_TESTS_DIR oppure eseguire bin/installa-suite-test.sh.\n" );

		exit( 1 );
	}

	return $dir;
}

/**
 * Percorso del file principale del componente comune, o stringa vuota.
 *
 * @return string
 */
function trasparenza_pa_file_core(): string {
	$core = getenv( 'CONFORMITA_CORE_FILE' );

	if ( ! $core ) {
		$radice = getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) : '/tmp/wordpress';
		$core   = $radice . '/wp-content/plugins/conformita-core/conformita-core.php';
	}

	return file_exists( $core ) ? $core : '';
}

/**
 * Prepara la suite, con o senza il componente comune.
 *
 * L'avvio automatico si puo spegnere. Nel caso della versione incompatibile
 * l'avvio della suite eseguirebbe la procedura una volta prima di ogni prova, e
 * il componente comune lascerebbe in bacheca un avviso agganciato con una
 * chiusura anonima, che non si puo togliere da fuori: la prova lo troverebbe e
 * lo scambierebbe per il proprio. Senza l'aggancio, l'unica esecuzione della
 * procedura in quel processo e quella che la prova fa di persona.
 *
 * @param bool $con_core         Carica il componente comune prima di questo.
 * @param bool $avvio_automatico Lascia agganciata la procedura di avvio a plugins_loaded.
 */
function trasparenza_pa_prepara_suite( bool $con_core, bool $avvio_automatico = true ): void {
	$dir_test = trasparenza_pa_dir_test();

	$polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

	if ( file_exists( $polyfills ) ) {
		require_once $polyfills;
	}

	require_once $dir_test . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $con_core, $avvio_automatico ): void {
			if ( $con_core ) {
				$core = trasparenza_pa_file_core();

				if ( '' === $core ) {
					fwrite( STDERR, "Componente comune non trovato: eseguire bin/installa-core.sh.\n" );

					exit( 1 );
				}

				require_once $core;
			}

			require dirname( __DIR__ ) . '/trasparenza-pa.php';

			require_once __DIR__ . '/ambiente-trasparenza.php';

			if ( ! $avvio_automatico ) {
				remove_action( 'plugins_loaded', array( 'TrasparenzaPa\\Avvio', 'da_plugins_loaded' ) );
			}
		}
	);

	require $dir_test . '/includes/bootstrap.php';
}
