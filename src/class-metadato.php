<?php
/**
 * Lettura di un metadato che distingue il dato assente dalla lettura fallita.
 *
 * Riga di collaudo T-61.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * I valori di un metadato letti dalla banca dati, con l'errore quando la lettura fallisce.
 *
 * `get_post_meta()` restituisce un testo vuoto sia quando il metadato non c'e
 * sia quando la lettura fallisce: chi decide una scrittura da quel valore
 * prenderebbe un guasto per un dato mai scritto. Stessa ragione di `Opzione`.
 */
final class Metadato {

	/**
	 * Tutti i valori di un metadato di un contenuto, nell'ordine di scrittura.
	 *
	 * Un elenco vuoto vuol dire metadato assente. WordPress ammette piu righe
	 * con la stessa chiave: chi legge decide che cosa fare se sono piu di una.
	 *
	 * @param int    $contenuto Identificativo del contenuto.
	 * @param string $chiave    Chiave del metadato.
	 * @return array<int, string>|WP_Error
	 */
	public static function leggi( int $contenuto, string $chiave ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve sapere se la lettura e fallita, cosa che get_post_meta() non dice; il valore non si mette in memoria apposta.
		$valori = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $contenuto, $chiave ) );

		if ( '' !== $wpdb->last_error ) {
			/* translators: %s: chiave del metadato. */
			return new WP_Error( 'trasparenza_lettura_fallita', sprintf( __( 'Il dato %s non si è potuto leggere dalla banca dati.', 'trasparenza-pa' ), $chiave ) );
		}

		return array_map( 'strval', $valori );
	}
}
