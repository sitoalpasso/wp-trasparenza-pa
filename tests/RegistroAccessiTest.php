<?php
/**
 * Prove del registro degli accessi: righe T-105..T-107.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\RegistroAccessi;
use TrasparenzaPa\SchermataRegistroAccessi;
use TrasparenzaPa\SchermataStati;
use WPDieException;
use WP_UnitTestCase;

/**
 * Chi tiene il registro degli accessi, quali righe si accettano, come si salvano.
 */
class RegistroAccessiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Utente con il permesso di amministrare la sezione.
	 *
	 * @var int
	 */
	private $responsabile;

	/**
	 * Amministratore del sito, senza il permesso della sezione.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Sezione registrata, registro vuoto, permesso a un solo utente.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-accesso-civico.json' );
		PaginaPubblica::da_init();

		delete_option( RegistroAccessi::OPZIONE );
		get_role( 'administrator' )->remove_cap( Permessi::AMMINISTRAZIONE );

		$this->responsabile   = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		get_user_by( 'id', $this->responsabile )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $this->responsabile );
	}

	/**
	 * Rimette l'albero finto e il permesso all'amministratore, come lo lascia l'avvio della suite.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'pre_update_option_' . RegistroAccessi::OPZIONE );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();
		get_role( 'administrator' )->add_cap( Permessi::AMMINISTRAZIONE );

		parent::tear_down();
	}

	/**
	 * Una riga valida, decisa.
	 *
	 * @return array<string, string>
	 */
	private function decisa(): array {
		return array(
			'tipo'      => 'generalizzato',
			'argomento' => 'Spese per la manutenzione dei sentieri nel 2025',
			'richiesta' => '2026-03-02',
			'esito'     => 'accolta_in_parte',
			'decisione' => '2026-03-30',
		);
	}

	/**
	 * Una riga valida, in corso.
	 *
	 * @return array<string, string>
	 */
	private function in_corso(): array {
		return array(
			'tipo'      => 'semplice',
			'argomento' => 'Curricula dei titolari di incarichi',
			'richiesta' => '2026-04-10',
			'esito'     => '',
			'decisione' => '',
		);
	}

	/**
	 * Una richiesta come la manderebbe il modulo, con il nonce dell'utente corrente.
	 *
	 * @param string                    $operazione Operazione.
	 * @param mixed                     $riga       Campi della riga.
	 * @param int|string|null           $numero     Numero della riga.
	 * @param array<string, mixed>|null $partenza   Riga mostrata all'apertura; nulla: quella conservata.
	 * @return array<string, mixed>
	 */
	private function richiesta( string $operazione, $riga = null, $numero = null, ?array $partenza = null ): array {
		$richiesta = array(
			'_wpnonce'                                 => wp_create_nonce( SchermataRegistroAccessi::AZIONE ),
			SchermataRegistroAccessi::CAMPO_OPERAZIONE => $operazione,
		);

		if ( null !== $riga ) {
			$richiesta[ SchermataRegistroAccessi::CAMPO ] = $riga;
		}

		if ( 'elimina' === $operazione ) {
			$richiesta[ SchermataRegistroAccessi::CAMPO_CONFERMA ] = '1';
		}

		if ( null !== $numero ) {
			$richiesta[ SchermataRegistroAccessi::CAMPO_NUMERO ]   = (string) $numero;
			$richiesta[ SchermataRegistroAccessi::CAMPO_PARTENZA ] = $partenza ?? ( RegistroAccessi::righe()[ (int) $numero ] ?? array() );
		}

		return $richiesta;
	}

	/**
	 * Le voci di registro del registro degli accessi, in ordine di scrittura.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce(): array {
		return conformita_core_voci_registro(
			array(
				'sezione' => \TrasparenzaPa\SEZIONE,
				'azione'  => SchermataRegistroAccessi::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * La schermata come la disegnerebbe WordPress per l'utente corrente.
	 *
	 * @return string
	 */
	private function schermata(): string {
		ob_start();

		try {
			SchermataRegistroAccessi::mostra();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * T-105: righe valide, in corso e decise, tornano uguali; oggi nel fuso del sito si accetta.
	 */
	public function test_t105_righe_valide(): void {
		$this->assertSame( $this->decisa(), RegistroAccessi::controlla( $this->decisa() ) );
		$this->assertSame( $this->in_corso(), RegistroAccessi::controlla( $this->in_corso() ) );

		foreach ( array_keys( RegistroAccessi::TIPI ) as $tipo ) {
			$riga = array_merge( $this->decisa(), array( 'tipo' => $tipo ) );
			$this->assertSame( $riga, RegistroAccessi::controlla( $riga ), $tipo );
		}

		foreach ( array_keys( RegistroAccessi::ESITI ) as $esito ) {
			$riga = array_merge( $this->decisa(), array( 'esito' => $esito ), '' === $esito ? array( 'decisione' => '' ) : array() );
			$this->assertSame( $riga, RegistroAccessi::controlla( $riga ), "Esito {$esito}." );
		}

		$this->assertSame( array( 'documentale', 'semplice', 'generalizzato' ), array_keys( RegistroAccessi::TIPI ) );
		$this->assertSame( array( '', 'accolta', 'accolta_in_parte', 'rifiutata', 'differita' ), array_keys( RegistroAccessi::ESITI ) );

		$stesso = array_merge( $this->decisa(), array( 'decisione' => '2026-03-02' ) );
		$this->assertSame( $stesso, RegistroAccessi::controlla( $stesso ), 'Decisa il giorno stesso.' );

		$lungo = array_merge( $this->decisa(), array( 'argomento' => str_repeat( 'à', 300 ) ) );
		$this->assertSame( $lungo, RegistroAccessi::controlla( $lungo ), 'Trecento caratteri si accettano, anche accentati.' );
	}

	/**
	 * T-105: oggi e domani si contano nel fuso del sito, con un fuso lontano da UTC.
	 */
	public function test_t105_fuso_del_sito(): void {
		$adesso = time();

		list( $fuso, $scarto ) = (int) gmdate( 'G', $adesso ) >= 10 ? array( 'Pacific/Kiritimati', 14 ) : array( 'Pacific/Pago_Pago', -11 );

		update_option( 'timezone_string', $fuso );
		update_option( 'gmt_offset', '' );

		$oggi   = gmdate( 'Y-m-d', $adesso + $scarto * HOUR_IN_SECONDS );
		$domani = gmdate( 'Y-m-d', $adesso + $scarto * HOUR_IN_SECONDS + DAY_IN_SECONDS );

		$this->assertNotSame( gmdate( 'Y-m-d', $adesso ), $oggi, 'Precondizione: nel fuso scelto il giorno e diverso da quello UTC.' );
		$this->assertSame( $oggi, RegistroAccessi::oggi() );

		$riga = array_merge(
			$this->decisa(),
			array(
				'richiesta' => $oggi,
				'decisione' => $oggi,
			)
		);
		$this->assertSame( $riga, RegistroAccessi::controlla( $riga ) );
		$this->assertWPError( RegistroAccessi::controlla( array_merge( $riga, array( 'decisione' => $domani ) ) ) );
		$this->assertWPError( RegistroAccessi::controlla( array_merge( $this->in_corso(), array( 'richiesta' => $domani ) ) ) );
	}

	/**
	 * T-105: [attacco] righe non valide: errore con il motivo.
	 */
	public function test_t105_righe_non_valide(): void {
		$casi = array(
			'tipo sconosciuto'        => array( 'tipo' => 'altro' ),
			'tipo vuoto'              => array( 'tipo' => '' ),
			'esito sconosciuto'       => array( 'esito' => 'forse' ),
			'argomento vuoto'         => array( 'argomento' => '' ),
			'argomento con codice'    => array( 'argomento' => 'Spese <script>x</script>' ),
			'argomento su due righe'  => array( 'argomento' => "Spese\nsentieri" ),
			'argomento con spazi'     => array( 'argomento' => ' Spese' ),
			'argomento lungo'         => array( 'argomento' => str_repeat( 'à', 301 ) ),
			'richiesta vuota'         => array( 'richiesta' => '' ),
			'richiesta altro formato' => array( 'richiesta' => '02/03/2026' ),
			'richiesta con altro'     => array( 'richiesta' => '2026-03-02T10:00' ),
			'richiesta inesistente'   => array( 'richiesta' => '2026-02-30' ),
			'richiesta a capo'        => array( 'richiesta' => "2026-03-02\n" ),
			'richiesta con prefisso'  => array( 'richiesta' => '02026-03-02' ),
			'richiesta futura'        => array( 'richiesta' => '2999-01-01' ),
			'decisione altro formato' => array( 'decisione' => '30/03/2026' ),
			'decisione inesistente'   => array( 'decisione' => '2026-02-31' ),
			'decisione a capo'        => array( 'decisione' => "2026-03-30\n" ),
			'decisione futura'        => array( 'decisione' => '2999-01-01' ),
			'decisione prima'         => array( 'decisione' => '2026-03-01' ),
			'esito senza decisione'   => array( 'decisione' => '' ),
			'in corso con decisione'  => array( 'esito' => '' ),
			'tipo non testo'          => array( 'tipo' => array( 'semplice' ) ),
			'argomento non testo'     => array( 'argomento' => 12 ),
			'decisione non testo'     => array( 'decisione' => null ),
		);

		foreach ( $casi as $nome => $guasto ) {
			$esito = RegistroAccessi::controlla( array_merge( $this->decisa(), $guasto ) );

			$this->assertWPError( $esito, $nome );
			$this->assertSame( 'trasparenza_registro_accessi_dati', $esito->get_error_code(), $nome );
			$this->assertNotSame( '', $esito->get_error_message(), $nome );
		}

		foreach ( RegistroAccessi::CAMPI as $campo ) {
			$monca = $this->decisa();
			unset( $monca[ $campo ] );
			$this->assertWPError( RegistroAccessi::controlla( $monca ), "Senza {$campo}." );
		}
	}

	/**
	 * T-106: aggiunta, correzione, correzione senza cambi e rimozione, ciascuna con la sua voce di registro senza valori.
	 */
	public function test_t106_salvataggi_e_registro(): void {
		$esito = SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );

		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );
		$this->assertSame( 1, $esito['numero'] );
		$this->assertSame( array( 1 => $this->decisa() ), RegistroAccessi::righe() );
		$this->assertArrayNotHasKey( RegistroAccessi::OPZIONE, wp_load_alloptions(), 'Il registro non si carica a ogni pagina.' );

		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) )['codice'] );

		$decisa = array_merge(
			$this->in_corso(),
			array(
				'esito'     => 'rifiutata',
				'decisione' => '2026-05-08',
			)
		);
		$esito  = SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $decisa, 2 ) );

		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );
		$this->assertSame( $decisa, RegistroAccessi::righe()[2] );
		$this->assertSame( $this->decisa(), RegistroAccessi::righe()[1], 'L\'altra riga non si tocca.' );

		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $decisa, 2 ) )['codice'], 'Correzione senza cambi.' );

		$esito = SchermataRegistroAccessi::salva( $this->richiesta( 'elimina', null, 1 ) );

		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );
		$this->assertSame( array( 2 => $decisa ), RegistroAccessi::righe() );

		$this->assertSame( 3, SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) )['numero'], 'Il numero non si riusa.' );

		$tracce = $this->tracce();

		$this->assertSame(
			array(
				array(
					'operazione' => 'aggiungi',
					'numero'     => 1,
					'campi'      => RegistroAccessi::CAMPI,
				),
				array(
					'operazione' => 'aggiungi',
					'numero'     => 2,
					'campi'      => RegistroAccessi::CAMPI,
				),
				array(
					'operazione' => 'modifica',
					'numero'     => 2,
					'campi'      => array( 'esito', 'decisione' ),
				),
				array(
					'operazione' => 'elimina',
					'numero'     => 1,
					'campi'      => RegistroAccessi::CAMPI,
				),
				array(
					'operazione' => 'aggiungi',
					'numero'     => 3,
					'campi'      => RegistroAccessi::CAMPI,
				),
			),
			array_map(
				static fn( array $dettagli ): array => array_intersect_key( $dettagli, array_flip( array( 'operazione', 'numero', 'campi' ) ) ),
				wp_list_pluck( $tracce, 'dettagli' )
			)
		);
	}

	/**
	 * T-109: la rimozione registra tipo, date ed esito della riga tolta; la correzione i valori di prima; mai l'argomento ne i valori nuovi.
	 */
	public function test_t109_valori_tolti_e_precedenti(): void {
		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );
		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) );

		$decisa = array_merge(
			$this->in_corso(),
			array(
				'argomento' => 'Curricula dei dirigenti',
				'esito'     => 'rifiutata',
				'decisione' => '2026-05-08',
			)
		);

		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $decisa, 2 ) )['codice'] );
		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'elimina', null, 1 ) )['codice'] );

		$dettagli = wp_list_pluck( $this->tracce(), 'dettagli' );

		$this->assertSame( array( 'operazione', 'numero', 'campi' ), array_keys( $dettagli[0] ), 'L\'aggiunta non ha valori di prima.' );
		$this->assertSame(
			array(
				'operazione'      => 'modifica',
				'numero'          => 2,
				'campi'           => array( 'argomento', 'esito', 'decisione' ),
				'prima_esito'     => '',
				'prima_decisione' => '',
			),
			$dettagli[2]
		);

		$this->assertSame(
			array(
				'operazione'      => 'elimina',
				'numero'          => 1,
				'campi'           => RegistroAccessi::CAMPI,
				'prima_tipo'      => 'generalizzato',
				'prima_richiesta' => '2026-03-02',
				'prima_esito'     => 'accolta_in_parte',
				'prima_decisione' => '2026-03-30',
			),
			$dettagli[3]
		);

		$scritto = (string) wp_json_encode( $this->tracce() );

		foreach ( array( 'sentieri', 'Curricula', 'dirigenti', '2026-05-08', 'rifiutata' ) as $valore ) {
			$this->assertStringNotContainsString( $valore, $scritto, 'Mai l\'argomento, mai i valori nuovi: ' . $valore );
		}
	}

	/**
	 * T-110: un'entita HTML scritta come testo torna dal modulo identica; la riga si corregge e si toglie.
	 */
	public function test_t110_entita_scritte_come_testo(): void {
		$riga = array_merge( $this->decisa(), array( 'argomento' => 'Spese &amp; forniture &lt;2025&gt;' ) );

		$this->assertSame( $riga, RegistroAccessi::controlla( $riga ), 'Il controllo la ammette.' );
		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $riga ) )['codice'] );

		$html = $this->schermata();

		$this->assertStringContainsString( 'value="Spese &amp;amp; forniture &amp;lt;2025&amp;gt;"', $html );

		// Quello che il browser rimanda: il valore degli attributi, con le entita sciolte.
		$rimandato = static function ( string $nome ) use ( $html ): string {
			// L'ultimo campo con quel nome: il primo e del modulo di aggiunta.
			preg_match_all( '/name="' . preg_quote( $nome, '/' ) . '" value="([^"]*)"/', $html, $parti );
			return html_entity_decode( (string) end( $parti[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		};

		$partenza = array();
		$valori   = array();

		foreach ( RegistroAccessi::CAMPI as $campo ) {
			$partenza[ $campo ] = $rimandato( SchermataRegistroAccessi::CAMPO_PARTENZA . '[' . $campo . ']' );
		}

		foreach ( array( 'argomento', 'richiesta', 'decisione' ) as $campo ) {
			$valori[ $campo ] = $rimandato( SchermataRegistroAccessi::CAMPO . '[' . $campo . ']' );
		}

		$this->assertSame( $riga, $partenza, 'La partenza torna identica.' );
		$this->assertSame( $riga['argomento'], $valori['argomento'], 'Il campo torna identico.' );

		$corretta = array_merge( $riga, $valori, array( 'esito' => 'accolta' ) );
		$esito    = SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $corretta, 1, $partenza ) );

		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );
		$this->assertSame( $corretta, RegistroAccessi::righe()[1] );
	}

	/**
	 * T-108: [attacco] togliere una riga senza la conferma non tocca niente; la correzione non la chiede.
	 */
	public function test_t108_conferma_per_togliere(): void {
		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );

		$conservato = get_option( RegistroAccessi::OPZIONE );
		$tracce     = count( $this->tracce() );
		$senza      = $this->richiesta( 'elimina', null, 1 );
		unset( $senza[ SchermataRegistroAccessi::CAMPO_CONFERMA ] );

		$casi = array(
			'senza conferma'   => $senza,
			'conferma vuota'   => array_merge( $senza, array( SchermataRegistroAccessi::CAMPO_CONFERMA => '' ) ),
			'conferma diversa' => array_merge( $senza, array( SchermataRegistroAccessi::CAMPO_CONFERMA => 'si' ) ),
			'conferma elenco'  => array_merge( $senza, array( SchermataRegistroAccessi::CAMPO_CONFERMA => array( '1' ) ) ),
		);

		foreach ( $casi as $nome => $richiesta ) {
			$esito = SchermataRegistroAccessi::salva( $richiesta );

			$this->assertSame( 'conferma', $esito['codice'], $nome );
			$this->assertStringContainsString( 'casella', $esito['messaggio'], $nome );
			$this->assertSame( 1, $esito['numero'], $nome );
		}

		$this->assertSame( $conservato, get_option( RegistroAccessi::OPZIONE ), 'Niente tolto.' );
		$this->assertCount( $tracce, $this->tracce(), 'Niente nel registro delle modifiche.' );

		$correzione = array_merge( $this->decisa(), array( 'esito' => 'accolta' ) );
		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $correzione, 1 ) )['codice'], 'La correzione non chiede conferma.' );
		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'elimina', null, 1 ) )['codice'] );
		$this->assertSame( array(), RegistroAccessi::righe() );

		update_option( RegistroAccessi::OPZIONE, $conservato );
		$html = $this->schermata();

		$this->assertSame( 1, substr_count( $html, 'type="checkbox" id="trasparenza-pa-registro-1-conferma" name="' . SchermataRegistroAccessi::CAMPO_CONFERMA . '" value="1"' ), 'La casella accanto al pulsante della riga.' );
		$this->assertStringContainsString( '<label for="trasparenza-pa-registro-1-conferma">', $html );
		$this->assertLessThan( strpos( $html, 'value="elimina"' ), strpos( $html, 'trasparenza-pa-registro-1-conferma' ), 'La casella prima del pulsante.' );
	}

	/**
	 * T-106: [attacco] permesso, nonce, operazione, numero.
	 */
	public function test_t106_rifiuti(): void {
		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );
		$conservato = get_option( RegistroAccessi::OPZIONE );
		$tracce     = count( $this->tracce() );

		wp_set_current_user( $this->amministratore );
		$this->assertSame( 'permesso', SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) )['codice'] );
		wp_set_current_user( $this->responsabile );

		$senza = $this->richiesta( 'aggiungi', $this->decisa() );
		unset( $senza['_wpnonce'] );
		$this->assertSame( 'nonce', SchermataRegistroAccessi::salva( $senza )['codice'] );

		$altra             = $this->richiesta( 'aggiungi', $this->decisa() );
		$altra['_wpnonce'] = wp_create_nonce( SchermataStati::AZIONE );
		$this->assertSame( 'nonce', SchermataRegistroAccessi::salva( $altra )['codice'] );

		$casi = array(
			'operazione sconosciuta' => array( $this->richiesta( 'cancella_tutto', $this->decisa() ), 'operazione' ),
			'operazione con riga'    => array( $this->richiesta( 'cancella_tutto', $this->decisa(), 1 ), 'operazione' ),
			'operazione non testo'   => array( array_merge( $this->richiesta( 'aggiungi', $this->decisa() ), array( SchermataRegistroAccessi::CAMPO_OPERAZIONE => array( 'aggiungi' ) ) ), 'operazione' ),
			'riga non valida'        => array( $this->richiesta( 'aggiungi', array_merge( $this->decisa(), array( 'tipo' => 'altro' ) ) ), 'dati' ),
			'riga non elenco'        => array( $this->richiesta( 'aggiungi', 'riga' ), 'dati' ),
			'riga mancante'          => array( $this->richiesta( 'aggiungi' ), 'dati' ),
			'numero inesistente'     => array( $this->richiesta( 'modifica', $this->decisa(), 9, $this->decisa() ), 'conflitto' ),
			'numero non valido'      => array( $this->richiesta( 'modifica', $this->decisa(), '1a', $this->decisa() ), 'operazione' ),
			'numero zero'            => array( $this->richiesta( 'elimina', null, 0, $this->decisa() ), 'operazione' ),
			'numero a capo'          => array( $this->richiesta( 'elimina', null, "1\n", $this->decisa() ), 'operazione' ),
			'numero mancante'        => array( $this->richiesta( 'elimina' ), 'operazione' ),
			'partenza mancante'      => array( array_diff_key( $this->richiesta( 'elimina', null, 1 ), array( SchermataRegistroAccessi::CAMPO_PARTENZA => 0 ) ), 'conflitto' ),
			'partenza non valida'    => array( $this->richiesta( 'elimina', null, 1, array( 'tipo' => 'semplice' ) ), 'conflitto' ),
			'modifica non valida'    => array( $this->richiesta( 'modifica', array_merge( $this->decisa(), array( 'esito' => '' ) ), 1 ), 'dati' ),
		);

		foreach ( $casi as $nome => list( $richiesta, $codice ) ) {
			$this->assertSame( $codice, SchermataRegistroAccessi::salva( $richiesta )['codice'], $nome );
		}

		$this->assertSame( $conservato, get_option( RegistroAccessi::OPZIONE ), 'Niente scritto.' );
		$this->assertCount( $tracce, $this->tracce(), 'Niente nel registro delle modifiche.' );
	}

	/**
	 * T-106: [attacco] la riga cambiata o tolta da altri dopo l'apertura del modulo e un conflitto.
	 */
	public function test_t106_conflitti(): void {
		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) );

		$aperta = RegistroAccessi::righe()[1];
		$di_a   = array_merge(
			$aperta,
			array(
				'esito'     => 'accolta',
				'decisione' => '2026-04-20',
			)
		);
		$di_b   = array_merge( $aperta, array( 'argomento' => 'Curricula dei dirigenti' ) );

		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $di_a, 1, $aperta ) )['codice'] );
		$this->assertSame( 'conflitto', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $di_b, 1, $aperta ) )['codice'] );
		$this->assertSame( 'conflitto', SchermataRegistroAccessi::salva( $this->richiesta( 'elimina', null, 1, $aperta ) )['codice'] );
		$this->assertSame( $di_a, RegistroAccessi::righe()[1] );

		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'elimina', null, 1 ) )['codice'] );
		$this->assertSame( 'conflitto', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $di_b, 1, $di_a ) )['codice'], 'Tolta da altri.' );
		$this->assertSame( array(), RegistroAccessi::righe() );
		$this->assertCount( 3, $this->tracce(), 'Aggiunta, correzione di A e rimozione: i conflitti non lasciano voci.' );
	}

	/**
	 * T-106: [attacco] un altro salvataggio tiene il blocco: niente scritto.
	 */
	public function test_t106_blocco_occupato(): void {
		global $wpdb;

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );

			$esito = SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );
			$monca = SchermataRegistroAccessi::salva( array_diff_key( $this->richiesta( 'elimina', null, 1, $this->decisa() ), array( SchermataRegistroAccessi::CAMPO_PARTENZA => 0 ) ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertSame( 'conflitto', $monca['codice'], 'Senza partenza la richiesta si rifiuta prima di chiedere il blocco.' );
		$this->assertFalse( get_option( RegistroAccessi::OPZIONE ) );
		$this->assertCount( 0, $this->tracce() );
	}

	/**
	 * T-106: [guasto] lettura fallita, registro delle modifiche che rifiuta, dato non scritto.
	 */
	public function test_t106_guasti(): void {
		global $wpdb;

		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );
		$conservato = get_option( RegistroAccessi::OPZIONE );
		$richiesta  = $this->richiesta( 'aggiungi', $this->in_corso() );

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

		$esito = SchermataRegistroAccessi::salva( $richiesta );

		remove_all_filters( 'query' );

		$this->assertSame( 'lettura', $esito['codice'] );
		$this->assertSame( $this->in_corso(), $esito['inviati'], 'Il modulo rimostra quanto inviato.' );
		$this->assertSame( $conservato, get_option( RegistroAccessi::OPZIONE ) );
		$this->assertCount( 1, $this->tracce() );

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);

		$esito = SchermataRegistroAccessi::salva( $richiesta );

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertSame( $conservato, get_option( RegistroAccessi::OPZIONE ) );

		add_filter(
			'pre_update_option_' . RegistroAccessi::OPZIONE,
			static function ( $valore, $vecchio ) {
				return $vecchio;
			},
			10,
			2
		);

		$esito = SchermataRegistroAccessi::salva( $richiesta );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertSame( $conservato, get_option( RegistroAccessi::OPZIONE ) );
	}

	/**
	 * T-106: righe conservate di forma strana non si perdono quando si salva un'altra riga.
	 */
	public function test_t106_righe_strane_conservate(): void {
		update_option(
			RegistroAccessi::OPZIONE,
			array(
				'ultimo' => 4,
				'righe'  => array(
					4 => array_merge( $this->decisa(), array( 'tipo' => 'vecchio' ) ),
				),
			)
		);

		$esito = SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) );

		$this->assertSame( 'salvato', $esito['codice'], $esito['messaggio'] );
		$this->assertSame( 5, $esito['numero'] );
		$this->assertSame( array( 4, 5 ), array_keys( RegistroAccessi::righe() ) );
		$this->assertSame( 'vecchio', RegistroAccessi::righe()[4]['tipo'] );

		update_option( RegistroAccessi::OPZIONE, 'non un registro' );
		$this->assertSame( array(), RegistroAccessi::righe() );

		update_option(
			RegistroAccessi::OPZIONE,
			array(
				'ultimo' => 1,
				'righe'  => array(
					3   => $this->decisa(),
					'a' => $this->decisa(),
					0   => $this->decisa(),
					5   => array_merge( $this->decisa(), array( 'esito' => 1 ) ),
				),
			)
		);

		$this->assertSame( array( 3 ), array_keys( RegistroAccessi::righe() ), 'Solo righe con numero positivo e campi testuali.' );
		$this->assertSame( 6, SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) )['numero'], 'Il numero nuovo supera ogni numero conservato, anche di righe scartate.' );

		foreach ( array(
			'parola'   => 'molti',
			'testo'    => '9',
			'negativo' => -3,
			'decimale' => 2.5,
		) as $nome => $ultimo ) {
			update_option(
				RegistroAccessi::OPZIONE,
				array(
					'ultimo' => $ultimo,
					'righe'  => array( 1 => $this->decisa() ),
				)
			);

			$esito = SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) );
			$this->assertSame( 'salvato', $esito['codice'], $nome );
			$this->assertSame( 2, $esito['numero'], 'Un ultimo numero conservato che non e un intero positivo non conta: ' . $nome );
		}
	}

	/**
	 * T-107: sottovoce di menu con il permesso della sezione; schermata rifiutata senza.
	 */
	public function test_t107_menu_e_schermata_solo_con_permesso(): void {
		global $submenu;

		set_current_screen( 'dashboard' );

		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la prova ricostruisce il menu da zero.
		SchermataStati::da_admin_menu();
		SchermataRegistroAccessi::da_admin_menu();

		$voce = array_values( wp_list_filter( $submenu[ SchermataStati::PAGINA ] ?? array(), array( 2 => SchermataRegistroAccessi::PAGINA ) ) );

		$this->assertCount( 1, $voce );
		$this->assertSame( Permessi::AMMINISTRAZIONE, $voce[0][1] );

		wp_set_current_user( $this->amministratore );

		$this->expectException( WPDieException::class );
		$this->schermata();
	}

	/**
	 * T-107: modulo di aggiunta, righe con il loro modulo e la loro partenza, riga non valida segnalata, valori con escaping.
	 */
	public function test_t107_schermata(): void {
		update_option(
			RegistroAccessi::OPZIONE,
			array(
				'ultimo' => 7,
				'righe'  => array(
					2 => array_merge(
						$this->decisa(),
						array(
							'argomento' => 'Spese "sentieri" & rifugi',
							'richiesta' => '2026-04-20',
							'decisione' => '2026-04-25',
						)
					),
					4 => array_merge( $this->in_corso(), array( 'argomento' => 'Pari data' ) ),
					7 => array_merge( $this->in_corso(), array( 'argomento' => '<b>nome</b>' ) ),
				),
			)
		);

		$html = $this->schermata();

		$this->assertSame( 4, substr_count( $html, 'name="action" value="' . SchermataRegistroAccessi::AZIONE . '"' ), 'Un modulo di aggiunta e uno per riga.' );
		$this->assertStringContainsString( 'name="' . SchermataRegistroAccessi::CAMPO_OPERAZIONE . '" value="aggiungi"', $html );
		$this->assertStringContainsString( 'non scrivere nomi', $html );
		$this->assertStringContainsString( 'name="' . SchermataRegistroAccessi::CAMPO_NUMERO . '" value="2"', $html );
		$this->assertStringContainsString( 'name="' . SchermataRegistroAccessi::CAMPO_PARTENZA . '[argomento]" value="Spese &quot;sentieri&quot; &amp; rifugi"', $html );
		$this->assertStringContainsString( 'name="' . SchermataRegistroAccessi::CAMPO . '[argomento]" value="Spese &quot;sentieri&quot; &amp; rifugi"', $html );
		$this->assertStringContainsString( '<option value="accolta_in_parte" selected', $html );
		$this->assertStringNotContainsString( '<b>nome</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;nome&lt;/b&gt;', $html );
		$this->assertSame( 1, substr_count( $html, 'Questa riga non supera il controllo' ), 'Solo la riga non valida.' );
		$posizione = static fn( int $numero ) => strpos( $html, 'name="' . SchermataRegistroAccessi::CAMPO_NUMERO . '" value="' . $numero . '"' );
		$this->assertLessThan( $posizione( 7 ), $posizione( 2 ), 'Prima le richieste piu recenti, qualunque sia il numero.' );
		$this->assertLessThan( $posizione( 4 ), $posizione( 7 ), 'A pari data, prima il numero piu alto.' );
	}

	/**
	 * T-107: dopo un rifiuto il modulo della riga rimostra quanto inviato, con la partenza di allora; una volta sola.
	 */
	public function test_t107_rifiuto_rimostrato(): void {
		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->in_corso() ) );

		$aperta = RegistroAccessi::righe()[1];

		SchermataRegistroAccessi::salva(
			$this->richiesta(
				'modifica',
				array_merge(
					$aperta,
					array(
						'esito'     => 'accolta',
						'decisione' => '2026-04-20',
					)
				),
				1,
				$aperta
			)
		);

		$mia   = array_merge( $aperta, array( 'argomento' => 'Il mio "argomento"' ) );
		$esito = SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $mia, 1, $aperta ) );

		$this->assertSame( 'conflitto', $esito['codice'] );

		set_transient( SchermataRegistroAccessi::ESITO . $this->responsabile, $esito, MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertStringContainsString( 'qualcun altro', $html );
		$this->assertStringContainsString( 'name="' . SchermataRegistroAccessi::CAMPO . '[argomento]" value="Il mio &quot;argomento&quot;"', $html );
		$this->assertStringContainsString( 'name="' . SchermataRegistroAccessi::CAMPO_PARTENZA . '[esito]" value=""', $html, 'La partenza di allora, non quella riletta.' );
		$this->assertFalse( get_transient( SchermataRegistroAccessi::ESITO . $this->responsabile ) );

		$rifiuto = SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', array_merge( $this->decisa(), array( 'argomento' => 'Nuova <i>richiesta</i>' ) ) ) );

		set_transient( SchermataRegistroAccessi::ESITO . $this->responsabile, $rifiuto, MINUTE_IN_SECONDS );
		$html = $this->schermata();

		$this->assertSame( 'dati', $rifiuto['codice'] );
		$this->assertStringContainsString( 'value="Nuova &lt;i&gt;richiesta&lt;/i&gt;"', $html, 'Il modulo di aggiunta rimostra quanto inviato.' );
		$this->assertSame( 1, substr_count( $html, 'Nuova &lt;i&gt;' ), 'Solo nel modulo di aggiunta.' );
	}

	/**
	 * T-119: ogni cambio conserva l'istante del salvataggio; correzione senza cambi e rifiuti non lo spostano.
	 */
	public function test_t119_data_di_ultimo_aggiornamento(): void {
		$istante = static function (): ?int {
			$conservato = RegistroAccessi::conservato();
			return is_wp_error( $conservato ) ? -1 : $conservato['aggiornato'];
		};

		$this->assertNull( $istante(), 'Registro mai scritto: nessuna data.' );

		foreach ( array(
			'aggiunta'   => fn() => $this->richiesta( 'aggiungi', $this->in_corso() ),
			'correzione' => fn() => $this->richiesta( 'modifica', $this->decisa(), 1 ),
			'rimozione'  => fn() => $this->richiesta( 'elimina', null, 1 ),
		) as $nome => $richiesta ) {
			update_option(
				RegistroAccessi::OPZIONE,
				array_merge( (array) get_option( RegistroAccessi::OPZIONE, array() ), array( 'aggiornato' => 1000 ) )
			);

			$prima = time();
			$esito = SchermataRegistroAccessi::salva( $richiesta() );
			$dopo  = time();

			$this->assertSame( 'salvato', $esito['codice'], $nome . ': ' . $esito['messaggio'] );
			$this->assertGreaterThanOrEqual( $prima, $istante(), $nome );
			$this->assertLessThanOrEqual( $dopo, $istante(), $nome );
			$this->assertSame( $istante(), get_option( RegistroAccessi::OPZIONE )['aggiornato'], $nome . ': la rilettura ritrova quanto scritto.' );
		}

		SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', $this->decisa() ) );
		update_option( RegistroAccessi::OPZIONE, array_merge( get_option( RegistroAccessi::OPZIONE ), array( 'aggiornato' => 1000 ) ) );

		$this->assertSame( 'salvato', SchermataRegistroAccessi::salva( $this->richiesta( 'modifica', $this->decisa(), 2 ) )['codice'] );
		$this->assertSame( 1000, $istante(), 'Correzione senza cambi: la data non si sposta.' );

		$this->assertSame( 'dati', SchermataRegistroAccessi::salva( $this->richiesta( 'aggiungi', array_merge( $this->decisa(), array( 'tipo' => 'altro' ) ) ) )['codice'] );
		$this->assertSame( 1000, $istante(), 'Salvataggio rifiutato: la data non si sposta.' );

		foreach ( array(
			'testo'    => '1000',
			'zero'     => 0,
			'negativo' => -5,
			'decimale' => 2.5,
		) as $nome => $valore ) {
			update_option( RegistroAccessi::OPZIONE, array_merge( get_option( RegistroAccessi::OPZIONE ), array( 'aggiornato' => $valore ) ) );
			$this->assertNull( $istante(), 'Data conservata non valida: ' . $nome );
		}
	}
}
