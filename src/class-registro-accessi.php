<?php
/**
 * Il registro degli accessi.
 *
 * Righe di collaudo T-105 e T-106.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use DateTimeImmutable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Le richieste di accesso ricevute dall'ente, una riga ciascuna.
 *
 * L'Allegato 1 della delibera ANAC 1310/2016 chiede l'elenco delle richieste
 * di accesso, agli atti, civico e generalizzato, con l'argomento e la data
 * della richiesta, l'esito e la data della decisione. Lo tengono gli addetti:
 * le richieste arrivano all'ente per i suoi canali, non da un modulo del sito.
 * Nessun dato di chi chiede.
 */
final class RegistroAccessi {

	/**
	 * Opzione in cui si conserva il registro: l'ultimo numero dato e le righe.
	 */
	const OPZIONE = 'trasparenza_pa_registro_accessi';

	/**
	 * I campi di una riga, nell'ordine in cui si mostrano.
	 */
	const CAMPI = array( 'tipo', 'argomento', 'richiesta', 'esito', 'decisione' );

	/**
	 * I tipi di accesso, con il loro nome; quello mostrato e tradotto da etichetta_tipo().
	 */
	const TIPI = array(
		'documentale'   => 'Accesso agli atti (l. 241/1990)',
		'semplice'      => 'Accesso civico semplice',
		'generalizzato' => 'Accesso civico generalizzato',
	);

	/**
	 * Gli esiti, con il loro nome; vuoto vuol dire in corso. Quello mostrato e tradotto da etichetta_esito().
	 */
	const ESITI = array(
		''                 => 'In corso',
		'accolta'          => 'Accolta',
		'accolta_in_parte' => 'Accolta in parte',
		'rifiutata'        => 'Rifiutata',
		'differita'        => 'Differita',
	);

	/**
	 * Lunghezza massima dell'argomento.
	 */
	const ARGOMENTO_MASSIMO = 300;

	/**
	 * Controlla una riga e la restituisce com'e.
	 *
	 * Un campo che la pulizia cambierebbe si rifiuta invece di salvarlo cambiato.
	 *
	 * @param array<string, mixed> $inviati Campi inviati.
	 * @return array<string, string>|WP_Error
	 */
	public static function controlla( array $inviati ) {
		$valori = array();

		foreach ( self::CAMPI as $campo ) {
			if ( ! isset( $inviati[ $campo ] ) || ! is_string( $inviati[ $campo ] ) ) {
				return self::errore( __( 'I campi della richiesta devono esserci tutti, ed essere testi.', 'trasparenza-pa' ) );
			}

			$valori[ $campo ] = $inviati[ $campo ];
		}

		if ( ! array_key_exists( $valori['tipo'], self::TIPI ) ) {
			return self::errore( __( 'Il tipo della richiesta è uno fra accesso agli atti, accesso civico semplice e accesso civico generalizzato.', 'trasparenza-pa' ) );
		}

		if ( '' === $valori['argomento'] || sanitize_text_field( $valori['argomento'] ) !== $valori['argomento'] || mb_strlen( $valori['argomento'] ) > self::ARGOMENTO_MASSIMO ) {
			return self::errore( __( 'L\'argomento della richiesta è obbligatorio: su una riga, senza codice, senza spazi ai bordi e al massimo di 300 caratteri.', 'trasparenza-pa' ) );
		}

		if ( ! self::giorno( $valori['richiesta'] ) || strcmp( $valori['richiesta'], self::oggi() ) > 0 ) {
			return self::errore( __( 'La data della richiesta è obbligatoria, nella forma AAAA-MM-GG, esistente e non dopo oggi.', 'trasparenza-pa' ) );
		}

		if ( ! array_key_exists( $valori['esito'], self::ESITI ) ) {
			return self::errore( __( 'L\'esito della richiesta è uno fra in corso, accolta, accolta in parte, rifiutata e differita.', 'trasparenza-pa' ) );
		}

		if ( '' === $valori['esito'] ) {
			if ( '' !== $valori['decisione'] ) {
				return self::errore( __( 'Una richiesta in corso non ha ancora la data della decisione.', 'trasparenza-pa' ) );
			}

			return $valori;
		}

		if ( ! self::giorno( $valori['decisione'] ) || strcmp( $valori['decisione'], $valori['richiesta'] ) < 0 || strcmp( $valori['decisione'], self::oggi() ) > 0 ) {
			return self::errore( __( 'Con l\'esito serve la data della decisione, nella forma AAAA-MM-GG, esistente, non prima della richiesta e non dopo oggi.', 'trasparenza-pa' ) );
		}

		return $valori;
	}

	/**
	 * Il nome di un tipo, tradotto.
	 *
	 * @param string $tipo Tipo.
	 * @return string
	 */
	public static function etichetta_tipo( string $tipo ): string {
		switch ( $tipo ) {
			case 'documentale':
				return __( 'Accesso agli atti (l. 241/1990)', 'trasparenza-pa' );
			case 'semplice':
				return __( 'Accesso civico semplice', 'trasparenza-pa' );
			case 'generalizzato':
				return __( 'Accesso civico generalizzato', 'trasparenza-pa' );
		}

		return $tipo;
	}

	/**
	 * Il nome di un esito, tradotto.
	 *
	 * @param string $esito Esito.
	 * @return string
	 */
	public static function etichetta_esito( string $esito ): string {
		switch ( $esito ) {
			case '':
				return __( 'In corso', 'trasparenza-pa' );
			case 'accolta':
				return __( 'Accolta', 'trasparenza-pa' );
			case 'accolta_in_parte':
				return __( 'Accolta in parte', 'trasparenza-pa' );
			case 'rifiutata':
				return __( 'Rifiutata', 'trasparenza-pa' );
			case 'differita':
				return __( 'Differita', 'trasparenza-pa' );
		}

		return $esito;
	}

	/**
	 * Le righe da mostrare al pubblico nel giorno dato, per numero, prima le piu recenti.
	 *
	 * Una riga decisa si mostra per cinque anni dal 1 gennaio che segue la decisione (art. 8,
	 * c. 3, del d.lgs. 33/2013); una richiesta in corso finche non e decisa.
	 * Si calcola a ogni lettura: nessun compito pianificato toglie le righe scadute.
	 *
	 * @param string $oggi Giorno, AAAA-MM-GG.
	 * @return array<int, array<string, string>>
	 */
	public static function pubblicabili( string $oggi ): array {
		$ultimo_anno = (int) substr( $oggi, 0, 4 ) - 5;
		$righe       = array();

		foreach ( self::righe() as $numero => $riga ) {
			if ( is_wp_error( self::controlla( $riga ) ) ) {
				continue;
			}

			if ( '' !== $riga['decisione'] && (int) substr( $riga['decisione'], 0, 4 ) < $ultimo_anno ) {
				continue;
			}

			$righe[ $numero ] = $riga;
		}

		uksort(
			$righe,
			static function ( int $a, int $b ) use ( $righe ): int {
				$ordine = strcmp( $righe[ $b ]['richiesta'], $righe[ $a ]['richiesta'] );
				return 0 !== $ordine ? $ordine : $b <=> $a;
			}
		);

		return $righe;
	}

	/**
	 * Il registro per la pagina pubblica: una tabella, o la frase del registro vuoto.
	 *
	 * @return string
	 */
	public static function html(): string {
		$righe = self::pubblicabili( self::oggi() );
		$html  = '<div class="trasparenza-pa-registro-accessi"><h3>' . esc_html__( 'Registro degli accessi', 'trasparenza-pa' ) . '</h3>';

		if ( array() === $righe ) {
			return $html . '<p>' . esc_html__( 'Il registro non contiene richieste negli ultimi cinque anni.', 'trasparenza-pa' ) . '</p></div>';
		}

		$html .= '<table><thead><tr>';

		foreach ( array( __( 'Tipo', 'trasparenza-pa' ), __( 'Argomento', 'trasparenza-pa' ), __( 'Data della richiesta', 'trasparenza-pa' ), __( 'Esito', 'trasparenza-pa' ), __( 'Data della decisione', 'trasparenza-pa' ) ) as $titolo ) {
			$html .= '<th scope="col">' . esc_html( $titolo ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $righe as $riga ) {
			$html .= '<tr><td>' . esc_html( self::etichetta_tipo( $riga['tipo'] ) ) . '</td>';
			// L'argomento come e stato scritto: esc_html() lascerebbe com'e un'entita gia scritta.
			$html .= '<td>' . htmlspecialchars( $riga['argomento'], ENT_QUOTES, 'UTF-8', true ) . '</td>';
			$html .= '<td>' . esc_html( self::data( $riga['richiesta'] ) ) . '</td>';
			$html .= '<td>' . esc_html( self::etichetta_esito( $riga['esito'] ) ) . '</td>';
			$html .= '<td>' . esc_html( self::data( $riga['decisione'] ) ) . '</td></tr>';
		}

		return $html . '</tbody></table></div>';
	}

	/**
	 * Il giorno di oggi nel fuso del sito, AAAA-MM-GG.
	 *
	 * @return string
	 */
	public static function oggi(): string {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
	}

	/**
	 * Il registro conservato, letto dalla banca dati per chi deve riscriverlo.
	 *
	 * Errore se la lettura e fallita: con una lettura fallita un salvataggio
	 * crederebbe di partire da zero e cancellerebbe le altre righe. Le righe di
	 * forma strana non si restituiscono, ma il numero nuovo le supera.
	 *
	 * @return array{ultimo: int, righe: array<int, array<string, string>>}|WP_Error
	 */
	public static function conservato() {
		$valore = Opzione::leggi( self::OPZIONE );

		if ( is_wp_error( $valore ) ) {
			return $valore;
		}

		return self::in_forma( $valore );
	}

	/**
	 * Le righe conservate, per chi le mostra, anche quelle che non superano il controllo.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function righe(): array {
		return self::in_forma( get_option( self::OPZIONE, null ) )['righe'];
	}

	/**
	 * Il registro nella forma attesa: numeri positivi, cinque campi testuali per riga.
	 *
	 * @param mixed $valore Valore conservato.
	 * @return array{ultimo: int, righe: array<int, array<string, string>>}
	 */
	private static function in_forma( $valore ): array {
		$ultimo = 0;
		$righe  = array();

		if ( ! is_array( $valore ) ) {
			return array(
				'ultimo' => 0,
				'righe'  => array(),
			);
		}

		if ( isset( $valore['ultimo'] ) && is_int( $valore['ultimo'] ) && $valore['ultimo'] > 0 ) {
			$ultimo = $valore['ultimo'];
		}

		foreach ( is_array( $valore['righe'] ?? null ) ? $valore['righe'] : array() as $numero => $riga ) {
			if ( ! is_int( $numero ) || $numero < 1 ) {
				continue;
			}

			$ultimo = max( $ultimo, $numero );

			if ( ! is_array( $riga ) ) {
				continue;
			}

			$campi = array();

			foreach ( self::CAMPI as $campo ) {
				if ( ! isset( $riga[ $campo ] ) || ! is_string( $riga[ $campo ] ) ) {
					continue 2;
				}

				$campi[ $campo ] = $riga[ $campo ];
			}

			$righe[ $numero ] = $campi;
		}

		return array(
			'ultimo' => $ultimo,
			'righe'  => $righe,
		);
	}

	/**
	 * Una data AAAA-MM-GG esistente nel calendario.
	 *
	 * @param string $valore Valore.
	 * @return bool
	 */
	private static function giorno( string $valore ): bool {
		return 1 === preg_match( '/\A(\d{4})-(\d{2})-(\d{2})\z/', $valore, $parti ) && checkdate( (int) $parti[2], (int) $parti[3], (int) $parti[1] );
	}

	/**
	 * Una data AAAA-MM-GG nella forma GG/MM/AAAA; vuota resta vuota.
	 *
	 * @param string $giorno Data.
	 * @return string
	 */
	private static function data( string $giorno ): string {
		return '' === $giorno ? '' : substr( $giorno, 8, 2 ) . '/' . substr( $giorno, 5, 2 ) . '/' . substr( $giorno, 0, 4 );
	}

	/**
	 * Errore del controllo.
	 *
	 * @param string $messaggio Messaggio.
	 * @return WP_Error
	 */
	private static function errore( string $messaggio ): WP_Error {
		return new WP_Error( 'trasparenza_registro_accessi_dati', $messaggio );
	}
}
