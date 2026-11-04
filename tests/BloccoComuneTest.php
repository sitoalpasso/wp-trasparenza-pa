<?php
/**
 * Prove del blocco dei salvataggi sui lucchetti del componente comune: riga T-167.
 *
 * Per la banca dati che tiene un solo blocco per connessione si usa
 * l'imitazione che il componente comune tiene fra le sue prove. Per vedere che
 * cosa vede un'altra richiesta si apre una seconda connessione e la si mette
 * al posto di quella del sito per il tempo di una chiamata.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Blocco;
use WP_UnitTestCase;
use wpdb;

/**
 * Il blocco della sezione passa dal componente comune.
 */
class BloccoComuneTest extends WP_UnitTestCase {

	/**
	 * Il lucchetto di un altro componente dello stesso sito.
	 */
	const ALTRO = 'prova_altro_componente_turno';

	/**
	 * La seconda connessione, se aperta.
	 *
	 * @var wpdb|null
	 */
	private $altra = null;

	/**
	 * L'imitazione della banca dati vecchia, dalle prove del componente comune.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		require_once CONFORMITA_CORE_PERCORSO . 'tests/class-conformita-core-lucchetto-unico.php';
	}

	/**
	 * Ogni prova parte senza lucchetti e senza stato ricordato.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Lucchetto_Unico::rilascia_tutti( $GLOBALS['wpdb'] );
		\Conformita_Core_Lucchetto_Unico::dimentica_stato();
	}

	/**
	 * Spegne l'imitazione e lascia i lucchetti, su tutte e due le connessioni.
	 */
	public function tear_down(): void {
		if ( null !== $this->altra ) {
			\Conformita_Core_Lucchetto_Unico::rilascia_tutti( $this->altra );
			$this->altra->close();
			$this->altra = null;
		}

		\Conformita_Core_Lucchetto_Unico::disattiva();
		\Conformita_Core_Lucchetto_Unico::rilascia_tutti( $GLOBALS['wpdb'] );
		\Conformita_Core_Lucchetto_Unico::dimentica_stato();

		parent::tear_down();
	}

	/**
	 * Esegue una chiamata come se venisse da un'altra richiesta.
	 *
	 * @param callable $chiamata Chiamata.
	 * @return mixed
	 */
	private function da_un_altra_richiesta( callable $chiamata ) {
		if ( null === $this->altra ) {
			$this->altra = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$this->altra->set_prefix( $GLOBALS['wpdb']->prefix );
		}

		$principale      = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = $this->altra; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la connessione di un'altra richiesta.

		try {
			return $chiamata();
		} finally {
			$GLOBALS['wpdb'] = $principale; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- si rimette quella del sito.
		}
	}

	/**
	 * Se un'altra richiesta riesce a prendere subito il blocco della sezione, e lo lascia.
	 *
	 * @return bool
	 */
	private function libero_per_altri(): bool {
		return (bool) $this->da_un_altra_richiesta(
			static function () {
				$preso = conformita_core_prendi_lucchetto( Blocco::nome(), 0 );

				if ( $preso ) {
					conformita_core_lascia_lucchetto( Blocco::nome() );
				}

				return $preso;
			}
		);
	}

	/**
	 * T-167: su una banca dati a blocco unico, il lucchetto di un altro
	 * componente preso dentro un salvataggio non apre il blocco della sezione.
	 */
	public function test_t167_lucchetto_di_un_altro_componente(): void {
		\Conformita_Core_Lucchetto_Unico::attiva();
		$this->assertTrue( conformita_core_lucchetto_unico(), 'Precondizione: la banca dati imitata tiene un blocco solo.' );

		$this->assertTrue( Blocco::prendi() );
		$this->assertFalse( $this->libero_per_altri(), 'Preso il blocco, un\'altra richiesta non entra.' );

		$this->assertTrue( conformita_core_prendi_lucchetto( self::ALTRO, 0 ), 'Il lucchetto dell\'altro componente, dentro il salvataggio.' );
		conformita_core_lascia_lucchetto( self::ALTRO );

		$this->assertFalse( $this->libero_per_altri(), 'Lasciato il lucchetto dell\'altro componente, il blocco resta chiuso.' );

		Blocco::rilascia();

		$this->assertTrue( $this->libero_per_altri(), 'Lasciato il salvataggio, il blocco e libero.' );
	}

	/**
	 * T-167: con il blocco tenuto da un'altra richiesta la presa non riesce; poi riesce.
	 */
	public function test_t167_blocco_tenuto_da_altri(): void {
		$this->assertTrue( $this->da_un_altra_richiesta( static fn() => conformita_core_prendi_lucchetto( Blocco::nome(), 0 ) ) );

		$this->assertFalse( Blocco::prendi(), 'Con il blocco tenuto da un\'altra richiesta, dopo l\'attesa si rinuncia.' );

		$this->da_un_altra_richiesta( static fn() => conformita_core_lascia_lucchetto( Blocco::nome() ) );

		$this->assertTrue( Blocco::prendi() );
		$this->assertFalse( $this->libero_per_altri() );
		Blocco::rilascia();
		$this->assertTrue( $this->libero_per_altri() );
	}

	/**
	 * T-167: interfaccia richiesta.
	 */
	public function test_t167_interfaccia(): void {
		$this->assertSame( '1.14.0', \TrasparenzaPa\CORE_API_RICHIESTA );
	}
}
