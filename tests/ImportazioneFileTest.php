<?php
/**
 * Prove dei file copiati dalle pagine nei contenuti in bozza: righe T-135..T-139.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\ImportazionePagine;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\Permessi;
use TrasparenzaPa\RegistroAccessi;
use TrasparenzaPa\SchedaDocumenti;
use TrasparenzaPa\SchermataImportazione;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * I file delle pagine portate entrano fra i documenti dei contenuti in bozza, con la loro traccia.
 */
class ImportazioneFileTest extends WP_UnitTestCase {

	use DepositoDiProva;

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Pagina d'ingresso.
	 *
	 * @var int
	 */
	private $radice;

	/**
	 * Pagine sotto la pagina d'ingresso, per nome.
	 *
	 * @var array<string, int>
	 */
	private $pagine = array();

	/**
	 * Percorsi relativi dei file di prova, per nome.
	 *
	 * @var array<string, string>
	 */
	private $percorsi = array();

	/**
	 * File scritti nella cartella dei caricamenti, da togliere alla fine.
	 *
	 * @var array<int, string>
	 */
	private $creati = array();

	/**
	 * Sezione e tipi registrati, permessi, albero di prova, pagine con i loro file, server finto.
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
		$this->assertTrue( Installazione::aggiorna_se_serve() );

		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-importazione.json' );
		PaginaPubblica::da_init();
		$this->assertInstanceOf( Alberatura::class, PaginaPubblica::albero() );

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->amministratore );

		$anno           = substr( RegistroAccessi::oggi(), 0, 4 );
		$this->percorsi = array(
			'entro'    => $anno . '/01/bando-entro.pdf',
			'doppio'   => $anno . '/01/bando-doppio.pdf',
			'oltre'    => '2015/03/bando-oltre.pdf',
			'senza'    => 'documenti/senza-data.pdf',
			'varie'    => $anno . '/02/varie.pdf',
			'mancante' => $anno . '/02/mancante.pdf',
			'fuori'    => $anno . '/../../fuori.pdf',
		);

		$byte_entro = $this->pdf( 'entro' );
		$this->file( $this->percorsi['entro'], $byte_entro );
		$this->file( $this->percorsi['doppio'], $byte_entro );
		$this->file( $this->percorsi['oltre'], $this->pdf( 'oltre' ) );
		$this->file( $this->percorsi['senza'], $this->pdf( 'senza' ) );
		$this->file( $this->percorsi['varie'], $this->pdf( 'varie' ) );

		$this->radice = $this->pagina( 'Amministrazione trasparente', 0, '' );

		$this->pagine['Bandi di concorso'] = $this->pagina( 'Bandi di concorso', $this->radice, $this->collegamenti( array( 'entro', 'oltre', 'senza' ) ), 0 );
		$this->pagine['Organizzazione']    = $this->pagina( 'Organizzazione', $this->radice, $this->collegamenti( array( 'varie' ) ), 1 );
		$this->pagine['Varie']             = $this->pagina( 'Varie', $this->radice, $this->collegamenti( array( 'varie', 'mancante', 'fuori' ) ), 2 );

		$this->aggancia_server_finto();
	}

	/**
	 * Toglie guasti, file, albero e blocco.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'map_meta_cap' );
		$wpdb->suppress_errors( false );
		Blocco::rilascia();

		foreach ( $this->creati as $pieno ) {
			wp_delete_file( $pieno );
		}

		$this->sgancia_server_finto();
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Un PDF minimo riconoscibile.
	 *
	 * @param string $segno Testo che lo distingue.
	 * @return string
	 */
	private function pdf( string $segno ): string {
		return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n% " . $segno . "\n";
	}

	/**
	 * Scrive un file nella cartella dei caricamenti.
	 *
	 * @param string $relativo Percorso relativo.
	 * @param string $byte     Contenuto.
	 * @return string Percorso pieno.
	 */
	private function file( string $relativo, string $byte ): string {
		$pieno = wp_upload_dir( null, false )['basedir'] . '/' . $relativo;
		wp_mkdir_p( dirname( $pieno ) );
		file_put_contents( $pieno, $byte ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->creati[] = $pieno;

		return $pieno;
	}

	/**
	 * Il testo di una pagina che collega i file dati per nome.
	 *
	 * @param array<int, string> $nomi Nomi dei file.
	 * @return string
	 */
	private function collegamenti( array $nomi ): string {
		$testo = '<p>Documenti</p>';

		foreach ( $nomi as $nome ) {
			$testo .= '<a href="' . wp_upload_dir( null, false )['baseurl'] . '/' . $this->percorsi[ $nome ] . '">' . $nome . '</a>';
		}

		return $testo;
	}

	/**
	 * Una pagina pubblicata.
	 *
	 * @param string $titolo Titolo.
	 * @param int    $padre  Genitore.
	 * @param string $testo  Testo.
	 * @param int    $ordine Ordine nel menu.
	 * @return int
	 */
	private function pagina( string $titolo, int $padre, string $testo, int $ordine = 0 ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $titolo,
				'post_parent'  => $padre,
				'post_content' => $testo,
				'menu_order'   => $ordine,
			)
		);
	}

	/**
	 * Porta in bozza le pagine date e restituisce il contenuto di ciascuna.
	 *
	 * @param array<string, string> $voci Voce per nome della pagina.
	 * @return array<string, int>
	 */
	private function porta( array $voci ): array {
		$scelte = array();

		foreach ( $voci as $nome => $voce ) {
			$scelte[ $this->pagine[ $nome ] ] = $voce;
		}

		$righe = ImportazionePagine::porta( $this->radice, $scelte, PaginaPubblica::albero(), RegistroAccessi::oggi() );
		$this->assertIsArray( $righe );

		$contenuti = array();

		foreach ( $righe as $riga ) {
			$this->assertSame( 'portata', $riga['esito'] );
			$contenuti[ (string) array_search( $riga['pagina'], $this->pagine, true ) ] = (int) $riga['contenuto'];
		}

		return $contenuti;
	}

	/**
	 * La richiesta del modulo dei file.
	 *
	 * @param mixed           $copia      Percorsi per pagina, campo normale.
	 * @param mixed           $verificata Percorsi per pagina, campo di conferma.
	 * @param int|string|null $radice     Pagina d'ingresso.
	 * @return array<string, mixed>
	 */
	private function richiesta( $copia, $verificata = array(), $radice = null ): array {
		return array(
			'_wpnonce'                              => wp_create_nonce( SchermataImportazione::AZIONE_FILE ),
			SchermataImportazione::CAMPO_RADICE     => (string) ( $radice ?? $this->radice ),
			SchermataImportazione::CAMPO_COPIA      => $copia,
			SchermataImportazione::CAMPO_VERIFICATA => $verificata,
		);
	}

	/**
	 * Gli esiti delle righe, per pagina e percorso.
	 *
	 * @param array<string, mixed> $esito Esito.
	 * @return array<string, string>
	 */
	private function esiti( array $esito ): array {
		$righe = array();

		foreach ( $esito['righe'] ?? array() as $riga ) {
			$righe[ $riga['pagina'] . ' ' . $riga['percorso'] ] = $riga['esito'];
		}

		return $righe;
	}

	/**
	 * La chiave di una riga negli esiti.
	 *
	 * @param string $pagina Nome della pagina.
	 * @param string $file   Nome del file.
	 * @return string
	 */
	private function chiave( string $pagina, string $file ): string {
		return $this->pagine[ $pagina ] . ' ' . $this->percorsi[ $file ];
	}

	/**
	 * I documenti di un contenuto.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, int>
	 */
	private function documenti( int $contenuto ): array {
		$documenti = SchedaDocumenti::documenti( $contenuto );
		$this->assertIsArray( $documenti );

		return $documenti;
	}

	/**
	 * I file temporanei che portano il nome di un file di prova.
	 *
	 * @return array<int, string>
	 */
	private function temporanei(): array {
		$trovati = glob( trailingslashit( get_temp_dir() ) . '*.tmp' );

		return array_values(
			array_filter(
				is_array( $trovati ) ? $trovati : array(),
				static function ( $percorso ) {
					return 1 === preg_match( '/(bando|varie|senza)/', basename( $percorso ) );
				}
			)
		);
	}

	/**
	 * T-135. Chi copia i file e cosa accetta.
	 */
	public function test_t135_permessi_nonce_e_righe_non_valide() {
		$contenuti = $this->porta(
			array(
				'Bandi di concorso' => 'bandi',
				'Varie'             => 'atti_altri',
			)
		);
		$bandi     = $this->pagine['Bandi di concorso'];
		$valida    = array( $bandi => array( $this->percorsi['entro'] ) );

		// Senza il permesso della sezione, o senza quello di creare contenuti.
		$redattore = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $redattore )->add_cap( get_post_type_object( TIPO_CONTENUTO )->cap->create_posts );
		get_user_by( 'id', $redattore )->add_cap( get_post_type_object( TIPO_CONTENUTO )->cap->edit_posts );
		wp_set_current_user( $redattore );
		$this->assertFalse( current_user_can( Permessi::AMMINISTRAZIONE ) );
		$this->assertSame( 'permesso', SchermataImportazione::salva_file( $this->richiesta( $valida ) )['codice'] );

		$solo_sezione = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_sezione )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $solo_sezione );
		$this->assertSame( 'permesso', SchermataImportazione::salva_file( $this->richiesta( $valida ) )['codice'] );

		wp_set_current_user( $this->amministratore );

		// Nonce mancante, o di un'altra azione.
		$richiesta = $this->richiesta( $valida );
		unset( $richiesta['_wpnonce'] );
		$this->assertSame( 'nonce', SchermataImportazione::salva_file( $richiesta )['codice'] );
		$richiesta['_wpnonce'] = wp_create_nonce( SchermataImportazione::AZIONE_BOZZE );
		$this->assertSame( 'nonce', SchermataImportazione::salva_file( $richiesta )['codice'] );

		// Pagina d'ingresso e campi non validi.
		$this->assertSame( 'radice', SchermataImportazione::salva_file( $this->richiesta( $valida, array(), '0' . $this->radice ) )['codice'] );
		$this->assertSame( 'dati', SchermataImportazione::salva_file( $this->richiesta( 'tutto' ) )['codice'] );
		$this->assertSame( 'dati', SchermataImportazione::salva_file( $this->richiesta( $valida, 'tutto' ) )['codice'] );
		$this->assertSame( array(), $this->documenti( $contenuti['Bandi di concorso'] ), 'Senza permessi, nonce o campi validi niente si copia.' );

		// Un file già protetto dal componente comune, collegato dalla pagina Varie.
		$altro    = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => 'draft',
				'post_title'  => 'Altro contenuto',
			)
		);
		$deposito = SchedaDocumenti::deposita( $altro, $this->file_temporaneo( 'protetto.pdf' ), 'percorso_locale' );
		$this->assertSame( 'salvato', $deposito['codice'] );
		$protetto = $this->documenti( $altro )[0];
		$relativo = (string) get_post_meta( $protetto, '_wp_attached_file', true );
		$this->assertTrue( conformita_core_allegato_protetto( $protetto ) );
		wp_update_post(
			array(
				'ID'           => $this->pagine['Varie'],
				'post_content' => $this->collegamenti( array( 'varie', 'mancante', 'fuori' ) ) . '<a href="' . wp_upload_dir( null, false )['baseurl'] . '/' . $relativo . '">protetto</a>',
			)
		);

		$rapporto = ImportazionePagine::rapporto( $this->radice, PaginaPubblica::albero(), RegistroAccessi::oggi() );
		$this->assertIsArray( $rapporto );
		$file_varie = array_column( array_column( $rapporto['pagine'], 'file', 'id' )[ $this->pagine['Varie'] ], 'protetto', 'percorso' );
		$this->assertTrue( $file_varie[ $relativo ] );
		$this->assertFalse( $file_varie[ $this->percorsi['varie'] ] );

		$esito = SchermataImportazione::salva_file(
			$this->richiesta(
				array(
					$bandi                          => array( $this->percorsi['entro'], 'altro/inesistente.pdf', '../' . $this->percorsi['entro'], array( $this->percorsi['entro'] ) ),
					$this->pagine['Organizzazione'] => array( $this->percorsi['varie'] ),
					'0' . $this->pagine['Varie']    => array( $this->percorsi['varie'] ),
					999999                          => array( $this->percorsi['varie'] ),
					$this->pagine['Varie']          => array( $this->percorsi['mancante'], $this->percorsi['fuori'], $relativo ),
				)
			)
		);
		$this->assertSame( 'copiato', $esito['codice'] );
		$esiti = $this->esiti( $esito );

		$this->assertSame( 'copiato', $esiti[ $this->chiave( 'Bandi di concorso', 'entro' ) ] );
		$this->assertSame( 'scartato', $esiti[ $bandi . ' altro/inesistente.pdf' ] );
		$this->assertSame( 'scartato', $esiti[ $bandi . ' ../' . $this->percorsi['entro'] ] );
		$this->assertSame( 'scartato', $esiti[ $bandi . ' ' ] );
		$this->assertSame( 'scartato', $esiti[ $this->chiave( 'Organizzazione', 'varie' ) ] );
		$this->assertSame( 'scartato', $esiti[ '0 ' . $this->percorsi['varie'] ] );
		$this->assertSame( 'scartato', $esiti[ '999999 ' . $this->percorsi['varie'] ] );
		$this->assertSame( 'scartato', $esiti[ $this->chiave( 'Varie', 'mancante' ) ] );
		$this->assertSame( 'scartato', $esiti[ $this->chiave( 'Varie', 'fuori' ) ] );
		$this->assertSame( 'scartato', $esiti[ $this->pagine['Varie'] . ' ' . $relativo ] );
		$this->assertCount( 1, $this->documenti( $contenuti['Bandi di concorso'] ) );
		$this->assertSame( array(), $this->documenti( $contenuti['Varie'] ) );
		$this->assertCount( 1, $this->documenti( $altro ), 'Il file protetto non ha un secondo documento.' );

		foreach ( $esito['righe'] as $riga ) {
			if ( 'scartato' === $riga['esito'] ) {
				$this->assertNotSame( '', $riga['motivo'], 'Ogni riga scartata dice perché.' );
			}
		}

		// Contenuto pubblicato, programmato, nel cestino: niente; in attesa di revisione: sì.
		$varie = $contenuti['Varie'];
		$file  = array( $this->pagine['Varie'] => array( $this->percorsi['varie'] ) );

		foreach ( array( 'publish', 'future', 'trash' ) as $stato ) {
			wp_update_post(
				array(
					'ID'            => $varie,
					'post_status'   => $stato,
					'post_date'     => 'future' === $stato ? '2099-01-01 10:00:00' : current_time( 'mysql' ),
					'post_date_gmt' => 'future' === $stato ? '2099-01-01 09:00:00' : current_time( 'mysql', true ),
				)
			);
			$this->assertSame( $stato, get_post_status( $varie ) );
			$this->assertSame( 'scartato', $this->esiti( SchermataImportazione::salva_file( $this->richiesta( $file ) ) )[ $this->chiave( 'Varie', 'varie' ) ], $stato );
			$this->assertSame( array(), $this->documenti( $varie ), $stato );
		}

		wp_update_post(
			array(
				'ID'          => $varie,
				'post_status' => 'pending',
			)
		);
		$this->assertSame( 'copiato', $this->esiti( SchermataImportazione::salva_file( $this->richiesta( $file ) ) )[ $this->chiave( 'Varie', 'varie' ) ] );
		$this->assertCount( 1, $this->documenti( $varie ) );

		// Senza il permesso di modificare il contenuto che riceve il file.
		$bandi_contenuto = $contenuti['Bandi di concorso'];
		add_filter(
			'map_meta_cap',
			static function ( $capacita, $capacita_chiesta, $utente, $argomenti ) use ( $bandi_contenuto ) {
				return 'edit_post' === $capacita_chiesta && isset( $argomenti[0] ) && (int) $argomenti[0] === $bandi_contenuto ? array( 'do_not_allow' ) : $capacita;
			},
			10,
			4
		);
		$esito = SchermataImportazione::salva_file( $this->richiesta( array(), array( $bandi => array( $this->percorsi['oltre'] ) ) ) );
		$this->assertSame( 'errore', $this->esiti( $esito )[ $this->chiave( 'Bandi di concorso', 'oltre' ) ] );
		$this->assertCount( 1, $this->documenti( $bandi_contenuto ) );
	}

	/**
	 * T-136. Il file copiato.
	 */
	public function test_t136_file_copiato() {
		$contenuti = $this->porta( array( 'Bandi di concorso' => 'bandi' ) );
		$id        = $contenuti['Bandi di concorso'];
		$sorgente  = wp_upload_dir( null, false )['basedir'] . '/' . $this->percorsi['entro'];
		$impronta  = hash_file( 'sha256', $sorgente );
		$prima     = $this->temporanei();

		$esito = SchermataImportazione::salva_file( $this->richiesta( array( $this->pagine['Bandi di concorso'] => array( $this->percorsi['entro'] ) ) ) );
		$this->assertSame( 'copiato', $esito['codice'] );
		$this->assertStringContainsString( 'File copiati: 1', $esito['messaggio'] );

		$documenti = $this->documenti( $id );
		$this->assertCount( 1, $documenti );
		$allegato = $documenti[0];
		$this->assertSame( $allegato, $esito['righe'][0]['allegato'] );
		$registrata = conformita_core_impronta_allegato( $allegato );
		$this->assertSame( 'sha256', $registrata['algoritmo'] );
		$this->assertSame( $impronta, $registrata['valore'] );
		$this->assertStringContainsString( \Conformita_Core_Allegati::cartella(), (string) get_attached_file( $allegato ) );
		$this->assertSame( 'bando-entro.pdf', wp_basename( (string) get_attached_file( $allegato ) ) );

		// L'originale resta dov'era, uguale.
		$this->assertFileExists( $sorgente );
		$this->assertSame( $impronta, hash_file( 'sha256', $sorgente ) );

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( $prima, $this->temporanei(), 'Nessun temporaneo resta.' );

		$righe = conformita_core_voci_registro(
			array(
				'contenuto' => $id,
				'azione'    => SchedaDocumenti::AZIONE_REGISTRO,
			)
		);
		$this->assertIsArray( $righe );
		$this->assertCount( 1, $righe );
		$this->assertSame( 'aggiunto', $righe[0]['dettagli']['operazione'] );
		$this->assertSame( $allegato, (int) $righe[0]['dettagli']['allegato'] );
		$this->assertSame( 'sha256:' . $impronta, $righe[0]['dettagli']['impronta'] );
		$this->assertSame( $this->pagine['Bandi di concorso'], (int) $righe[0]['dettagli']['pagina'] );
		$this->assertSame( $this->percorsi['entro'], $righe[0]['dettagli']['percorso'] );
		$this->assertSame( $this->amministratore, (int) $righe[0]['utente'] );

		// I dettagli aggiunti non sostituiscono quelli di base.
		$this->assertTrue( Blocco::prendi() );

		try {
			$altro = SchedaDocumenti::deposita_nel_blocco(
				$id,
				$this->file_temporaneo( 'aggiunto.pdf' ),
				'percorso_locale',
				array(
					'operazione' => 'tolto',
					'impronta'   => 'sha256:falsa',
				)
			);
		} finally {
			Blocco::rilascia();
		}

		$this->assertSame( 'salvato', $altro['codice'] );
		$righe  = conformita_core_voci_registro(
			array(
				'contenuto' => $id,
				'azione'    => SchedaDocumenti::AZIONE_REGISTRO,
			)
		);
		$ultima = end( $righe );
		$this->assertSame( 'aggiunto', $ultima['dettagli']['operazione'] );
		$this->assertSame( 'sha256:' . conformita_core_impronta_allegato( $altro['allegato'] )['valore'], $ultima['dettagli']['impronta'] );
	}

	/**
	 * T-137. File oltre il termine ordinario e senza data: solo con la conferma.
	 */
	public function test_t137_oltre_il_termine_solo_con_conferma() {
		$contenuti = $this->porta( array( 'Bandi di concorso' => 'bandi' ) );
		$id        = $contenuti['Bandi di concorso'];
		$bandi     = $this->pagine['Bandi di concorso'];

		$rapporto = ImportazionePagine::rapporto( $this->radice, PaginaPubblica::albero(), RegistroAccessi::oggi() );
		$this->assertIsArray( $rapporto );
		$finite = array_column( array_column( $rapporto['pagine'], 'file', 'id' )[ $bandi ], 'finita', 'percorso' );
		$this->assertFalse( $finite[ $this->percorsi['entro'] ], 'Precondizione: entro il termine.' );
		$this->assertTrue( $finite[ $this->percorsi['oltre'] ], 'Precondizione: oltre il termine.' );
		$this->assertNull( $finite[ $this->percorsi['senza'] ], 'Precondizione: senza data.' );

		$esito = SchermataImportazione::salva_file( $this->richiesta( array( $bandi => array( $this->percorsi['oltre'], $this->percorsi['senza'] ) ) ) );
		$esiti = $this->esiti( $esito );
		$this->assertSame( 'scartato', $esiti[ $this->chiave( 'Bandi di concorso', 'oltre' ) ] );
		$this->assertSame( 'scartato', $esiti[ $this->chiave( 'Bandi di concorso', 'senza' ) ] );
		$this->assertStringContainsString( 'conferma', $esito['righe'][0]['motivo'] );
		$this->assertSame( array(), $this->documenti( $id ) );

		$esito = SchermataImportazione::salva_file( $this->richiesta( array(), array( $bandi => array( $this->percorsi['oltre'], $this->percorsi['senza'], $this->percorsi['entro'] ) ) ) );
		$esiti = $this->esiti( $esito );
		$this->assertSame( 'copiato', $esiti[ $this->chiave( 'Bandi di concorso', 'oltre' ) ] );
		$this->assertSame( 'copiato', $esiti[ $this->chiave( 'Bandi di concorso', 'senza' ) ] );
		$this->assertSame( 'copiato', $esiti[ $this->chiave( 'Bandi di concorso', 'entro' ) ] );
		$this->assertCount( 3, $this->documenti( $id ) );
	}

	/**
	 * T-138. Nessun doppione; guasti.
	 */
	public function test_t138_doppioni_e_guasti() {
		global $wpdb;

		wp_update_post(
			array(
				'ID'           => $this->pagine['Bandi di concorso'],
				'post_content' => $this->collegamenti( array( 'entro', 'doppio', 'oltre', 'senza' ) ),
			)
		);
		$contenuti = $this->porta(
			array(
				'Bandi di concorso' => 'bandi',
				'Varie'             => 'atti_altri',
			)
		);
		$bandi     = $this->pagine['Bandi di concorso'];
		$id        = $contenuti['Bandi di concorso'];

		// Due percorsi con gli stessi byte nella stessa richiesta: uno solo.
		$esito = SchermataImportazione::salva_file( $this->richiesta( array( $bandi => array( $this->percorsi['entro'], $this->percorsi['doppio'] ) ) ) );
		$esiti = $this->esiti( $esito );
		$this->assertSame( 'copiato', $esiti[ $this->chiave( 'Bandi di concorso', 'entro' ) ] );
		$this->assertSame( 'gia_copiato', $esiti[ $this->chiave( 'Bandi di concorso', 'doppio' ) ] );
		$this->assertCount( 1, $this->documenti( $id ) );

		// Secondo passaggio: già copiato.
		$esito = SchermataImportazione::salva_file( $this->richiesta( array( $bandi => array( $this->percorsi['entro'] ) ) ) );
		$this->assertSame( 'gia_copiato', $this->esiti( $esito )[ $this->chiave( 'Bandi di concorso', 'entro' ) ] );
		$this->assertCount( 1, $this->documenti( $id ) );

		// Blocco occupato: niente copiato.
		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );
		$altra                         = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );
			$esito = SchermataImportazione::salva_file( $this->richiesta( array( $this->pagine['Varie'] => array( $this->percorsi['varie'] ) ) ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertSame( array(), $this->documenti( $contenuti['Varie'] ) );

		// Documenti di Bandi non leggibili: Bandi niente, Varie prosegue.
		$wpdb->suppress_errors( true );
		$filtro = static function ( $sql ) use ( $wpdb, $id ) {
			return false !== strpos( (string) $sql, 'post_parent = ' . $id . " AND post_type = 'attachment'" )
				? 'SELECT ID FROM ' . $wpdb->posts . '_inesistente'
				: $sql;
		};
		add_filter( 'query', $filtro );

		try {
			$esito = SchermataImportazione::salva_file(
				$this->richiesta(
					array(),
					array(
						$bandi                 => array( $this->percorsi['oltre'] ),
						$this->pagine['Varie'] => array( $this->percorsi['varie'] ),
					)
				)
			);
		} finally {
			remove_filter( 'query', $filtro );
			$wpdb->suppress_errors( false );
		}

		$esiti = $this->esiti( $esito );
		$this->assertSame( 'errore', $esiti[ $this->chiave( 'Bandi di concorso', 'oltre' ) ] );
		$this->assertSame( 'copiato', $esiti[ $this->chiave( 'Varie', 'varie' ) ] );
		$this->assertCount( 1, $this->documenti( $id ) );

		// Deposito rifiutato dal componente comune: niente copiato, nessun temporaneo.
		$prima = $this->temporanei();
		$this->server_che_serve_l_esca();
		$esito = SchermataImportazione::salva_file( $this->richiesta( array(), array( $bandi => array( $this->percorsi['oltre'], $this->percorsi['senza'] ) ) ) );
		$esiti = $this->esiti( $esito );
		$this->assertSame( 'errore', $esiti[ $this->chiave( 'Bandi di concorso', 'oltre' ) ] );
		$this->assertSame( 'errore', $esiti[ $this->chiave( 'Bandi di concorso', 'senza' ) ] );
		$this->assertCount( 1, $this->documenti( $id ) );
		$this->assertSame( $prima, $this->temporanei() );
		$this->assertFileExists( wp_upload_dir( null, false )['basedir'] . '/' . $this->percorsi['oltre'] );
		$this->aggancia_server_finto();

		// Registro che rifiuta: niente copiato.
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );
		$esito = SchermataImportazione::salva_file( $this->richiesta( array(), array( $bandi => array( $this->percorsi['oltre'] ) ) ) );
		$this->assertSame( 'errore', $this->esiti( $esito )[ $this->chiave( 'Bandi di concorso', 'oltre' ) ] );
		$this->assertCount( 1, $this->documenti( $id ) );
	}

	/**
	 * T-139. La schermata: modulo dei file, caselle, stati, esito, escaping.
	 */
	public function test_t139_schermata() {
		$strano = substr( RegistroAccessi::oggi(), 0, 4 ) . '/01/a&b.pdf';
		$this->file( $strano, $this->pdf( 'strano' ) );
		$this->creati[] = wp_upload_dir( null, false )['basedir'] . '/' . $strano;
		wp_update_post(
			array(
				'ID'           => $this->pagine['Bandi di concorso'],
				'post_title'   => 'Bandi <b>di</b> concorso',
				'post_content' => $this->collegamenti( array( 'entro', 'oltre', 'senza' ) ) . '<a href="' . wp_upload_dir( null, false )['baseurl'] . '/' . str_replace( '&', '&amp;', $strano ) . '">strano</a>',
			)
		);
		$contenuti = $this->porta(
			array(
				'Bandi di concorso' => 'bandi',
				'Varie'             => 'atti_altri',
			)
		);
		wp_update_post(
			array(
				'ID'          => $contenuti['Varie'],
				'post_status' => 'publish',
			)
		);

		$bandi = $this->pagine['Bandi di concorso'];
		$get   = array(
			'page'                              => SchermataImportazione::PAGINA,
			SchermataImportazione::CAMPO_RADICE => (string) $this->radice,
			'_wpnonce'                          => wp_create_nonce( SchermataImportazione::AZIONE ),
		);

		$this->assertSame( 10, has_action( 'admin_post_' . SchermataImportazione::AZIONE_FILE, array( SchermataImportazione::class, 'da_admin_post' ) ) );

		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'name="action" value="' . SchermataImportazione::AZIONE_FILE . '"', $html );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="' . preg_quote( wp_create_nonce( SchermataImportazione::AZIONE_FILE ), '/' ) . '"/', $html );
		$this->assertStringContainsString( '<input type="checkbox" name="' . SchermataImportazione::CAMPO_COPIA . '[' . $bandi . '][]" value="' . esc_attr( $this->percorsi['entro'] ) . '" checked', $html );
		$this->assertStringContainsString( '<input type="checkbox" name="' . SchermataImportazione::CAMPO_VERIFICATA . '[' . $bandi . '][]" value="' . esc_attr( $this->percorsi['oltre'] ) . '">', $html );
		$this->assertStringContainsString( '<input type="checkbox" name="' . SchermataImportazione::CAMPO_VERIFICATA . '[' . $bandi . '][]" value="' . esc_attr( $this->percorsi['senza'] ) . '">', $html );
		$this->assertStringContainsString( 'value="' . substr( $strano, 0, 8 ) . 'a&amp;b.pdf"', $html );
		$this->assertStringNotContainsString( 'a&b.pdf', $html );
		$this->assertStringContainsString( 'Bandi &lt;b&gt;di&lt;/b&gt; concorso', $html );
		$this->assertStringNotContainsString( 'Bandi <b>di</b>', $html );

		// Varie è pubblicata: nessuna casella per i suoi file; Organizzazione non è portata.
		$this->assertStringNotContainsString( '[' . $this->pagine['Varie'] . '][]', $html );
		$this->assertStringNotContainsString( '[' . $this->pagine['Organizzazione'] . '][]', $html );

		// Dopo la copia: esito una volta, file già copiato senza casella.
		$esito = SchermataImportazione::salva_file( $this->richiesta( array( $bandi => array( $this->percorsi['entro'] ) ) ) );
		SchermataImportazione::conserva_esito( $esito );
		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( 'File copiati: 1', $html );
		$this->assertStringNotContainsString( 'value="' . esc_attr( $this->percorsi['entro'] ) . '"', $html );
		$this->assertStringContainsString( 'già copiato', $html );
		$this->assertStringNotContainsString( 'File copiati: 1', SchermataImportazione::html( $get ) );

		// Mancante e fuori: il motivo, senza casella.
		wp_update_post(
			array(
				'ID'          => $contenuti['Varie'],
				'post_status' => 'draft',
			)
		);
		$html = SchermataImportazione::html( $get );
		$this->assertStringContainsString( '[' . $this->pagine['Varie'] . '][]" value="' . esc_attr( $this->percorsi['varie'] ) . '"', $html );
		$this->assertStringNotContainsString( 'value="' . esc_attr( $this->percorsi['mancante'] ) . '"', $html );
		$this->assertStringNotContainsString( 'value="' . esc_attr( $this->percorsi['fuori'] ) . '"', $html );

		// Chi non può creare contenuti non vede il modulo.
		$solo_sezione = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_user_by( 'id', $solo_sezione )->add_cap( Permessi::AMMINISTRAZIONE );
		wp_set_current_user( $solo_sezione );
		$get['_wpnonce'] = wp_create_nonce( SchermataImportazione::AZIONE );
		$this->assertStringNotContainsString( SchermataImportazione::AZIONE_FILE, SchermataImportazione::html( $get ) );
	}
}
