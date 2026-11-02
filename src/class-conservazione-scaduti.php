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
		add_filter( 'wp_insert_attachment_data', array( self::class, 'da_wp_insert_post_data' ), PHP_INT_MAX, 2 );
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
	 * Il contenuto e pubblicato, ha una data di fine, ed e scaduto.
	 *
	 * Lo stato si legge dalla banca dati: una lettura fallita vale come fermo,
	 * perche una cancellazione rifiutata si riprova e una fatta non si disfa.
	 *
	 * @param int $id Identificativo.
	 * @return bool
	 */
	public static function fermo( int $id ): bool {
		if ( TIPO_CONTENUTO !== get_post_type( $id ) ) {
			return false;
		}

		$stato = self::stato_scritto( $id );

		if ( null === $stato ) {
			return true;
		}

		if ( 'publish' !== $stato ) {
			return false;
		}

		// Senza il componente comune la data non si legge: come per una lettura fallita, il contenuto e fermo.
		if ( ! function_exists( 'conformita_core_fine_pubblicazione' ) || ! function_exists( 'conformita_core_scaduto' ) ) {
			return true;
		}

		/*
		 * Senza data di fine il contenuto non e mai stato visibile: non c'e un
		 * periodo di pubblicazione finito, quindi niente da conservare. Una
		 * lettura fallita o una data doppia valgono come fermo.
		 */
		$fine = conformita_core_fine_pubblicazione( $id );

		if ( '' === $fine ) {
			return false;
		}

		return is_wp_error( $fine ) || conformita_core_scaduto( $id );
	}

	/**
	 * Lo stato scritto nella banca dati, nullo se la lettura fallisce.
	 *
	 * @param int $id Identificativo.
	 * @return string|null
	 */
	private static function stato_scritto( int $id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lo stato decide una cancellazione: si legge dalla banca dati, non dalla memoria.
		$stato = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );

		return '' !== $wpdb->last_error || ! is_string( $stato ) ? null : $stato;
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
	 * Aggancio a `map_meta_cap`: nessuno cancella un contenuto fermo o un suo allegato, e nessuno modifica l'allegato.
	 *
	 * @param array<int, string> $permessi  Permessi richiesti.
	 * @param string             $permesso  Permesso chiesto.
	 * @param int                $utente    Utente.
	 * @param array<int, mixed>  $argomenti Argomenti.
	 * @return array<int, string>
	 */
	public static function da_map_meta_cap( $permessi, $permesso, $utente, $argomenti ) {
		unset( $utente );

		/*
		 * Anche la modifica dell'allegato: dalla libreria si scollega con una
		 * scrittura diretta del padre, e un allegato scollegato non e piu
		 * protetto. La consegna dei file chiede la lettura, non la modifica.
		 */
		$allegato = 'edit_post' === $permesso && isset( $argomenti[0] ) && is_numeric( $argomenti[0] ) && 'attachment' === get_post_type( (int) $argomenti[0] );

		if ( ( 'delete_post' === $permesso || $allegato ) && isset( $argomenti[0] ) && is_numeric( $argomenti[0] ) && self::fermo_o_suo_allegato( (int) $argomenti[0] ) ) {
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
	 * Aggancio a `wp_insert_post_data` e `wp_insert_attachment_data`: un contenuto fermo resta pubblicato e del suo tipo, un suo allegato resta legato, il resto della scrittura passa.
	 *
	 * @param array<string, mixed> $dati      Dati che WordPress sta per scrivere.
	 * @param array<string, mixed> $richiesti Dati richiesti da chi scrive.
	 * @return array<string, mixed>
	 */
	public static function da_wp_insert_post_data( $dati, $richiesti ) {
		$id = isset( $richiesti['ID'] ) ? (int) $richiesti['ID'] : 0;

		if ( $id <= 0 ) {
			return $dati;
		}

		global $wpdb;

		// Il tipo e il padre si leggono dalla banca dati, non da quello che la scrittura chiede.
		if ( 'attachment' === get_post_type( $id ) ) {
			$padre = (int) get_post_field( 'post_parent', $id, 'raw' );

			if ( $padre > 0 && (int) ( $dati['post_parent'] ?? 0 ) !== $padre && self::fermo( $padre ) ) {
				$dati['post_parent'] = $padre;

				self::ricorda_motivo();
			}

			return $dati;
		}

		if ( ! self::fermo( $id ) || ( 'publish' === ( $dati['post_status'] ?? '' ) && TIPO_CONTENUTO === ( $dati['post_type'] ?? '' ) ) ) {
			return $dati;
		}

		/*
		 * Con la lettura dello stato fallita non si sa se il contenuto e
		 * pubblicato: forzarlo a pubblicato puo pubblicare una bozza, lasciar
		 * passare la scrittura puo mettere in bozza uno scaduto, che poi si
		 * cancella. Lo stato non cambia: restano stato, tipo e data del
		 * contenuto che WordPress ha caricato per la scrittura.
		 */
		if ( null === self::stato_scritto( $id ) ) {
			$caricato = get_post( $id );

			if ( $caricato instanceof \WP_Post && ( ( $dati['post_status'] ?? '' ) !== $caricato->post_status || ( $dati['post_type'] ?? '' ) !== $caricato->post_type ) ) {
				$dati['post_status']   = $caricato->post_status;
				$dati['post_type']     = $caricato->post_type;
				$dati['post_date']     = $caricato->post_date;
				$dati['post_date_gmt'] = $caricato->post_date_gmt;

				self::ricorda_motivo();
			}

			return $dati;
		}

		$dati['post_status'] = 'publish';
		$dati['post_type']   = TIPO_CONTENUTO;

		// Anche la data resta quella della pubblicazione: una data futura chiedeva la programmazione.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- la data scritta, non quella della memoria.
		$scritto = $wpdb->get_row( $wpdb->prepare( "SELECT post_date, post_date_gmt FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );

		if ( is_array( $scritto ) ) {
			$dati['post_date']     = $scritto['post_date'];
			$dati['post_date_gmt'] = $scritto['post_date_gmt'];
		}

		self::ricorda_motivo();

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
