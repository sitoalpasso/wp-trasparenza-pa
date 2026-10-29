<?php
/**
 * Prove dell'albero normativo: righe T-20..T-25 e T-55.
 *
 * I nomi attesi sono scritti qui, trascritti dalle fonti, e non letti dal file:
 * una prova che confrontasse il file con se stesso passerebbe sempre.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use WP_UnitTestCase;

use const TrasparenzaPa\FILE_ALBERATURA;

/**
 * L'albero che il componente distribuisce e quello della norma.
 */
class AlberoNormativoTest extends WP_UnitTestCase {

	/**
	 * Le 23 voci di primo livello, nell'ordine dell'Allegato A del d.lgs.
	 * 33/2013, con la voce dell'art. 23-bis al posto di "Controlli sulle imprese".
	 *
	 * @var array<int, string>
	 */
	const PRIMO_LIVELLO = array(
		'Disposizioni generali',
		'Organizzazione',
		'Consulenti e collaboratori',
		'Personale',
		'Bandi di concorso',
		'Performance',
		'Enti controllati',
		'Attività e procedimenti',
		'Provvedimenti',
		'Controlli sulle attività economiche',
		'Bandi di gara e contratti',
		'Sovvenzioni, contributi, sussidi, vantaggi economici',
		'Bilanci',
		'Beni immobili e gestione patrimonio',
		'Controlli e rilievi sull\'amministrazione',
		'Servizi erogati',
		'Pagamenti dell\'amministrazione',
		'Opere pubbliche',
		'Pianificazione e governo del territorio',
		'Informazioni ambientali',
		'Strutture sanitarie private accreditate',
		'Interventi straordinari e di emergenza',
		'Altri contenuti',
	);

	/**
	 * Le voci di secondo livello per padre, nell'ordine dell'Allegato 1 della
	 * delibera ANAC 1310/2016. Le voci sui pagamenti stanno sotto "Pagamenti
	 * dell'amministrazione", come nell'Allegato A.
	 *
	 * @var array<string, array<int, string>>
	 */
	const SECONDO_LIVELLO = array(
		'Disposizioni generali'                     => array(
			'Piano triennale per la prevenzione della corruzione e della trasparenza',
			'Atti generali',
			'Oneri informativi per cittadini e imprese',
			'Burocrazia zero',
		),
		'Organizzazione'                            => array(
			'Titolari di incarichi politici, di amministrazione, di direzione o di governo',
			'Sanzioni per mancata comunicazione dei dati',
			'Rendiconti gruppi consiliari regionali/provinciali',
			'Articolazione degli uffici',
			'Telefono e posta elettronica',
		),
		'Consulenti e collaboratori'                => array(
			'Titolari di incarichi di collaborazione o consulenza',
		),
		'Personale'                                 => array(
			'Titolari di incarichi dirigenziali amministrativi di vertice',
			'Titolari di incarichi dirigenziali (dirigenti non generali)',
			'Dirigenti cessati',
			'Sanzioni per mancata comunicazione dei dati',
			'Posizioni organizzative',
			'Dotazione organica',
			'Personale non a tempo indeterminato',
			'Tassi di assenza',
			'Incarichi conferiti e autorizzati ai dipendenti (dirigenti e non dirigenti)',
			'Contrattazione collettiva',
			'Contrattazione integrativa',
			'OIV',
		),
		'Performance'                               => array(
			'Sistema di misurazione e valutazione della Performance',
			'Piano della Performance',
			'Relazione sulla Performance',
			'Ammontare complessivo dei premi',
			'Dati relativi ai premi',
			'Benessere organizzativo',
		),
		'Enti controllati'                          => array(
			'Enti pubblici vigilati',
			'Società partecipate',
			'Enti di diritto privato controllati',
			'Rappresentazione grafica',
		),
		'Attività e procedimenti'                   => array(
			'Dati aggregati attività amministrativa',
			'Tipologie di procedimento',
			'Monitoraggio tempi procedimentali',
			'Dichiarazioni sostitutive e acquisizione d\'ufficio dei dati',
		),
		'Provvedimenti'                             => array(
			'Provvedimenti organi indirizzo politico',
			'Provvedimenti dirigenti amministrativi',
		),
		'Bandi di gara e contratti'                 => array(
			'Informazioni sulle singole procedure in formato tabellare',
			'Atti delle amministrazioni aggiudicatrici e degli enti aggiudicatori distintamente per ogni procedura',
		),
		'Sovvenzioni, contributi, sussidi, vantaggi economici' => array(
			'Criteri e modalità',
			'Atti di concessione',
		),
		'Bilanci'                                   => array(
			'Bilancio preventivo e consuntivo',
			'Piano degli indicatori e dei risultati attesi di bilancio',
		),
		'Beni immobili e gestione patrimonio'       => array(
			'Patrimonio immobiliare',
			'Canoni di locazione o affitto',
		),
		'Controlli e rilievi sull\'amministrazione' => array(
			'Organismi indipendenti di valutazione, nuclei di valutazione o altri organismi con funzioni analoghe',
			'Organi di revisione amministrativa e contabile',
			'Corte dei conti',
		),
		'Servizi erogati'                           => array(
			'Carta dei servizi e standard di qualità',
			'Class action',
			'Costi contabilizzati',
			'Liste di attesa',
			'Servizi in rete',
		),
		'Pagamenti dell\'amministrazione'           => array(
			'Dati sui pagamenti',
			'Dati sui pagamenti del servizio sanitario nazionale',
			'Indicatore di tempestività dei pagamenti',
			'IBAN e pagamenti informatici',
		),
		'Opere pubbliche'                           => array(
			'Nuclei di valutazione e verifica degli investimenti pubblici',
			'Atti di programmazione delle opere pubbliche',
			'Tempi costi e indicatori di realizzazione delle opere pubbliche',
		),
		'Altri contenuti'                           => array(
			'Prevenzione della Corruzione',
			'Accesso civico',
			'Accessibilità e Catalogo dei dati, metadati e banche dati',
			'Dati ulteriori',
		),
	);

	/**
	 * Identificativi del modello at_map di ANAC che l'albero adotta.
	 *
	 * Il modello ne ha 71: tre non hanno una voce nelle fonti dell'albero
	 * ("budget", e i due della pianificazione integrata) e restano fuori.
	 *
	 * @var array<int, string>
	 */
	const IDENTIFICATIVI_AT_MAP = array(
		'amministrazione_trasparente',
		'disposizioni_generali',
		'organizzazione',
		'consulenti_collaboratori',
		'personale',
		'bandi_di_concorso',
		'performance',
		'enti_controllati',
		'attivita_e_procedimenti',
		'provvedimenti',
		'bandi_gara_e_contratti',
		'sovvenzioni',
		'bilanci',
		'beni_immobili',
		'controlli_e_rilievi',
		'servizi_erogati',
		'pagamenti',
		'opere_pubbliche',
		'pianificazione_governo_territorio',
		'informazioni_ambientali',
		'strutture_sanitarie_accreditate',
		'interventi_straordinari',
		'altri_contenuti',
		'piano_triennale_prevenzione_corruzione_e_trasparenza',
		'atti_generali',
		'organi_di_indirizzo_politico_amministativo',
		'sanzioni_mancata_comunicazione_dati',
		'articolazione_uffici',
		'telefono_e_posta_elettronica',
		'titolari_incarichi_collaborazione_consulenza',
		'incarichi_amministrativi_di_vertice',
		'titolari_incarichi_dirigenziali_non_generali',
		'dirigenti_cessati',
		'dotazione_organica',
		'tassi_di_assenza',
		'incarichi_conferiti_e_autorizzati_ai_dipendenti',
		'contrattazione_collettiva',
		'contrattazione_integrativa',
		'ammontare_complessivo_dei_premi',
		'dati_relativi_ai_premi',
		'societa_partecipate',
		'enti_diritto_privato_controllati',
		'rappresentazione_grafica',
		'tipologie_di_procedimento',
		'provvedimenti_organo_indirizzo_politico',
		'provvedimenti_dirigenti_amministrativi',
		'criteri_e_modalita',
		'atti_di_concessione',
		'bilancio_preventivo_e_consuntivo',
		'patrimonio_immobiliare',
		'canoni_locazione_o_affitto',
		'nuclei_di_valutazione',
		'organi_revisione_amministrativa_e_contabile',
		'corte_dei_conti',
		'servizi_e_standard_di_qualita',
		'class_action',
		'costi_contabilizzati',
		'liste_di_attesa',
		'servizi_in_rete',
		'dati_sui_pagamenti',
		'tempestivita_dei_pagamenti',
		'IBAN_e_pagamenti_informatici',
		'atti_programmazione_opere_pubbliche',
		'tempi_costi_opere_pubbliche',
		'prevenzione_della_corruzione',
		'accesso_civico',
		'accessibilita_e_catalogo_dati',
		'dati_ulteriori',
	);

	/**
	 * L'albero distribuito.
	 *
	 * @return Alberatura
	 */
	private function albero(): Alberatura {
		$albero = Alberatura::carica( FILE_ALBERATURA );

		$this->assertInstanceOf( Alberatura::class, $albero, is_wp_error( $albero ) ? $albero->get_error_message() : '' );

		return $albero;
	}

	/**
	 * Nomi dei figli di una voce, nell'ordine del file.
	 *
	 * @param Alberatura $albero Albero.
	 * @param string     $id     Identificativo.
	 * @return array<int, string>
	 */
	private function nomi_figli( Alberatura $albero, string $id ): array {
		return array_map(
			static function ( string $figlio ) use ( $albero ): string {
				return $albero->voce( $figlio )['nome'];
			},
			$albero->figli( $id )
		);
	}

	/**
	 * Riga T-20: il file distribuito supera i controlli del motore.
	 */
	public function test_albero_distribuito_si_carica(): void {
		$albero = $this->albero();

		$this->assertSame( 'amministrazione_trasparente', $albero->radice() );
		$this->assertSame( 'Amministrazione trasparente', $albero->voce( $albero->radice() )['nome'] );
	}

	/**
	 * Riga T-21: 23 voci di primo livello, con i nomi e nell'ordine della norma.
	 */
	public function test_primo_livello(): void {
		$albero = $this->albero();

		$this->assertCount( 23, self::PRIMO_LIVELLO );
		$this->assertSame( self::PRIMO_LIVELLO, $this->nomi_figli( $albero, $albero->radice() ) );
	}

	/**
	 * Riga T-22: la voce dell'art. 23-bis esiste e quella vecchia no.
	 */
	public function test_controlli_sulle_attivita_economiche(): void {
		$albero = $this->albero();
		$nomi   = array();

		foreach ( $albero->identificativi() as $id ) {
			$nomi[ $albero->voce( $id )['nome'] ] = $id;
		}

		$this->assertArrayNotHasKey( 'Controlli sulle imprese', $nomi );
		$this->assertArrayHasKey( 'Controlli sulle attività economiche', $nomi );
		$this->assertStringContainsString( 'Art. 23-bis', $albero->voce( $nomi['Controlli sulle attività economiche'] )['riferimento'] );
	}

	/**
	 * Riga T-23: secondo livello voce per voce, e nessun terzo livello.
	 */
	public function test_secondo_livello(): void {
		$albero = $this->albero();

		foreach ( $albero->figli( $albero->radice() ) as $primo ) {
			$nome = $albero->voce( $primo )['nome'];

			$this->assertSame( self::SECONDO_LIVELLO[ $nome ] ?? array(), $this->nomi_figli( $albero, $primo ), $nome );

			foreach ( $albero->figli( $primo ) as $secondo ) {
				$this->assertSame( array(), $albero->figli( $secondo ), $secondo );
			}
		}

		$this->assertCount( 1 + 23 + 65, $albero->identificativi() );
	}

	/**
	 * Riga T-24: gli identificativi coincidono con quelli del modello at_map.
	 */
	public function test_identificativi_at_map(): void {
		$albero = $this->albero();

		$this->assertCount( 68, self::IDENTIFICATIVI_AT_MAP );

		foreach ( self::IDENTIFICATIVI_AT_MAP as $id ) {
			$this->assertTrue( $albero->esiste( $id ), $id );
		}
	}

	/**
	 * Riga T-25: dicitura obbligata e fonti dichiarate.
	 */
	public function test_dicitura_e_fonti(): void {
		$albero = $this->albero();

		$this->assertSame( "L'obbligo di pubblicazione non è applicabile all'amministrazione", $albero->dicitura( 'non_applicabile' ) );

		$dati  = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$fonti = array();

		$this->assertIsArray( $dati['fonti'] ?? null );

		foreach ( $dati['fonti'] as $fonte ) {
			$this->assertNotEmpty( $fonte['titolo'] ?? '' );
			$this->assertNotEmpty( $fonte['url'] ?? $fonte['estremi'] ?? '' );
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $fonte['consultata'] ?? '' );
			$fonti[ $fonte['chiave'] ] = true;
		}

		foreach ( array( 'allegato_a', 'anac_1310_2016_all_1', 'art_23_bis', 'anac_at_map' ) as $chiave ) {
			$this->assertArrayHasKey( $chiave, $fonti, $chiave );
		}

		foreach ( Alberatura::DICITURE as $stato ) {
			$this->assertNotEmpty( $dati['fonte_diciture'][ $stato ] ?? '', $stato );
		}
	}

	/**
	 * Riga T-55: il responsabile della trasparenza sta in "Prevenzione della Corruzione", sotto "Altri contenuti", con la fonte dichiarata.
	 *
	 * La collocazione e quella dell'Allegato 1 della delibera ANAC 1310/2016,
	 * dove la riga del responsabile sta nella sottosezione "Prevenzione della
	 * Corruzione" di "Altri contenuti".
	 */
	public function test_t55_collocazione_del_responsabile(): void {
		$albero = $this->albero();
		$voce   = $albero->collocazione( 'responsabile_trasparenza' );

		$this->assertNotNull( $voce );
		$this->assertSame( 'Prevenzione della Corruzione', $albero->voce( $voce )['nome'] );
		$this->assertSame( 'Altri contenuti', $albero->voce( $albero->percorso( $voce )[1] )['nome'] );

		$dati   = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$chiavi = wp_list_pluck( $dati['fonti'], 'chiave' );

		$this->assertSame( 'anac_1310_2016_all_1', $dati['fonte_collocazioni']['responsabile_trasparenza'] ?? null );
		$this->assertContains( $dati['fonte_collocazioni']['responsabile_trasparenza'], $chiavi, 'La fonte citata deve essere fra le fonti del file.' );
	}

	/**
	 * Riga T-55: la collocazione e arrivata con una versione nuova dell'albero, e la versione di prima resta com'era.
	 *
	 * Uno stesso identificativo di versione non puo indicare due alberi
	 * diversi: la versione 2026-10-24 resta nel suo file, byte per byte, e la
	 * versione 2026-10-28, che ha portato le collocazioni, ha un identificativo
	 * suo. L'impronta attesa e scritta qui, cosi una modifica al file vecchio fa
	 * diventare rossa la prova.
	 */
	public function test_t55_versione_nuova_accanto_alla_vecchia(): void {
		$vecchio = dirname( FILE_ALBERATURA ) . '/2026-10-24.json';

		$this->assertFileExists( $vecchio );
		$this->assertSame( 'cd6122a1657922a329a98fa8a017939b2104143ee2bc88f058fb879e750f3f60', hash_file( 'sha256', $vecchio ), 'La versione 2026-10-24 non si modifica.' );

		$nuovo = dirname( FILE_ALBERATURA ) . '/2026-10-28.json';
		$prima = json_decode( (string) file_get_contents( $vecchio ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$ora   = json_decode( (string) file_get_contents( $nuovo ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		$this->assertSame( '2026-10-24', $prima['versione'] );
		$this->assertArrayNotHasKey( 'collocazioni', $prima );
		$this->assertSame( '2026-10-28', $ora['versione'], 'La versione che porta le collocazioni ha un identificativo suo.' );

		unset( $ora['versione'], $ora['collocazioni'], $ora['fonte_collocazioni'], $prima['versione'] );

		$this->assertSame( $prima, $ora, 'La versione nuova aggiunge le collocazioni e non cambia altro.' );
		$this->assertInstanceOf( Alberatura::class, Alberatura::carica( $vecchio ), 'La versione vecchia resta leggibile dal motore.' );
	}

	/**
	 * Riga T-63: la durata della pubblicazione sta nell'albero in vigore, con la sua fonte.
	 *
	 * Cinque anni dall'art. 8, c. 3, del d.lgs. 33/2013. Il numero atteso e
	 * scritto qui e non letto dal file.
	 */
	public function test_t63_durata_nell_albero(): void {
		$this->assertSame( 5, $this->albero()->durata_anni() );

		$dati   = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$fonti  = array_column( $dati['fonti'], null, 'chiave' );
		$chiave = $dati['fonte_durata'] ?? '';

		$this->assertSame( 'dlgs_33_2013_art_8', $chiave );
		$this->assertArrayHasKey( $chiave, $fonti, 'La fonte citata deve essere fra le fonti del file.' );
		$this->assertStringContainsString( 'art. 8, c. 3', $fonti[ $chiave ]['titolo'] );
		$this->assertStringContainsString( '~art8', $fonti[ $chiave ]['url'] );
	}

	/**
	 * Riga T-63: la durata e arrivata con la versione 2026-10-28-2, e la 2026-10-28 resta com'era.
	 *
	 * Il nome della versione nuova e il giorno seguito da un numero, perche
	 * il giorno aveva gia una versione distribuita. La 2026-10-28-2 non e piu
	 * quella in vigore, e si guarda per nome.
	 */
	public function test_t63_versione_nuova_accanto_alla_vecchia(): void {
		$vecchio = dirname( FILE_ALBERATURA ) . '/2026-10-28.json';
		$durata  = dirname( FILE_ALBERATURA ) . '/2026-10-28-2.json';

		$this->assertSame( 'fdd8b013e96999e5499ca6470ebedb10b8ad8b7cabea206dc271ff9bda4a874d', hash_file( 'sha256', $vecchio ), 'La versione 2026-10-28 non si modifica.' );

		$prima = json_decode( (string) file_get_contents( $vecchio ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$ora   = json_decode( (string) file_get_contents( $durata ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		$this->assertSame( '2026-10-28-2', $ora['versione'], 'Il nome del file dice la versione.' );
		$this->assertArrayNotHasKey( 'durata_pubblicazione', $prima );
		$this->assertNull( Alberatura::carica( $vecchio )->durata_anni(), 'La versione vecchia resta leggibile, senza durata.' );

		$nuova          = array_values( array_filter( $ora['fonti'], static fn( array $fonte ): bool => 'dlgs_33_2013_art_8' !== $fonte['chiave'] ) );
		$ora['fonti']   = $nuova;
		$prima_versione = $prima['versione'];

		unset( $ora['versione'], $ora['durata_pubblicazione'], $ora['fonte_durata'], $prima['versione'] );

		$this->assertSame( '2026-10-28', $prima_versione );
		$this->assertSame( $prima, $ora, 'La versione nuova aggiunge la durata e la sua fonte e non cambia altro.' );
	}

	/**
	 * Riga T-63: [attacco] durate non valide nel file.
	 */
	public function test_t63_durata_non_valida(): void {
		$dati = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		foreach ( array(
			0,
			-5,
			'5',
			5.0,
			2.5,
			null,
			array(),
			array( 'anni' => '5' ),
			array( 'anni' => 0 ),
			array( 'anni' => true ),
			array( 'mesi' => 60 ),
			array(
				'anni' => 5,
				'mesi' => 2,
			),
		) as $durata ) {
			$prova = $dati;

			$prova['durata_pubblicazione'] = is_array( $durata ) ? $durata : array( 'anni' => $durata );

			$esito = Alberatura::da_dati( $prova );

			$this->assertWPError( $esito, wp_json_encode( $durata ) );
			$this->assertSame( 'trasparenza_albero_durata', $esito->get_error_code() );
		}

		unset( $dati['durata_pubblicazione'] );
		$this->assertNull( Alberatura::da_dati( $dati )->durata_anni(), 'Senza durata il file si carica, e la durata non c\'e.' );
	}

	/**
	 * Riga T-75: le voci coperte da un modulo stanno nell'albero in vigore, con la loro fonte.
	 *
	 * L'elenco atteso e scritto qui e non letto dal file: una voce tolta o
	 * aggiunta nel file fa diventare rossa la prova.
	 */
	public function test_t75_voci_coperte_da_un_modulo(): void {
		$albero   = $this->albero();
		$attese   = array(
			'organi_di_indirizzo_politico_amministativo'  => 'art_13',
			'articolazione_uffici'                        => 'art_13',
			'telefono_e_posta_elettronica'                => 'art_13',
			'nuclei_di_valutazione'                       => 'art_31',
			'organi_revisione_amministrativa_e_contabile' => 'art_31',
			'corte_dei_conti'                             => 'art_31',
			'dati_sui_pagamenti'                          => 'art_4_bis',
		);
		$articoli = array(
			'art_13'    => 'Art. 13, ',
			'art_31'    => 'Art. 31, ',
			'art_4_bis' => 'Art. 4-bis, ',
		);
		$trovate  = array();

		foreach ( $albero->identificativi() as $voce ) {
			$modulo = $albero->modulo( $voce );

			if ( null !== $modulo ) {
				$trovate[ $voce ] = $modulo['obbligo'];
				$this->assertStringContainsString( $articoli[ $modulo['obbligo'] ], (string) $albero->voce( $voce )['riferimento'], 'Il riferimento della voce cita l\'articolo del modulo: ' . $voce );
			}
		}

		ksort( $attese );
		ksort( $trovate );
		$this->assertSame( $attese, $trovate );
		$this->assertSame( 'art. 13 del d.lgs. 33/2013', $albero->modulo( 'articolazione_uffici' )['nome'] );
		$this->assertSame( 'art. 4-bis del d.lgs. 33/2013', $albero->modulo( 'dati_sui_pagamenti' )['nome'] );

		$catalogo = \TrasparenzaPa\CatalogoSchemi::carica( \TrasparenzaPa\FILE_CATALOGO_SCHEMI );

		foreach ( array_unique( $attese ) as $obbligo ) {
			$this->assertNotSame( array(), $catalogo->versioni( $obbligo ), 'Ogni modulo dell\'albero ha versioni nel catalogo: ' . $obbligo );
		}

		$dati   = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$fonti  = array_column( $dati['fonti'], null, 'chiave' );
		$chiave = $dati['fonte_moduli'] ?? '';

		$this->assertSame( 'anac_schemi_pubblicazione', $chiave );
		$this->assertArrayHasKey( $chiave, $fonti, 'La fonte citata deve essere fra le fonti del file.' );
		$this->assertStringContainsString( 'guida-servizi.anticorruzione.it', $fonti[ $chiave ]['url'] );
	}

	/**
	 * Riga T-75: l'elenco e arrivato con la versione 2026-10-29, in vigore, e la 2026-10-28-2 resta com'era.
	 */
	public function test_t75_versione_nuova_accanto_alla_vecchia(): void {
		$vecchio = dirname( FILE_ALBERATURA ) . '/2026-10-28-2.json';

		$this->assertSame( '11b6171e28bc0b01cbcc97fc436da45826dbc415723945a0d08ac388202d39c4', hash_file( 'sha256', $vecchio ), 'La versione 2026-10-28-2 non si modifica.' );
		$this->assertSame( '2026-10-29.json', basename( FILE_ALBERATURA ), 'La versione in vigore e la 2026-10-29.' );

		$prima = json_decode( (string) file_get_contents( $vecchio ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$ora   = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		$this->assertSame( '2026-10-29', $ora['versione'], 'Il nome del file dice la versione.' );
		$this->assertArrayNotHasKey( 'moduli', $prima );
		$this->assertNull( Alberatura::carica( $vecchio )->modulo( 'articolazione_uffici' ), 'La versione vecchia resta leggibile, senza moduli.' );

		$ora['fonti'] = array_values( array_filter( $ora['fonti'], static fn( array $fonte ): bool => 'anac_schemi_pubblicazione' !== $fonte['chiave'] ) );

		unset( $ora['versione'], $ora['moduli'], $ora['fonte_moduli'], $prima['versione'] );

		$this->assertSame( $prima, $ora, 'La versione nuova aggiunge l\'elenco e la sua fonte e non cambia altro.' );
	}

	/**
	 * Riga T-76: [attacco] elenchi delle voci coperte da un modulo non validi nel file.
	 */
	public function test_t76_moduli_non_validi(): void {
		$dati  = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$buono = array(
			'nome' => 'art. 13 del d.lgs. 33/2013',
			'voci' => array( 'articolazione_uffici' ),
		);

		$casi = array(
			'non un elenco'         => 'art_13',
			'elenco senza nomi'     => array( $buono ),
			'identificativo strano' => array( 'Art 13' => $buono ),
			'identificativo lungo'  => array( str_repeat( 'a', 81 ) => $buono ),
			'modulo non elenco'     => array( 'art_13' => 'articolazione_uffici' ),
			'senza nome'            => array( 'art_13' => array( 'voci' => $buono['voci'] ) ),
			'senza voci'            => array( 'art_13' => array( 'nome' => $buono['nome'] ) ),
			'chiave in piu'         => array( 'art_13' => $buono + array( 'dal' => '2026-06-10' ) ),
			'nome vuoto'            => array( 'art_13' => array( 'nome' => '  ' ) + $buono ),
			'nome non testo'        => array( 'art_13' => array( 'nome' => 13 ) + $buono ),
			'nome troppo lungo'     => array( 'art_13' => array( 'nome' => str_repeat( 'a', 201 ) ) + $buono ),
			'voci vuote'            => array( 'art_13' => array( 'voci' => array() ) + $buono ),
			'voci con nomi'         => array( 'art_13' => array( 'voci' => array( 'x' => 'articolazione_uffici' ) ) + $buono ),
			'voce non testo'        => array( 'art_13' => array( 'voci' => array( 13 ) ) + $buono ),
			'voce inesistente'      => array( 'art_13' => array( 'voci' => array( 'voce_inesistente' ) ) + $buono ),
			'radice'                => array( 'art_13' => array( 'voci' => array( 'amministrazione_trasparente' ) ) + $buono ),
			'voce con sottovoci'    => array( 'art_13' => array( 'voci' => array( 'organizzazione' ) ) + $buono ),
			'voce ripetuta'         => array( 'art_13' => array( 'voci' => array( 'articolazione_uffici', 'articolazione_uffici' ) ) + $buono ),
			'voce in due moduli'    => array(
				'art_13' => $buono,
				'art_31' => $buono,
			),
		);

		foreach ( $casi as $nome => $moduli ) {
			$prova           = $dati;
			$prova['moduli'] = $moduli;

			$esito = Alberatura::da_dati( $prova );

			$this->assertWPError( $esito, $nome );
			$this->assertSame( 'trasparenza_albero_moduli', $esito->get_error_code(), $nome );
		}

		$prova           = $dati;
		$prova['moduli'] = array( 'art_13' => array( 'nome' => "  art. 13\n" ) + $buono );
		$this->assertSame( 'art. 13', Alberatura::da_dati( $prova )->modulo( 'articolazione_uffici' )['nome'], 'Il nome si ripulisce dagli spazi ai bordi.' );

		$prova['moduli'] = array();
		$this->assertNull( Alberatura::da_dati( $prova )->modulo( 'articolazione_uffici' ), 'Un elenco vuoto si accetta.' );

		unset( $dati['moduli'] );
		$this->assertNull( Alberatura::da_dati( $dati )->modulo( 'articolazione_uffici' ), 'Senza elenco il file si carica, e nessuna voce ha un modulo.' );
	}
}
