<?php
/**
 * Prove del registro degli accessi sulla pagina pubblica: righe T-111..T-113.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\AccessoCivico;
use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\RegistroAccessi;
use TrasparenzaPa\Responsabile;
use WP_UnitTestCase;

/**
 * Cosa trova del registro degli accessi chi consulta la sezione.
 */
class RegistroAccessiPubblicoTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Sezione registrata, albero finto con la collocazione dell'accesso civico, nessun dato.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-accesso-civico.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero() );

		delete_option( RegistroAccessi::OPZIONE );
		delete_option( AccessoCivico::OPZIONE );
		delete_option( Responsabile::OPZIONE );
	}

	/**
	 * Rimette l'albero finto e il fuso, come li lascia l'avvio della suite.
	 */
	public function tear_down(): void {
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 0 );

		parent::tear_down();
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
	 * Conserva le righe date, con l'ultimo numero.
	 *
	 * @param array<int, mixed> $righe Righe per numero.
	 */
	private function conserva( array $righe ): void {
		update_option(
			RegistroAccessi::OPZIONE,
			array(
				'ultimo' => array() === $righe ? 0 : max( array_keys( $righe ) ),
				'righe'  => $righe,
			)
		);
	}

	/**
	 * L'anno di oggi nel fuso del sito, meno gli anni dati.
	 *
	 * @param int $meno Anni da togliere.
	 * @return int
	 */
	private function anno( int $meno ): int {
		return (int) substr( RegistroAccessi::oggi(), 0, 4 ) - $meno;
	}

	/**
	 * Una riga decisa.
	 *
	 * @param string $richiesta Data della richiesta.
	 * @param string $decisione Data della decisione.
	 * @param array  $altro     Altri campi.
	 * @return array<string, string>
	 */
	private function decisa( string $richiesta, string $decisione, array $altro = array() ): array {
		return array_merge(
			array(
				'tipo'      => 'generalizzato',
				'argomento' => 'Spese per la manutenzione dei sentieri',
				'richiesta' => $richiesta,
				'esito'     => 'accolta_in_parte',
				'decisione' => $decisione,
			),
			$altro
		);
	}

	/**
	 * Una riga in corso.
	 *
	 * @param string $richiesta Data della richiesta.
	 * @param array  $altro     Altri campi.
	 * @return array<string, string>
	 */
	private function in_corso( string $richiesta, array $altro = array() ): array {
		return array_merge(
			array(
				'tipo'      => 'semplice',
				'argomento' => 'Curricula dei titolari di incarichi',
				'richiesta' => $richiesta,
				'esito'     => '',
				'decisione' => '',
			),
			$altro
		);
	}

	/**
	 * T-111: una riga per richiesta, con i nomi di tipo ed esito, le date GG/MM/AAAA e l'ordine della schermata.
	 */
	public function test_t111_righe(): void {
		$anno = $this->anno( 1 );

		$this->conserva(
			array(
				2 => $this->decisa( "{$anno}-04-20", "{$anno}-05-02" ),
				4 => $this->in_corso( "{$anno}-03-10", array( 'argomento' => 'Pari data' ) ),
				7 => $this->in_corso( "{$anno}-03-10", array( 'tipo' => 'documentale' ) ),
			)
		);

		$html = $this->pagina();

		$this->assertSame( 1, substr_count( $html, 'class="trasparenza-pa-registro-accessi"' ) );
		$this->assertStringContainsString( '<h3>Registro degli accessi</h3>', $html );
		$this->assertStringContainsString( '<thead><tr><th scope="col">Tipo</th><th scope="col">Argomento</th><th scope="col">Data della richiesta</th><th scope="col">Esito</th><th scope="col">Data della decisione</th></tr></thead>', $html );

		$righe = array(
			"<tr><td>Accesso civico generalizzato</td><td>Spese per la manutenzione dei sentieri</td><td>20/04/{$anno}</td><td>Accolta in parte</td><td>02/05/{$anno}</td></tr>",
			"<tr><td>Accesso agli atti (l. 241/1990)</td><td>Curricula dei titolari di incarichi</td><td>10/03/{$anno}</td><td>In corso</td><td></td></tr>",
			"<tr><td>Accesso civico semplice</td><td>Pari data</td><td>10/03/{$anno}</td><td>In corso</td><td></td></tr>",
		);

		$posizione = 0;

		foreach ( $righe as $riga ) {
			$trovata = strpos( $html, $riga, $posizione );

			$this->assertIsInt( $trovata, "Riga mancante o fuori ordine: {$riga}" );
			$posizione = $trovata + strlen( $riga );
		}

		$this->assertSame( 3, substr_count( $html, '<tr><td>' ), 'Una riga per richiesta.' );
		$this->assertStringNotContainsString( 'Il registro non contiene', $html );
	}

	/**
	 * T-111: [attacco] argomento con codice o entita, righe non valide, registro di forma strana.
	 */
	public function test_t111_valori_e_righe_strane(): void {
		$anno = $this->anno( 1 );

		$this->conserva(
			array(
				1 => $this->decisa( "{$anno}-01-10", "{$anno}-01-20", array( 'argomento' => 'Spese &amp; "forniture"' ) ),
				2 => $this->decisa( "{$anno}-01-10", "{$anno}-01-20", array( 'argomento' => '<script>alert(1)</script>' ) ),
				3 => $this->decisa( "{$anno}-01-10", "{$anno}-01-05" ),
				5 => $this->in_corso( "{$anno}-01-10", array( 'tipo' => 'altro' ) ),
			)
		);

		$html = $this->pagina();

		$this->assertSame( 1, substr_count( $html, '<tr><td>' ), 'Solo la riga valida.' );
		$this->assertStringContainsString( '<td>Spese &amp;amp; &quot;forniture&quot;</td>', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'Accesso agli atti', $html );

		foreach ( array(
			'non un registro'  => 'righe',
			'righe non elenco' => array(
				'ultimo' => 3,
				'righe'  => 'riga',
			),
		) as $caso => $conservato ) {
			update_option( RegistroAccessi::OPZIONE, $conservato );

			$html = $this->pagina();

			$this->assertStringContainsString( '<p>Il registro non contiene richieste negli ultimi cinque anni.</p>', $html, $caso );
			$this->assertStringNotContainsString( '<table', $html, $caso );
		}

		delete_option( RegistroAccessi::OPZIONE );
		$this->assertStringContainsString( '<h3>Registro degli accessi</h3><p>Il registro non contiene richieste negli ultimi cinque anni.</p>', $this->pagina(), 'Registro mai scritto.' );
	}

	/**
	 * T-112: cinque anni dal 1 gennaio successivo alla decisione; la richiesta in corso resta.
	 */
	public function test_t112_cinque_anni(): void {
		$decisa   = $this->decisa( '2020-01-10', '2025-06-01' );
		$in_corso = $this->in_corso( '2019-05-05' );
		$vecchia  = $this->decisa( '2020-01-10', '2020-02-01' );

		$this->conserva(
			array(
				1 => $decisa,
				2 => $in_corso,
				3 => $vecchia,
			)
		);

		$this->assertSame(
			array(
				1 => $decisa,
				2 => $in_corso,
			),
			RegistroAccessi::pubblicabili( '2026-01-01' ),
			'La riga decisa nel 2020 esce il 1/1/2026.'
		);
		$this->assertSame( array( 1, 2 ), array_keys( RegistroAccessi::pubblicabili( '2030-12-31' ) ), 'Decisa nel 2025: visibile fino al 31/12/2030, anche se la richiesta e del 2020.' );
		$this->assertSame( array( 2 ), array_keys( RegistroAccessi::pubblicabili( '2031-01-01' ) ), 'Dal 1/1/2031 no; la richiesta in corso resta.' );
		$this->assertSame( array( 2 ), array_keys( RegistroAccessi::pubblicabili( '2099-06-30' ) ) );
		$this->assertSame( array( 3, 1, 2 ), array_keys( RegistroAccessi::pubblicabili( '2025-12-31' ) ), 'Decisa nel 2020: visibile fino al 31/12/2025; a pari data della richiesta prima il numero piu alto.' );
	}

	/**
	 * T-112: sulla pagina decide l'anno di oggi nel fuso del sito.
	 */
	public function test_t112_pagina_nel_fuso_del_sito(): void {
		update_option( 'timezone_string', 'Pacific/Kiritimati' );

		$cinque = $this->anno( 5 );
		$sei    = $this->anno( 6 );

		$this->conserva(
			array(
				1 => $this->decisa( "{$cinque}-01-01", "{$cinque}-01-01", array( 'argomento' => 'Dentro i cinque anni' ) ),
				2 => $this->decisa( "{$sei}-12-31", "{$sei}-12-31", array( 'argomento' => 'Fuori dai cinque anni' ) ),
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Dentro i cinque anni', $html );
		$this->assertStringNotContainsString( 'Fuori dai cinque anni', $html );
	}

	/**
	 * T-112: con un fuso lontano da UTC la riga di oggi si vede e quella di domani no; l'attesa si calcola a parte.
	 */
	public function test_t112_giorno_del_sito_per_le_date_future(): void {
		$adesso = time();

		list( $fuso, $scarto ) = (int) gmdate( 'G', $adesso ) >= 10 ? array( 'Pacific/Kiritimati', 14 ) : array( 'Pacific/Pago_Pago', -11 );

		update_option( 'timezone_string', $fuso );
		update_option( 'gmt_offset', '' );

		$oggi   = gmdate( 'Y-m-d', $adesso + $scarto * HOUR_IN_SECONDS );
		$domani = gmdate( 'Y-m-d', $adesso + $scarto * HOUR_IN_SECONDS + DAY_IN_SECONDS );

		$this->assertNotSame( gmdate( 'Y-m-d', $adesso ), $oggi, 'Precondizione: nel fuso scelto il giorno e diverso da quello UTC.' );

		$this->conserva(
			array(
				1 => $this->decisa( $oggi, $oggi, array( 'argomento' => 'Decisa oggi' ) ),
				2 => $this->decisa( $domani, $domani, array( 'argomento' => 'Decisa domani' ) ),
			)
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'Decisa oggi', $html );
		$this->assertStringNotContainsString( 'Decisa domani', $html );
	}

	/**
	 * T-114: [guasto] con la lettura fallita la pagina non dice che il registro e vuoto.
	 */
	public function test_t114_lettura_fallita(): void {
		global $wpdb;

		$anno = $this->anno( 1 );

		$this->conserva( array( 1 => $this->decisa( "{$anno}-01-10", "{$anno}-01-20" ) ) );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( RegistroAccessi::OPZIONE, 'options' );

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name = '" . RegistroAccessi::OPZIONE . "'" )
					? 'SELECT option_value FROM ' . $wpdb->options . '_inesistente'
					: $sql;
			}
		);

		try {
			$html = $this->pagina();
		} finally {
			remove_all_filters( 'query' );
			$wpdb->suppress_errors( false );
		}

		$this->assertStringContainsString( '<h3>Registro degli accessi</h3><p>Il registro non è disponibile in questo momento.</p>', $html );
		$this->assertStringNotContainsString( 'Il registro non contiene', $html );
		$this->assertStringNotContainsString( '<table', $html );
	}

	/**
	 * T-113: il registro solo nella voce indicata dall'albero, sotto il riquadro, anche senza i dati dell'accesso civico.
	 */
	public function test_t113_dove(): void {
		$anno = $this->anno( 1 );

		$this->conserva( array( 1 => $this->decisa( "{$anno}-01-10", "{$anno}-01-20" ) ) );

		$html = $this->pagina();

		$this->assertStringContainsString( 'class="trasparenza-pa-registro-accessi"', $html, 'Anche senza i dati dell\'accesso civico.' );
		$this->assertStringNotContainsString( 'trasparenza-pa-accesso-civico"', $html );

		update_option(
			AccessoCivico::OPZIONE,
			array(
				'ufficio'            => 'Ufficio relazioni con il pubblico',
				'ufficio_email'      => 'urp@example.org',
				'ufficio_telefono'   => '+39 06 1234567',
				'sostituto'          => 'Paolo Verdi, segretario generale',
				'sostituto_email'    => 'sostituto@example.org',
				'sostituto_telefono' => '06 7654321',
				'modulo'             => '',
			)
		);

		$html = $this->pagina();

		$this->assertIsInt( strpos( $html, 'class="trasparenza-pa-accesso-civico"' ) );
		$this->assertGreaterThan( strpos( $html, 'class="trasparenza-pa-accesso-civico"' ), strpos( $html, 'class="trasparenza-pa-registro-accessi"' ), 'Sotto il riquadro.' );

		foreach ( array( 'radice', 'zeta', 'zeta_uno', 'alfa' ) as $voce ) {
			$this->assertStringNotContainsString( 'trasparenza-pa-registro-accessi', $this->pagina( $voce ), "Voce {$voce}." );
		}

		$albero = Alberatura::carica( __DIR__ . '/dati/albero-finto.json' );

		$this->assertInstanceOf( Alberatura::class, $albero );

		foreach ( $albero->identificativi() as $voce ) {
			$this->assertStringNotContainsString( 'trasparenza-pa-registro-accessi', PaginaPubblica::disegna( $albero, $voce, 'https://example.org/sezione/' ), "Albero senza collocazione, voce {$voce}." );
		}
	}
}
