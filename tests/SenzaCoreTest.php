<?php
/**
 * Riga T-03: avvio con il componente comune assente.
 *
 * Gira in un avvio separato, senza quel componente caricato: le sue funzioni
 * non esistono, che e la condizione da verificare.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use WP_UnitTestCase;

/**
 * Il componente comune non c'e.
 */
class SenzaCoreTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Percorso del componente come lo conosce WordPress.
	 *
	 * @var string
	 */
	private $componente;

	/**
	 * Precondizione: il componente risulta fra quelli attivi.
	 *
	 * Senza, "e rimasto attivo" sarebbe vero per niente: nell'ambiente di prova
	 * il componente e caricato dall'avvio della suite e non compare fra gli
	 * attivi.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->componente = plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE );

		Avvio::azzera();

		update_option( 'active_plugins', array( $this->componente ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * L'ambiente e davvero quello previsto.
	 */
	public function test_precondizione_core_assente(): void {
		$this->assertFalse(
			function_exists( 'conformita_core_avvia_componente' ),
			'Precondizione: le funzioni del componente comune non devono esistere.'
		);

		$this->assertContains( $this->componente, (array) get_option( 'active_plugins', array() ) );
	}

	/**
	 * T-03: il componente resta attivo e inerte.
	 */
	public function test_t03_resta_attivo_e_inerte(): void {
		$this->assertFalse( Avvio::esegui(), 'Senza il componente comune l\'avvio non prosegue.' );

		$this->assertContains(
			$this->componente,
			(array) get_option( 'active_plugins', array() ),
			'Il componente resta attivo: la disattivazione appartiene alla sola incompatibilita di versione.'
		);

		$this->assertFalse( Avvio::registrata(), 'Nessuna sezione registrata.' );
	}

	/**
	 * T-03: l'avviso dice quale versione serve e che non se n'e trovata nessuna.
	 *
	 * Si verificano i valori e non la frase. Nel testo deve comparire un solo
	 * numero di versione, quello richiesto: se l'avviso riportasse anche una
	 * versione disponibile, la prova diventerebbe rossa.
	 */
	public function test_t03_avviso_dice_cosa_manca(): void {
		$this->assertSame( '', $this->avvisi_in_bacheca(), 'La bacheca deve essere pulita prima della chiamata.' );

		Avvio::esegui();

		$avviso = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( \TrasparenzaPa\NOME, $avviso, 'L\'avviso deve dire quale componente e fermo.' );
		$this->assertStringContainsString( \TrasparenzaPa\CORE_NOME, $avviso, 'L\'avviso deve nominare il componente da installare.' );
		$this->assertStringContainsString( \TrasparenzaPa\CORE_API_RICHIESTA, $avviso, 'L\'avviso deve dire la versione richiesta.' );

		preg_match_all( '/\d+\.\d+\.\d+/', $avviso, $versioni );

		$this->assertSame(
			array( \TrasparenzaPa\CORE_API_RICHIESTA ),
			array_values( array_unique( $versioni[0] ) ),
			'Nell\'avviso deve comparire la sola versione richiesta.'
		);
	}

	/**
	 * T-03: l'avviso e riservato a chi puo attivare i componenti.
	 */
	public function test_t03_avviso_riservato(): void {
		Avvio::esegui();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->avvisi_in_bacheca(), 'Chi non puo attivare i componenti non deve vedere l\'avviso.' );
	}
}
