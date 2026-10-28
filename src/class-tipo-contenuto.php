<?php
/**
 * Il tipo di contenuto libero della sezione: testo e documenti dentro una voce.
 *
 * Riga di collaudo T-59.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Il contenuto: un testo con titolo, collocato in una voce dell'albero.
 *
 * E un tipo a se e non una forma della voce di trasparenza: la voce porta un
 * dato compilato su uno schema ANAC e resta chiusa alla pubblicazione finche
 * non ci sono gli stati editoriali, che le istruzioni ANAC legano alla
 * conformita allo schema. Il contenuto non ha uno schema, e si pubblica come
 * un articolo. Come la voce, si registra attraverso il componente comune ed e
 * chiuso al pubblico: la consultazione passa dalla pagina della sezione.
 */
final class TipoContenuto {

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
			return new WP_Error( 'trasparenza_sezione_non_registrata', __( 'Il contenuto della sezione non è registrato perché la sezione non risulta registrata da questo componente.', 'trasparenza-pa' ) );
		}

		$esito = conformita_core_registra_tipo(
			TIPO_CONTENUTO,
			array(
				'sezione'      => SEZIONE,
				'show_in_rest' => false,
				'argomenti'    => self::argomenti(),
			)
		);

		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		self::$definizione = get_post_type_object( TIPO_CONTENUTO );

		return true;
	}

	/**
	 * Il tipo esiste ed e quello registrato da questo componente.
	 *
	 * @return bool
	 */
	public static function registrato(): bool {
		return null !== self::$definizione && get_post_type_object( TIPO_CONTENUTO ) === self::$definizione;
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
	 * Il menu e quello della voce di trasparenza: una sola voce "Trasparenza"
	 * nel pannello, con i contenuti come sottovoce.
	 *
	 * @return array<string, mixed>
	 */
	private static function argomenti(): array {
		return array(
			'labels'              => array(
				'name'          => __( 'Contenuti della sezione', 'trasparenza-pa' ),
				'singular_name' => __( 'Contenuto della sezione', 'trasparenza-pa' ),
				'menu_name'     => __( 'Contenuti', 'trasparenza-pa' ),
				'all_items'     => __( 'Contenuti', 'trasparenza-pa' ),
				'add_new_item'  => __( 'Nuovo contenuto', 'trasparenza-pa' ),
				'edit_item'     => __( 'Modifica contenuto', 'trasparenza-pa' ),
				'search_items'  => __( 'Cerca contenuti', 'trasparenza-pa' ),
				'not_found'     => __( 'Nessun contenuto.', 'trasparenza-pa' ),
			),
			'description'         => __( 'Testi e documenti pubblicati nelle voci della sezione Amministrazione trasparente.', 'trasparenza-pa' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . TIPO_VOCE,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}
}
