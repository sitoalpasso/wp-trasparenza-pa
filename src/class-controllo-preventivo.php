<?php
/**
 * La conferma sui dati personali prima di pubblicare un contenuto.
 *
 * Righe di collaudo T-159 e T-160.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Un contenuto della sezione si pubblica, o si programma, solo dopo che chi
 * lo pubblica ha confermato di aver controllato i dati personali (TRASP-16:
 * GDPR art. 5; art. 7-bis, c. 4, del d.lgs. 33/2013). Senza la conferma resta
 * in bozza, con il motivo. Due sbarramenti, come per le voci (T-40): la
 * scrittura del contenuto e il cambio di stato fatto direttamente. Scheda:
 * `docs/implementazione/dati-personali.md`.
 */
final class ControlloPreventivo {

	/**
	 * Stati visibili, o che lo diventano da soli.
	 */
	const STATI_VISIBILI = array( 'publish', 'future' );

	/**
	 * Campo della casella di conferma: porta l'identificativo del contenuto.
	 */
	const CAMPO_CONFERMA = 'trasparenza_pa_conferma_dati_personali';

	/**
	 * Campo del gettone di sicurezza.
	 */
	const CAMPO_GETTONE = 'trasparenza_pa_gettone_dati_personali';

	/**
	 * Azione scritta nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'conferma_dati_personali';

	/**
	 * Identificativo del riquadro.
	 */
	const ID_RIQUADRO = 'trasparenza-pa-dati-personali';

	/**
	 * Prefisso del dato temporaneo che conserva il motivo per l'utente.
	 */
	const MOTIVO = 'trasparenza_pa_motivo_dati_personali_';

	/**
	 * Contenuti con una conferma registrata in questa richiesta, in attesa del cambio di stato.
	 *
	 * @var array<int, bool>
	 */
	private static $concessi = array();

	/**
	 * Solo per le prove: la conferma si considera data.
	 *
	 * @var bool
	 */
	private static $conferma_per_prove = false;

	/**
	 * Registra i due sbarramenti, il riquadro e l'avviso.
	 */
	public static function aggancia(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'su_scrittura' ), PHP_INT_MAX - 1, 2 );
		// Subito dopo il registro del componente comune, come lo sbarramento delle voci.
		add_action( 'transition_post_status', array( self::class, 'su_cambio_di_stato' ), PHP_INT_MIN + 1, 3 );
		add_action( 'add_meta_boxes_' . TIPO_CONTENUTO, array( self::class, 'aggiungi_riquadro' ) );
		add_action( 'admin_notices', array( self::class, 'mostra_motivo' ) );
	}

	/**
	 * Solo per le prove che pubblicano contenuti senza occuparsi della conferma.
	 *
	 * @param bool $data La conferma si considera data.
	 */
	public static function fissa_conferma_per_prove( bool $data ): void {
		self::$conferma_per_prove = $data;
		self::$concessi           = array();
	}

	/**
	 * Azione del gettone, legata al contenuto.
	 *
	 * @param int $id Contenuto.
	 * @return string
	 */
	public static function azione( int $id ): string {
		return 'trasparenza_pa_dati_personali_' . $id;
	}

	/**
	 * Il motivo, uguale per ogni strada.
	 *
	 * @return string
	 */
	public static function motivo(): string {
		return __( 'Il contenuto resta in bozza: prima di pubblicarlo o programmarlo serve la conferma, nel riquadro "Dati personali", di aver controllato che non contenga dati personali non necessari.', 'trasparenza-pa' );
	}

	/**
	 * Sbarramento sulla scrittura del contenuto.
	 *
	 * @param array<string, mixed> $dati      Dati che WordPress sta per scrivere.
	 * @param array<string, mixed> $richiesti Dati richiesti da chi scrive.
	 * @return array<string, mixed>
	 */
	public static function su_scrittura( $dati, $richiesti ) {
		if ( self::$conferma_per_prove || TIPO_CONTENUTO !== ( $dati['post_type'] ?? '' ) || ! in_array( $dati['post_status'] ?? '', self::STATI_VISIBILI, true ) ) {
			return $dati;
		}

		$id = isset( $richiesti['ID'] ) ? (int) $richiesti['ID'] : 0;

		// Un contenuto nuovo non ha ancora un gettone: nasce in bozza.
		if ( $id > 0 && ( self::gia_visibile( $id ) || self::registra_conferma( $id ) ) ) {
			return $dati;
		}

		$dati['post_status'] = 'draft';

		self::ricorda_motivo();

		return $dati;
	}

	/**
	 * Sbarramento sul cambio di stato fatto senza passare dalla scrittura.
	 *
	 * @param string   $nuovo    Stato nuovo.
	 * @param string   $vecchio  Stato precedente.
	 * @param \WP_Post $articolo Contenuto.
	 */
	public static function su_cambio_di_stato( $nuovo, $vecchio, $articolo ): void {
		if ( self::$conferma_per_prove || ! $articolo instanceof \WP_Post || TIPO_CONTENUTO !== $articolo->post_type || ! in_array( $nuovo, self::STATI_VISIBILI, true ) ) {
			return;
		}

		$id = (int) $articolo->ID;

		if ( isset( self::$concessi[ $id ] ) ) {
			unset( self::$concessi[ $id ] );

			return;
		}

		// Un programmato che arriva alla sua data, o un pubblicato che si riscrive.
		if ( in_array( $vecchio, self::STATI_VISIBILI, true ) ) {
			return;
		}

		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- riporta in bozza uno stato scritto direttamente.
		clean_post_cache( $id );

		// Il ritorno in bozza si annuncia come ogni cambio di stato, perche il registro lo scriva.
		$riletto = get_post( $id );

		if ( $riletto instanceof \WP_Post ) {
			wp_transition_post_status( 'draft', $nuovo, $riletto );
		}

		self::ricorda_motivo();
	}

	/**
	 * Lo stato scritto nella banca dati e gia visibile: la conferma c'e stata, o non serviva.
	 *
	 * Una lettura fallita vale come non visibile, cioe la conferma serve.
	 *
	 * @param int $id Contenuto.
	 * @return bool
	 */
	private static function gia_visibile( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lo stato decide se serve la conferma: si legge dalla banca dati, non dalla memoria.
		$stato = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );

		if ( '' !== $wpdb->last_error || ! is_string( $stato ) ) {
			return false;
		}

		return in_array( $stato, self::STATI_VISIBILI, true );
	}

	/**
	 * La conferma nella richiesta e valida per il contenuto, e il registro l'ha scritta.
	 *
	 * @param int $id Contenuto.
	 * @return bool
	 */
	private static function registra_conferma( int $id ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- il gettone si verifica qui sotto.
		$casella = isset( $_POST[ self::CAMPO_CONFERMA ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::CAMPO_CONFERMA ] ) ) : '';
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::CAMPO_GETTONE ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( (string) $id !== $casella || false === wp_verify_nonce( $gettone, self::azione( $id ) ) || ! current_user_can( 'publish_post', $id ) ) {
			return false;
		}

		if ( ! function_exists( 'conformita_core_registra_voce' ) ) {
			return false;
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $id,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => array( 'conferma' => 'dati personali controllati' ),
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return false;
		}

		self::$concessi[ $id ] = true;

		return true;
	}

	/**
	 * Aggancio a `add_meta_boxes_`: il riquadro nella schermata del contenuto.
	 *
	 * @param \WP_Post $contenuto Contenuto.
	 */
	public static function aggiungi_riquadro( $contenuto ): void {
		if ( ! $contenuto instanceof \WP_Post || '' === self::riquadro( $contenuto ) ) {
			return;
		}

		add_meta_box( self::ID_RIQUADRO, __( 'Dati personali', 'trasparenza-pa' ), array( self::class, 'disegna' ), TIPO_CONTENUTO, 'side', 'high' );
	}

	/**
	 * Stampa il riquadro.
	 *
	 * @param \WP_Post $contenuto Contenuto.
	 */
	public static function disegna( $contenuto ): void {
		if ( $contenuto instanceof \WP_Post ) {
			echo self::riquadro( $contenuto ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composto con parti gia protette.
		}
	}

	/**
	 * Il riquadro, vuoto dove non serve: contenuto gia pubblicato o programmato, o chi non puo pubblicarlo.
	 *
	 * @param \WP_Post $contenuto Contenuto.
	 * @return string
	 */
	public static function riquadro( \WP_Post $contenuto ): string {
		$id = (int) $contenuto->ID;

		if ( in_array( $contenuto->post_status, self::STATI_VISIBILI, true ) || ! current_user_can( 'publish_post', $id ) ) {
			return '';
		}

		$casi = array(
			__( 'curriculum con recapiti personali, codice fiscale, firma o fotografia che non servono;', 'trasparenza-pa' ),
			__( 'dati patrimoniali e reddituali del coniuge e dei parenti che non hanno dato il consenso;', 'trasparenza-pa' ),
			__( 'destinatari di contributi e sussidi da cui si capisce lo stato di salute o il disagio economico e sociale (art. 26, c. 4, del d.lgs. 33/2013);', 'trasparenza-pa' ),
			__( 'coordinate bancarie, codici fiscali e indirizzi di residenza negli atti di liquidazione;', 'trasparenza-pa' ),
			__( 'graduatorie con punteggi legati a condizioni sociali o familiari.', 'trasparenza-pa' ),
		);

		$voci = '';

		foreach ( $casi as $caso ) {
			$voci .= '<li>' . esc_html( $caso ) . '</li>';
		}

		return '<p>' . esc_html__( 'Prima di pubblicare, controllare che il contenuto e i suoi file non mostrino dati personali che non servono allo scopo della pubblicazione. Casi tipici:', 'trasparenza-pa' ) . '</p>'
			. '<ul class="ul-disc">' . $voci . '</ul>'
			. '<p>' . esc_html__( 'I dati relativi alla salute non si pubblicano mai (art. 2-septies, c. 8, del d.lgs. 196/2003).', 'trasparenza-pa' ) . '</p>'
			. '<p><label><input type="checkbox" name="' . esc_attr( self::CAMPO_CONFERMA ) . '" value="' . esc_attr( (string) $id ) . '" /> '
			. esc_html__( 'Ho controllato i dati personali: il contenuto si può pubblicare.', 'trasparenza-pa' ) . '</label></p>'
			. wp_nonce_field( self::azione( $id ), self::CAMPO_GETTONE, false, false );
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
