<?php
/**
 * Lettura di un'opzione che distingue l'assenza dal guasto.
 *
 * Righe di collaudo T-51 e T-57.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * L'opzione come sta nella banca dati, o il motivo per cui non si sa.
 *
 * `get_option()` restituisce il valore predefinito sia quando l'opzione non
 * c'e sia quando la lettura fallisce. Per mostrare un dato va bene; per
 * riscriverlo no: un salvataggio che parte da una lettura fallita crede di
 * partire da zero e cancella quello che c'era. Chi salva legge da qui.
 */
final class Opzione {

	/**
	 * Il valore conservato, nullo se l'opzione non c'e, errore se la lettura e fallita.
	 *
	 * Si legge dalla banca dati e non dalla memoria della richiesta, che puo
	 * essere rimasta indietro rispetto a un'altra richiesta.
	 *
	 * @param string $nome Nome dell'opzione.
	 * @return mixed|null|WP_Error
	 */
	public static function leggi( string $nome ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve sapere se la lettura e fallita, cosa che get_option() non dice; il valore non si mette in memoria apposta.
		$valore = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $nome ) );

		if ( '' !== $wpdb->last_error ) {
			/* translators: %s: nome dell'opzione. */
			return new WP_Error( 'trasparenza_lettura_fallita', sprintf( __( 'L\'opzione %s non si è potuta leggere dalla banca dati.', 'trasparenza-pa' ), $nome ) );
		}

		return null === $valore ? null : maybe_unserialize( $valore );
	}
}
