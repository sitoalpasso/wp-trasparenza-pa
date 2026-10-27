<?php
/**
 * La pubblicazione delle voci, chiusa fino agli stati editoriali.
 *
 * Riga di collaudo T-40.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Ogni tentativo di pubblicare una voce la lascia in bozza, con il motivo.
 *
 * Pubblicare richiede gli stati editoriali e la segnalazione al responsabile
 * della trasparenza (TRASP-28): finche non ci sono, una voce pubblicata
 * sarebbe un dato messo online senza il passaggio che la norma chiede. Due
 * sbarramenti, perche WordPress ha due strade: la scrittura di un contenuto e
 * il cambio di stato fatto direttamente, come in `wp_publish_post()`.
 */
final class ChiusuraPubblicazione {

	/**
	 * Stati che vorrebbero dire "visibile", o che lo diventerebbero da soli.
	 */
	const STATI_CHIUSI = array( 'publish', 'future', 'private' );

	/**
	 * Prefisso del dato temporaneo che conserva il motivo per l'utente.
	 */
	const MOTIVO = 'trasparenza_pa_motivo_bozza_';

	/**
	 * Registra i due sbarramenti e l'avviso.
	 */
	public static function aggancia(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'su_scrittura' ), PHP_INT_MAX, 2 );
		add_action( 'transition_post_status', array( self::class, 'su_cambio_di_stato' ), PHP_INT_MAX, 3 );
		add_action( 'admin_notices', array( self::class, 'mostra_motivo' ) );
	}

	/**
	 * Il motivo, uguale per ogni strada.
	 *
	 * @return string
	 */
	public static function motivo(): string {
		return __( 'La voce resta in bozza: la pubblicazione delle voci di trasparenza non è ancora aperta, perché richiede gli stati editoriali e la segnalazione al responsabile della trasparenza.', 'trasparenza-pa' );
	}

	/**
	 * Sbarramento sulla scrittura del contenuto.
	 *
	 * @param array<string, mixed> $dati      Dati che WordPress sta per scrivere.
	 * @param array<string, mixed> $richiesti Dati richiesti da chi scrive.
	 * @return array<string, mixed>
	 */
	public static function su_scrittura( $dati, $richiesti ) {
		unset( $richiesti );

		if ( TIPO_VOCE === ( $dati['post_type'] ?? '' ) && in_array( $dati['post_status'] ?? '', self::STATI_CHIUSI, true ) ) {
			$dati['post_status'] = 'draft';

			self::ricorda_motivo();
		}

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
		unset( $vecchio );

		if ( ! $articolo instanceof \WP_Post || TIPO_VOCE !== $articolo->post_type || ! in_array( $nuovo, self::STATI_CHIUSI, true ) ) {
			return;
		}

		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $articolo->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- riporta in bozza uno stato scritto direttamente.
		clean_post_cache( $articolo->ID );

		/*
		 * La scrittura diretta non passa da nessun aggancio, quindi il registro
		 * del componente comune, che ha gia scritto il passaggio allo stato
		 * chiuso, non saprebbe del ritorno in bozza. Il ritorno si annuncia
		 * come ogni cambio di stato, e il registro lo scrive.
		 */
		$riletto = get_post( $articolo->ID );

		if ( $riletto instanceof \WP_Post ) {
			wp_transition_post_status( 'draft', $nuovo, $riletto );
		}

		self::ricorda_motivo();
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

		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( self::motivo() ) );
	}

	/**
	 * Conserva il motivo per l'utente corrente, per cinque minuti.
	 */
	private static function ricorda_motivo(): void {
		set_transient( self::MOTIVO . get_current_user_id(), 1, 5 * MINUTE_IN_SECONDS );
	}
}
