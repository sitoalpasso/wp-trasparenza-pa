<?php
/**
 * Copia i file NOTICE delle librerie accanto alla loro copia rinominata.
 *
 * La licenza Apache-2.0 (sezione 4, lettera d) chiede di distribuire il file
 * NOTICE insieme all'opera. Lo strumento che rinomina le librerie copia il
 * testo della licenza ma non questo file: senza questo passaggio il pacchetto
 * distribuito non rispetterebbe la licenza della libreria che contiene.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

$radice     = dirname( __DIR__ );
$rinominate = $radice . '/vendor-prefixed';

if ( ! is_dir( $rinominate ) ) {
	fwrite( STDERR, "Cartella vendor-prefixed assente: eseguire prima strauss.\n" );

	exit( 1 );
}

foreach ( glob( $rinominate . '/*/*', GLOB_ONLYDIR ) as $pacchetto ) {
	$nome    = substr( $pacchetto, strlen( $rinominate ) + 1 );
	$origine = $radice . '/vendor/' . $nome . '/NOTICE';

	if ( is_file( $origine ) && ! copy( $origine, $pacchetto . '/NOTICE' ) ) {
		fwrite( STDERR, "Copia del NOTICE di {$nome} non riuscita.\n" );

		exit( 1 );
	}
}
