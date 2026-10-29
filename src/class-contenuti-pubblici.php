<?php
/**
 * I contenuti che la pagina pubblica mostra in una voce, con le date di ultimo aggiornamento.
 *
 * Righe di collaudo T-71..T-73.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Contenuti visibili, date di ultimo aggiornamento, HTML.
 *
 * Un contenuto compare solo se e pubblicato, senza password, collocato in una
 * voce che l'albero in vigore ammette e con una fine della pubblicazione non
 * ancora passata. La fine si guarda su ogni contenuto mentre la pagina si
 * disegna: la scadenza e una proprieta del dato letto, e un contenuto scaduto
 * sparisce anche se il compito pianificato non e mai passato. Chi amministra
 * vede sulla pagina pubblica quello che vede il pubblico, ne piu ne meno.
 */
final class ContenutiPubblici {

	/**
	 * I contenuti visibili collocati in una delle voci indicate, dal piu recente, ciascuno con la sua voce.
	 *
	 * Legge dalla banca dati con interrogazioni che distinguono "nessun
	 * contenuto" da "lettura fallita": una voce che sembra vuota perche la
	 * lettura non e riuscita farebbe passare per assente un dato che c'e.
	 *
	 * @param Alberatura         $albero Albero in vigore.
	 * @param array<int, string> $voci   Identificativi delle voci.
	 * @return array<int, array{contenuto: WP_Post, voce: string}>|WP_Error
	 */
	public static function visibili( Alberatura $albero, array $voci ) {
		global $wpdb;

		$voci = array_values( array_intersect( $voci, SchedaContenuto::voci_ammesse( $albero ) ) );

		if ( array() === $voci ) {
			return array();
		}

		$segnaposti = implode( ', ', array_fill( 0, count( $voci ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- serve sapere se la lettura e fallita, cosa che WP_Query non dice; i segnaposti sono uno per voce.
		$identificativi = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_password = '' AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = %s AND m.meta_value IN ( {$segnaposti} ) ) ORDER BY p.post_date DESC, p.ID DESC",
				array_merge( array( TIPO_CONTENUTO, SchedaContenuto::META_COLLOCAZIONE ), $voci )
			)
		);
		// phpcs:enable

		if ( '' !== $wpdb->last_error ) {
			return self::errore();
		}

		$visibili = array();

		foreach ( $identificativi as $id ) {
			$id        = (int) $id;
			$contenuto = get_post( $id );

			if ( ! $contenuto instanceof WP_Post ) {
				return self::errore();
			}

			$collocazione = Metadato::leggi( $id, SchedaContenuto::META_COLLOCAZIONE );
			$fine         = Metadato::leggi( $id, conformita_core_chiave_fine_pubblicazione() );

			if ( is_wp_error( $collocazione ) || is_wp_error( $fine ) ) {
				return self::errore();
			}

			if ( 1 !== count( $collocazione ) || ! in_array( $collocazione[0], $voci, true ) || ! self::visibile( $contenuto ) ) {
				continue;
			}

			$visibili[] = array(
				'contenuto' => $contenuto,
				'voce'      => $collocazione[0],
			);
		}

		return $visibili;
	}

	/**
	 * La lettura non e riuscita.
	 *
	 * @return WP_Error
	 */
	private static function errore(): WP_Error {
		return new WP_Error( 'trasparenza_lettura_fallita', __( 'I contenuti non si sono potuti leggere dalla banca dati.', 'trasparenza-pa' ) );
	}

	/**
	 * Il contenuto si mostra al pubblico, voce a parte.
	 *
	 * Ricontrolla sul contenuto caricato quello che l'interrogazione ha gia
	 * chiesto, e chiede la scadenza al componente comune.
	 *
	 * @param WP_Post $contenuto Contenuto.
	 * @return bool
	 */
	public static function visibile( WP_Post $contenuto ): bool {
		return TIPO_CONTENUTO === $contenuto->post_type
			&& 'publish' === $contenuto->post_status
			&& '' === $contenuto->post_password
			&& ! conformita_core_scaduto( $contenuto->ID );
	}

	/**
	 * La voce e tutte quelle sotto di lei.
	 *
	 * @param Alberatura $albero Albero.
	 * @param string     $voce   Voce.
	 * @return array<int, string>
	 */
	public static function sottoalbero( Alberatura $albero, string $voce ): array {
		$voci = array( $voce );

		foreach ( $albero->figli( $voce ) as $figlio ) {
			$voci = array_merge( $voci, self::sottoalbero( $albero, $figlio ) );
		}

		return $voci;
	}

	/**
	 * L'istante dell'ultimo aggiornamento fra i contenuti visibili, o nullo se non ce ne sono.
	 *
	 * @param array<int, array{contenuto: WP_Post, voce: string}> $contenuti Contenuti visibili.
	 * @return int|null Secondi dall'epoca.
	 */
	public static function ultimo_aggiornamento( array $contenuti ): ?int {
		$ultimo = null;

		foreach ( $contenuti as $riga ) {
			$istante = (int) get_post_modified_time( 'U', true, $riga['contenuto'] );
			$ultimo  = null === $ultimo ? $istante : max( $ultimo, $istante );
		}

		return $ultimo;
	}

	/**
	 * L'HTML di una voce: la sua data e i contenuti collocati proprio in lei.
	 *
	 * La data guarda i contenuti della voce e di tutte quelle sotto; l'elenco
	 * solo quelli della voce. Se la lettura non riesce, al posto di data e
	 * contenuti c'e un avviso: una voce che sembra vuota non deve essere il
	 * risultato di un guasto.
	 *
	 * @param Alberatura $albero Albero.
	 * @param string     $voce   Voce esistente.
	 * @return string
	 */
	public static function html( Alberatura $albero, string $voce ): string {
		$tutti = self::visibili( $albero, self::sottoalbero( $albero, $voce ) );

		if ( is_wp_error( $tutti ) ) {
			return '<p class="trasparenza-pa-indisponibile" role="alert">' . esc_html__( 'I contenuti di questa voce non sono disponibili in questo momento. Riprovare più tardi.', 'trasparenza-pa' ) . '</p>';
		}

		$ultimo = self::ultimo_aggiornamento( $tutti );

		if ( null === $ultimo ) {
			return '';
		}

		/* translators: %s: data. */
		$html = '<p class="trasparenza-pa-aggiornamento">' . esc_html( sprintf( __( 'Ultimo aggiornamento: %s', 'trasparenza-pa' ), self::giorno( $ultimo ) ) ) . '</p>';

		$propri = array_filter(
			$tutti,
			static function ( array $riga ) use ( $voce ): bool {
				return $riga['voce'] === $voce;
			}
		);

		if ( array() === $propri ) {
			return $html;
		}

		$html .= '<div class="trasparenza-pa-contenuti">';

		foreach ( $propri as $riga ) {
			$contenuto = $riga['contenuto'];

			$html .= '<article class="trasparenza-pa-contenuto">';
			$html .= '<h3>' . esc_html( get_the_title( $contenuto ) ) . '</h3>';
			$html .= wpautop( wp_kses_post( $contenuto->post_content ) );
			/* translators: %s: data. */
			$html .= '<p class="trasparenza-pa-aggiornamento">' . esc_html( sprintf( __( 'Aggiornato il %s', 'trasparenza-pa' ), self::giorno( (int) get_post_modified_time( 'U', true, $contenuto ) ) ) ) . '</p>';
			$html .= '</article>';
		}

		return $html . '</div>';
	}

	/**
	 * Un istante come giorno, nel formato e nel fuso del sito.
	 *
	 * @param int $istante Secondi dall'epoca.
	 * @return string
	 */
	public static function giorno( int $istante ): string {
		return (string) wp_date( (string) get_option( 'date_format' ), $istante );
	}
}
