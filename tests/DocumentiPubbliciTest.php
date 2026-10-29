<?php
/**
 * Prove dei documenti scaricabili dalla pagina pubblica: righe T-78..T-80.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\ContenutiPubblici;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchedaDocumenti;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Quali documenti compaiono sotto un contenuto, e quali il punto pubblico consegna.
 */
class DocumentiPubbliciTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;
	use DepositoDiProva;

	/**
	 * Risposte del punto pubblico raccolte al posto dell'emissione.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $risposte = array();

	/**
	 * Sezione e tipi registrati, permessi, albero finto, amministratore corrente, server finto, emissione raccolta.
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

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->aggancia_server_finto();

		$this->risposte = array();
		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ) {
				$this->risposte[] = $risposta;
			}
		);
	}

	/**
	 * Toglie guasti, emissione raccolta e file.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );
		\Conformita_Core_Consegna::azzera_emettitore();
		$this->sgancia_server_finto();
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Un contenuto pubblicato e visibile in una voce, con un documento depositato.
	 *
	 * @param string|null $voce  Voce; nulla per nessuna.
	 * @param string      $nome  Nome del file.
	 * @param string      $stato Stato del contenuto.
	 * @return array{0: int, 1: int} Contenuto e documento.
	 */
	private function con_documento( ?string $voce = 'zeta_uno', string $nome = 'bilancio.pdf', string $stato = 'publish' ): array {
		$contenuto = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => $stato,
				'post_title'  => 'Contenuto con documento',
			)
		);

		if ( null !== $voce ) {
			add_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, $voce );
		}

		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, '2099-12-31' ) );

		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( $nome ), 'percorso_locale' );

		$this->assertSame( 'salvato', $esito['codice'], 'Precondizione: il deposito deve riuscire. ' . $esito['messaggio'] );

		$documenti = SchedaDocumenti::documenti( $contenuto );

		return array( $contenuto, (int) end( $documenti ) );
	}

	/**
	 * Chiede un documento al punto pubblico da visitatore anonimo, con l'indirizzo che la pagina mostra.
	 *
	 * @param int $documento Documento.
	 * @return int|null Stato HTTP della risposta, nullo se il punto pubblico non ha risposto.
	 */
	private function scarica( int $documento ): ?int {
		$indirizzo = conformita_core_indirizzo_consegna( $documento );

		$this->assertIsString( $indirizzo );

		parse_str( (string) wp_parse_url( $indirizzo, PHP_URL_QUERY ), $variabili );

		$utente = get_current_user_id();
		wp_set_current_user( 0 );

		$richiesta             = new WP();
		$richiesta->query_vars = $variabili;
		$this->risposte        = array();

		try {
			\Conformita_Core_Consegna::serve_pubblica( $richiesta );
		} finally {
			wp_set_current_user( $utente );
		}

		return array() === $this->risposte ? null : (int) $this->risposte[0]['stato'];
	}

	/**
	 * La voce disegnata sulla pagina pubblica, da visitatore anonimo.
	 *
	 * @param string $voce Voce.
	 * @return string
	 */
	private function pagina( string $voce = 'zeta_uno' ): string {
		$utente = get_current_user_id();
		wp_set_current_user( 0 );

		try {
			return PaginaPubblica::disegna( PaginaPubblica::albero(), $voce, 'https://example.org/sezione/' );
		} finally {
			wp_set_current_user( $utente );
		}
	}

	/**
	 * T-78: sotto il contenuto, i suoi documenti con l'indirizzo pubblico del componente comune e il nome del file.
	 */
	public function test_t78_documenti_sotto_il_contenuto(): void {
		list( $contenuto, $primo ) = $this->con_documento( 'zeta_uno', 'bilancio.pdf' );
		$secondo                   = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( 'allegato-due.pdf' ), 'percorso_locale' );

		$this->assertSame( 'salvato', $secondo['codice'] );

		$documenti = SchedaDocumenti::documenti( $contenuto );
		$html      = $this->pagina();

		$this->assertStringContainsString( '<ul class="trasparenza-pa-documenti"><li><a href="' . esc_url( conformita_core_indirizzo_consegna( $primo ) ) . '">bilancio.pdf</a></li><li><a href="' . esc_url( conformita_core_indirizzo_consegna( (int) end( $documenti ) ) ) . '">allegato-due.pdf</a></li></ul>', $html );
		$this->assertStringNotContainsString( 'wp-content/uploads', $html, 'Mai l\'indirizzo diretto della cartella dei caricamenti.' );
	}

	/**
	 * T-78: un contenuto senza documenti non ha elenco; un documento cestinato non si elenca.
	 */
	public function test_t78_senza_documenti_e_cestinati(): void {
		$senza = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $senza, SchedaContenuto::META_COLLOCAZIONE, 'zeta_due' );
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $senza, '2099-12-31' ) );

		$this->assertStringContainsString( '<article', $this->pagina( 'zeta_due' ) );
		$this->assertStringNotContainsString( 'trasparenza-pa-documenti', $this->pagina( 'zeta_due' ) );

		list( , $documento ) = $this->con_documento();
		wp_update_post(
			array(
				'ID'          => $documento,
				'post_status' => 'trash',
			)
		);

		$this->assertStringNotContainsString( 'bilancio.pdf', $this->pagina() );
		$this->assertNotSame( 200, $this->scarica( $documento ), 'Il cestinato non si scarica, e quindi non si elenca.' );
	}

	/**
	 * T-78: [guasto] file registrato mancante o sparito dal disco: il nome resta, il collegamento no.
	 */
	public function test_t78_file_mancante(): void {
		list( , $senza_riga ) = $this->con_documento( 'zeta_uno', 'senza-riga.pdf' );
		list( , $sparito )    = $this->con_documento( 'zeta_due', 'sparito.pdf' );

		$titolo = get_the_title( $senza_riga );

		$this->assertNotSame( '', $titolo, 'Precondizione: l\'allegato ha un titolo.' );

		delete_post_meta( $senza_riga, '_wp_attached_file' );
		wp_delete_file( (string) get_attached_file( $sparito ) );

		$html = $this->pagina( 'zeta_uno' );

		$this->assertStringContainsString( '<li>' . esc_html( $titolo ) . ' <span class="trasparenza-pa-non-disponibile">(file non disponibile)</span></li>', $html );
		$this->assertStringNotContainsString( '<a href="' . esc_url( (string) conformita_core_indirizzo_consegna( $senza_riga ) ) . '">', $html );

		$html = $this->pagina( 'zeta_due' );

		$this->assertStringContainsString( '<li>sparito.pdf <span class="trasparenza-pa-non-disponibile">(file non disponibile)</span></li>', $html );
		$this->assertStringNotContainsString( '<a href="' . esc_url( (string) conformita_core_indirizzo_consegna( $sparito ) ) . '">', $html );
	}

	/**
	 * T-78: [guasto] lettura fallita dei documenti: avviso al posto di contenuti e date.
	 */
	public function test_t78_lettura_fallita_dei_documenti(): void {
		global $wpdb;

		$this->con_documento();

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				return false !== strpos( (string) $sql, "SELECT ID FROM {$wpdb->posts} WHERE post_parent = " ) ? 'SELECT 1 FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);

		$html = $this->pagina();

		$this->assertStringContainsString( 'trasparenza-pa-indisponibile', $html );
		$this->assertStringNotContainsString( '<article', $html );
		$this->assertStringNotContainsString( 'trasparenza-pa-aggiornamento', $html );
	}

	/**
	 * T-79: il documento di un contenuto mostrato sulla pagina si scarica da anonimo.
	 */
	public function test_t79_scaricabile_da_anonimo(): void {
		list( , $documento ) = $this->con_documento();

		$this->assertSame( 200, $this->scarica( $documento ) );
	}

	/**
	 * T-79: [attacco] contenuto pubblicato che non compare sulla pagina: il documento non si scarica, anche con l'indirizzo.
	 */
	public function test_t79_contenuto_non_mostrato_non_scaricabile(): void {
		$casi = array(
			'senza voce'       => $this->con_documento( null ),
			'voce non ammessa' => $this->con_documento( 'zeta' ),
			'voce inesistente' => $this->con_documento( 'voce_inesistente' ),
		);

		$piu_voci = $this->con_documento();
		add_post_meta( $piu_voci[0], SchedaContenuto::META_COLLOCAZIONE, 'zeta_due' );
		$casi['piu voci'] = $piu_voci;

		foreach ( $casi as $nome => list( $contenuto, $documento ) ) {
			$this->assertSame( 404, $this->scarica( $documento ), $nome );
			$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, $contenuto ), $nome );
		}

		$this->assertStringNotContainsString( 'bilancio.pdf', $this->pagina() . $this->pagina( 'zeta_due' ) . $this->pagina( 'zeta' ) );
	}

	/**
	 * T-79: [attacco] bozza, password, fine passata: il componente comune rifiuta, e la pagina non elenca.
	 */
	public function test_t79_controlli_del_componente_comune(): void {
		$bozza = $this->con_documento( 'zeta_uno', 'bilancio.pdf', 'draft' );

		$con_password = $this->con_documento();
		wp_update_post(
			array(
				'ID'            => $con_password[0],
				'post_password' => 'segreta',
			)
		);

		$scaduto = $this->con_documento();
		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $scaduto[0], '2000-01-01' ) );

		foreach ( array(
			'bozza'    => $bozza,
			'password' => $con_password,
			'scaduto'  => $scaduto,
		) as $nome => list( , $documento ) ) {
			$this->assertSame( 404, $this->scarica( $documento ), $nome );
		}

		$this->assertStringNotContainsString( 'bilancio.pdf', $this->pagina() );
	}

	/**
	 * T-79: [guasto] lettura fallita della voce: il documento non si scarica.
	 */
	public function test_t79_lettura_fallita_della_voce(): void {
		global $wpdb;

		list( $contenuto, $documento ) = $this->con_documento();

		$this->assertTrue( ContenutiPubblici::allegato_consegnabile( $documento, $contenuto ) );

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				return false !== strpos( (string) $sql, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id" ) && false !== strpos( (string) $sql, "'" . SchedaContenuto::META_COLLOCAZIONE . "'" )
					? 'SELECT 1 FROM ' . $wpdb->posts . '_inesistente'
					: $sql;
			}
		);

		$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, $contenuto ) );
		$this->assertSame( 404, $this->scarica( $documento ) );
	}

	/**
	 * T-80: la funzione risponde no a ogni chiamata fuori forma, e senza albero.
	 */
	public function test_t80_funzione_fuori_forma(): void {
		list( $contenuto, $documento ) = $this->con_documento();

		$voce = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'draft',
			)
		);
		add_post_meta( $voce, SchedaContenuto::META_COLLOCAZIONE, 'zeta_uno' );

		$this->assertTrue( ContenutiPubblici::allegato_consegnabile( $documento, $contenuto ) );
		$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, (string) $contenuto ), 'Numero come testo.' );
		$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, null ) );
		$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, 0 ) );
		$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, $voce ), 'Altro tipo.' );

		PaginaPubblica::fissa_file( __DIR__ . '/dati/inesistente.json' );
		PaginaPubblica::da_init();

		$this->assertNull( PaginaPubblica::albero() );
		$this->assertFalse( ContenutiPubblici::allegato_consegnabile( $documento, $contenuto ), 'Senza albero.' );
		$this->assertSame( 404, $this->scarica( $documento ) );
	}
}
