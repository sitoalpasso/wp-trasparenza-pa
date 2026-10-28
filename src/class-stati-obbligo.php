<?php
/**
 * Lo stato dell'obbligo, voce per voce, come lo dichiara l'ente.
 *
 * Righe di collaudo T-13 e T-14.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Tre stati dichiarati e un quarto che non si dichiara: "non dichiarato".
 *
 * Non c'e un valore predefinito. "Applicabile" come predefinito farebbe passare
 * per conforme una voce vuota che nessuno ha guardato, e una sottosezione vuota
 * senza stato non e conforme (TRASP-27). La voce senza stato resta tale finche
 * qualcuno non sceglie, e compare nell'elenco delle non conformi.
 */
final class StatiObbligo {

	/**
	 * L'obbligo si applica e la voce va popolata.
	 */
	const APPLICABILE = 'applicabile';

	/**
	 * L'obbligo non si applica all'ente: si stampa la dicitura.
	 */
	const NON_APPLICABILE = 'non_applicabile';

	/**
	 * L'obbligo e sospeso da una norma o da un atto dell'Autorita.
	 */
	const SOSPESO = 'sospeso';

	/**
	 * Opzione in cui si conservano gli stati dichiarati.
	 */
	const OPZIONE = 'trasparenza_pa_stati_obbligo';

	/**
	 * I tre stati che si possono dichiarare.
	 *
	 * @return array<int, string>
	 */
	public static function ammessi(): array {
		return array( self::APPLICABILE, self::NON_APPLICABILE, self::SOSPESO );
	}

	/**
	 * Stato dichiarato di una voce, o nullo se non dichiarato.
	 *
	 * Un valore conservato che non e fra i tre ammessi vale come non
	 * dichiarato: lo si e scritto a mano o con una versione diversa, e non si
	 * indovina cosa volesse dire.
	 *
	 * @param string $voce Identificativo della voce.
	 * @return string|null
	 */
	public static function stato( string $voce ): ?string {
		return self::valido( self::tutti()[ $voce ] ?? null );
	}

	/**
	 * Un valore conservato come stato, o nullo se non e uno dei tre.
	 *
	 * @param mixed $valore Valore conservato.
	 * @return string|null
	 */
	public static function valido( $valore ): ?string {
		return is_string( $valore ) && in_array( $valore, self::ammessi(), true ) ? $valore : null;
	}

	/**
	 * Dichiara lo stato di una voce dell'albero.
	 *
	 * Chi puo chiamarla lo decide la schermata che la usa: questa funzione
	 * controlla il dato, non il permesso.
	 *
	 * Si parte dagli stati letti dalla banca dati, non da `get_option()`: con
	 * una lettura fallita quella restituirebbe un elenco vuoto, e la scrittura
	 * cancellerebbe le dichiarazioni delle altre voci. Dopo la scrittura gli
	 * stati si rileggono tutti, perche il valore restituito da `update_option()`
	 * non basta: e falso anche quando il valore era gia quello, e non dice
	 * niente di un filtro che ha cambiato la scrittura. Conta quello che e
	 * conservato, non il tentativo.
	 *
	 * @param Alberatura $albero Albero di riferimento.
	 * @param string     $voce   Identificativo della voce.
	 * @param string     $stato  Uno dei tre stati ammessi.
	 * @return true|WP_Error
	 */
	public static function imposta( Alberatura $albero, string $voce, string $stato ) {
		if ( ! $albero->esiste( $voce ) ) {
			/* translators: %s: identificativo della voce. */
			return new WP_Error( 'trasparenza_stato_voce', sprintf( __( 'La voce %s non esiste nell\'albero della sezione.', 'trasparenza-pa' ), $voce ) );
		}

		if ( ! in_array( $stato, self::ammessi(), true ) ) {
			return new WP_Error( 'trasparenza_stato_valore', __( 'Lo stato dell\'obbligo deve essere applicabile, non applicabile o sospeso.', 'trasparenza-pa' ) );
		}

		$stati = self::conservati();

		if ( is_wp_error( $stati ) ) {
			return $stati;
		}

		$stati[ $voce ] = $stato;

		update_option( self::OPZIONE, $stati );

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( self::OPZIONE, 'options' );

		if ( self::conservati() !== $stati ) {
			/* translators: %s: identificativo della voce. */
			return new WP_Error( 'trasparenza_stato_non_scritto', sprintf( __( 'Lo stato della voce %s non risulta scritto nella banca dati.', 'trasparenza-pa' ), $voce ) );
		}

		return true;
	}

	/**
	 * Voci dell'albero senza uno stato dichiarato, nell'ordine dell'albero.
	 *
	 * La radice non porta obblighi propri e non si elenca.
	 *
	 * @param Alberatura $albero Albero di riferimento.
	 * @return array<int, string>
	 */
	public static function non_dichiarate( Alberatura $albero ): array {
		return array_values(
			array_filter(
				$albero->identificativi(),
				static function ( string $voce ) use ( $albero ): bool {
					return $voce !== $albero->radice() && null === self::stato( $voce );
				}
			)
		);
	}

	/**
	 * Gli stati conservati, letti dalla banca dati per chi deve riscriverli.
	 *
	 * Opzione assente: nessuno stato dichiarato. Lettura fallita, o valore che
	 * non e un elenco: errore, perche riscrivere da li cancellerebbe quello che
	 * c'e.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function conservati() {
		$stati = Opzione::leggi( self::OPZIONE );

		if ( is_wp_error( $stati ) ) {
			return $stati;
		}

		if ( null === $stati ) {
			return array();
		}

		if ( ! is_array( $stati ) ) {
			return new WP_Error( 'trasparenza_stati_illeggibili', __( 'Gli stati conservati non sono un elenco: non si riscrivono per non cancellare quello che c\'è.', 'trasparenza-pa' ) );
		}

		return $stati;
	}

	/**
	 * Gli stati conservati, come stanno nell'opzione, per chi li mostra.
	 *
	 * @return array<string, mixed>
	 */
	private static function tutti(): array {
		$stati = get_option( self::OPZIONE, array() );

		return is_array( $stati ) ? $stati : array();
	}
}
