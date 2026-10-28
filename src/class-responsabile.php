<?php
/**
 * Il responsabile della prevenzione della corruzione e della trasparenza.
 *
 * Righe di collaudo T-56..T-58.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * I dati del responsabile, il loro controllo e come si mostrano.
 *
 * L'art. 43 del d.lgs. 33/2013 vuole il responsabile indicato nella sezione. Il
 * nome e i recapiti li dichiara l'ente; il componente controlla che
 * siano dati sensati e li mostra nella voce che il file dell'albero indica.
 * Nessun valore predefinito: un responsabile non indicato resta non indicato,
 * e chi amministra la sezione lo vede.
 */
final class Responsabile {

	/**
	 * Opzione in cui si conservano i dati.
	 */
	const OPZIONE = 'trasparenza_pa_responsabile';

	/**
	 * Nome del dato nelle collocazioni del file dell'albero.
	 */
	const DATO = 'responsabile_trasparenza';

	/**
	 * I campi, nell'ordine in cui si mostrano.
	 */
	const CAMPI = array( 'nome', 'email', 'telefono', 'nomina' );

	/**
	 * Lunghezza massima del nome.
	 */
	const NOME_MASSIMO = 200;

	/**
	 * I dati dichiarati, o nullo se il responsabile non e indicato.
	 *
	 * Dati conservati che non superano il controllo valgono come non indicati:
	 * scritti a mano o da una versione diversa, non si mostrano al pubblico.
	 *
	 * @return array{nome: string, email: string, telefono: string, nomina: string}|null
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
	 * Nullo se il responsabile non e mai stato indicato o se i dati conservati
	 * non superano il controllo; errore se la lettura e fallita. `dati()` non
	 * basta a chi salva: con una lettura fallita risponderebbe "mai indicato",
	 * e il salvataggio registrerebbe come cambiati campi che non lo sono.
	 *
	 * @return array{nome: string, email: string, telefono: string, nomina: string}|null|WP_Error
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
	 * Controlla i dati inviati, tutti, e li restituisce ripuliti.
	 *
	 * Nome ed email obbligatori; telefono e atto di nomina facoltativi. Un
	 * campo che la pulizia cambierebbe si rifiuta invece di salvarlo cambiato:
	 * chi scrive deve vedere salvato quello che ha scritto, o un errore.
	 *
	 * @param array<string, mixed> $inviati Campi inviati.
	 * @return array{nome: string, email: string, telefono: string, nomina: string}|WP_Error
	 */
	public static function controlla( array $inviati ) {
		$valori = array();

		foreach ( self::CAMPI as $campo ) {
			$valore = $inviati[ $campo ] ?? '';

			if ( ! is_string( $valore ) ) {
				return self::errore( __( 'I dati del responsabile devono essere testi.', 'trasparenza-pa' ) );
			}

			$valori[ $campo ] = trim( $valore );
		}

		if ( '' === $valori['nome'] || sanitize_text_field( $valori['nome'] ) !== $valori['nome'] || mb_strlen( $valori['nome'] ) > self::NOME_MASSIMO ) {
			return self::errore( __( 'Il nome del responsabile è obbligatorio, su una riga, senza codice e al massimo di 200 caratteri.', 'trasparenza-pa' ) );
		}

		if ( ! is_email( $valori['email'] ) || sanitize_email( $valori['email'] ) !== $valori['email'] ) {
			return self::errore( __( 'L\'indirizzo email del responsabile è obbligatorio e deve essere un indirizzo valido.', 'trasparenza-pa' ) );
		}

		if ( '' !== $valori['telefono'] && 1 !== preg_match( '/^\+?[0-9][0-9 .\/-]{4,28}[0-9]$/', $valori['telefono'] ) ) {
			return self::errore( __( 'Il telefono del responsabile può contenere solo cifre, spazi, punti, barre, trattini e un + iniziale.', 'trasparenza-pa' ) );
		}

		$schema_web = 1 === preg_match( '#^https?://#i', $valori['nomina'] );

		if ( '' !== $valori['nomina'] && ( ! $schema_web || esc_url_raw( $valori['nomina'], array( 'http', 'https' ) ) !== $valori['nomina'] ) ) {
			return self::errore( __( 'L\'indirizzo dell\'atto di nomina deve essere un indirizzo web completo, che inizia con https:// o http://.', 'trasparenza-pa' ) );
		}

		return $valori;
	}

	/**
	 * HTML del riquadro pubblico, vuoto se il responsabile non e indicato.
	 *
	 * @return string
	 */
	public static function html(): string {
		$dati = self::dati();

		if ( null === $dati ) {
			return '';
		}

		$html  = '<div class="trasparenza-pa-responsabile">';
		$html .= '<h3>' . esc_html__( 'Responsabile della prevenzione della corruzione e della trasparenza', 'trasparenza-pa' ) . '</h3>';
		$html .= '<p>' . esc_html( $dati['nome'] ) . '</p><ul>';
		$html .= '<li>' . esc_html__( 'Email:', 'trasparenza-pa' ) . ' <a href="' . esc_url( 'mailto:' . $dati['email'] ) . '">' . esc_html( $dati['email'] ) . '</a></li>';

		if ( '' !== $dati['telefono'] ) {
			$html .= '<li>' . esc_html__( 'Telefono:', 'trasparenza-pa' ) . ' ' . esc_html( $dati['telefono'] ) . '</li>';
		}

		if ( '' !== $dati['nomina'] ) {
			$html .= '<li><a href="' . esc_url( $dati['nomina'] ) . '">' . esc_html__( 'Atto di nomina', 'trasparenza-pa' ) . '</a></li>';
		}

		return $html . '</ul></div>';
	}

	/**
	 * Errore del controllo.
	 *
	 * @param string $messaggio Messaggio.
	 * @return WP_Error
	 */
	private static function errore( string $messaggio ): WP_Error {
		return new WP_Error( 'trasparenza_responsabile_dati', $messaggio );
	}
}
