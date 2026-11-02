<?php
/**
 * Le voci della sezione visibili ai motori di ricerca.
 *
 * Righe di collaudo T-152 e T-153.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Ogni voce si apre sulla pagina della sezione con il parametro della voce.
 * Senza questa classe WordPress dichiara come canonico di ogni voce l'indirizzo
 * della pagina senza parametro, e la mappa del sito non elenca nessuna voce:
 * per un motore la sezione sarebbe la sola pagina d'ingresso (TRASP-03).
 *
 * Non tocca `wp_robots` ne le intestazioni (T-18). Scheda:
 * `docs/implementazione/sezione-indicizzabile.md`.
 */
final class IndicizzazioneVoci {

	/**
	 * Nome del fornitore della mappa del sito.
	 */
	const FORNITORE = 'trasparenza';

	/**
	 * Aggancia i filtri.
	 */
	public static function aggancia(): void {
		add_filter( 'get_canonical_url', array( self::class, 'da_get_canonical_url' ), 10, 2 );
		add_action( 'wp_sitemaps_init', array( self::class, 'da_wp_sitemaps_init' ) );
	}

	/**
	 * Il canonico di una voce e l'indirizzo della voce.
	 *
	 * @param mixed $indirizzo Canonico calcolato da WordPress.
	 * @param mixed $pagina    Contenuto.
	 * @return mixed
	 */
	public static function da_get_canonical_url( $indirizzo, $pagina = null ) {
		$albero = PaginaPubblica::albero();

		if ( ! is_string( $indirizzo ) || null === $albero || ! $pagina instanceof \WP_Post || (int) get_queried_object_id() !== (int) $pagina->ID || ! self::della_sezione( $pagina ) ) {
			return $indirizzo;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		$voce = PaginaPubblica::voce_chiesta( wp_unslash( $_GET ) );

		return null === $voce ? $indirizzo : PaginaPubblica::indirizzo( $indirizzo, $voce, $albero->radice() );
	}

	/**
	 * Registra la mappa delle voci.
	 *
	 * @param mixed $mappe Server delle mappe del sito.
	 */
	public static function da_wp_sitemaps_init( $mappe ): void {
		if ( $mappe instanceof \WP_Sitemaps ) {
			$mappe->registry->add_provider( self::FORNITORE, new MappaVoci() );
		}
	}

	/**
	 * Una pagina pubblicata, senza password, con il codice breve della sezione.
	 *
	 * @param \WP_Post $pagina Pagina.
	 * @return bool
	 */
	public static function della_sezione( \WP_Post $pagina ): bool {
		return 'page' === $pagina->post_type && 'publish' === $pagina->post_status && '' === $pagina->post_password && has_shortcode( $pagina->post_content, PaginaPubblica::CODICE_BREVE );
	}

	/**
	 * Le pagine della sezione: pubblicate, senza password, con il codice breve.
	 *
	 * @return array<int, \WP_Post>|null Nullo se la lettura e fallita.
	 */
	public static function pagine(): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- ricerca del codice breve nel testo, che get_posts() non offre.
		$righe = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_password = '' AND post_content LIKE %s ORDER BY ID ASC", 'page', 'publish', '%' . $wpdb->esc_like( '[' . PaginaPubblica::CODICE_BREVE ) . '%' ) );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return null;
		}

		$pagine = array();

		foreach ( $righe as $id ) {
			$pagina = get_post( (int) $id );

			if ( $pagina instanceof \WP_Post && self::della_sezione( $pagina ) ) {
				$pagine[] = $pagina;
			}
		}

		return $pagine;
	}

	/**
	 * Gli indirizzi di tutte le voci, tranne la radice, di ogni pagina della sezione.
	 *
	 * Una lettura fallita da un elenco vuoto: meglio nessun indirizzo per una
	 * volta che indirizzi sbagliati.
	 *
	 * @return array<int, string>
	 */
	public static function indirizzi(): array {
		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return array();
		}

		$radice    = $albero->radice();
		$indirizzi = array();

		foreach ( self::pagine() ?? array() as $pagina ) {
			$base = (string) get_permalink( $pagina );

			foreach ( $albero->identificativi() as $voce ) {
				if ( $voce !== $radice ) {
					$indirizzi[] = PaginaPubblica::indirizzo( $base, $voce, $radice );
				}
			}
		}

		return $indirizzi;
	}
}
