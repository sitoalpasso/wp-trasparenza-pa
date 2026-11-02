<?php
/**
 * Prove delle letture fallite nel riquadro della voce e nell'installazione: righe T-161..T-162.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\SchedaVoce;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_VOCE;
use const TrasparenzaPa\VERSIONE;

/**
 * Una lettura fallita non vale come dato assente.
 */
class LettureFalliteTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Dato salvato nella voce.
	 */
	const DATO = '{"prova":"intero"}';

	/**
	 * Tipo registrato, permessi assegnati, amministratore corrente, catalogo vero.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! TipoVoce::registrato() ) {
			\Conformita_Core_Tipi::azzera();
			TipoVoce::azzera();

			if ( ! Avvio::registrata() ) {
				\Conformita_Core_Sezioni::azzera();
				Avvio::azzera();
				update_option( 'active_plugins', array( plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE ) ) );
				Avvio::esegui();
			}

			$this->assertTrue( TipoVoce::registra() );
		}

		delete_option( OPZIONE_VERSIONE );
		Installazione::aggiorna_se_serve();
		SchedaVoce::azzera();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Guasti tolti, richiesta vuota.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		$_POST = array();
		SchedaVoce::azzera();

		// Il permesso tolto dalla prova d'installazione torna, anche se la prova si ferma a meta.
		$mappa = Permessi::mappa();

		if ( is_array( $mappa ) ) {
			get_role( 'administrator' )->add_cap( $mappa['edit_posts'] );
		}

		parent::tear_down();
	}

	/**
	 * Una voce legata all'obbligo dell'art. 13, con un dato salvato.
	 *
	 * @return int
	 */
	private function voce(): int {
		$voce = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'draft',
				'post_title'  => 'Voce di prova',
			)
		);

		add_post_meta( $voce, SchedaVoce::META_OBBLIGO, 'art_13', true );
		add_post_meta( $voce, SchedaVoce::META_VERSIONE, '1.0@2026-06-10', true );
		add_post_meta( $voce, SchedaVoce::META_DATO, wp_slash( self::DATO ), true );

		return $voce;
	}

	/**
	 * La lettura di un metadato della voce fallisce.
	 *
	 * @param string $chiave Chiave del metadato.
	 */
	private function guasta_metadato( string $chiave ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $chiave ) {
				return 0 === strpos( (string) $sql, 'SELECT meta_value FROM ' . $wpdb->postmeta ) && false !== strpos( (string) $sql, "'" . $chiave . "'" ) ? 'SELECT meta_value FROM ' . $wpdb->postmeta . '_inesistente' : $sql;
			}
		);
	}

	/**
	 * Toglie i guasti.
	 */
	private function ripara(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
	}

	/**
	 * Salva la voce come la schermata, con i campi dati.
	 *
	 * @param int                  $voce  Voce.
	 * @param array<string, mixed> $campi Campi inviati, senza il nonce.
	 */
	private function salva( int $voce, array $campi ): void {
		$campi[ SchedaVoce::CAMPO_NONCE ] = wp_create_nonce( SchedaVoce::azione_nonce( $voce ) );
		$_POST                            = wp_slash( $campi );

		wp_update_post(
			array(
				'ID'         => $voce,
				'post_title' => 'Voce di prova',
			)
		);

		$_POST = array();
	}

	/**
	 * Il dato scritto nella banca dati.
	 *
	 * @param int $voce Voce.
	 * @return string
	 */
	private function scritto( int $voce ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- la prova guarda la banca dati, non la memoria.
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $voce, SchedaVoce::META_DATO ) );
	}

	/**
	 * Un invio completo del modulo, che svuoterebbe il dato.
	 *
	 * @return array<string, mixed>
	 */
	private static function invio_vuoto(): array {
		return array(
			SchedaVoce::CAMPO_DATO   => array(),
			SchedaVoce::CAMPO_INTERO => '1',
		);
	}

	/**
	 * T-161. Il riquadro con una lettura fallita mostra l'avviso e nessun campo.
	 */
	public function test_t161_riquadro() {
		$voce = $this->voce();

		// Senza guasto: il modulo c'e.
		$riquadro = SchedaVoce::riquadro( get_post( $voce ) );
		$this->assertStringContainsString( 'name="' . SchedaVoce::CAMPO_INTERO . '"', $riquadro );
		$this->assertStringNotContainsString( 'non si sono potuti leggere', $riquadro );

		foreach ( array( SchedaVoce::META_OBBLIGO, SchedaVoce::META_VERSIONE, SchedaVoce::META_DATO ) as $chiave ) {
			$this->guasta_metadato( $chiave );
			$riquadro = SchedaVoce::riquadro( get_post( $voce ) );
			$this->ripara();

			$this->assertStringContainsString( 'non si sono potuti leggere', $riquadro, $chiave );
			$this->assertStringNotContainsString( 'name="' . SchedaVoce::CAMPO_DATO, $riquadro, $chiave );
			$this->assertStringNotContainsString( 'name="' . SchedaVoce::CAMPO_OBBLIGO, $riquadro, $chiave );
			$this->assertStringNotContainsString( 'name="' . SchedaVoce::CAMPO_INTERO, $riquadro, $chiave );
			$this->assertStringNotContainsString( 'non è più nel catalogo', $riquadro, $chiave );
			$this->assertStringContainsString( 'name="' . SchedaVoce::CAMPO_SENZA_MODULO . '"', $riquadro, $chiave );
		}

		// Obbligo con due valori: lo stesso.
		add_post_meta( $voce, SchedaVoce::META_OBBLIGO, 'art_31' );
		$riquadro = SchedaVoce::riquadro( get_post( $voce ) );
		$this->assertStringContainsString( 'non si sono potuti leggere', $riquadro );
		$this->assertStringNotContainsString( 'name="' . SchedaVoce::CAMPO_DATO, $riquadro );
	}

	/**
	 * T-161. Il salvataggio con una lettura fallita non scrive e lo dice.
	 */
	public function test_t161_salvataggio() {
		$voce = $this->voce();
		$this->avvisi_in_bacheca();

		foreach ( array( SchedaVoce::META_OBBLIGO, SchedaVoce::META_VERSIONE ) as $chiave ) {
			$this->guasta_metadato( $chiave );
			$this->salva( $voce, self::invio_vuoto() );
			$this->ripara();

			$this->assertSame( self::DATO, $this->scritto( $voce ), $chiave );
			$this->assertSame( array( 'art_13' ), get_post_meta( $voce, SchedaVoce::META_OBBLIGO, false ), $chiave );

			$avvisi = $this->avvisi_in_bacheca();
			$this->assertStringContainsString( 'non sono stati salvati', $avvisi, $chiave );
			$this->assertStringContainsString( 'non si sono potuti leggere', $avvisi, $chiave );
			$this->assertStringNotContainsString( 'non si sono potuti leggere', $this->avvisi_in_bacheca(), 'Il motivo si mostra una volta.' );
		}

		// Invio dal riquadro senza modulo, con le letture tornate: non si scrive.
		$this->salva( $voce, array( SchedaVoce::CAMPO_SENZA_MODULO => '1' ) + self::invio_vuoto() );
		$this->assertSame( self::DATO, $this->scritto( $voce ) );
		$avvisi = $this->avvisi_in_bacheca();
		$this->assertStringContainsString( 'non si sono potuti leggere', $avvisi );
		$this->assertStringNotContainsString( 'max_input_vars', $avvisi );

		// Obbligo con due valori: non si scrive.
		add_post_meta( $voce, SchedaVoce::META_OBBLIGO, 'art_31' );
		$this->salva( $voce, self::invio_vuoto() );
		$this->assertSame( self::DATO, $this->scritto( $voce ) );
		$this->assertStringContainsString( 'non si sono potuti leggere', $this->avvisi_in_bacheca() );
		delete_post_meta( $voce, SchedaVoce::META_OBBLIGO, 'art_31' );

		// Senza guasto il salvataggio scrive come prima.
		$this->salva( $voce, self::invio_vuoto() );
		$this->assertNotSame( self::DATO, $this->scritto( $voce ) );
		$this->assertStringNotContainsString( 'non sono stati salvati', $this->avvisi_in_bacheca() );
	}

	/**
	 * T-162. Con la lettura della versione installata fallita i permessi non si toccano.
	 */
	public function test_t162_installazione() {
		global $wpdb;

		$permesso = Permessi::mappa()['edit_posts'];
		$ruolo    = get_role( 'administrator' );

		$this->assertTrue( $ruolo->has_cap( $permesso ) );
		$this->assertNotFalse( has_action( 'admin_notices', array( Installazione::class, 'mostra_avviso' ) ) );

		// Versione da aggiornare, permesso tolto a mano, lettura della versione fallita.
		update_option( OPZIONE_VERSIONE, '0.0.0' );
		$ruolo->remove_cap( $permesso );

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				return 0 === strpos( (string) $sql, 'SELECT option_value FROM ' . $wpdb->options ) && false !== strpos( (string) $sql, "'" . OPZIONE_VERSIONE . "'" ) ? 'SELECT option_value FROM ' . $wpdb->options . '_inesistente' : $sql;
			}
		);
		$this->assertFalse( Installazione::aggiorna_se_serve() );
		$this->ripara();

		$this->assertFalse( ( new \WP_Roles() )->get_role( 'administrator' )->has_cap( $permesso ), 'Il permesso tolto resta tolto.' );
		$this->assertSame( '0.0.0', get_option( OPZIONE_VERSIONE ) );
		$this->assertStringContainsString( 'versione installata', $this->avvisi_in_bacheca() );

		// A letture riprese l'aggiornamento si fa.
		$this->assertTrue( Installazione::aggiorna_se_serve() );
		$this->assertTrue( ( new \WP_Roles() )->get_role( 'administrator' )->has_cap( $permesso ) );
		$this->assertStringNotContainsString( 'versione installata', $this->avvisi_in_bacheca() );

		// La versione in memoria rimasta indietro, quella scritta gia aggiornata: niente da fare.
		$this->assertSame( VERSIONE, get_option( OPZIONE_VERSIONE ) );
		get_role( 'administrator' )->remove_cap( $permesso );
		$indietro = static function () {
			return '0.0.0';
		};
		add_filter( 'option_' . OPZIONE_VERSIONE, $indietro );
		$this->assertFalse( Installazione::aggiorna_se_serve() );
		remove_filter( 'option_' . OPZIONE_VERSIONE, $indietro );
		$this->assertFalse( ( new \WP_Roles() )->get_role( 'administrator' )->has_cap( $permesso ) );
	}
}
