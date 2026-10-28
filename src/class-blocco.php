<?php
/**
 * Il blocco della banca dati che tiene in fila i salvataggi della gestione.
 *
 * Righe di collaudo T-33 e T-37.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Un salvataggio della gestione alla volta.
 *
 * Due salvataggi contemporanei leggerebbero entrambi lo stesso punto di
 * partenza e l'ultimo cancellerebbe il primo, che pure e nel registro. Il
 * blocco e della banca dati, quindi vale fra richieste diverse; il nome porta
 * la banca dati e il prefisso, perche lo stesso server puo ospitare piu siti.
 * Le schermate della gestione condividono lo stesso blocco: i salvataggi sono
 * rari e brevi, e un solo nome evita di dover ragionare su chi blocca cosa.
 */
final class Blocco {

	/**
	 * Secondi di attesa prima di rinunciare.
	 */
	const ATTESA = 5;

	/**
	 * Prende il blocco, aspettando al massimo qualche secondo.
	 *
	 * @return bool
	 */
	public static function prendi(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- un blocco della banca dati non passa da nessuna funzione di WordPress e non si mette in memoria.
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::nome(), self::ATTESA ) );
	}

	/**
	 * Rilascia il blocco.
	 */
	public static function rilascia(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- come sopra.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::nome() ) );
	}

	/**
	 * Nome del blocco per questo sito.
	 *
	 * @internal Pubblico per le prove, che prendono il blocco da un'altra connessione.
	 *
	 * @return string
	 */
	public static function nome(): string {
		global $wpdb;

		return 'trasparenza_pa_' . md5( DB_NAME . '|' . $wpdb->prefix );
	}
}
