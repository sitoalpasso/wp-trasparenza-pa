<?php
/**
 * Prove dei documenti allegati ai contenuti: righe T-67..T-70.
 *
 * I file sono veri e passano dal componente comune, con la verifica della
 * protezione fatta rispondere da un server finto.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\SchedaDocumenti;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Deposito, rimozione, riquadro, salvataggio.
 */
class DocumentiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;
	use DepositoDiProva;

	/**
	 * Sezione e tipi registrati, permessi assegnati, amministratore corrente, server finto.
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

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->aggancia_server_finto();
	}

	/**
	 * Toglie i guasti, il modulo simulato e i file.
	 */
	public function tear_down(): void {
		global $wpdb;

		$_POST  = array();
		$_FILES = array();
		remove_all_filters( 'query' );
		remove_all_filters( 'pre_delete_attachment' );
		remove_all_filters( 'wp_delete_file' );
		$wpdb->suppress_errors( false );
		$this->sgancia_server_finto();

		parent::tear_down();
	}

	/**
	 * Un contenuto.
	 *
	 * @param string $stato Stato del contenuto.
	 * @return int
	 */
	private function contenuto( string $stato = 'draft' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => $stato,
				'post_title'  => 'Contenuto di prova',
			)
		);
	}

	/**
	 * Deposita un documento vero e pretende che riesca.
	 *
	 * @param int    $contenuto Contenuto.
	 * @param string $nome      Nome del file.
	 * @return int
	 */
	private function deposita( int $contenuto, string $nome = 'documento.pdf' ): int {
		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( $nome ), 'percorso_locale' );

		$this->assertSame( 'salvato', $esito['codice'], 'Precondizione: il deposito deve riuscire. ' . $esito['messaggio'] );

		$documenti = SchedaDocumenti::documenti( $contenuto );

		return (int) end( $documenti );
	}

	/**
	 * Una richiesta come la manderebbe il riquadro.
	 *
	 * @param int          $contenuto Contenuto.
	 * @param array<mixed> $togli     Documenti da togliere.
	 * @return array<string, mixed>
	 */
	private function richiesta( int $contenuto, array $togli = array() ): array {
		return array(
			SchedaDocumenti::CAMPO_NONCE => wp_create_nonce( SchedaDocumenti::azione_nonce( $contenuto ) ),
			SchedaDocumenti::CAMPO_TOGLI => $togli,
		);
	}

	/**
	 * Le voci di registro dei documenti di un contenuto.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce( int $contenuto ): array {
		return conformita_core_voci_registro(
			array(
				'contenuto' => $contenuto,
				'azione'    => SchedaDocumenti::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * Fa rifiutare al registro ogni nuova voce.
	 */
	private function registro_che_rifiuta(): void {
		global $wpdb;

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
	}

	/**
	 * Rende fallite le letture dirette che cominciano con un testo.
	 *
	 * @param string $inizio Inizio dell'interrogazione, dopo il nome della tabella dei contenuti.
	 */
	private function guasta_lettura( string $inizio ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $inizio ) {
				return 0 === strpos( (string) $sql, sprintf( $inizio, $wpdb->posts ) ) ? 'SELECT ID FROM ' . $wpdb->posts . '_inesistente' : $sql;
			}
		);
	}

	/**
	 * Toglie i guasti.
	 */
	private function ripara(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'pre_delete_attachment' );
		remove_all_filters( 'wp_delete_file' );
		$wpdb->suppress_errors( false );
	}

	/**
	 * T-67: il documento si deposita nella cartella protetta, compare fra i documenti e lascia la sua traccia.
	 */
	public function test_t67_deposito_e_registro(): void {
		$contenuto = $this->contenuto();
		$documento = $this->deposita( $contenuto, 'bilancio.pdf' );

		$this->assertSame( array( $documento ), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertTrue( conformita_core_allegato_protetto( $documento ) );
		$this->assertStringStartsWith( \Conformita_Core_Allegati::cartella(), (string) get_attached_file( $documento ), 'Il file sta nella cartella protetta.' );
		$this->assertCount( 1, $this->documenti_sul_disco() );

		$impronta = conformita_core_impronta_allegato( $documento );
		$tracce   = $this->tracce( $contenuto );

		$this->assertCount( 1, $tracce );
		$this->assertSame(
			array(
				'operazione' => 'aggiunto',
				'allegato'   => $documento,
				'nome'       => 'bilancio.pdf',
				'impronta'   => $impronta['algoritmo'] . ':' . $impronta['valore'],
			),
			$tracce[0]['dettagli']
		);

		// Anche su un contenuto pubblicato, e in ordine di deposito.
		$pubblicato = $this->contenuto( 'publish' );
		$primo      = $this->deposita( $pubblicato, 'primo.pdf' );
		$secondo    = $this->deposita( $pubblicato, 'secondo.pdf' );

		$this->assertSame( array( $primo, $secondo ), SchedaDocumenti::documenti( $pubblicato ) );
	}

	/**
	 * T-67: [attacco] deposito rifiutato dal componente comune: protezione non verificata, tipo non ammesso, origine incoerente.
	 */
	public function test_t67_depositi_rifiutati(): void {
		$contenuto = $this->contenuto();

		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo( 'codice.php', '<?php echo 1;' ), 'percorso_locale' );
		$this->assertSame( 'deposito', $esito['codice'], 'Tipo non ammesso.' );

		$segreto = $this->file_temporaneo( 'documento.pdf' );
		$esito   = SchedaDocumenti::deposita( $contenuto, $segreto, 'caricamento' );
		$this->assertSame( 'deposito', $esito['codice'], 'Un file che non viene da un caricamento HTTP.' );
		$this->assertStringContainsString( 'caricamento HTTP', $esito['messaggio'] );
		$this->assertFileExists( $segreto['tmp_name'], 'Il file indicato resta dov\'era.' );

		$this->server_che_serve_l_esca();
		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo(), 'percorso_locale' );
		$this->assertSame( 'deposito', $esito['codice'], 'Protezione non verificata.' );

		$this->assertSame( array(), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertSame( array(), $this->documenti_sul_disco() );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-67: [attacco] nonce, permesso e tipo; caricamento non riuscito; valori di rimozione non validi.
	 */
	public function test_t67_richieste_rifiutate(): void {
		$contenuto = $this->contenuto();
		$altro     = $this->contenuto();
		$file      = $this->file_temporaneo();

		$senza_nonce = $this->richiesta( $contenuto );
		unset( $senza_nonce[ SchedaDocumenti::CAMPO_NONCE ] );

		$this->assertSame( 'nonce', SchedaDocumenti::applica( $contenuto, $senza_nonce, $file )[0]['codice'] );
		$this->assertSame( 'nonce', SchedaDocumenti::applica( $contenuto, $this->richiesta( $altro ), $file )[0]['codice'], 'Nonce di un altro contenuto.' );

		$voce_di_trasparenza = (int) self::factory()->post->create( array( 'post_type' => TIPO_VOCE ) );
		$this->assertSame( 'permesso', SchedaDocumenti::deposita( $voce_di_trasparenza, $file, 'percorso_locale' )['codice'], 'Un contenuto di un altro tipo.' );

		$amministratore = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 'permesso', SchedaDocumenti::deposita( $contenuto, $file, 'percorso_locale' )['codice'] );
		wp_set_current_user( $amministratore );

		$non_riuscito          = $file;
		$non_riuscito['error'] = UPLOAD_ERR_INI_SIZE;
		$this->assertSame( 'caricamento', SchedaDocumenti::applica( $contenuto, $this->richiesta( $contenuto ), $non_riuscito )[0]['codice'] );
		$this->assertSame( array(), SchedaDocumenti::applica( $contenuto, $this->richiesta( $contenuto ), array() ), 'Nessun file e niente da togliere: nessun esito.' );

		$non_elenco                                 = $this->richiesta( $contenuto );
		$non_elenco[ SchedaDocumenti::CAMPO_TOGLI ] = '12';
		$this->assertSame( 'dati', SchedaDocumenti::applica( $contenuto, $non_elenco, array() )[0]['codice'] );

		foreach ( array( '0', '-1', '1e3', '12 ', "12\n", 'x', array( 1 ), 12, '99999999999999999999' ) as $valore ) {
			$this->assertSame( 'dati', SchedaDocumenti::applica( $contenuto, $this->richiesta( $contenuto, array( $valore ) ), array() )[0]['codice'], (string) wp_json_encode( $valore ) );
		}

		$this->assertCount( 1, SchedaDocumenti::applica( $contenuto, $this->richiesta( $contenuto, array( 'x', array( 1 ), '0' ) ), array() ), 'Valori non validi insieme: un esito solo.' );
		$this->assertSame( array(), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertSame( array(), get_children( array( 'post_parent' => $voce_di_trasparenza ) ) );
		$this->assertSame( array(), $this->documenti_sul_disco() );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-67: registro che rifiuta l'aggiunta: il documento appena depositato si toglie, file compreso; blocco tenuto.
	 */
	public function test_t67_guasti(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		$this->registro_che_rifiuta();
		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo(), 'percorso_locale' );
		$this->ripara();

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertStringContainsString( 'Allegato non aggiunto', $esito['messaggio'] );
		$this->assertSame( array(), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertSame( array(), get_children( array( 'post_parent' => $contenuto ) ) );
		$this->assertSame( array(), $this->documenti_sul_disco(), 'Il file appena depositato non resta sul disco.' );

		$this->registro_che_rifiuta();
		add_filter( 'pre_delete_attachment', '__return_false' );
		$esito = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo(), 'percorso_locale' );
		$this->ripara();

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertStringContainsString( 'Toglierlo a mano', $esito['messaggio'], 'Se il documento non si toglie, l\'esito lo dice.' );
		$this->assertCount( 1, SchedaDocumenti::documenti( $contenuto ) );

		// La riga si cancella, il file no: l'esito lo dice con il nome del file, mai con il percorso.
		$rimasto = $this->contenuto();

		$this->registro_che_rifiuta();
		add_filter( 'wp_delete_file', '__return_empty_string' );
		$esito = SchedaDocumenti::deposita( $rimasto, $this->file_temporaneo( 'rimasto.pdf' ), 'percorso_locale' );
		$this->ripara();

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertStringContainsString( 'il file rimasto.pdf è rimasto nella cartella protetta', $esito['messaggio'] );
		$this->assertStringNotContainsString( \Conformita_Core_Allegati::cartella(), $esito['messaggio'] );
		$this->assertSame( array(), SchedaDocumenti::documenti( $rimasto ) );
		$this->assertCount( 2, $this->documenti_sul_disco(), 'Il file e rimasto davvero, accanto a quello dell\'allegato non tolto.' );

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );

			$deposito  = SchedaDocumenti::deposita( $contenuto, $this->file_temporaneo(), 'percorso_locale' );
			$rimozione = SchedaDocumenti::togli( $contenuto, SchedaDocumenti::documenti( $contenuto )[0] );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $deposito['codice'] );
		$this->assertSame( 'occupato', $rimozione['codice'] );
		$this->assertCount( 1, SchedaDocumenti::documenti( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-68: su un contenuto non pubblicato il documento si toglie con il file, dopo la sua voce di registro.
	 */
	public function test_t68_rimozione(): void {
		$contenuto = $this->contenuto();
		$tenuto    = $this->deposita( $contenuto, 'tenuto.pdf' );
		$tolto     = $this->deposita( $contenuto, 'tolto.pdf' );
		$impronta  = conformita_core_impronta_allegato( $tolto );
		$percorso  = (string) get_attached_file( $tolto );

		$esiti = SchedaDocumenti::applica( $contenuto, $this->richiesta( $contenuto, array( (string) $tolto, (string) $tolto ) ), array() );

		$this->assertCount( 1, $esiti, 'Lo stesso documento chiesto due volte si toglie una volta.' );
		$this->assertSame( 'salvato', $esiti[0]['codice'] );
		$this->assertSame( array( $tenuto ), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertNull( get_post( $tolto ) );
		$this->assertFileDoesNotExist( $percorso );

		$tracce = array_column( $this->tracce( $contenuto ), 'dettagli' );

		$this->assertContains(
			array(
				'operazione' => 'tolto',
				'allegato'   => $tolto,
				'nome'       => 'tolto.pdf',
				'impronta'   => $impronta['algoritmo'] . ':' . $impronta['valore'],
			),
			$tracce
		);

		$this->assertSame( 'dati', SchedaDocumenti::togli( $contenuto, $tolto )['codice'], 'Un documento gia tolto non si toglie di nuovo.' );

		foreach ( array( $tenuto . 'x', $tenuto . ' ', '-' . $tenuto, '0' . $tenuto ) as $quasi ) {
			$this->assertSame( 'dati', SchedaDocumenti::applica( $contenuto, $this->richiesta( $contenuto, array( $quasi ) ), array() )[0]['codice'], $quasi );
		}

		$this->assertSame( array( $tenuto ), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertCount( 3, $this->tracce( $contenuto ) );

		// Un contenuto pubblicato e poi rimesso in bozza: il documento si toglie.
		$ripreso  = $this->contenuto( 'publish' );
		$errore   = $this->deposita( $ripreso );
		$aggiorna = wp_update_post(
			array(
				'ID'          => $ripreso,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( $ripreso, $aggiorna );
		$this->assertSame( 'salvato', SchedaDocumenti::togli( $ripreso, $errore )['codice'] );
	}

	/**
	 * T-68: [attacco] rimozioni rifiutate: contenuto pubblicato, documento di un altro contenuto o non depositato dal componente comune.
	 */
	public function test_t68_rimozioni_rifiutate(): void {
		$pubblicato = $this->contenuto( 'publish' );
		$suo        = $this->deposita( $pubblicato );
		$contenuto  = $this->contenuto();
		$altro      = $this->contenuto();
		$di_altro   = $this->deposita( $altro );
		$libero     = (int) self::factory()->attachment->create_object(
			'libero.pdf',
			$contenuto,
			array(
				'post_mime_type' => 'application/pdf',
				'post_type'      => 'attachment',
			)
		);

		$this->assertSame( 'pubblicato', SchedaDocumenti::togli( $pubblicato, $suo )['codice'] );
		$this->assertSame( 'dati', SchedaDocumenti::togli( $contenuto, $di_altro )['codice'], 'Documento di un altro contenuto.' );
		$this->assertSame( 'dati', SchedaDocumenti::togli( $contenuto, $libero )['codice'], 'Allegato non depositato dal componente comune.' );
		$this->assertSame( array(), SchedaDocumenti::documenti( $contenuto ), 'Un allegato non protetto non e un documento del contenuto.' );

		$voce_di_trasparenza = (int) self::factory()->post->create( array( 'post_type' => TIPO_VOCE ) );
		$this->assertSame( 'permesso', SchedaDocumenti::togli( $voce_di_trasparenza, $di_altro )['codice'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 'permesso', SchedaDocumenti::togli( $altro, $di_altro )['codice'] );

		$this->assertNotNull( get_post( $suo ) );
		$this->assertNotNull( get_post( $di_altro ) );
		$this->assertNotNull( get_post( $libero ) );
		$this->assertCount( 1, $this->tracce( $pubblicato ) );
		$this->assertCount( 1, $this->tracce( $altro ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-68: la riga si cancella e il file no: esito proprio con il nome del file, voce di registro che richiama la rimozione.
	 */
	public function test_t68_file_rimasto(): void {
		$contenuto = $this->contenuto();
		$allegato  = $this->deposita( $contenuto, 'dati-personali.pdf' );

		add_filter( 'wp_delete_file', '__return_empty_string' );
		$esito = SchedaDocumenti::togli( $contenuto, $allegato );
		$this->ripara();

		$this->assertSame( 'file', $esito['codice'] );
		$this->assertStringContainsString( 'il file dati-personali.pdf è rimasto nella cartella protetta', $esito['messaggio'] );
		$this->assertStringContainsString( 'Il registro delle modifiche lo annota.', $esito['messaggio'] );
		$this->assertStringNotContainsString( \Conformita_Core_Allegati::cartella(), $esito['messaggio'] );
		$this->assertSame( array(), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertCount( 1, $this->documenti_sul_disco() );

		$tracce = array();

		foreach ( $this->tracce( $contenuto ) as $voce ) {
			$tracce[ $voce['dettagli']['operazione'] ] = $voce;
		}

		ksort( $tracce );

		$this->assertSame( array( 'aggiunto', 'file_rimasto', 'tolto' ), array_keys( $tracce ) );
		$this->assertSame( $tracce['tolto']['id'], $tracce['file_rimasto']['riferimento'], 'La voce del file rimasto richiama quella della rimozione.' );
		$this->assertSame( 'dati-personali.pdf', $tracce['file_rimasto']['dettagli']['nome'] );
	}

	/**
	 * T-68: guasti: letture fallite, registro che rifiuta, allegato non cancellato.
	 */
	public function test_t68_guasti(): void {
		$contenuto = $this->contenuto();
		$documento = $this->deposita( $contenuto );

		foreach ( array( 'SELECT ID FROM %s WHERE post_parent', 'SELECT post_status FROM %s WHERE ID' ) as $lettura ) {
			$this->guasta_lettura( $lettura );
			$esito = SchedaDocumenti::togli( $contenuto, $documento );
			$this->ripara();

			$this->assertSame( 'lettura', $esito['codice'], $lettura );
		}

		$this->registro_che_rifiuta();
		$esito = SchedaDocumenti::togli( $contenuto, $documento );
		$this->ripara();

		$this->assertSame( 'registro', $esito['codice'] );
		$this->assertCount( 1, $this->tracce( $contenuto ) );

		add_filter( 'pre_delete_attachment', '__return_false' );
		$esito = SchedaDocumenti::togli( $contenuto, $documento );
		$this->ripara();

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'rimozione che potrebbe non essere avvenuta', $esito['messaggio'] );

		$this->assertSame( array( $documento ), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertCount( 1, $this->documenti_sul_disco() );

		// La lettura che controlla la cancellazione fallisce: l'esito chiede di controllare.
		$this->guasta_lettura( 'SELECT COUNT(*) FROM %s WHERE ID' );
		$esito = SchedaDocumenti::togli( $contenuto, $documento );
		$this->ripara();

		$this->assertSame( 'scrittura', $esito['codice'] );
	}

	/**
	 * T-69: il riquadro: elenco con il collegamento amministrativo, casella per togliere solo se non pubblicato, campo del file, modulo con i file.
	 */
	public function test_t69_riquadro(): void {
		$contenuto = $this->contenuto();
		$riquadro  = SchedaDocumenti::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'name="' . SchedaDocumenti::CAMPO_NONCE . '"', $riquadro );
		$this->assertStringContainsString( 'type="file"', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaDocumenti::CAMPO_FILE . '"', $riquadro );
		$this->assertStringContainsString( 'Nessun allegato.', $riquadro );

		$documento = $this->deposita( $contenuto, 'relazione.pdf' );
		$riquadro  = SchedaDocumenti::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'relazione.pdf</a>', $riquadro );
		$this->assertStringContainsString( esc_url( conformita_core_indirizzo_consegna_amministrativa( $documento ) ), $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaDocumenti::CAMPO_TOGLI . '[]" value="' . $documento . '"', $riquadro );

		wp_update_post(
			array(
				'ID'          => $contenuto,
				'post_status' => 'publish',
			)
		);

		$riquadro = SchedaDocumenti::riquadro( get_post( $contenuto ) );

		$this->assertStringNotContainsString( SchedaDocumenti::CAMPO_TOGLI, $riquadro );
		$this->assertStringContainsString( 'rimettere il contenuto in bozza', $riquadro );

		ob_start();
		SchedaDocumenti::modulo( get_post( $contenuto ) );
		$this->assertSame( ' enctype="multipart/form-data"', ob_get_clean() );

		ob_start();
		SchedaDocumenti::modulo( self::factory()->post->create_and_get() );
		$this->assertSame( '', ob_get_clean(), 'Solo sulla schermata dei contenuti.' );

		$this->guasta_lettura( 'SELECT ID FROM %s WHERE post_parent' );
		$riquadro = SchedaDocumenti::riquadro( get_post( $contenuto ) );
		$this->ripara();

		$this->assertStringContainsString( 'non si sono potuti leggere', $riquadro );
	}

	/**
	 * T-70: dal salvataggio del contenuto: la rimozione avviene, il file non caricato si rifiuta, l'esito si mostra una volta; senza riquadro niente.
	 */
	public function test_t70_dal_salvataggio(): void {
		$contenuto = $this->contenuto();
		$documento = $this->deposita( $contenuto );
		$segreto   = $this->file_temporaneo( 'altro.pdf' );

		$_POST  = $this->richiesta( $contenuto, array( (string) $documento ) );
		$_FILES = array( SchedaDocumenti::CAMPO_FILE => $segreto );
		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Titolo cambiato',
			)
		);
		$_POST  = array();
		$_FILES = array();

		$this->assertSame( array(), SchedaDocumenti::documenti( $contenuto ), 'La rimozione e avvenuta; il file non caricato non si deposita.' );
		$this->assertFileExists( $segreto['tmp_name'] );

		set_current_screen( 'edit-' . TIPO_CONTENUTO );
		$GLOBALS['post'] = get_post( $contenuto ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- il contenuto della schermata, come lo mette WordPress.
		$this->assertStringNotContainsString( 'Allegato', $this->avvisi_in_bacheca(), 'Solo sulla schermata del contenuto, non nell\'elenco.' );

		// Un secondo contenuto salvato subito dopo: il suo esito non cancella quello del primo.
		$secondo = $this->contenuto();
		$_POST   = $this->richiesta( $secondo, array( 'x' ) );
		wp_update_post(
			array(
				'ID'         => $secondo,
				'post_title' => 'Secondo',
			)
		);
		$_POST = array();

		set_current_screen( TIPO_CONTENUTO );
		$GLOBALS['post'] = get_post( $secondo ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.
		$avvisi          = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( 'non indicava un allegato valido', $avvisi );
		$this->assertStringNotContainsString( 'Allegato tolto', $avvisi, 'L\'esito del primo non compare sul secondo.' );

		$GLOBALS['post'] = get_post( $contenuto ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.
		$avvisi          = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( 'Allegato tolto', $avvisi );
		$this->assertStringNotContainsString( 'non indicava un allegato valido', $avvisi );
		$this->assertStringContainsString( 'caricamento HTTP', $avvisi );
		$this->assertStringNotContainsString( 'Allegato tolto', $this->avvisi_in_bacheca(), 'L\'esito si mostra una volta.' );

		unset( $GLOBALS['post'] );
		set_current_screen( 'front' );

		$nuovo = $this->deposita( $contenuto );

		$_FILES = array( SchedaDocumenti::CAMPO_FILE => $this->file_temporaneo() );
		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Senza riquadro',
			)
		);
		$_FILES = array();

		$this->assertSame( array( $nuovo ), SchedaDocumenti::documenti( $contenuto ) );
		$this->assertFalse( get_transient( SchedaDocumenti::chiave_esito( $contenuto ) ), 'Senza riquadro nessun esito.' );
		$this->assertCount( 3, $this->tracce( $contenuto ) );
	}
}
