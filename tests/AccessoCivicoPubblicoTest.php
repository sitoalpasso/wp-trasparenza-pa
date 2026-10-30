<?php
/**
 * Prove del riquadro pubblico dell'accesso civico: righe T-103 e T-104.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\AccessoCivico;
use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Responsabile;
use WP_UnitTestCase;

/**
 * Cosa trova nella voce dell'accesso civico chi consulta la sezione.
 */
class AccessoCivicoPubblicoTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Sezione registrata, albero finto con le due collocazioni, nessun dato.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-accesso-civico.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero() );

		delete_option( AccessoCivico::OPZIONE );
		delete_option( Responsabile::OPZIONE );
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
	 * Dati validi dell'accesso civico.
	 *
	 * @return array<string, string>
	 */
	private function validi(): array {
		return array(
			'ufficio'            => 'Ufficio relazioni con il pubblico',
			'ufficio_email'      => 'urp@example.org',
			'ufficio_telefono'   => '+39 06 1234567',
			'sostituto'          => 'Paolo Verdi, segretario generale',
			'sostituto_email'    => 'sostituto@example.org',
			'sostituto_telefono' => '06 7654321',
			'modulo'             => 'https://example.org/moduli/accesso-civico.pdf?formato=pdf&lingua=it',
		);
	}

	/**
	 * Dati validi del responsabile.
	 *
	 * @return array<string, string>
	 */
	private function responsabile(): array {
		return array(
			'nome'     => 'Maria Rossi',
			'email'    => 'responsabile@example.org',
			'telefono' => '06 1111111',
			'nomina'   => '',
		);
	}

	/**
	 * Una voce dell'albero in uso, disegnata.
	 *
	 * @param string $voce Voce.
	 * @return string
	 */
	private function pagina( string $voce = 'zeta_due' ): string {
		return PaginaPubblica::disegna( PaginaPubblica::albero(), $voce, 'https://example.org/sezione/' );
	}

	/**
	 * T-103: con il responsabile indicato, il riquadro dice a chi si scrive per ciascun accesso, il sostituto e il riesame.
	 */
	public function test_t103_riquadro_completo(): void {
		update_option( AccessoCivico::OPZIONE, $this->validi() );
		update_option( Responsabile::OPZIONE, $this->responsabile() );

		$html = $this->pagina();

		$this->assertSame( 1, substr_count( $html, 'class="trasparenza-pa-accesso-civico"' ) );
		$this->assertStringContainsString( '<h3>Come si chiede l&#039;accesso civico</h3>', $html );

		$parti = array(
			'<h4>Accesso civico semplice</h4>',
			'Chiunque può chiedere i dati che la sezione dovrebbe pubblicare e non pubblica (art. 5, c. 1, del d.lgs. 33/2013). La richiesta va al responsabile della prevenzione della corruzione e della trasparenza:</p><ul><li>Maria Rossi</li><li>Email: <a href="mailto:responsabile@example.org">responsabile@example.org</a></li><li>Telefono: 06 1111111</li></ul>',
			'<h4>Accesso civico generalizzato</h4>',
			'Chiunque può chiedere dati e documenti ulteriori che l&#039;ente detiene, nei limiti dell&#039;art. 5-bis (art. 5, c. 2, del d.lgs. 33/2013). La richiesta va a:</p><ul><li>Ufficio relazioni con il pubblico</li><li>Email: <a href="mailto:urp@example.org">urp@example.org</a></li><li>Telefono: +39 06 1234567</li></ul>',
			'La richiesta non va motivata ed è gratuita, salvo il rimborso del costo di riproduzione su supporti materiali (art. 5, cc. 3 e 4, del d.lgs. 33/2013).',
			'<a href="https://example.org/moduli/accesso-civico.pdf?formato=pdf&#038;lingua=it">Modulo per la richiesta</a>',
			'<h4>Se la risposta non arriva</h4>',
			'Nei casi di ritardo o mancata risposta ci si può rivolgere al titolare del potere sostitutivo (art. 2, c. 9-bis, della l. 241/1990):</p><ul><li>Paolo Verdi, segretario generale</li><li>Email: <a href="mailto:sostituto@example.org">sostituto@example.org</a></li><li>Telefono: 06 7654321</li></ul>',
			'<h4>Contro il rifiuto</h4>',
			'Contro il rifiuto, totale o parziale, o la mancata risposta a una richiesta generalizzata si può chiedere il riesame al responsabile della prevenzione della corruzione e della trasparenza (art. 5, c. 7, del d.lgs. 33/2013).',
		);

		$posizione = 0;

		foreach ( $parti as $parte ) {
			$trovata = strpos( $html, $parte, $posizione );

			$this->assertIsInt( $trovata, "Parte mancante o fuori ordine: {$parte}" );
			$posizione = $trovata + strlen( $parte );
		}
	}

	/**
	 * T-103: senza responsabile la frase resta senza nome e recapiti; senza telefono del responsabile, niente riga vuota; senza modulo, niente collegamento.
	 */
	public function test_t103_senza_responsabile_e_senza_modulo(): void {
		update_option( AccessoCivico::OPZIONE, array_merge( $this->validi(), array( 'modulo' => '' ) ) );

		$html = $this->pagina();

		$this->assertStringContainsString( 'La richiesta va al responsabile della prevenzione della corruzione e della trasparenza.</p>', $html );
		$this->assertStringNotContainsString( 'Maria Rossi', $html );
		$this->assertStringNotContainsString( 'Modulo per la richiesta', $html );
		$this->assertStringContainsString( 'Paolo Verdi, segretario generale', $html );

		update_option( Responsabile::OPZIONE, array_merge( $this->responsabile(), array( 'nome' => '<b>Maria</b>' ) ) );

		$html = $this->pagina();

		$this->assertStringContainsString( 'La richiesta va al responsabile della prevenzione della corruzione e della trasparenza.</p>', $html, 'Un responsabile conservato non valido vale come non indicato.' );
		$this->assertStringNotContainsString( 'Maria', $html );

		update_option( Responsabile::OPZIONE, array_merge( $this->responsabile(), array( 'telefono' => '' ) ) );

		$html = $this->pagina();

		$this->assertStringContainsString( '<ul><li>Maria Rossi</li><li>Email: <a href="mailto:responsabile@example.org">responsabile@example.org</a></li></ul>', $html );
	}

	/**
	 * T-103: il riquadro solo nella voce indicata dall'albero.
	 */
	public function test_t103_solo_nella_voce(): void {
		update_option( AccessoCivico::OPZIONE, $this->validi() );
		update_option( Responsabile::OPZIONE, $this->responsabile() );

		foreach ( array( 'radice', 'zeta', 'zeta_uno', 'alfa' ) as $voce ) {
			$this->assertStringNotContainsString( 'trasparenza-pa-accesso-civico', $this->pagina( $voce ), "Voce {$voce}." );
		}
	}

	/**
	 * T-104: [attacco] senza dati validi nessun riquadro.
	 */
	public function test_t104_senza_dati_validi(): void {
		update_option( Responsabile::OPZIONE, $this->responsabile() );

		$this->assertStringNotContainsString( 'trasparenza-pa-accesso-civico', $this->pagina(), 'Dati non indicati.' );

		foreach ( array(
			'sostituto con codice' => array_merge( $this->validi(), array( 'sostituto' => '<script>x</script>' ) ),
			'modulo javascript'    => array_merge( $this->validi(), array( 'modulo' => 'javascript:alert(1)' ) ),
			'telefono mancante'    => array_merge( $this->validi(), array( 'ufficio_telefono' => '' ) ),
			'non un elenco'        => 'Ufficio relazioni con il pubblico',
		) as $caso => $conservato ) {
			update_option( AccessoCivico::OPZIONE, $conservato );

			$html = $this->pagina();

			$this->assertStringNotContainsString( 'trasparenza-pa-accesso-civico', $html, $caso );
			$this->assertStringNotContainsString( '<script', $html, $caso );
			$this->assertStringNotContainsString( 'javascript', $html, $caso );
		}
	}

	/**
	 * T-104: albero senza la collocazione: nessun riquadro in nessuna voce.
	 */
	public function test_t104_senza_collocazione(): void {
		update_option( AccessoCivico::OPZIONE, $this->validi() );

		$albero = Alberatura::carica( __DIR__ . '/dati/albero-finto.json' );

		$this->assertInstanceOf( Alberatura::class, $albero );

		foreach ( $albero->identificativi() as $voce ) {
			$this->assertStringNotContainsString( 'trasparenza-pa-accesso-civico', PaginaPubblica::disegna( $albero, $voce, 'https://example.org/sezione/' ), $voce );
		}
	}

	/**
	 * T-104: [attacco] i valori escono con escaping.
	 */
	public function test_t104_escaping(): void {
		update_option(
			AccessoCivico::OPZIONE,
			array_merge(
				$this->validi(),
				array(
					'ufficio'       => 'Ufficio "A" & B',
					'ufficio_email' => 'urp&info@example.org',
					'sostituto'     => 'Paolo & Verdi',
				)
			)
		);
		update_option( Responsabile::OPZIONE, array_merge( $this->responsabile(), array( 'nome' => 'Maria & Rossi' ) ) );

		$html = $this->pagina();

		$this->assertStringContainsString( '<li>Ufficio &quot;A&quot; &amp; B</li>', $html );
		$this->assertStringContainsString( '<li>Paolo &amp; Verdi</li>', $html );
		$this->assertStringContainsString( '<li>Maria &amp; Rossi</li>', $html );
		$this->assertStringContainsString( '<a href="mailto:urp&#038;info@example.org">urp&amp;info@example.org</a>', $html );
	}
}
