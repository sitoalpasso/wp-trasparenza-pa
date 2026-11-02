<?php
/**
 * La conservazione dei contenuti scaduti.
 *
 * Righe di collaudo T-157 e T-158.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Un contenuto pubblicato e scaduto esce dalla sezione ma si conserva, perche
 * resti ottenibile con l'accesso civico (TRASP-09, art. 8, c. 3, del d.lgs.
 * 33/2013): non va nel cestino, non si cancella, non esce dalla
 * pubblicazione, e i suoi documenti non si cancellano. Scheda:
 * `docs/implementazione/scaduti-conservati.md`.
 */
final class ConservazioneScaduti {

	/**
	 * Prefisso del dato temporaneo che conserva il motivo per l'utente.
	 */
	const MOTIVO = 'trasparenza_pa_motivo_conservazione_';

	/**
	 * Aggancia i fermi e l'avviso.
	 */
	public static function aggancia(): void {
		add_filter( 'map_meta_cap', array( self::class, 'da_map_meta_cap' ), PHP_INT_MAX, 4 );
		add_filter( 'pre_trash_post', array( self::class, 'da_pre_rimozione' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_post', array( self::class, 'da_pre_rimozione' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_attachment', array( self::class, 'da_pre_rimozione' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'da_wp_insert_post_data' ), PHP_INT_MAX, 2 );
		add_action( 'admin_notices', array( self::class, 'mostra_motivo' ) );
	}

	/**
	 * Il motivo, uguale per ogni strada.
	 *
	 * @return string
	 */
	public static function motivo(): string {
		return __( 'Il contenuto è scaduto: è uscito dalla sezione, ma per legge si conserva perché resti ottenibile con l\'accesso civico. Non si può mettere nel cestino, cancellare o togliere dalla pubblicazione, e i suoi documenti non si possono cancellare.', 'trasparenza-pa' );
	}

	/**
	 * Il contenuto e pubblicato e scaduto.
	 *
	 * Lo stato si legge dalla banca dati: una lettura fallita vale come fermo,
	 * perche una cancellazione rifiutata si riprova e una fatta non si disfa.
	 *
	 * @param int $id Identificativo.
	 * @return bool
	 */
	public static function fermo( int $id ): bool {
		global $wpdb;

		if ( TIPO_CONTENUTO !== get_post_type( $id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lo stato decide una cancellazione: si legge dalla banca dati, non dalla memoria.
		$stato = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );

		if ( '' !== $wpdb->last_error || ! is_string( $stato ) ) {
			return true;
		}

		return 'publish' === $stato && conformita_core_scaduto( $id );
	}

	/**
	 * Il contenuto, o l'allegato di un contenuto, e fermo.
	 *
	 * @param int $id Identificativo.
	 * @return bool
	 */
	private static function fermo_o_suo_allegato( int $id ): bool {
		if ( 'attachment' === get_post_type( $id ) ) {
			$padre = (int) get_post_field( 'post_parent', $id );

			return $padre > 0 && self::fermo( $padre );
		}

		return self::fermo( $id );
	}

	/**
	 * Aggancio a `map_meta_cap`: nessuno ha il permesso di cancellare un contenuto fermo o un suo allegato.
	 *
	 * @param array<int, string> $permessi  Permessi richiesti.
	 * @param string             $permesso  Permesso chiesto.
	 * @param int                $utente    Utente.
	 * @param array<int, mixed>  $argomenti Argomenti.
	 * @return array<int, string>
	 */
	public static function da_map_meta_cap( $permessi, $permesso, $utente, $argomenti ) {
		unset( $utente );

		if ( 'delete_post' === $permesso && isset( $argomenti[0] ) && is_numeric( $argomenti[0] ) && self::fermo_o_suo_allegato( (int) $argomenti[0] ) ) {
			return array( 'do_not_allow' );
		}

		return $permessi;
	}

	/**
	 * Aggancio a `pre_trash_post` e `pre_delete_post`: le funzioni di WordPress non guardano i permessi.
	 *
	 * @param mixed $esito    Risposta proposta, nulla per proseguire.
	 * @param mixed $articolo Contenuto.
	 * @return mixed
	 */
	public static function da_pre_rimozione( $esito, $articolo ) {
		if ( ! $articolo instanceof \WP_Post || ! self::fermo_o_suo_allegato( (int) $articolo->ID ) ) {
			return $esito;
		}

		self::ricorda_motivo();

		return false;
	}

	/**
	 * Aggancio a `wp_insert_post_data`: un contenuto fermo resta pubblicato, il resto della scrittura passa.
	 *
	 * @param array<string, mixed> $dati      Dati che WordPress sta per scrivere.
	 * @param array<string, mixed> $richiesti Dati richiesti da chi scrive.
	 * @return array<string, mixed>
	 */
	public static function da_wp_insert_post_data( $dati, $richiesti ) {
		$id = isset( $richiesti['ID'] ) ? (int) $richiesti['ID'] : 0;

		if ( $id > 0 && TIPO_CONTENUTO === ( $dati['post_type'] ?? '' ) && 'publish' !== ( $dati['post_status'] ?? '' ) && self::fermo( $id ) ) {
			$dati['post_status'] = 'publish';

			// Anche la data resta quella della pubblicazione: una data futura chiedeva la programmazione.
			$scritto = get_post( $id );

			if ( $scritto instanceof \WP_Post ) {
				$dati['post_date']     = $scritto->post_date;
				$dati['post_date_gmt'] = $scritto->post_date_gmt;
			}

			self::ricorda_motivo();
		}

		return $dati;
	}

	/**
	 * Mostra il motivo una volta, a chi ha tentato.
	 */
	public static function mostra_motivo(): void {
		$chiave = self::MOTIVO . get_current_user_id();

		if ( false === get_transient( $chiave ) ) {
			return;
		}

		delete_transient( $chiave );

		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( NOME . ': ' . self::motivo() ) );
	}

	/**
	 * Conserva il motivo per l'utente corrente, per cinque minuti.
	 */
	private static function ricorda_motivo(): void {
		set_transient( self::MOTIVO . get_current_user_id(), 1, 5 * MINUTE_IN_SECONDS );
	}
}
