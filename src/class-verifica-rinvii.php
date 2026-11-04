<?php
/**
 * I rinvii affidati al verificatore dei collegamenti del componente comune.
 *
 * Righe di collaudo T-163..T-166. Scheda: `docs/implementazione/verifica-rinvii.md`.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Affidamento, riallineamento ed esito della verifica periodica dei rinvii.
 *
 * Un rinvio a una banca dati assolve l'obbligo solo se il collegamento
 * risponde (art. 9-bis del d.lgs. 33/2013): il componente comune lo
 * ricontrolla da solo, qui si decide cosa affidargli e come mostrarne l'esito.
 */
final class VerificaRinvii {

	/**
	 * Intervallo di verifica, in giorni: il piu breve che il verificatore ammette.
	 *
	 * Controllare piu spesso non e mai meno conforme, quindi questo valore non
	 * puo essere sbagliato per nessun ente.
	 */
	const INTERVALLO = 1;

	/**
	 * Codice dell'errore del riallineamento saltato per un salvataggio in corso.
	 */
	const OCCUPATO = 'trasparenza_rinvii_occupato';

	/**
	 * La chiave della voce presso il verificatore.
	 *
	 * Il verificatore accetta solo minuscole, le voci ammettono anche le
	 * maiuscole: una voce gia in minuscolo resta com'e, le altre diventano
	 * `v-` piu l'impronta. Il trattino non compare negli identificativi delle
	 * voci, quindi le due forme non si confondono.
	 *
	 * @param string $voce Voce.
	 * @return string
	 */
	public static function chiave( string $voce ): string {
		return 1 === preg_match( '/\A[a-z0-9_]{1,100}\z/', $voce ) ? $voce : 'v-' . md5( $voce );
	}

	/**
	 * Affida il rinvio della voce al verificatore, o lo toglie se il rinvio e vuoto.
	 *
	 * @param string                $voce   Voce.
	 * @param array<string, string> $rinvio Rinvio controllato, vuoto se tolto.
	 * @return true|WP_Error
	 */
	public static function affida( string $voce, array $rinvio ) {
		if ( Rinvii::vuoto() === $rinvio || '' === ( $rinvio['banca'] ?? '' ) ) {
			return conformita_core_togli_collegamento( SEZIONE, self::chiave( $voce ) );
		}

		return conformita_core_registra_collegamento( SEZIONE, self::chiave( $voce ), $rinvio['indirizzo'], self::INTERVALLO );
	}

	/**
	 * Rimette il verificatore in linea con i rinvii conservati e l'albero in vigore.
	 *
	 * Ogni voce con un rinvio conservato o legata dall'albero a una banca dati:
	 * il rinvio valido si affida, gli altri si tolgono. Con la lettura dei
	 * rinvii fallita non si fa niente, perche si toglierebbero rinvii validi.
	 *
	 * Si fa con il blocco dei salvataggi: senza, un salvataggio arrivato fra
	 * la lettura dei rinvii e l'affidamento verrebbe coperto dal rinvio letto
	 * prima. Se il blocco e preso, non si fa niente e lo si dice.
	 *
	 * @param Alberatura $albero Albero in vigore.
	 * @return true|WP_Error Errore se il blocco e preso o i rinvii non si sono letti.
	 */
	public static function riallinea( Alberatura $albero ) {
		if ( ! Blocco::prendi() ) {
			return new WP_Error( self::OCCUPATO, __( 'Un salvataggio dei rinvii è in corso: la verifica periodica si riallinea alla prossima apertura di questa schermata.', 'trasparenza-pa' ) );
		}

		try {
			return self::riallinea_con_blocco( $albero );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Il riallineamento, con il blocco gia preso.
	 *
	 * @param Alberatura $albero Albero in vigore.
	 * @return true|WP_Error Errore se i rinvii non si sono letti.
	 */
	private static function riallinea_con_blocco( Alberatura $albero ) {
		$conservati = Rinvii::conservati();

		if ( is_wp_error( $conservati ) ) {
			return $conservati;
		}

		$voci = array_keys( $conservati );

		foreach ( $albero->identificativi() as $voce ) {
			if ( array() !== $albero->rinvii( $voce ) ) {
				$voci[] = $voce;
			}
		}

		foreach ( array_unique( $voci ) as $voce ) {
			$rinvio = self::valido( $albero, (string) $voce, $conservati[ $voce ] ?? null );

			// Un guasto su una voce non ferma le altre: la voce resta da guardare.
			self::affida( (string) $voce, $rinvio ?? Rinvii::vuoto() );
		}

		return true;
	}

	/**
	 * Il rinvio conservato se vale con l'albero in vigore e non e vuoto, altrimenti nullo.
	 *
	 * @param Alberatura $albero     Albero in vigore.
	 * @param string     $voce       Voce.
	 * @param mixed      $conservato Rinvio conservato.
	 * @return array<string, string>|null
	 */
	public static function valido( Alberatura $albero, string $voce, $conservato ): ?array {
		if ( ! is_array( $conservato ) ) {
			return null;
		}

		$rinvio = Rinvii::controlla( $albero, $voce, $conservato );

		return is_wp_error( $rinvio ) || '' === $rinvio['banca'] ? null : $rinvio;
	}

	/**
	 * L'esito della verifica del rinvio di una voce, per la schermata.
	 *
	 * @param string                $voce   Voce.
	 * @param array<string, string> $rinvio Rinvio valido conservato.
	 * @return array{testo: string, da_guardare: string}
	 */
	public static function esito( string $voce, array $rinvio ): array {
		$letto = conformita_core_collegamento( SEZIONE, self::chiave( $voce ) );

		if ( is_wp_error( $letto ) ) {
			$testo = __( 'L\'esito dell\'ultimo controllo non si è potuto leggere dalla banca dati.', 'trasparenza-pa' );

			return array(
				'testo'       => $testo,
				'da_guardare' => $testo,
			);
		}

		if ( ! is_array( $letto ) || $letto['indirizzo'] !== $rinvio['indirizzo'] ) {
			$testo = __( 'Non ancora affidato alla verifica periodica: si riprova alla prossima apertura di questa schermata.', 'trasparenza-pa' );

			return array(
				'testo'       => $testo,
				'da_guardare' => $testo,
			);
		}

		$guardare = array();

		if ( 'mai' === $letto['esito'] ) {
			$testo = __( 'Mai controllato: il primo controllo avviene entro qualche ora.', 'trasparenza-pa' );
		} else {
			$quando = self::quando( (string) $letto['verificato'] );

			if ( 'raggiungibile' === $letto['esito'] ) {
				/* translators: %s: data e ora dell'ultimo controllo. */
				$testo = sprintf( __( 'Risponde: ultimo controllo il %s.', 'trasparenza-pa' ), $quando );
			} else {
				/* translators: 1: data e ora dell'ultimo controllo, 2: motivo. */
				$testo      = sprintf( __( 'Non risponde: ultimo controllo il %1$s. %2$s', 'trasparenza-pa' ), $quando, (string) $letto['motivo'] );
				$guardare[] = __( 'il collegamento non risponde, e finché non risponde l\'obbligo non è assolto', 'trasparenza-pa' );
			}
		}

		if ( true === $letto['in_ritardo'] ) {
			$testo     .= ' ' . __( 'Controllo in ritardo: il controllo periodico potrebbe essere fermo.', 'trasparenza-pa' );
			$guardare[] = __( 'il controllo è in ritardo', 'trasparenza-pa' );
		}

		return array(
			'testo'       => trim( $testo ),
			'da_guardare' => array() === $guardare ? '' : ucfirst( implode( '; ', $guardare ) ) . '.',
		);
	}

	/**
	 * Data e ora di un istante UTC nel fuso e nei formati del sito.
	 *
	 * @param string $utc Istante `Y-m-d H:i:s` in UTC.
	 * @return string
	 */
	private static function quando( string $utc ): string {
		$istante = strtotime( $utc . ' UTC' );

		if ( false === $istante ) {
			return $utc;
		}

		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $istante );
	}
}
