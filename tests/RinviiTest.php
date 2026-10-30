<?php
/**
 * Prove dei rinvii alle banche dati nazionali: righe T-92 e T-93.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Alberatura;
use WP_UnitTestCase;

use const TrasparenzaPa\FILE_ALBERATURA;

/**
 * L'elenco delle banche dati nel file dell'albero.
 */
class RinviiTest extends WP_UnitTestCase {

	/**
	 * L'albero in vigore.
	 *
	 * @return Alberatura
	 */
	private function albero(): Alberatura {
		$albero = Alberatura::carica( FILE_ALBERATURA );

		$this->assertInstanceOf( Alberatura::class, $albero );

		return $albero;
	}

	/**
	 * I dati del file dell'albero in vigore.
	 *
	 * @return array<string, mixed>
	 */
	private function dati(): array {
		return json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
	}

	/**
	 * Riga T-92: le banche dati dell'albero in vigore, con titolare, voci e fonte.
	 *
	 * L'elenco atteso e scritto qui e non letto dal file: una banca dati o una
	 * voce tolta o aggiunta nel file fa diventare rossa la prova.
	 */
	public function test_t92_banche_dati_nell_albero(): void {
		$albero = $this->albero();
		$attese = array(
			'perla_pa'                     => array( 'Perla PA', 'PCM-DFP', array( 'titolari_incarichi_collaborazione_consulenza', 'personale_non_tempo_indeterminato', 'incarichi_conferiti_e_autorizzati_ai_dipendenti' ) ),
			'sico'                         => array( 'SICO - Sistema Conoscitivo del personale dipendente dalle Amministrazioni pubbliche', 'MEF-RGS (IGOP)', array( 'dotazione_organica', 'personale_non_tempo_indeterminato', 'contrattazione_collettiva', 'contrattazione_integrativa' ) ),
			'archivio_contratti'           => array( 'Archivio contratti del settore pubblico', 'ARAN CNEL', array( 'contrattazione_collettiva', 'contrattazione_integrativa' ) ),
			'siquel'                       => array( 'SIQuEL - Sistema Informativo Questionari Enti Locali', 'Corte dei conti', array( 'enti_pubblici_vigilati', 'societa_partecipate', 'enti_diritto_privato_controllati' ) ),
			'patrimonio_pa'                => array( 'Patrimonio della PA', 'MEF-DT', array( 'enti_pubblici_vigilati', 'societa_partecipate', 'enti_diritto_privato_controllati', 'patrimonio_immobiliare', 'canoni_locazione_o_affitto' ) ),
			'rendiconti_gruppi_consiliari' => array( 'Rendiconti dei gruppi consiliari regionali', 'Corte dei conti', array( 'rendiconti_gruppi_consiliari' ) ),
			'bdap'                         => array( 'BDAP - Banca Dati Amministrazioni Pubbliche', 'MEF-RGS', array( 'bilancio_preventivo_e_consuntivo', 'informazioni_singole_procedure', 'atti_amministrazioni_aggiudicatrici', 'nuclei_valutazione_investimenti_pubblici', 'atti_programmazione_opere_pubbliche', 'tempi_costi_opere_pubbliche' ) ),
			'rems'                         => array( 'REMS (Real Estate Management System) - Sistema di Gestione degli Immobili di Proprietà Statale', 'Demanio', array( 'patrimonio_immobiliare', 'canoni_locazione_o_affitto' ) ),
			'bdncp'                        => array( 'BDNCP - Banca Dati Nazionale Contratti Pubblici', 'ANAC', array( 'informazioni_singole_procedure', 'atti_amministrazioni_aggiudicatrici' ) ),
			'servizio_contratti_pubblici'  => array( 'Servizio Contratti Pubblici', 'MIT', array( 'informazioni_singole_procedure', 'atti_amministrazioni_aggiudicatrici' ) ),
			'soldi_pubblici'               => array( 'Soldi pubblici', null, array( 'dati_sui_pagamenti' ) ),
		);

		$trovate = array();

		foreach ( $albero->identificativi() as $voce ) {
			foreach ( $albero->rinvii( $voce ) as $rinvio ) {
				$trovate[ $rinvio['banca'] ][] = $voce;
			}
		}

		$per_voce = array();

		foreach ( $attese as $banca => $attesa ) {
			$rinvio = $albero->banca_dati( $banca );

			$this->assertIsArray( $rinvio, $banca );
			$this->assertSame( $attesa[0], $rinvio['nome'], $banca );
			$this->assertSame( $attesa[1], $rinvio['titolare'], $banca );

			$voci = $attesa[2];
			sort( $voci );
			$avute = $trovate[ $banca ] ?? array();
			sort( $avute );
			$this->assertSame( $voci, $avute, 'Voci della banca dati ' . $banca );

			foreach ( $attesa[2] as $voce ) {
				$per_voce[ $voce ][] = $banca;
			}
		}

		$banche_trovate = array_keys( $trovate );
		$banche_attese  = array_keys( $attese );
		sort( $banche_trovate );
		sort( $banche_attese );
		$this->assertSame( $banche_attese, $banche_trovate, 'Nessuna banca dati in piu, nessuna in meno.' );

		foreach ( $per_voce as $voce => $banche ) {
			$this->assertSame( $banche, array_column( $albero->rinvii( $voce ), 'banca' ), 'Le banche dati di una voce, nell\'ordine del file: ' . $voce );
		}

		$this->assertSame( 'solo enti locali', $albero->banca_dati( 'siquel' )['limite'] );
		$this->assertSame( 'solo immobili di proprietà statale', $albero->banca_dati( 'rems' )['limite'], 'Il dato di un ente non statale in REMS non c\'è.' );
		$this->assertNull( $albero->banca_dati( 'patrimonio_pa' )['limite'] );
		$this->assertSame( array(), $albero->rinvii( 'bandi_di_concorso' ), 'Art. 19: nessuna banca dati dell\'Allegato B, la voce si popola sul sito.' );
		$this->assertSame( array(), $albero->rinvii( 'rappresentazione_grafica' ), 'Lettura prudente: la banca dati non contiene una rappresentazione grafica.' );
		$this->assertSame( array(), $albero->rinvii( 'tassi_di_assenza' ), 'Art. 16, c. 3: fuori dai commi dell\'Allegato B.' );
		$this->assertSame( array(), $albero->rinvii( 'voce_inesistente' ) );
		$this->assertNull( $albero->banca_dati( 'banca_inesistente' ) );

		$dati  = $this->dati();
		$fonti = array_column( $dati['fonti'], null, 'chiave' );

		foreach ( $dati['rinvii'] as $banca => $rinvio ) {
			$attesa = 'soldi_pubblici' === $banca ? 'dl_19_2026_art_8' : 'dlgs_33_2013_allegato_b';

			$this->assertSame( $attesa, $rinvio['fonte'], $banca );
			$this->assertSame( $attesa, $albero->banca_dati( $banca )['fonte'], $banca );
		}

		foreach ( array( 'dlgs_33_2013_allegato_b', 'dlgs_33_2013_art_9_bis', 'dl_19_2026_art_8' ) as $chiave ) {
			$this->assertArrayHasKey( $chiave, $fonti, 'La fonte deve essere fra le fonti del file.' );
			$this->assertStringContainsString( 'normattiva.it', $fonti[ $chiave ]['url'] );
			$this->assertSame( '2026-10-30', $fonti[ $chiave ]['consultata'] );
		}
	}

	/**
	 * Riga T-92: l'elenco e arrivato con la versione 2026-10-30, in vigore, e la 2026-10-29-2 resta com'era.
	 */
	public function test_t92_versione_nuova_accanto_alla_vecchia(): void {
		$vecchio = dirname( FILE_ALBERATURA ) . '/2026-10-29-2.json';

		$this->assertSame( 'f18fdaa75319e612dd9656157115aef7ec96a774cb1fa0af31ef78fbea0e6470', hash_file( 'sha256', $vecchio ), 'La versione 2026-10-29-2 non si modifica.' );
		$this->assertSame( '2026-10-30.json', basename( FILE_ALBERATURA ), 'La versione in vigore e la 2026-10-30.' );

		$prima = json_decode( (string) file_get_contents( $vecchio ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.
		$ora   = $this->dati();

		$this->assertSame( '2026-10-30', $ora['versione'], 'Il nome del file dice la versione.' );
		$this->assertArrayNotHasKey( 'rinvii', $prima );
		$this->assertSame( array(), Alberatura::carica( $vecchio )->rinvii( 'dotazione_organica' ), 'La versione vecchia resta leggibile, senza rinvii.' );

		$ora['fonti'] = array_values( array_filter( $ora['fonti'], static fn( array $fonte ): bool => ! in_array( $fonte['chiave'], array( 'dlgs_33_2013_allegato_b', 'dlgs_33_2013_art_9_bis', 'dl_19_2026_art_8' ), true ) ) );

		unset( $ora['versione'], $ora['rinvii'], $prima['versione'] );

		$this->assertSame( $prima, $ora, 'La versione nuova aggiunge l\'elenco e le sue fonti e non cambia altro.' );
	}

	/**
	 * Riga T-93: [attacco] elenchi delle banche dati non validi nel file.
	 */
	public function test_t93_rinvii_non_validi(): void {
		$dati  = $this->dati();
		$buona = array(
			'nome'     => 'Perla PA',
			'titolare' => 'PCM-DFP',
			'fonte'    => 'dlgs_33_2013_allegato_b',
			'voci'     => array( 'titolari_incarichi_collaborazione_consulenza' ),
		);

		$casi = array(
			'non un elenco'         => 'perla_pa',
			'elenco senza nomi'     => array( $buona ),
			'identificativo strano' => array( 'Perla PA' => $buona ),
			'identificativo lungo'  => array( str_repeat( 'a', 81 ) => $buona ),
			'banca non elenco'      => array( 'perla_pa' => 'titolari_incarichi_collaborazione_consulenza' ),
			'senza nome'            => array( 'perla_pa' => array_diff_key( $buona, array( 'nome' => 0 ) ) ),
			'senza fonte'           => array( 'perla_pa' => array_diff_key( $buona, array( 'fonte' => 0 ) ) ),
			'senza voci'            => array( 'perla_pa' => array_diff_key( $buona, array( 'voci' => 0 ) ) ),
			'chiave in piu'         => array( 'perla_pa' => $buona + array( 'indirizzo' => 'https://example.org/' ) ),
			'nome vuoto'            => array( 'perla_pa' => array( 'nome' => '  ' ) + $buona ),
			'nome troppo lungo'     => array( 'perla_pa' => array( 'nome' => str_repeat( 'a', 201 ) ) + $buona ),
			'nome non testo'        => array( 'perla_pa' => array( 'nome' => 15 ) + $buona ),
			'fonte non testo'       => array( 'perla_pa' => array( 'fonte' => 15 ) + $buona ),
			'fonte assente'         => array( 'perla_pa' => array( 'fonte' => 'fonte_inesistente' ) + $buona ),
			'voci vuote'            => array( 'perla_pa' => array( 'voci' => array() ) + $buona ),
			'voci con nomi'         => array( 'perla_pa' => array( 'voci' => array( 'x' => 'dotazione_organica' ) ) + $buona ),
			'voce non testo'        => array( 'perla_pa' => array( 'voci' => array( 15 ) ) + $buona ),
			'voce inesistente'      => array( 'perla_pa' => array( 'voci' => array( 'voce_inesistente' ) ) + $buona ),
			'radice'                => array( 'perla_pa' => array( 'voci' => array( 'amministrazione_trasparente' ) ) + $buona ),
			'voce con sottovoci'    => array( 'perla_pa' => array( 'voci' => array( 'personale' ) ) + $buona ),
			'voce ripetuta'         => array( 'perla_pa' => array( 'voci' => array( 'dotazione_organica', 'dotazione_organica' ) ) + $buona ),
			'titolare vuoto'        => array( 'perla_pa' => array( 'titolare' => ' ' ) + $buona ),
			'titolare lungo'        => array( 'perla_pa' => array( 'titolare' => str_repeat( 'a', 201 ) ) + $buona ),
			'titolare non testo'    => array( 'perla_pa' => array( 'titolare' => null ) + $buona ),
			'limite vuoto'          => array( 'perla_pa' => $buona + array( 'limite' => '' ) ),
			'limite lungo'          => array( 'perla_pa' => $buona + array( 'limite' => str_repeat( 'a', 201 ) ) ),
			'limite non testo'      => array( 'perla_pa' => $buona + array( 'limite' => true ) ),
		);

		foreach ( $casi as $nome => $rinvii ) {
			$prova           = $dati;
			$prova['rinvii'] = $rinvii;

			$esito = Alberatura::da_dati( $prova );

			$this->assertWPError( $esito, $nome );
			$this->assertSame( 'trasparenza_albero_rinvii', $esito->get_error_code(), $nome );
		}

		/*
		 * Una voce con identificativo di sole cifre diventa una chiave numerica:
		 * il numero al posto del testo non deve passare per quella voce.
		 */
		$prova           = $dati;
		$prova['nodi'][] = array(
			'id'          => '15',
			'nome'        => 'Voce numerica',
			'padre'       => 'altri_contenuti',
			'riferimento' => '',
		);
		$prova['rinvii'] = array( 'perla_pa' => array( 'voci' => array( 15 ) ) + $buona );
		$esito           = Alberatura::da_dati( $prova );
		$this->assertWPError( $esito, 'Voce numero al posto del testo.' );
		$this->assertSame( 'trasparenza_albero_rinvii', $esito->get_error_code() );
		$prova['rinvii'] = array( 'perla_pa' => array( 'voci' => array( '15' ) ) + $buona );
		$this->assertSame( 'perla_pa', Alberatura::da_dati( $prova )->rinvii( '15' )[0]['banca'], 'Come testo la voce si accetta.' );

		/*
		 * Un albero fatto della sola radice: la radice non ha sottovoci, e
		 * resta esclusa lo stesso.
		 */
		$prova = array(
			'formato'  => $dati['formato'],
			'versione' => $dati['versione'],
			'diciture' => $dati['diciture'],
			'fonti'    => $dati['fonti'],
			'nodi'     => array( $dati['nodi'][0] ),
			'rinvii'   => array( 'perla_pa' => array( 'voci' => array( 'amministrazione_trasparente' ) ) + $buona ),
		);
		$this->assertSame( 'amministrazione_trasparente', $dati['nodi'][0]['id'] );
		$this->assertInstanceOf( Alberatura::class, Alberatura::da_dati( array_diff_key( $prova, array( 'rinvii' => 0 ) ) ), 'Precondizione: l\'albero della sola radice si legge.' );
		$esito = Alberatura::da_dati( $prova );
		$this->assertWPError( $esito, 'Radice senza sottovoci.' );
		$this->assertSame( 'trasparenza_albero_rinvii', $esito->get_error_code() );

		$prova           = $dati;
		$prova['rinvii'] = array(
			'perla_pa' => array(
				'nome'     => "  Perla PA\n",
				'titolare' => ' PCM-DFP ',
				'limite'   => ' solo alcuni ',
			) + $buona,
		);
		$this->assertSame(
			array(
				array(
					'banca'    => 'perla_pa',
					'nome'     => 'Perla PA',
					'titolare' => 'PCM-DFP',
					'limite'   => 'solo alcuni',
					'fonte'    => 'dlgs_33_2013_allegato_b',
				),
			),
			Alberatura::da_dati( $prova )->rinvii( 'titolari_incarichi_collaborazione_consulenza' ),
			'Nome, titolare e limite si ripuliscono dagli spazi ai bordi.'
		);

		$prova['rinvii'] = array( 'perla_pa' => array_diff_key( $buona, array( 'titolare' => 0 ) ) );
		$this->assertNull( Alberatura::da_dati( $prova )->banca_dati( 'perla_pa' )['titolare'], 'Senza titolare si accetta: l\'accesso civico resta all\'ente.' );

		$prova['rinvii'] = array();
		$this->assertSame( array(), Alberatura::da_dati( $prova )->rinvii( 'dotazione_organica' ), 'Un elenco vuoto si accetta.' );

		unset( $dati['rinvii'] );
		$this->assertSame( array(), Alberatura::da_dati( $dati )->rinvii( 'dotazione_organica' ), 'Senza elenco il file si carica, e nessuna voce ha rinvii.' );
	}
}
