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

use WP_Post;
use WP_Query;

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
	 * I contenuti visibili collocati in una delle voci indicate, dal piu recente.
	 *
	 * @param array<int, string> $voci Identificativi delle voci.
	 * @return array<int, WP_Post>
	 */
	public static function visibili( array $voci ): array {
		if ( array() === $voci ) {
			return array();
		}

		$interrogazione = new WP_Query(
			array(
				'post_type'        => TIPO_CONTENUTO,
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- la voce e la sola chiave che dice dove sta un contenuto.
					array(
						'key'     => SchedaContenuto::META_COLLOCAZIONE,
						'value'   => $voci,
						'compare' => 'IN',
					),
				),
				'suppress_filters' => false,
			)
		);

		$visibili = array();

		foreach ( $interrogazione->posts as $contenuto ) {
			if ( ! $contenuto instanceof WP_Post || ! self::visibile( $contenuto ) ) {
				continue;
			}

			if ( in_array( SchedaContenuto::collocazione( $contenuto->ID ), $voci, true ) ) {
				$visibili[] = $contenuto;
			}
		}

		return $visibili;
	}

	/**
	 * Il contenuto si mostra al pubblico, voce a parte.
	 *
	 * Ricontrolla tutto quello che l'interrogazione ha gia chiesto: un filtro
	 * di un altro componente puo cambiare l'interrogazione, non questo.
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
	 * @param array<int, WP_Post> $contenuti Contenuti visibili.
	 * @return int|null Secondi dall'epoca.
	 */
	public static function ultimo_aggiornamento( array $contenuti ): ?int {
		$ultimo = null;

		foreach ( $contenuti as $contenuto ) {
			$istante = (int) get_post_modified_time( 'U', true, $contenuto );
			$ultimo  = null === $ultimo ? $istante : max( $ultimo, $istante );
		}

		return $ultimo;
	}

	/**
	 * L'HTML di una voce: la sua data e i contenuti collocati proprio in lei.
	 *
	 * La data guarda i contenuti della voce e di tutte quelle sotto; l'elenco
	 * solo quelli della voce.
	 *
	 * @param Alberatura $albero Albero.
	 * @param string     $voce   Voce esistente.
	 * @return string
	 */
	public static function html( Alberatura $albero, string $voce ): string {
		$tutti  = self::visibili( self::sottoalbero( $albero, $voce ) );
		$ultimo = self::ultimo_aggiornamento( $tutti );

		if ( null === $ultimo ) {
			return '';
		}

		/* translators: %s: data. */
		$html = '<p class="trasparenza-pa-aggiornamento">' . esc_html( sprintf( __( 'Ultimo aggiornamento: %s', 'trasparenza-pa' ), self::giorno( $ultimo ) ) ) . '</p>';

		$propri = array_filter(
			$tutti,
			static function ( WP_Post $contenuto ) use ( $voce ): bool {
				return SchedaContenuto::collocazione( $contenuto->ID ) === $voce;
			}
		);

		if ( array() === $propri ) {
			return $html;
		}

		$html .= '<div class="trasparenza-pa-contenuti">';

		foreach ( $propri as $contenuto ) {
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
