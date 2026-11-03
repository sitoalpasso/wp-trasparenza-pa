<?php
/**
 * Prove della verifica periodica dei rinvii: righe T-163..T-166.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\Rinvii;
use TrasparenzaPa\SchermataRinvii;
use TrasparenzaPa\VerificaRinvii;
use WP_UnitTestCase;

/**
 * I rinvii affidati al verificatore del componente comune, e l'esito nella schermata.
 */
class VerificaRinviiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Codice che l'altro sito finto risponde.
	 *
	 * @var int
	 */
	private $risposta = 200;

	/**
	 * Sezione registrata, albero finto con le banche dati, nessun rinvio, tabella del verificatore vuota.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		Avvio::esegui();

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-rinvii.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero(), 'Precondizione: l\'albero finto con le banche dati si legge.' );

		delete_option( Rinvii::OPZIONE );

		$this->assertTrue( \Conformita_Core_Collegamenti::installa() );
		$tabella = \Conformita_Core_Collegamenti::tabella();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabella vuota a ogni prova.
		$wpdb->query( "DELETE FROM `{$tabella}`" );

		$utente = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_user_by( 'id', $utente )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $utente );

		$this->risposta = 200;
		add_filter( 'pre_http_request', array( $this, 'altro_sito' ), 10, 3 );
	}

	/**
	 * Guasti tolti, albero finto della suite rimesso.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_filter( 'pre_http_request', array( $this, 'altro_sito' ), 10 );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * L'altro sito finto: risponde con il codice della prova.
	 *
	 * @param mixed                $esito     Risposta proposta.
	 * @param array<string, mixed> $argomenti Argomenti.
	 * @param string               $indirizzo Indirizzo.
	 * @return array<string, mixed>
	 */
	public function altro_sito( $esito, $argomenti, $indirizzo ): array {
		unset( $esito, $argomenti, $indirizzo );

		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => $this->risposta,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Un rinvio valido per le voci dell'albero finto.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @param string $banca     Banca dati.
	 * @return array<string, string>
	 */
	private function rinvio( string $indirizzo = 'https://example.org/banca/ente?dato=1', string $banca = 'banca_uno' ): array {
		return array(
			'banca'     => $banca,
			'indirizzo' => $indirizzo,
			'etichetta' => 'Dati dell\'ente',
			'data'      => '2026-01-15',
		);
	}

	/**
	 * Salva il rinvio di una voce come farebbe il modulo.
	 *
	 * @param string                $voce   Voce.
	 * @param array<string, string> $rinvio Rinvio.
	 * @return array<string, mixed>
	 */
	private function salva( string $voce, array $rinvio ): array {
		return SchermataRinvii::salva(
			array(
				'_wpnonce'                      => wp_create_nonce( SchermataRinvii::AZIONE ),
				SchermataRinvii::CAMPO_VOCE     => $voce,
				SchermataRinvii::CAMPO          => $rinvio,
				SchermataRinvii::CAMPO_PARTENZA => Rinvii::dati()[ $voce ] ?? Rinvii::vuoto(),
			)
		);
	}

	/**
	 * Il collegamento della voce presso il verificatore.
	 *
	 * @param string $voce Voce.
	 * @return array<string, mixed>|null
	 */
	private function affidato( string $voce ): ?array {
		$letto = conformita_core_collegamento( \TrasparenzaPa\SEZIONE, VerificaRinvii::chiave( $voce ) );
		$this->assertNotWPError( $letto );

		return $letto;
	}

	/**
	 * Controlla adesso il collegamento della voce.
	 *
	 * @param string $voce Voce.
	 */
	private function controlla( string $voce ): void {
		$this->assertIsArray( conformita_core_verifica_collegamento( \TrasparenzaPa\SEZIONE, VerificaRinvii::chiave( $voce ) ) );
	}

	/**
	 * Fissa a mano campi del collegamento della voce.
	 *
	 * @param string               $voce  Voce.
	 * @param array<string, mixed> $campi Campi.
	 */
	private function fissa( string $voce, array $campi ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- La prova fissa lo stato.
		$wpdb->update(
			\Conformita_Core_Collegamenti::tabella(),
			$campi,
			array(
				'sezione' => \TrasparenzaPa\SEZIONE,
				'chiave'  => VerificaRinvii::chiave( $voce ),
			)
		);
	}

	/**
	 * Fa fallire le query sulla tabella del verificatore che iniziano con il verbo dato.
	 *
	 * @param string $verbo SELECT, INSERT, UPDATE o DELETE.
	 */
	private function guasta_verificatore( string $verbo ): void {
		global $wpdb;

		$tabella = \Conformita_Core_Collegamenti::tabella();
		$wpdb->suppress_errors( true );

		add_filter(
			'query',
			static function ( $sql ) use ( $verbo, $tabella ) {
				$sql = (string) $sql;

				return 0 === strpos( ltrim( $sql ), $verbo ) && false !== strpos( $sql, $tabella ) ? 'SELECT * FROM `' . $tabella . '_inesistente`' : $sql;
			}
		);
	}

	/**
	 * Fa fallire la lettura dei rinvii conservati.
	 */
	private function guasta_rinvii(): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT option_value FROM {$wpdb->options} WHERE option_name = '" . Rinvii::OPZIONE . "'" )
					? 'SELECT option_value FROM ' . $wpdb->options . '_inesistente'
					: $sql;
			}
		);
	}

	/**
	 * La schermata come la disegnerebbe WordPress.
	 *
	 * @return string
	 */
	private function schermata(): string {
		ob_start();

		try {
			SchermataRinvii::mostra();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Il riquadro delle voci da guardare, o niente.
	 *
	 * @param string $html Schermata.
	 * @return string
	 */
	private static function da_guardare( string $html ): string {
		return 1 === preg_match( '#<div class="trasparenza-pa-da-guardare.*?</div>#s', $html, $trovato ) ? $trovato[0] : '';
	}

	/**
	 * T-163: il salvataggio affida il rinvio al verificatore, lo cambia, lo toglie.
	 */
	public function test_t163_salvataggio(): void {
		$this->assertSame( 'salvato', $this->salva( 'zeta_uno', $this->rinvio() )['codice'] );

		$letto = $this->affidato( 'zeta_uno' );
		$this->assertSame( 'https://example.org/banca/ente?dato=1', $letto['indirizzo'] );
		$this->assertSame( 1, $letto['intervallo'] );
		$this->assertSame( 'mai', $letto['esito'] );
		$this->assertNull( $this->affidato( 'alfa' ), 'Le altre voci non si toccano.' );

		$this->controlla( 'zeta_uno' );
		$this->assertSame( 'raggiungibile', $this->affidato( 'zeta_uno' )['esito'] );

		// Cambio di indirizzo: il verificatore ha quello nuovo, mai controllato.
		$this->assertSame( 'salvato', $this->salva( 'zeta_uno', $this->rinvio( 'https://example.org/banca/nuovo' ) )['codice'] );
		$letto = $this->affidato( 'zeta_uno' );
		$this->assertSame( 'https://example.org/banca/nuovo', $letto['indirizzo'] );
		$this->assertSame( 'mai', $letto['esito'] );

		// Cambio della sola etichetta: l'esito resta.
		$this->controlla( 'zeta_uno' );
		$this->assertSame( 'salvato', $this->salva( 'zeta_uno', array_merge( $this->rinvio( 'https://example.org/banca/nuovo' ), array( 'etichetta' => 'Altro' ) ) )['codice'] );
		$this->assertSame( 'raggiungibile', $this->affidato( 'zeta_uno' )['esito'] );

		// Rimozione: il verificatore non l'ha piu.
		$this->assertSame( 'salvato', $this->salva( 'zeta_uno', Rinvii::vuoto() )['codice'] );
		$this->assertNull( $this->affidato( 'zeta_uno' ) );
	}

	/**
	 * T-163: [guasto] il verificatore che non registra o non toglie non ferma il salvataggio, e lo si dice.
	 */
	public function test_t163_guasti(): void {
		$this->guasta_verificatore( 'INSERT' );
		$esito = $this->salva( 'zeta_uno', $this->rinvio() );
		remove_all_filters( 'query' );

		$this->assertSame( 'verifica', $esito['codice'] );
		$this->assertStringContainsString( 'Rinvio salvato', $esito['messaggio'] );
		$this->assertStringContainsString( 'verifica periodica', $esito['messaggio'] );
		$this->assertSame( '', $esito['voce'], 'Il modulo non rimostra i valori inviati: sono gia salvati.' );
		$this->assertSame( array( 'zeta_uno' => $this->rinvio() ), Rinvii::conservati() );
		$this->assertNull( $this->affidato( 'zeta_uno' ) );

		// Affidato poi da un salvataggio riuscito di un altro indirizzo.
		$this->assertSame( 'salvato', $this->salva( 'zeta_uno', $this->rinvio( 'https://example.org/banca/nuovo' ) )['codice'] );
		$this->assertNotNull( $this->affidato( 'zeta_uno' ) );

		$this->guasta_verificatore( 'DELETE' );
		$esito = $this->salva( 'zeta_uno', Rinvii::vuoto() );
		remove_all_filters( 'query' );

		$this->assertSame( 'verifica', $esito['codice'] );
		$this->assertStringContainsString( 'Rinvio tolto', $esito['messaggio'] );
		$this->assertSame( array(), Rinvii::conservati() );
		$this->assertNotNull( $this->affidato( 'zeta_uno' ) );
	}

	/**
	 * T-164: aprendo la schermata, i rinvii validi si affidano e gli altri si tolgono.
	 */
	public function test_t164_riallineamento(): void {
		update_option(
			Rinvii::OPZIONE,
			array(
				'zeta_uno' => $this->rinvio(),
				// Banca dati che l'albero non lega a questa voce: non vale piu.
				'alfa'     => $this->rinvio( 'https://example.org/banca/alfa', 'banca_due' ),
			)
		);
		$this->assertTrue( conformita_core_registra_collegamento( \TrasparenzaPa\SEZIONE, VerificaRinvii::chiave( 'alfa' ), 'https://example.org/banca/alfa', 1 ) );

		$this->schermata();

		$this->assertSame( 'https://example.org/banca/ente?dato=1', $this->affidato( 'zeta_uno' )['indirizzo'] );
		$this->assertNull( $this->affidato( 'alfa' ) );

		// Rinvio uguale gia affidato e controllato: esito e data non cambiano.
		$this->controlla( 'zeta_uno' );
		$prima = $this->affidato( 'zeta_uno' );
		$this->schermata();
		$this->assertSame( $prima, $this->affidato( 'zeta_uno' ) );
	}

	/**
	 * T-164: si tolgono anche i collegamenti rimasti di voci senza rinvio o che l'albero non lega piu.
	 */
	public function test_t164_collegamenti_rimasti(): void {
		// Zeta Due e nei rinvii conservati, ma l'albero in vigore non la lega a nessuna banca dati.
		update_option( Rinvii::OPZIONE, array( 'zeta_due' => $this->rinvio( 'https://example.org/banca/zeta-due' ) ) );

		// Alfa non ha rinvio conservato: il suo collegamento e rimasto da una rimozione non riuscita.
		foreach ( array( 'alfa', 'zeta_due' ) as $voce ) {
			$this->assertTrue( conformita_core_registra_collegamento( \TrasparenzaPa\SEZIONE, VerificaRinvii::chiave( $voce ), 'https://example.org/banca/' . $voce, 1 ) );
		}

		$this->schermata();

		$this->assertNull( $this->affidato( 'alfa' ) );
		$this->assertNull( $this->affidato( 'zeta_due' ) );
	}

	/**
	 * T-164: [guasto] con la lettura dei rinvii fallita non si registra e non si toglie niente.
	 */
	public function test_t164_lettura_fallita(): void {
		update_option( Rinvii::OPZIONE, array( 'zeta_uno' => $this->rinvio() ) );
		$this->assertTrue( conformita_core_registra_collegamento( \TrasparenzaPa\SEZIONE, VerificaRinvii::chiave( 'alfa' ), 'https://example.org/banca/alfa', 1 ) );

		$this->guasta_rinvii();
		$html = $this->schermata();
		remove_all_filters( 'query' );

		$this->assertNull( $this->affidato( 'zeta_uno' ) );
		$this->assertNotNull( $this->affidato( 'alfa' ) );
		$this->assertStringContainsString( 'non si sono potuti affidare alla verifica periodica', $html );
	}

	/**
	 * T-165: l'esito per voce, con data nel fuso del sito, e le voci da guardare.
	 */
	public function test_t165_esito(): void {
		update_option( 'timezone_string', 'Asia/Tokyo' );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );

		$this->assertSame( 'salvato', $this->salva( 'zeta_uno', $this->rinvio() )['codice'] );
		$this->assertSame( 'salvato', $this->salva( 'alfa', $this->rinvio( 'https://example.org/banca/alfa' ) )['codice'] );

		// Tutti e due mai controllati: niente da guardare.
		$html = $this->schermata();
		$this->assertSame( 2, substr_count( $html, 'Mai controllato' ) );
		$this->assertSame( '', self::da_guardare( $html ) );

		$this->controlla( 'zeta_uno' );
		$this->risposta = 404;
		$this->controlla( 'alfa' );
		$this->fissa( 'zeta_uno', array( 'verificato' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
		$this->fissa( 'alfa', array( 'verificato' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );

		$html     = $this->schermata();
		$guardare = self::da_guardare( $html );
		$quando   = wp_date( 'Y-m-d H:i', time() - HOUR_IN_SECONDS );

		$this->assertStringContainsString( 'Risponde: ultimo controllo il ' . $quando, $html );
		$this->assertStringContainsString( 'Non risponde: ultimo controllo il ' . $quando . '. Risposta con codice 404.', $html );
		$this->assertStringContainsString( 'Voce Alfa', $guardare );
		$this->assertStringNotContainsString( 'Voce Zeta Uno', $guardare );

		// Controllo in ritardo, con la data nel fuso del sito.
		$this->fissa( 'zeta_uno', array( 'verificato' => '2026-01-15 10:00:00' ) );
		$html = $this->schermata();
		$this->assertStringContainsString( 'Risponde: ultimo controllo il 2026-01-15 19:00', $html );
		$this->assertStringContainsString( 'Controllo in ritardo', $html );
		$this->assertStringContainsString( 'Voce Zeta Uno', self::da_guardare( $html ) );

		// [attacco] motivo con codice.
		$this->fissa( 'alfa', array( 'motivo' => '<script>x</script>' ) );
		$html = $this->schermata();
		$this->assertStringNotContainsString( '<script>x</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;x&lt;/script&gt;', $html );

		// Indirizzo diverso nel verificatore che non si riesce a riallineare: non affidata.
		$this->fissa( 'alfa', array( 'indirizzo' => 'https://example.org/altro' ) );
		$this->guasta_verificatore( 'UPDATE' );
		$html = $this->schermata();
		remove_all_filters( 'query' );
		$this->assertStringContainsString( 'Non ancora affidato alla verifica periodica', $html );
		$this->assertStringContainsString( 'Voce Alfa', self::da_guardare( $html ) );

		// Non registrato e registrazione che non riesce: non affidata.
		conformita_core_togli_collegamento( \TrasparenzaPa\SEZIONE, VerificaRinvii::chiave( 'alfa' ) );
		$this->guasta_verificatore( 'INSERT' );
		$html = $this->schermata();
		remove_all_filters( 'query' );
		$this->assertStringContainsString( 'Non ancora affidato alla verifica periodica', $html );

		// [guasto] esito che non si legge.
		$this->guasta_verificatore( 'SELECT' );
		$html = $this->schermata();
		remove_all_filters( 'query' );
		$this->assertSame( 4, substr_count( $html, 'L&#039;esito dell&#039;ultimo controllo non si è potuto leggere' ) ); // Due voci: sotto il modulo e fra quelle da guardare.
		$guardare = self::da_guardare( $html );
		$this->assertStringContainsString( 'Voce Alfa', $guardare );
		$this->assertStringContainsString( 'Voce Zeta Uno', $guardare );
	}

	/**
	 * T-166: versione richiesta e forma della chiave.
	 */
	public function test_t166_versione_e_chiave(): void {
		$this->assertSame( '1.13.0', \TrasparenzaPa\CORE_API_RICHIESTA );
		$this->assertSame( 1, VerificaRinvii::INTERVALLO );

		$this->assertSame( 'zeta_uno', VerificaRinvii::chiave( 'zeta_uno' ) );
		$this->assertSame( 'v-' . md5( 'Zeta_Uno' ), VerificaRinvii::chiave( 'Zeta_Uno' ) );
		$this->assertNotSame( VerificaRinvii::chiave( 'Zeta_Uno' ), VerificaRinvii::chiave( 'zeta_Uno' ) );
		$this->assertSame( 1, preg_match( '/\A[a-z0-9_-]{1,100}\z/', VerificaRinvii::chiave( str_repeat( 'A', 80 ) ) ) );
	}
}
