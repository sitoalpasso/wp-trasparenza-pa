<?php
/**
 * I rinvii alle banche dati nazionali dichiarati dall'ente.
 *
 * Righe di collaudo T-94, T-95, T-97 e T-98.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use DateTimeImmutable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Per voce, il collegamento alla banca dati che assolve l'obbligo.
 *
 * L'art. 9-bis del d.lgs. 33/2013 permette di assolvere alcuni obblighi con il
 * collegamento alla banca dati nazionale a cui l'ente ha comunicato i dati.
 * Quali banche dati coprono quale voce lo dice il file dell'albero; qui
 * stanno le scelte dell'ente: la banca dati, l'indirizzo che porta al suo
 * dato, un'etichetta che dice cosa si trova dall'altra parte e il giorno da
 * cui rinvia. Nessun valore predefinito: una voce senza rinvio dichiarato
 * resta senza rinvio.
 */
final class Rinvii {

	/**
	 * Opzione in cui si conservano i rinvii, per voce.
	 */
	const OPZIONE = 'trasparenza_pa_rinvii';

	/**
	 * I campi di un rinvio, nell'ordine in cui si mostrano.
	 */
	const CAMPI = array( 'banca', 'indirizzo', 'etichetta', 'data' );

	/**
	 * Lunghezza massima dell'indirizzo.
	 */
	const INDIRIZZO_MASSIMO = 2000;

	/**
	 * Lunghezza massima dell'etichetta.
	 */
	const ETICHETTA_MASSIMA = 200;

	/**
	 * Un rinvio vuoto: nessun rinvio.
	 *
	 * @return array{banca: string, indirizzo: string, etichetta: string, data: string}
	 */
	public static function vuoto(): array {
		return array_fill_keys( self::CAMPI, '' );
	}

	/**
	 * Controlla il rinvio inviato per una voce e lo restituisce com'e.
	 *
	 * Vuoto in ogni campo vuol dire nessun rinvio. Un campo che la pulizia
	 * cambierebbe si rifiuta invece di salvarlo cambiato: chi scrive deve
	 * vedere salvato quello che ha scritto, o un errore.
	 *
	 * @param Alberatura           $albero  Albero in vigore.
	 * @param string               $voce    Voce.
	 * @param array<string, mixed> $inviati Campi inviati.
	 * @return array{banca: string, indirizzo: string, etichetta: string, data: string}|WP_Error
	 */
	public static function controlla( Alberatura $albero, string $voce, array $inviati ) {
		if ( array() === $albero->rinvii( $voce ) ) {
			return self::errore( __( 'Il file dell\'albero non lega questa voce a nessuna banca dati: per questa voce non si dichiara un rinvio.', 'trasparenza-pa' ) );
		}

		$valori = array();

		foreach ( self::CAMPI as $campo ) {
			if ( ! isset( $inviati[ $campo ] ) || ! is_string( $inviati[ $campo ] ) ) {
				return self::errore( __( 'I campi del rinvio devono esserci tutti, ed essere testi.', 'trasparenza-pa' ) );
			}

			$valori[ $campo ] = $inviati[ $campo ];
		}

		if ( '' === $valori['banca'] ) {
			if ( self::vuoto() !== $valori ) {
				return self::errore( __( 'Senza banca dati non c\'è rinvio: indirizzo, etichetta e data vanno lasciati vuoti.', 'trasparenza-pa' ) );
			}

			return $valori;
		}

		if ( ! in_array( $valori['banca'], array_column( $albero->rinvii( $voce ), 'banca' ), true ) ) {
			return self::errore( __( 'La banca dati scelta non è fra quelle che il file dell\'albero lega a questa voce.', 'trasparenza-pa' ) );
		}

		$schema_web = 1 === preg_match( '#^https?://#i', $valori['indirizzo'] );

		if ( ! $schema_web || strlen( $valori['indirizzo'] ) > self::INDIRIZZO_MASSIMO || esc_url_raw( $valori['indirizzo'], array( 'http', 'https' ) ) !== $valori['indirizzo'] ) {
			return self::errore( __( 'L\'indirizzo del rinvio è obbligatorio: un indirizzo web completo, che inizia con https:// o http://, al massimo di 2000 caratteri.', 'trasparenza-pa' ) );
		}

		if ( '' === $valori['etichetta'] || sanitize_text_field( $valori['etichetta'] ) !== $valori['etichetta'] || mb_strlen( $valori['etichetta'] ) > self::ETICHETTA_MASSIMA ) {
			return self::errore( __( 'L\'etichetta del rinvio è obbligatoria: su una riga, senza codice e al massimo di 200 caratteri. Dice cosa si trova dall\'altra parte del collegamento.', 'trasparenza-pa' ) );
		}

		$giorno = 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $valori['data'], $parti ) && checkdate( (int) $parti[2], (int) $parti[3], (int) $parti[1] );

		if ( ! $giorno ) {
			return self::errore( __( 'La data del rinvio è obbligatoria, nella forma AAAA-MM-GG, ed esistente nel calendario.', 'trasparenza-pa' ) );
		}

		if ( strcmp( $valori['data'], self::oggi() ) > 0 ) {
			return self::errore( __( 'La data del rinvio non può essere dopo oggi: è il giorno da cui il collegamento è nella sezione.', 'trasparenza-pa' ) );
		}

		return $valori;
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
	 * I rinvii conservati, letti dalla banca dati per chi deve riscriverli.
	 *
	 * Errore se la lettura e fallita: con una lettura fallita un salvataggio
	 * crederebbe di partire da zero e cancellerebbe i rinvii delle altre voci.
	 * Le righe che non hanno la forma di un rinvio non si restituiscono.
	 *
	 * @return array<string, array{banca: string, indirizzo: string, etichetta: string, data: string}>|WP_Error
	 */
	public static function conservati() {
		$valore = Opzione::leggi( self::OPZIONE );

		if ( is_wp_error( $valore ) ) {
			return $valore;
		}

		return self::in_forma( $valore );
	}

	/**
	 * I rinvii conservati, per chi li mostra.
	 *
	 * @return array<string, array{banca: string, indirizzo: string, etichetta: string, data: string}>
	 */
	public static function dati(): array {
		return self::in_forma( get_option( self::OPZIONE, array() ) );
	}

	/**
	 * Il riquadro pubblico del rinvio di una voce, o niente.
	 *
	 * Il rinvio conservato ripassa dal controllo con l'albero in vigore: uno
	 * che non lo supera non si mostra, come se non ci fosse. La frase
	 * sull'accesso civico c'e solo se il file dell'albero dice chi tiene la
	 * banca dati (art. 9-bis, cc. 3 e 4, del d.lgs. 33/2013).
	 *
	 * @param Alberatura $albero Albero in vigore.
	 * @param string     $voce   Voce.
	 * @return string
	 */
	public static function html( Alberatura $albero, string $voce ): string {
		$conservato = self::dati()[ $voce ] ?? null;

		if ( null === $conservato ) {
			return '';
		}

		$rinvio = self::controlla( $albero, $voce, $conservato );

		if ( is_wp_error( $rinvio ) || '' === $rinvio['banca'] ) {
			return '';
		}

		$banca = $albero->banca_dati( $rinvio['banca'] );

		if ( null === $banca ) {
			return '';
		}

		$titolare = $banca['titolare'] ?? null;
		$giorno   = DateTimeImmutable::createFromFormat( '!Y-m-d', $rinvio['data'], wp_timezone() );

		if ( false === $giorno ) {
			return '';
		}

		$html  = '<div class="trasparenza-pa-rinvio">';
		$html .= '<h3>' . esc_html__( 'Dati nella banca dati nazionale', 'trasparenza-pa' ) . '</h3>';
		$html .= '<p><a href="' . esc_url( $rinvio['indirizzo'] ) . '">' . esc_html( $rinvio['etichetta'] ) . '</a></p>';

		$html .= '<p>' . esc_html(
			null === $titolare
				/* translators: %s: nome della banca dati. */
				? sprintf( __( 'Banca dati: %s.', 'trasparenza-pa' ), $banca['nome'] )
				/* translators: 1: nome della banca dati, 2: chi la tiene. */
				: sprintf( __( 'Banca dati: %1$s, tenuta da %2$s.', 'trasparenza-pa' ), $banca['nome'], $titolare )
		) . '</p>';

		$html .= '<p>' . sprintf(
			/* translators: %s: giorno da cui l'ente rinvia. */
			esc_html__( 'Collegamento presente in questa voce dal %s (art. 9-bis del d.lgs. 33/2013).', 'trasparenza-pa' ),
			'<time datetime="' . esc_attr( $rinvio['data'] ) . '">' . esc_html( (string) wp_date( (string) get_option( 'date_format' ), $giorno->getTimestamp() ) ) . '</time>'
		) . '</p>';

		if ( null !== $titolare ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: %s: chi tiene la banca dati. */
					__( 'Se un dato comunicato alla banca dati non vi si trova, la richiesta di accesso civico va al responsabile della prevenzione della corruzione e della trasparenza di chi tiene la banca dati, %s; se la mancanza dipende da questo ente, va al responsabile di questo ente (art. 9-bis, commi 3 e 4, del d.lgs. 33/2013).', 'trasparenza-pa' ),
					$titolare
				)
			) . '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * Le sole righe che hanno la forma di un rinvio: voce testuale, i quattro campi testuali.
	 *
	 * @param mixed $valore Valore conservato.
	 * @return array<string, array{banca: string, indirizzo: string, etichetta: string, data: string}>
	 */
	private static function in_forma( $valore ): array {
		if ( ! is_array( $valore ) ) {
			return array();
		}

		$rinvii = array();

		foreach ( $valore as $voce => $rinvio ) {
			if ( ! is_string( $voce ) || ! is_array( $rinvio ) ) {
				continue;
			}

			$campi = array();

			foreach ( self::CAMPI as $campo ) {
				if ( ! isset( $rinvio[ $campo ] ) || ! is_string( $rinvio[ $campo ] ) ) {
					continue 2;
				}

				$campi[ $campo ] = $rinvio[ $campo ];
			}

			$rinvii[ $voce ] = $campi;
		}

		return $rinvii;
	}

	/**
	 * Errore del controllo.
	 *
	 * @param string $messaggio Messaggio.
	 * @return WP_Error
	 */
	private static function errore( string $messaggio ): WP_Error {
		return new WP_Error( 'trasparenza_rinvio_dati', $messaggio );
	}
}
