<?php
/**
 * Prove dell'avviso sulle voci coperte da un modulo ANAC: riga T-77.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchedaVoce;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\FILE_ALBERATURA;
use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Cosa dice il riquadro "Voce della sezione" quando la voce ha un modulo.
 */
class VociConModuloTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Sezione e tipi registrati, permessi assegnati, albero e catalogo distribuiti, amministratore corrente.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! TipoVoce::registrato() || ! TipoContenuto::registrato() ) {
			\Conformita_Core_Tipi::azzera();
			TipoVoce::azzera();
			TipoContenuto::azzera();

			if ( ! Avvio::registrata() ) {
				\Conformita_Core_Sezioni::azzera();
				Avvio::azzera();
				update_option( 'active_plugins', array( plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE ) ) );
				Avvio::esegui();
			}

			$this->assertTrue( TipoVoce::registra() );
			$this->assertTrue( TipoContenuto::registra() );
		}

		PaginaPubblica::fissa_file( FILE_ALBERATURA );
		PaginaPubblica::da_init();
		SchedaVoce::azzera();

		update_option( 'date_format', 'Y-m-d' );
		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Rimette l'albero finto e il catalogo distribuito.
	 */
	public function tear_down(): void {
		remove_all_filters( 'user_has_cap' );
		SchedaVoce::azzera();
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Il riquadro di un contenuto collocato in una voce, o in nessuna.
	 *
	 * @param string|null $voce Voce.
	 * @return string
	 */
	private function riquadro( ?string $voce ): string {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'draft',
			)
		);

		if ( null !== $voce ) {
			add_post_meta( $id, SchedaContenuto::META_COLLOCAZIONE, $voce );
		}

		return SchedaContenuto::riquadro( get_post( $id ) );
	}

	/**
	 * T-77: voce con un modulo in vigore: modulo, versione, che cosa arriva al pubblico, collegamento al modulo.
	 */
	public function test_t77_avviso_con_modulo_in_vigore(): void {
		$casi = array(
			'articolazione_uffici' => 'art. 13 del d.lgs. 33/2013',
			'corte_dei_conti'      => 'art. 31 del d.lgs. 33/2013',
			'dati_sui_pagamenti'   => 'art. 4-bis del d.lgs. 33/2013',
		);

		foreach ( $casi as $voce => $nome ) {
			$html = $this->riquadro( $voce );

			$this->assertStringContainsString( '<div class="trasparenza-pa-modulo">', $html, $voce );
			$this->assertStringContainsString( esc_html( 'Per i dati dell\'' . $nome . ' questa voce ha un modulo ANAC, versione 1.0 del 2026-06-10.' ), $html, $voce );
			$this->assertStringContainsString( 'restano in bozza', $html, $voce );
			$this->assertStringContainsString( 'al pubblico arriva il testo di questo contenuto', $html, $voce );
			$this->assertStringContainsString( '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . TIPO_VOCE ) ) . '">Compila il modulo</a>', $html, $voce );
		}
	}

	/**
	 * T-77: voce senza modulo, o nessuna voce: nessun avviso.
	 */
	public function test_t77_niente_avviso_senza_modulo(): void {
		$this->assertStringNotContainsString( 'trasparenza-pa-modulo', $this->riquadro( 'bandi_di_concorso' ) );
		$this->assertStringNotContainsString( 'trasparenza-pa-modulo', $this->riquadro( null ) );
		$this->assertStringNotContainsString( 'trasparenza-pa-modulo', $this->riquadro( 'organizzazione' ), 'Una voce con sottovoci non e una collocazione valida.' );
	}

	/**
	 * T-77: il collegamento al modulo compare solo a chi puo creare voci.
	 */
	public function test_t77_collegamento_solo_con_permesso(): void {
		$creare = get_post_type_object( TIPO_VOCE )->cap->create_posts;

		add_filter(
			'user_has_cap',
			static function ( $permessi ) use ( $creare ) {
				unset( $permessi[ $creare ] );

				return $permessi;
			}
		);

		$html = $this->riquadro( 'articolazione_uffici' );

		$this->assertStringContainsString( 'trasparenza-pa-modulo', $html );
		$this->assertStringNotContainsString( 'Compila il modulo', $html );
	}

	/**
	 * T-77: nessuna versione del modulo in vigore, catalogo non leggibile: l'avviso lo dice.
	 */
	public function test_t77_modulo_non_in_vigore_o_catalogo_assente(): void {
		// Il catalogo finto non ha versioni per gli obblighi dell'albero distribuito.
		SchedaVoce::fissa_file( __DIR__ . '/dati/schemi/catalogo.json' );

		$html = $this->riquadro( 'articolazione_uffici' );

		$this->assertStringContainsString( 'nessuna sua versione è ancora in vigore: per ora si usa il testo di questo contenuto', $html );
		$this->assertStringNotContainsString( 'Compila il modulo', $html );

		SchedaVoce::fissa_file( __DIR__ . '/dati/schemi/inesistente.json' );

		$html = $this->riquadro( 'articolazione_uffici' );

		$this->assertStringContainsString( 'il catalogo dei moduli non si è potuto leggere', $html );
		$this->assertStringNotContainsString( 'Compila il modulo', $html );
	}
}
