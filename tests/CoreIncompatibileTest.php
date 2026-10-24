<?php
/**
 * Riga T-05: avvio con una versione di interfaccia incompatibile.
 *
 * Gira in un avvio separato che definisce la costante della versione prima di
 * caricare il componente comune, con la procedura di avvio non agganciata:
 * l'unica sua esecuzione e quella che la prova fa di persona.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use WP_UnitTestCase;

/**
 * Il componente comune c'e ma non parla la stessa lingua.
 */
class CoreIncompatibileTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Percorso del componente come lo conosce WordPress.
	 *
	 * @var string
	 */
	private $componente;

	/**
	 * Precondizione: il componente risulta fra quelli attivi.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->componente = plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE );

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();

		update_option( 'active_plugins', array( $this->componente ) );
	}

	/**
	 * L'ambiente e davvero quello previsto, e niente e gia successo.
	 */
	public function test_precondizione_versione_incompatibile(): void {
		$this->assertSame( '2.0.0', conformita_core_versione_api() );

		$this->assertFalse(
			conformita_core_api_compatibile( \TrasparenzaPa\CORE_API_RICHIESTA, conformita_core_versione_api() ),
			'Precondizione: le due versioni non devono essere compatibili.'
		);

		$this->assertFalse(
			has_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) ),
			'Precondizione: la procedura non deve essere agganciata, altrimenti e gia girata una volta.'
		);

		$this->assertSame( '', $this->avvisi_in_bacheca(), 'Precondizione: nessun avviso deve preesistere.' );
	}

	/**
	 * T-05: la guardia disattiva il componente e non registra niente.
	 */
	public function test_t05_effetto_completo_della_guardia(): void {
		$this->assertSame( '', $this->avvisi_in_bacheca(), 'La bacheca deve essere pulita prima della chiamata.' );

		$this->assertFalse( Avvio::esegui(), 'Con la versione incompatibile l\'avvio non prosegue.' );

		$this->assertNotContains(
			$this->componente,
			(array) get_option( 'active_plugins', array() ),
			'La guardia del componente comune deve disattivare il componente.'
		);

		$this->assertNotContains( \TrasparenzaPa\SEZIONE, conformita_core_sezioni_registrate(), 'Nessuna sezione deve risultare registrata.' );

		$avviso = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( \TrasparenzaPa\CORE_API_RICHIESTA, $avviso, 'L\'avviso deve dire la versione richiesta.' );
		$this->assertStringContainsString( conformita_core_versione_api(), $avviso, 'L\'avviso deve dire la versione trovata.' );
	}
}
