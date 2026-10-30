<?php
/**
 * A chi si chiede l'accesso civico.
 *
 * Righe di collaudo T-100..T-102.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * L'ufficio e il titolare del potere sostitutivo, il loro controllo.
 *
 * L'art. 5, c. 3, del d.lgs. 33/2013 manda la richiesta generalizzata a un
 * ufficio che l'ente puo indicare nella sezione; l'art. 2, c. 9-bis, della l.
 * 241/1990 da a chi non riceve risposta il titolare del potere sostitutivo.
 * L'Allegato 1 della delibera ANAC 1310/2016 chiede di pubblicarli con
 * telefono ed email. Li dichiara l'ente; nessun valore predefinito: dati non
 * indicati restano non indicati, e chi amministra la sezione lo vede.
 */
final class AccessoCivico {

	/**
	 * Opzione in cui si conservano i dati.
	 */
	const OPZIONE = 'trasparenza_pa_accesso_civico';

	/**
	 * Nome del dato nelle collocazioni del file dell'albero.
	 */
	const DATO = 'accesso_civico';

	/**
	 * I campi, nell'ordine in cui si mostrano.
	 */
	const CAMPI = array( 'ufficio', 'ufficio_email', 'ufficio_telefono', 'sostituto', 'sostituto_email', 'sostituto_telefono', 'modulo' );

	/**
	 * Lunghezza massima dei nomi.
	 */
	const NOME_MASSIMO = 200;

	/**
	 * Lunghezza massima dell'indirizzo del modulo.
	 */
	const INDIRIZZO_MASSIMO = 2000;

	/**
	 * I dati dichiarati, o nullo se non sono indicati.
	 *
	 * Dati conservati che non superano il controllo valgono come non indicati.
	 *
	 * @return array<string, string>|null
	 */
	public static function dati(): ?array {
		$dati = get_option( self::OPZIONE, null );

		if ( ! is_array( $dati ) ) {
			return null;
		}

		$controllati = self::controlla( $dati );

		return is_wp_error( $controllati ) ? null : $controllati;
	}

	/**
	 * I dati conservati, letti dalla banca dati per chi deve riscriverli.
	 *
	 * Nullo se mai indicati o non validi; errore se la lettura e fallita.
	 *
	 * @return array<string, string>|null|WP_Error
	 */
	public static function conservati() {
		$dati = Opzione::leggi( self::OPZIONE );

		if ( is_wp_error( $dati ) ) {
			return $dati;
		}

		if ( ! is_array( $dati ) ) {
			return null;
		}

		$controllati = self::controlla( $dati );

		return is_wp_error( $controllati ) ? null : $controllati;
	}

	/**
	 * Controlla i dati inviati, tutti, e li restituisce ripuliti dagli spazi ai bordi.
	 *
	 * Tutto obbligatorio salvo l'indirizzo del modulo. Un campo che la pulizia
	 * cambierebbe si rifiuta invece di salvarlo cambiato.
	 *
	 * @param array<string, mixed> $inviati Campi inviati.
	 * @return array<string, string>|WP_Error
	 */
	public static function controlla( array $inviati ) {
		$valori = array();

		foreach ( self::CAMPI as $campo ) {
			$valore = $inviati[ $campo ] ?? '';

			if ( ! is_string( $valore ) ) {
				return self::errore( __( 'I dati dell\'accesso civico devono essere testi.', 'trasparenza-pa' ) );
			}

			$valori[ $campo ] = trim( $valore );
		}

		foreach ( array( 'ufficio', 'sostituto' ) as $campo ) {
			if ( '' === $valori[ $campo ] || sanitize_text_field( $valori[ $campo ] ) !== $valori[ $campo ] || mb_strlen( $valori[ $campo ] ) > self::NOME_MASSIMO ) {
				return self::errore( __( 'L\'ufficio e il titolare del potere sostitutivo sono obbligatori, ciascuno su una riga, senza codice e al massimo di 200 caratteri.', 'trasparenza-pa' ) );
			}

			if ( ! is_email( $valori[ $campo . '_email' ] ) || sanitize_email( $valori[ $campo . '_email' ] ) !== $valori[ $campo . '_email' ] ) {
				return self::errore( __( 'Gli indirizzi email dell\'ufficio e del titolare del potere sostitutivo sono obbligatori e devono essere indirizzi validi.', 'trasparenza-pa' ) );
			}

			if ( 1 !== preg_match( '/^\+?[0-9][0-9 .\/-]{4,28}[0-9]$/', $valori[ $campo . '_telefono' ] ) ) {
				return self::errore( __( 'I telefoni dell\'ufficio e del titolare del potere sostitutivo sono obbligatori e possono contenere solo cifre, spazi, punti, barre, trattini e un + iniziale.', 'trasparenza-pa' ) );
			}
		}

		$schema_web = 1 === preg_match( '#^https?://#i', $valori['modulo'] );

		if ( '' !== $valori['modulo'] && ( ! $schema_web || strlen( $valori['modulo'] ) > self::INDIRIZZO_MASSIMO || esc_url_raw( $valori['modulo'], array( 'http', 'https' ) ) !== $valori['modulo'] ) ) {
			return self::errore( __( 'L\'indirizzo del modulo deve essere un indirizzo web completo, che inizia con https:// o http://, al massimo di 2000 caratteri.', 'trasparenza-pa' ) );
		}

		return $valori;
	}

	/**
	 * Errore del controllo.
	 *
	 * @param string $messaggio Messaggio.
	 * @return WP_Error
	 */
	private static function errore( string $messaggio ): WP_Error {
		return new WP_Error( 'trasparenza_accesso_civico_dati', $messaggio );
	}
}
