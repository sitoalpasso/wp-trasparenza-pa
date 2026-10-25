<?php
/**
 * Prove degli stati dell'obbligo: righe T-13 e T-14.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\StatiObbligo;
use WP_UnitTestCase;

/**
 * Nessuno stato predefinito, nessuno stato inventato.
 */
class StatiObbligoTest extends WP_UnitTestCase {

	/**
	 * Albero finto.
	 *
	 * @var Alberatura
	 */
	private $albero;

	/**
	 * Carica l'albero finto e parte senza stati.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->albero = Alberatura::carica( __DIR__ . '/dati/albero-finto.json' );

		delete_option( StatiObbligo::OPZIONE );
	}

	/**
	 * T-13: una voce che nessuno ha guardato non e applicabile, e non dichiarata.
	 */
	public function test_t13_voce_senza_stato_non_dichiarata(): void {
		$this->assertNull( StatiObbligo::stato( 'alfa' ) );
		$this->assertSame( array( 'zeta', 'alfa', 'zeta_uno', 'zeta_due' ), StatiObbligo::non_dichiarate( $this->albero ) );

		$this->assertTrue( StatiObbligo::imposta( $this->albero, 'alfa', StatiObbligo::APPLICABILE ) );
		$this->assertSame( StatiObbligo::APPLICABILE, StatiObbligo::stato( 'alfa' ) );
		$this->assertNotContains( 'alfa', StatiObbligo::non_dichiarate( $this->albero ) );
	}

	/**
	 * T-13: un valore conservato fuori dai tre vale come non dichiarato.
	 */
	public function test_t13_valore_conservato_estraneo_non_dichiarato(): void {
		update_option( StatiObbligo::OPZIONE, array( 'alfa' => 'forse' ) );

		$this->assertNull( StatiObbligo::stato( 'alfa' ) );
		$this->assertContains( 'alfa', StatiObbligo::non_dichiarate( $this->albero ) );
	}

	/**
	 * T-14: voce inesistente o valore fuori dai tre, rifiutati senza scrivere.
	 */
	public function test_t14_stato_non_valido_rifiutato(): void {
		$esito = StatiObbligo::imposta( $this->albero, 'inesistente', StatiObbligo::SOSPESO );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_stato_voce', $esito->get_error_code() );

		$esito = StatiObbligo::imposta( $this->albero, 'alfa', 'applicabile ' );

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_stato_valore', $esito->get_error_code() );

		$this->assertFalse( get_option( StatiObbligo::OPZIONE ), 'Niente deve essere stato scritto.' );
	}
}
