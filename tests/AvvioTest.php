<?php
/**
 * Prove dell'avvio: righe T-01, T-02, T-04, T-06, T-07, T-08, T-09, T-10.
 *
 * Le righe T-03 e T-05 stanno in avvii separati, perche il loro caso non esiste
 * in un processo dove il componente comune e caricato e compatibile.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use WP_UnitTestCase;

/**
 * Avvio del componente e dichiarazione delle politiche.
 */
class AvvioTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Percorso del componente come lo conosce WordPress.
	 *
	 * @var string
	 */
	private $componente;

	/**
	 * Registri azzerati prima di ogni prova.
	 *
	 * L'utente corrente e un amministratore perche gli avvisi sono riservati a
	 * chi puo attivare i componenti: senza dire chi guarda la bacheca, "l'avviso
	 * c'e" e "l'avviso non c'e" sarebbero la stessa osservazione.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->componente = plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE );

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();

		update_option( 'active_plugins', array( $this->componente ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Elenco corrente dei componenti attivi.
	 *
	 * @return array<int, string>
	 */
	private function componenti_attivi(): array {
		return (array) get_option( 'active_plugins', array() );
	}

	/**
	 * La politica dichiarata, come la rilegge il componente comune.
	 *
	 * @return array<string, string>
	 */
	private function politica_registrata(): array {
		$politica = conformita_core_politica_sezione( \TrasparenzaPa\SEZIONE );

		$this->assertNotWPError( $politica, 'La sezione deve risultare registrata.' );

		return $politica->come_array();
	}

	/**
	 * T-01: la dipendenza e dichiarata in due posti, e i due dicono la stessa cosa.
	 */
	public function test_t01_dipendenza_dichiarata_in_due_posti(): void {
		$intestazione = get_file_data(
			\TrasparenzaPa\FILE_PRINCIPALE,
			array( 'richiede' => 'Requires Plugins' )
		);

		$this->assertSame(
			'conformita-core',
			$intestazione['richiede'],
			'L\'intestazione deve dichiarare la dipendenza con lo slug del componente comune.'
		);

		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			\TrasparenzaPa\CORE_API_RICHIESTA,
			'La versione di interfaccia richiesta deve essere completa.'
		);

		$this->assertTrue(
			conformita_core_api_compatibile( \TrasparenzaPa\CORE_API_RICHIESTA, conformita_core_versione_api() ),
			'La versione richiesta deve essere soddisfatta da quella che il componente comune espone.'
		);
	}

	/**
	 * T-01: WordPress rifiuta l'attivazione con il codice dedicato.
	 *
	 * Si verifica il codice e non il testo, che su un sito italiano e tradotto.
	 * La prova usa un componente finto con una dipendenza inesistente, perche
	 * nell'ambiente di prova questo componente non sta nella cartella dei
	 * componenti e fallirebbe per un altro motivo.
	 */
	public function test_t01_wordpress_rifiuta_con_il_codice_dedicato(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$cartella = WP_PLUGIN_DIR . '/trasparenza-prova-dipendenza';
		$file     = $cartella . '/trasparenza-prova-dipendenza.php';

		if ( ! is_dir( $cartella ) ) {
			mkdir( $cartella, 0777, true );
		}

		file_put_contents(
			$file,
			"<?php\n/**\n * Plugin Name: Prova dipendenza\n * Requires Plugins: componente-che-non-esiste\n */\n"
		);

		try {
			wp_clean_plugins_cache( false );
			\WP_Plugin_Dependencies::initialize();

			$esito = validate_plugin_requirements( 'trasparenza-prova-dipendenza/trasparenza-prova-dipendenza.php' );

			$this->assertWPError( $esito, 'Una dipendenza inesistente deve far fallire la verifica.' );
			$this->assertSame( 'plugin_missing_dependencies', $esito->get_error_code() );
		} finally {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}

			if ( is_dir( $cartella ) ) {
				rmdir( $cartella );
			}

			wp_clean_plugins_cache( false );
		}
	}

	/**
	 * T-02: il file principale non chiama il componente comune al caricamento.
	 */
	public function test_t02_avvio_rimandato_a_plugins_loaded(): void {
		$this->assertNotFalse(
			has_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) ),
			'L\'avvio deve essere agganciato a plugins_loaded.'
		);

		$sorgente = file_get_contents( \TrasparenzaPa\FILE_PRINCIPALE );
		$sorgente = preg_replace( '/^\s*\*.*$/m', '', (string) $sorgente );

		$this->assertSame(
			0,
			preg_match( '/conformita_core_[a-z_]+\s*\(/', (string) $sorgente ),
			'Il file principale non deve chiamare il componente comune: al suo caricamento potrebbe non esserci.'
		);
	}

	/**
	 * T-04: il criterio di compatibilita sui tre casi che contano.
	 *
	 * @dataProvider casi_di_compatibilita
	 *
	 * @param string $disponibile Versione esposta dal componente comune.
	 * @param bool   $atteso      Esito atteso.
	 */
	public function test_t04_criterio_di_compatibilita( string $disponibile, bool $atteso ): void {
		$this->assertSame(
			$atteso,
			conformita_core_api_compatibile( \TrasparenzaPa\CORE_API_RICHIESTA, $disponibile ),
			'Criterio di compatibilita sbagliato per la versione ' . $disponibile . '.'
		);
	}

	/**
	 * I tre casi della compatibilita.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function casi_di_compatibilita(): array {
		return array(
			'versione inferiore, stesso numero maggiore' => array( '1.9.0', false ),
			'numero maggiore diverso'                    => array( '2.0.0', false ),
			'versione superiore, stesso numero maggiore' => array( '1.11.0', true ),
		);
	}

	/**
	 * T-06: con il componente comune compatibile l'avvio prosegue davvero.
	 *
	 * E il controllo positivo: senza, le prove che pretendono un rifiuto
	 * sarebbero soddisfatte da un componente che non parte mai.
	 */
	public function test_t06_avvio_con_core_compatibile(): void {
		$this->assertTrue( Avvio::esegui(), 'Con il componente comune compatibile l\'avvio deve riuscire.' );

		$this->assertContains( $this->componente, $this->componenti_attivi(), 'Il componente deve restare attivo.' );

		$this->assertContains(
			\TrasparenzaPa\SEZIONE,
			conformita_core_sezioni_registrate(),
			'La sezione deve risultare registrata presso il componente comune.'
		);

		$this->assertSame( '', $this->avvisi_in_bacheca(), 'Un avvio riuscito non produce avvisi.' );
	}

	/**
	 * T-07: le due politiche si rileggono dal componente comune.
	 *
	 * I valori attesi sono scritti qui per esteso e non presi dalle costanti del
	 * componente: se qualcuno cambiasse la costante in `vietata`, una prova che
	 * confronta la costante con se stessa resterebbe verde.
	 */
	public function test_t07_politiche_rilette_dal_core(): void {
		$this->assertTrue( Avvio::esegui() );

		$this->assertSame(
			array(
				'indicizzazione' => 'consentita',
				'scadenza'       => 'archivio',
			),
			$this->politica_registrata(),
			'La trasparenza va indicizzata e il dato scaduto va in archivio, non distrutto.'
		);
	}

	/**
	 * T-08: la seconda invocazione nella stessa richiesta non registra di nuovo.
	 */
	public function test_t08_avvio_invocato_due_volte_nella_stessa_richiesta(): void {
		$this->assertTrue( Avvio::esegui(), 'La prima invocazione deve riuscire.' );

		$prima = $this->politica_registrata();

		$this->assertTrue( Avvio::esegui(), 'La seconda invocazione deve riuscire senza registrare di nuovo.' );

		$this->assertSame(
			1,
			count(
				array_filter(
					conformita_core_sezioni_registrate(),
					static function ( $sezione ) {
						return \TrasparenzaPa\SEZIONE === $sezione;
					}
				)
			),
			'Dopo due invocazioni la sezione deve risultare registrata una volta sola.'
		);

		$this->assertSame( $prima, $this->politica_registrata(), 'Le politiche non devono cambiare.' );
		$this->assertSame( '', $this->avvisi_in_bacheca(), 'La seconda invocazione non produce avvisi.' );
	}

	/**
	 * T-09: la sezione gia registrata da altri e un conflitto in entrambi i casi.
	 *
	 * @dataProvider politiche_preesistenti
	 *
	 * @param array<string, string> $politica Politica con cui altri hanno registrato la sezione.
	 */
	public function test_t09_sezione_gia_registrata_da_altri( array $politica ): void {
		$this->assertTrue(
			conformita_core_registra_sezione( \TrasparenzaPa\SEZIONE, $politica ),
			'Precondizione: la sezione deve risultare registrata da altri prima dell\'avvio.'
		);

		$esito = Avvio::esegui();

		$this->assertInstanceOf( 'WP_Error', $esito, 'La sezione gia registrata da altri e un conflitto.' );
		$this->assertSame( 'conformita_core_sezione_duplicata', $esito->get_error_code() );

		$this->assertContains(
			$this->componente,
			$this->componenti_attivi(),
			'Il componente resta attivo e inerte: non si disattiva per un conflitto di sezione.'
		);

		$this->assertFalse( Avvio::registrata(), 'La sezione non e di questo componente.' );

		$this->assertSame( $politica, $this->politica_registrata(), 'Le politiche preesistenti devono restare intatte.' );

		$this->assertStringContainsString(
			\TrasparenzaPa\SEZIONE,
			$this->avvisi_in_bacheca(),
			'L\'avviso deve nominare la sezione in conflitto.'
		);
	}

	/**
	 * Le due preregistrazioni esterne.
	 *
	 * Quella con politiche identiche e il caso che conta: l'uguaglianza dei
	 * valori non dimostra che la registrazione sia di questo componente.
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	public function politiche_preesistenti(): array {
		return array(
			'politiche diverse'   => array(
				array(
					'indicizzazione' => 'vietata',
					'scadenza'       => 'irraggiungibile',
				),
			),
			'politiche identiche' => array(
				array(
					'indicizzazione' => 'consentita',
					'scadenza'       => 'archivio',
				),
			),
		);
	}

	/**
	 * T-09: l'avviso del conflitto e riservato a chi puo attivare i componenti.
	 */
	public function test_t09_avviso_riservato_a_chi_attiva_i_componenti(): void {
		conformita_core_registra_sezione(
			\TrasparenzaPa\SEZIONE,
			array(
				'indicizzazione' => 'vietata',
				'scadenza'       => 'irraggiungibile',
			)
		);

		Avvio::esegui();

		$this->assertNotSame( '', $this->avvisi_in_bacheca(), 'Precondizione: l\'amministratore vede l\'avviso.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->avvisi_in_bacheca(), 'Chi non puo attivare i componenti non deve vedere l\'avviso.' );
	}

	/**
	 * T-10: con il filtro di scadenza non avviato la registrazione e rifiutata.
	 *
	 * L'errore si osserva attraverso l'avvio di questo componente: chiamare la
	 * registrazione del componente comune ricollauderebbe la sua guardia e non
	 * direbbe niente su come reagisce la trasparenza.
	 */
	public function test_t10_motore_di_scadenza_non_avviato(): void {
		\Conformita_Core_Filtro_Scadenza::azzera_avvio();

		try {
			$esito = Avvio::esegui();

			$this->assertInstanceOf( 'WP_Error', $esito, 'Con il motore non avviato l\'avvio deve restituire l\'errore.' );
			$this->assertSame( 'conformita_core_motore_non_avviato', $esito->get_error_code() );

			$this->assertContains( $this->componente, $this->componenti_attivi(), 'Il componente resta attivo e inerte.' );

			$this->assertNotContains(
				\TrasparenzaPa\SEZIONE,
				conformita_core_sezioni_registrate(),
				'Nessuna sezione deve risultare registrata.'
			);

			$this->assertNotSame( '', $this->avvisi_in_bacheca(), 'La condizione deve essere segnalata.' );
		} finally {
			\Conformita_Core_Filtro_Scadenza::avvia();
		}

		$this->assertTrue( \Conformita_Core_Filtro_Scadenza::avviato(), 'Il filtro deve essere ripristinato.' );
	}
}
