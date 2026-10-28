<?php
/**
 * Il tipo di contenuto della voce di trasparenza.
 *
 * Riga di collaudo T-38.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * La voce: il contenitore di un dato compilato su uno schema.
 *
 * Il tipo non si registra direttamente in WordPress ma attraverso il
 * componente comune, che lo accetta solo dentro una sezione gia dichiarata e
 * ricava da se i nomi dei permessi. Il tipo e chiuso al pubblico: la
 * consultazione passera dalla pagina della sezione e dallo scarico dei dati.
 */
final class TipoVoce {

	/**
	 * La definizione del tipo come registrata da questo componente.
	 *
	 * @var \WP_Post_Type|null
	 */
	private static $definizione = null;

	/**
	 * Aggancio a `init`.
	 */
	public static function da_init(): void {
		self::registra();
	}

	/**
	 * Registra il tipo, se non e gia registrato da questo componente.
	 *
	 * @return true|WP_Error
	 */
	public static function registra() {
		if ( self::registrato() ) {
			return true;
		}

		if ( ! Avvio::registrata() || ! function_exists( 'conformita_core_registra_tipo' ) ) {
			return new WP_Error( 'trasparenza_sezione_non_registrata', __( 'La voce di trasparenza non è registrata perché la sezione non risulta registrata da questo componente.', 'trasparenza-pa' ) );
		}

		$esito = conformita_core_registra_tipo(
			TIPO_VOCE,
			array(
				'sezione'      => SEZIONE,
				'show_in_rest' => false,
				'argomenti'    => self::argomenti(),
			)
		);

		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		self::$definizione = get_post_type_object( TIPO_VOCE );

		return true;
	}

	/**
	 * Il tipo esiste ed e quello registrato da questo componente.
	 *
	 * @return bool
	 */
	public static function registrato(): bool {
		return null !== self::$definizione && get_post_type_object( TIPO_VOCE ) === self::$definizione;
	}

	/**
	 * Dimentica la registrazione. Serve alle prove.
	 */
	public static function azzera(): void {
		self::$definizione = null;
	}

	/**
	 * Argomenti di registrazione, scritti uno per uno invece di lasciarli derivare.
	 *
	 * @return array<string, mixed>
	 */
	private static function argomenti(): array {
		return array(
			'labels'              => array(
				'name'          => __( 'Voci di trasparenza', 'trasparenza-pa' ),
				'singular_name' => __( 'Voce di trasparenza', 'trasparenza-pa' ),
				'menu_name'     => __( 'Trasparenza', 'trasparenza-pa' ),
				'add_new_item'  => __( 'Nuova voce', 'trasparenza-pa' ),
				'edit_item'     => __( 'Modifica voce', 'trasparenza-pa' ),
				'search_items'  => __( 'Cerca voci', 'trasparenza-pa' ),
				'not_found'     => __( 'Nessuna voce.', 'trasparenza-pa' ),
			),
			'description'         => __( 'Dati pubblicati nella sezione Amministrazione trasparente, compilati sugli schemi ANAC.', 'trasparenza-pa' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-visibility',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author', 'revisions' ),
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}
}
