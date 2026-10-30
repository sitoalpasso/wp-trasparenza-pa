<?php
/**
 * Prove del rinvio sulla pagina pubblica: righe T-97 e T-98.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Rinvii;
use TrasparenzaPa\StatiObbligo;
use WP_UnitTestCase;

/**
 * Cosa vede chi consulta la voce di un rinvio, e quando non lo vede.
 */
class RinviiPubbliciTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Albero finto con le banche dati.
	 *
	 * @var Alberatura
	 */
	private $albero;

	/**
	 * Sezione registrata, albero finto con le banche dati, nessun rinvio, nessuno stato, date in un formato noto.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-rinvii.json' );
		PaginaPubblica::da_init();

		$albero = PaginaPubblica::albero();
		$this->assertInstanceOf( Alberatura::class, $albero, 'Precondizione: l\'albero finto con le banche dati si legge.' );
		$this->albero = $albero;

		delete_option( Rinvii::OPZIONE );
		delete_option( StatiObbligo::OPZIONE );
		update_option( 'date_format', 'd/m/Y' );
	}

	/**
	 * Rimette l'albero finto, come lo lascia l'avvio della suite.
	 */
	public function tear_down(): void {
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Un rinvio valido per la voce zeta_uno.
	 *
	 * @return array<string, string>
	 */
	private function valido(): array {
		return array(
			'banca'     => 'banca_uno',
			'indirizzo' => 'https://example.org/banca/ente?dato=1',
			'etichetta' => 'Dati dell\'ente nella Banca Uno',
			'data'      => '2026-01-15',
		);
	}

	/**
	 * La pagina di una voce.
	 *
	 * @param string $voce Voce.
	 * @return string
	 */
	private function pagina( string $voce ): string {
		return PaginaPubblica::disegna( $this->albero, $voce, 'https://example.org/trasparenza/' );
	}

	/**
	 * T-97: la voce con un rinvio a una banca dati con chi la tiene mostra collegamento, banca dati, data e accesso civico.
	 */
	public function test_t97_rinvio_con_titolare(): void {
		update_option( Rinvii::OPZIONE, array( 'zeta_uno' => $this->valido() ) );

		$html = $this->pagina( 'zeta_uno' );

		$this->assertSame( 1, substr_count( $html, 'class="trasparenza-pa-rinvio"' ) );
		$this->assertStringContainsString( '<h3>Dati nella banca dati nazionale</h3>', $html );
		$this->assertStringContainsString( '<a href="https://example.org/banca/ente?dato=1">Dati dell&#039;ente nella Banca Uno</a>', $html );
		$this->assertStringContainsString( 'Banca dati: Banca Uno, tenuta da Titolare Uno.', $html );
		$this->assertStringContainsString( '<time datetime="2026-01-15">15/01/2026</time>', $html );
		$this->assertStringContainsString( 'la richiesta di accesso civico va al responsabile della prevenzione della corruzione e della trasparenza di chi tiene la banca dati, Titolare Uno; se la mancanza dipende da questo ente, va al responsabile di questo ente (art. 9-bis, commi 3 e 4, del d.lgs. 33/2013).', $html );
	}

	/**
	 * T-97: una banca dati senza chi la tiene: niente titolare e niente frase sull'accesso civico; il nome con escaping.
	 */
	public function test_t97_rinvio_senza_titolare(): void {
		update_option( Rinvii::OPZIONE, array( 'alfa' => array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) ) ) );

		$html = $this->pagina( 'alfa' );

		$this->assertStringContainsString( 'class="trasparenza-pa-rinvio"', $html );
		$this->assertStringContainsString( 'Banca dati: Banca Tre &lt;b&gt;.', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'tenuta da', $html );
		$this->assertStringNotContainsString( 'accesso civico', $html );
	}

	/**
	 * T-97: il riquadro solo nella voce del rinvio, non nelle altre.
	 */
	public function test_t97_solo_nella_voce(): void {
		update_option( Rinvii::OPZIONE, array( 'zeta_uno' => $this->valido() ) );

		foreach ( array( 'radice', 'zeta', 'zeta_due', 'alfa' ) as $voce ) {
			$this->assertStringNotContainsString( 'trasparenza-pa-rinvio', $this->pagina( $voce ), "Voce {$voce}: nessun riquadro." );
		}
	}

	/**
	 * T-97: con lo stato non applicabile o sospeso il riquadro resta, dopo la dicitura.
	 */
	public function test_t97_con_lo_stato(): void {
		update_option( Rinvii::OPZIONE, array( 'zeta_uno' => $this->valido() ) );

		foreach ( array( StatiObbligo::NON_APPLICABILE, StatiObbligo::SOSPESO ) as $stato ) {
			$this->assertTrue( StatiObbligo::imposta( $this->albero, 'zeta_uno', $stato ) );

			$html     = $this->pagina( 'zeta_uno' );
			$dicitura = strpos( $html, 'trasparenza-pa-dicitura' );
			$rinvio   = strpos( $html, 'trasparenza-pa-rinvio' );

			$this->assertIsInt( $dicitura, "Stato {$stato}: dicitura." );
			$this->assertIsInt( $rinvio, "Stato {$stato}: riquadro." );
			$this->assertGreaterThan( $dicitura, $rinvio, "Stato {$stato}: il riquadro dopo la dicitura." );
		}
	}

	/**
	 * T-98: [attacco] un rinvio conservato che non supera il controllo non si mostra.
	 */
	public function test_t98_rinvii_conservati_non_validi(): void {
		$casi = array(
			'banca di altra voce'     => array( 'zeta_uno' => array_merge( $this->valido(), array( 'banca' => 'banca_tre' ) ) ),
			'banca inesistente'       => array( 'zeta_uno' => array_merge( $this->valido(), array( 'banca' => 'banca_zero' ) ) ),
			'voce senza banche'       => array( 'zeta_due' => $this->valido() ),
			'indirizzo altro schema'  => array( 'zeta_uno' => array_merge( $this->valido(), array( 'indirizzo' => 'javascript:alert(1)' ) ) ),
			'indirizzo con codice'    => array( 'zeta_uno' => array_merge( $this->valido(), array( 'indirizzo' => 'https://example.org/"><script>' ) ) ),
			'etichetta con codice'    => array( 'zeta_uno' => array_merge( $this->valido(), array( 'etichetta' => '<script>x</script>' ) ) ),
			'data futura'             => array( 'zeta_uno' => array_merge( $this->valido(), array( 'data' => '2999-01-01' ) ) ),
			'data inesistente'        => array( 'zeta_uno' => array_merge( $this->valido(), array( 'data' => '2026-02-30' ) ) ),
			'campo mancante'          => array( 'zeta_uno' => array_diff_key( $this->valido(), array( 'etichetta' => 0 ) ) ),
			'campo non testo'         => array( 'zeta_uno' => array_merge( $this->valido(), array( 'data' => 20260115 ) ) ),
			'rinvio vuoto conservato' => array( 'zeta_uno' => Rinvii::vuoto() ),
			'opzione non elenco'      => 'zeta_uno',
		);

		foreach ( $casi as $nome => $conservato ) {
			update_option( Rinvii::OPZIONE, $conservato );

			foreach ( array( 'zeta_uno', 'zeta_due' ) as $voce ) {
				$html = $this->pagina( $voce );

				$this->assertStringNotContainsString( 'trasparenza-pa-rinvio', $html, "Caso {$nome}, voce {$voce}." );
				$this->assertStringNotContainsString( '<script', $html, "Caso {$nome}, voce {$voce}." );
				$this->assertStringNotContainsString( 'javascript', $html, "Caso {$nome}, voce {$voce}." );
			}
		}
	}

	/**
	 * T-98: il rinvio valido di una voce si mostra anche se quello di un'altra voce non vale.
	 */
	public function test_t98_rinvio_valido_accanto_a_uno_non_valido(): void {
		update_option(
			Rinvii::OPZIONE,
			array(
				'zeta_due' => $this->valido(),
				'zeta_uno' => $this->valido(),
			)
		);

		$this->assertStringContainsString( 'trasparenza-pa-rinvio', $this->pagina( 'zeta_uno' ) );
		$this->assertStringNotContainsString( 'trasparenza-pa-rinvio', $this->pagina( 'zeta_due' ) );
	}
}
