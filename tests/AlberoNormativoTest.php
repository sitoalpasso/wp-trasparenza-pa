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
	 * versione in vigore ha un identificativo suo. L'impronta attesa e scritta
	 * qui, cosi una modifica al file vecchio fa diventare rossa la prova.
	 */
	public function test_t55_versione_nuova_accanto_alla_vecchia(): void {
		$vecchio = dirname( FILE_ALBERATURA ) . '/2026-10-24.json';

		$this->assertFileExists( $vecchio );
		$this->assertSame( 'cd6122a1657922a329a98fa8a017939b2104143ee2bc88f058fb879e750f3f60', hash_file( 'sha256', $vecchio ), 'La versione 2026-10-24 non si modifica.' );

		$prima = json_decode( (string) file_get_contents( $vecchio ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$ora   = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		$this->assertSame( '2026-10-24', $prima['versione'] );
		$this->assertArrayNotHasKey( 'collocazioni', $prima );
		$this->assertNotSame( $prima['versione'], $ora['versione'], 'La versione in vigore ha un identificativo suo.' );
		$this->assertSame( $ora['versione'] . '.json', basename( FILE_ALBERATURA ), 'Il nome del file dice la versione.' );

		unset( $ora['versione'], $ora['collocazioni'], $ora['fonte_collocazioni'], $prima['versione'] );

		$this->assertSame( $prima, $ora, 'La versione nuova aggiunge le collocazioni e non cambia altro.' );
		$this->assertInstanceOf( Alberatura::class, Alberatura::carica( $vecchio ), 'La versione vecchia resta leggibile dal motore.' );
	}
}
