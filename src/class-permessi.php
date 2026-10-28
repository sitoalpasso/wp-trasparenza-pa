<?php
/**
 * I permessi sulle voci di trasparenza.
 *
 * Riga di collaudo T-39.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chi puo fare cosa sulle voci.
 *
 * I nomi dei permessi non sono scritti qui: si chiedono al componente comune,
 * che li ricava dall'identificativo del tipo. Qui stanno solo i nomi generici
 * di WordPress, cioe quali permessi si vogliono. In questa unita li riceve il
 * solo amministratore; i ruoli della redazione arrivano con gli stati
 * editoriali.
 */
final class Permessi {

	/**
	 * Nomi generici concessi all'amministratore.
	 */
	const CHIAVI = array(
		'edit_posts',
		'edit_others_posts',
		'edit_private_posts',
		'edit_published_posts',
		'publish_posts',
		'read_private_posts',
		'delete_posts',
		'delete_private_posts',
		'delete_published_posts',
		'delete_others_posts',
		'create_posts',
	);

	/**
	 * Ruoli e nomi generici che ricevono.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function assegnazioni(): array {
		return array( 'administrator' => self::CHIAVI );
	}

	/**
	 * Corrispondenza fra nomi generici e permessi del tipo.
	 *
	 * @return array<string, string>|WP_Error
	 */
	public static function mappa() {
		if ( ! function_exists( 'conformita_core_capacita_tipo' ) ) {
			return new WP_Error( 'trasparenza_meccanismo_comune_assente', __( 'I permessi della voce si chiedono al componente comune, che non è caricato.', 'trasparenza-pa' ) );
		}

		return conformita_core_capacita_tipo( TIPO_VOCE );
	}

	/**
	 * Assegna i permessi e rilegge i ruoli per verificare che siano scritti.
	 *
	 * @return true|WP_Error
	 */
	public static function applica() {
		$mappa = self::mappa();

		if ( is_wp_error( $mappa ) ) {
			return $mappa;
		}

		foreach ( self::assegnazioni() as $nome => $chiavi ) {
			$ruolo = get_role( $nome );

			if ( null === $ruolo ) {
				continue;
			}

			foreach ( $chiavi as $chiave ) {
				if ( isset( $mappa[ $chiave ] ) ) {
					$ruolo->add_cap( $mappa[ $chiave ] );
				}
			}
		}

		$riletti = new \WP_Roles();

		foreach ( self::assegnazioni() as $nome => $chiavi ) {
			$ruolo = $riletti->get_role( $nome );

			if ( null === $ruolo ) {
				continue;
			}

			foreach ( $chiavi as $chiave ) {
				if ( isset( $mappa[ $chiave ] ) && ! $ruolo->has_cap( $mappa[ $chiave ] ) ) {
					/* translators: %s: identificativo del ruolo. */
					return new WP_Error( 'trasparenza_permessi_non_scritti', sprintf( __( 'I permessi della voce non risultano scritti sul ruolo %s.', 'trasparenza-pa' ), $nome ) );
				}
			}
		}

		return true;
	}
}
