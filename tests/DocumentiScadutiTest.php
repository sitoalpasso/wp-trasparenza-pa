<?php
/**
 * Prove dei documenti dei contenuti scaduti: righe T-90 e T-91.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\SchedaDocumenti;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Chi apre i file di un contenuto scaduto dal punto amministrativo, e cosa mostra il riquadro.
 */
class DocumentiScadutiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;
	use DepositoDiProva;

	/**
	 * Risposte del punto amministrativo raccolte al posto dell'emissione.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $risposte = array();

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	private $amministratore = 0;

	/**
	 * Sezione e tipi registrati, permessi, albero finto, server finto, emissione raccolta.
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

		$this->amministratore = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->amministratore );

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
		remove_all_filters( 'option_' . $GLOBALS['wpdb']->get_blog_prefix() . 'user_roles' );
		\Conformita_Core_Consegna::azzera_emettitore();
		$this->sgancia_server_finto();
		$_GET = array();

		parent::tear_down();
	}

	/**
	 * Il permesso di consultare gli scaduti dei contenuti.
	 *
	 * @return string
	 */
	private function consultazione(): string {
		$permesso = conformita_core_capacita_consultazione_scaduti( TIPO_CONTENUTO );

		$this->assertIsString( $permesso );

		return $permesso;
	}

	/**
	 * Un utente che gestisce i contenuti, anche altrui, ma non consulta gli scaduti.
	 *
	 * @return int
	 */
	private function gestore(): int {
		$utente = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$mappa  = Permessi::mappa( TIPO_CONTENUTO );

		$this->assertIsArray( $mappa );

		foreach ( Permessi::CHIAVI as $chiave ) {
			$utente->add_cap( $mappa[ $chiave ] );
		}

		$this->assertFalse( user_can( $utente, $this->consultazione() ) );

		return (int) $utente->ID;
	}

	/**
	 * Un contenuto con un documento depositato.
	 *
	 * @param string $stato Stato del contenuto.
	 * @param string $fine  Fine della pubblicazione.
	 * @return array{0: int, 1: int} Contenuto e documento.
	 */
	private function con_documento( string $stato, string $fine ): array {
		$contenuto = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => $stato,
				'post_author' => $this->amministratore,
			)
		);

		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $contenuto, $fine ) );

		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( 'relazione.pdf' ), 'percorso_locale' );

		$this->assertSame( 'salvato', $esito['codice'], 'Precondizione: il deposito deve riuscire. ' . $esito['messaggio'] );

		$documenti = SchedaDocumenti::documenti( $contenuto );

		return array( $contenuto, (int) end( $documenti ) );
	}

	/**
	 * Chiede il documento al punto amministrativo, con l'indirizzo che il componente comune dà a chi chiede.
	 *
	 * @param int $documento Documento.
	 * @return int Stato HTTP della risposta.
	 */
	private function apri( int $documento ): int {
		$indirizzo = conformita_core_indirizzo_consegna_amministrativa( $documento );

		$this->assertIsString( $indirizzo );

		// L'indirizzo esce con le e commerciali codificate, pronto per una pagina.
		parse_str( (string) wp_parse_url( html_entity_decode( $indirizzo ), PHP_URL_QUERY ), $variabili );

		$_GET           = $variabili;
		$this->risposte = array();

		\Conformita_Core_Consegna::serve_amministrativa();

		$this->assertCount( 1, $this->risposte );

		return (int) $this->risposte[0]['stato'];
	}

	/**
	 * T-90: l'amministratore riceve il permesso di consultare gli scaduti dei contenuti, gli altri ruoli no.
	 */
	public function test_t90_permesso_all_amministratore(): void {
		$permesso = $this->consultazione();

		$this->assertTrue( get_role( 'administrator' )->has_cap( $permesso ) );

		get_role( 'administrator' )->remove_cap( $permesso );
		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve() );

		$riletti = new \WP_Roles();

		$this->assertTrue( $riletti->get_role( 'administrator' )->has_cap( $permesso ), 'Il permesso torna a ogni cambio di versione, nei ruoli conservati.' );

		foreach ( array( 'editor', 'author', 'contributor', 'subscriber' ) as $ruolo ) {
			$this->assertFalse( get_role( $ruolo )->has_cap( $permesso ), "Il ruolo {$ruolo} non deve consultare gli scaduti." );
		}

		$voce = conformita_core_capacita_consultazione_scaduti( TIPO_VOCE );

		$this->assertIsString( $voce );
		$this->assertFalse( get_role( 'administrator' )->has_cap( $voce ), 'Le voci non hanno documenti e non scadono.' );
	}

	/**
	 * T-90: un permesso di consultazione non scritto nei ruoli conservati e un errore, e la versione non si memorizza.
	 */
	public function test_t90_permesso_non_scritto(): void {
		$permesso = $this->consultazione();

		get_role( 'administrator' )->remove_cap( $permesso );
		delete_option( OPZIONE_VERSIONE );

		add_filter(
			'option_' . $GLOBALS['wpdb']->get_blog_prefix() . 'user_roles',
			static function ( $ruoli ) use ( $permesso ) {
				unset( $ruoli['administrator']['capabilities'][ $permesso ] );

				return $ruoli;
			}
		);

		$esito = Permessi::applica();

		$this->assertWPError( $esito );
		$this->assertSame( 'trasparenza_permessi_non_scritti', $esito->get_error_code() );
		$this->assertFalse( Installazione::aggiorna_se_serve() );
		$this->assertFalse( get_option( OPZIONE_VERSIONE ) );
	}

	/**
	 * T-91: l'amministratore apre i file in bozza, non scaduti e scaduti, e il riquadro gli mostra sempre il collegamento.
	 */
	public function test_t91_amministratore(): void {
		foreach ( array(
			'bozza'       => array( 'draft', '2099-12-31' ),
			'non scaduto' => array( 'publish', '2099-12-31' ),
			'scaduto'     => array( 'publish', '2020-12-31' ),
		) as $caso => $dati ) {
			list( $contenuto, $documento ) = $this->con_documento( $dati[0], $dati[1] );
			$nome                          = wp_basename( (string) get_attached_file( $documento ) );
			$nome                          = wp_basename( (string) get_attached_file( $documento ) );

			$this->assertSame( 200, $this->apri( $documento ), "Caso {$caso}: il file si apre." );

			$riquadro = SchedaDocumenti::riquadro( get_post( $contenuto ) );

			$this->assertStringContainsString( '<a href="' . esc_url( conformita_core_indirizzo_consegna_amministrativa( $documento ) ) . '">' . $nome . '</a>', $riquadro, "Caso {$caso}: collegamento." );
			$this->assertStringNotContainsString( 'consultare i contenuti scaduti', $riquadro, "Caso {$caso}: nessuna frase." );
		}
	}

	/**
	 * T-91: chi gestisce senza consultare gli scaduti apre bozza e non scaduto; sullo scaduto e rifiutato e il riquadro lo dice.
	 */
	public function test_t91_gestore_senza_consultazione(): void {
		$casi = array(
			'bozza'       => $this->con_documento( 'draft', '2099-12-31' ),
			'non scaduto' => $this->con_documento( 'publish', '2099-12-31' ),
			'scaduto'     => $this->con_documento( 'publish', '2020-12-31' ),
		);

		wp_set_current_user( $this->gestore() );

		foreach ( $casi as $caso => $dati ) {
			list( $contenuto, $documento ) = $dati;
			$nome                          = wp_basename( (string) get_attached_file( $documento ) );
			$nome                          = wp_basename( (string) get_attached_file( $documento ) );

			$riquadro = SchedaDocumenti::riquadro( get_post( $contenuto ) );

			if ( 'scaduto' === $caso ) {
				$this->assertNotSame( 200, $this->apri( $documento ), 'Il componente comune rifiuta il file scaduto.' );
				$this->assertStringNotContainsString( '<a href=', $riquadro, 'Nessun collegamento che porta a un rifiuto.' );
				$this->assertStringContainsString( '<li>' . $nome, $riquadro );
				$this->assertStringContainsString( 'Il contenuto è scaduto: i file allegati si aprono solo con il permesso di consultare i contenuti scaduti.', $riquadro );
				continue;
			}

			$this->assertSame( 200, $this->apri( $documento ), "Caso {$caso}: il file si apre." );
			$this->assertStringContainsString( '">' . $nome . '</a>', $riquadro, "Caso {$caso}: collegamento." );
			$this->assertStringNotContainsString( 'consultare i contenuti scaduti', $riquadro, "Caso {$caso}: nessuna frase." );
		}
	}
}
