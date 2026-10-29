<?php
/**
 * Prove della decorrenza e della fine della pubblicazione: righe T-64..T-66.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Blocco;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaPubblicazione;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\FILE_ALBERATURA;
use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * La data da cui decorre l'obbligo, la fine calcolata, la traccia, la visibilita.
 */
class PubblicazioneTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * File temporanei da togliere a fine prova.
	 *
	 * @var array<int, string>
	 */
	private $temporanei = array();

	/**
	 * Sezione e tipi registrati, permessi assegnati, albero in vigore, amministratore corrente.
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

		$this->albero_in_uso( FILE_ALBERATURA );

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Toglie i guasti e i file temporanei.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'update_post_metadata' );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		foreach ( $this->temporanei as $file ) {
			wp_delete_file( $file );
		}

		parent::tear_down();
	}

	/**
	 * Mette in uso un file dell'albero.
	 *
	 * @param string $file Percorso.
	 */
	private function albero_in_uso( string $file ): void {
		PaginaPubblica::fissa_file( $file );
		PaginaPubblica::da_init();
	}

	/**
	 * Mette in uso l'albero distribuito con un'altra durata, o senza.
	 *
	 * @param mixed $durata Valore di `durata_pubblicazione`; nullo per toglierla.
	 */
	private function albero_con_durata( $durata ): void {
		$dati = json_decode( (string) file_get_contents( FILE_ALBERATURA ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		if ( null === $durata ) {
			unset( $dati['durata_pubblicazione'] );
		} else {
			$dati['durata_pubblicazione'] = $durata;
		}

		$file = wp_tempnam( 'albero' );

		file_put_contents( $file, wp_json_encode( $dati ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- file temporaneo della prova.
		$this->temporanei[] = $file;
		$this->albero_in_uso( $file );
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
	 * Una richiesta come la manderebbe il riquadro.
	 *
	 * @param int        $contenuto Contenuto.
	 * @param mixed      $data      Data scritta.
	 * @param mixed|null $partenza  Data mostrata all'apertura; se non indicata, quella attuale.
	 * @return array<string, mixed>
	 */
	private function richiesta( int $contenuto, $data, $partenza = null ): array {
		$valori  = get_post_meta( $contenuto, SchedaPubblicazione::META_DECORRENZA, false );
		$effetti = get_post_meta( $contenuto, SchedaPubblicazione::META_EFFETTI, false );
		$effetti = 1 === count( $effetti ) ? (string) $effetti[0] : '';

		return array(
			SchedaPubblicazione::CAMPO_NONCE               => wp_create_nonce( SchedaPubblicazione::azione_nonce( $contenuto ) ),
			SchedaPubblicazione::CAMPO                     => $data,
			SchedaPubblicazione::CAMPO_PARTENZA            => $partenza ?? ( 1 === count( $valori ) ? (string) $valori[0] : '' ),
			SchedaPubblicazione::CAMPO_EFFETTI             => $effetti,
			SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA    => $effetti,
			SchedaPubblicazione::CAMPO_CESSAZIONE          => '',
			SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA => '',
		);
	}

	/**
	 * Le voci di registro della pubblicazione di un contenuto.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce( int $contenuto ): array {
		return conformita_core_voci_registro(
			array(
				'contenuto' => $contenuto,
				'azione'    => SchedaPubblicazione::AZIONE_REGISTRO,
			)
		);
	}

	/**
	 * Le decorrenze memorizzate, tutte.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>
	 */
	private function decorrenze( int $contenuto ): array {
		return array_map( 'strval', get_post_meta( $contenuto, SchedaPubblicazione::META_DECORRENZA, false ) );
	}

	/**
	 * Le fini della pubblicazione memorizzate, tutte.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>
	 */
	private function fini( int $contenuto ): array {
		return array_map( 'strval', get_post_meta( $contenuto, conformita_core_chiave_fine_pubblicazione(), false ) );
	}

	/**
	 * Rende fallita la lettura diretta di un metadato.
	 *
	 * @param string $chiave Chiave del metadato.
	 */
	private function guasta_lettura( string $chiave ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb, $chiave ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id" ) && false !== strpos( $sql, "'" . $chiave . "'" )
					? 'SELECT meta_value FROM ' . $wpdb->postmeta . '_inesistente'
					: $sql;
			}
		);
	}

	/**
	 * T-64: la fine e il 31 dicembre dell'anno di decorrenza piu gli anni, casi a cavallo d'anno compresi.
	 */
	public function test_t64_fine_di_legge(): void {
		$casi = array(
			array( '2026-03-10', 5, '2031-12-31' ),
			array( '2026-01-01', 5, '2031-12-31' ),
			array( '2026-12-31', 5, '2031-12-31' ),
			array( '2025-12-31', 5, '2030-12-31' ),
			array( '2024-02-29', 5, '2029-12-31' ),
			array( '2026-06-15', 1, '2027-12-31' ),
			array( '2026-06-15', 7, '2033-12-31' ),
			array( '9994-01-01', 5, '9999-12-31' ),
		);

		foreach ( $casi as list( $decorrenza, $anni, $attesa ) ) {
			$this->assertSame( $attesa, SchedaPubblicazione::fine_di_legge( $decorrenza, $anni ), $decorrenza . ' + ' . $anni );
		}

		$this->assertNull( SchedaPubblicazione::fine_di_legge( '9995-01-01', 5 ), 'Oltre l\'anno 9999 non c\'e una fine.' );
	}

	/**
	 * T-64: [attacco] solo date vere del calendario, scritte AAAA-MM-GG e nient'altro.
	 */
	public function test_t64_data_valida(): void {
		foreach ( array( '2026-03-10', '2024-02-29', '0001-01-01' ) as $data ) {
			$this->assertTrue( SchedaPubblicazione::data_valida( $data ), $data );
		}

		$attacchi = array( '2026-02-30', '2025-02-29', '2026-13-01', '2026-00-10', '2026-01-00', '2026-2-3', '20260203', '2026/02/03', '+2026-02-03', ' 2026-02-03', '2026-02-03 ', "2026-02-03\n", "2026-02-03\0", '2026-02-03T00:00', '', 'oggi', 20260203, null, array( '2026-02-03' ) );

		foreach ( $attacchi as $data ) {
			$this->assertFalse( SchedaPubblicazione::data_valida( $data ), (string) wp_json_encode( $data ) );
		}
	}

	/**
	 * T-64: gli anni vengono dall'albero in vigore, non dal codice.
	 */
	public function test_t64_durata_dall_albero(): void {
		$this->albero_con_durata( array( 'anni' => 2 ) );

		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$this->assertSame( array( '2028-12-31' ), $this->fini( $contenuto ) );
	}

	/**
	 * T-65: il riquadro, con e senza data.
	 */
	public function test_t65_riquadro(): void {
		$contenuto = $this->contenuto();
		$riquadro  = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_NONCE . '"', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_PARTENZA . '" value=""', $riquadro );
		$this->assertStringContainsString( 'type="date"', $riquadro );
		$this->assertStringContainsString( 'non compare al pubblico', $riquadro );
		$this->assertStringContainsString( 'dura 5 anni', $riquadro );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_PARTENZA . '" value="2026-03-10"', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO . '" value="2026-03-10"', $riquadro );
		$this->assertStringContainsString( 'Fine della pubblicazione: ' . wp_date( (string) get_option( 'date_format' ), ( new \DateTimeImmutable( '2031-12-31', wp_timezone() ) )->getTimestamp() ), $riquadro );
		$this->assertStringNotContainsString( 'non compare al pubblico', $riquadro );
		$this->assertStringNotContainsString( 'Salvando il contenuto si riallinea', $riquadro );

		$this->albero_con_durata( null );
		$this->assertStringContainsString( 'non dice la durata', SchedaPubblicazione::riquadro( get_post( $contenuto ) ) );
	}

	/**
	 * T-65: [attacco] un valore memorizzato con codice esce con escaping.
	 */
	public function test_t65_riquadro_con_escaping(): void {
		$contenuto = $this->contenuto();

		add_post_meta( $contenuto, SchedaPubblicazione::META_DECORRENZA, '"><script>alert(1)</script>' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringNotContainsString( '<script>', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO . '" value=""', $riquadro, 'Un valore che non e una data non si mostra come data.' );
	}

	/**
	 * T-65: data salvata, fine scritta da core, traccia nel registro; la stessa data non si registra di nuovo.
	 */
	public function test_t65_salvataggio_e_registro(): void {
		$contenuto = $this->contenuto();
		$esito     = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertStringContainsString( 'Fine della pubblicazione', $esito['messaggio'] );
		$this->assertSame( array( '2026-03-10' ), $this->decorrenze( $contenuto ) );
		$this->assertSame( '2031-12-31', conformita_core_fine_pubblicazione( $contenuto ) );

		$tracce = $this->tracce( $contenuto );

		$this->assertCount( 1, $tracce );
		$this->assertSame(
			array(
				'prima'            => '',
				'dopo'             => '2026-03-10',
				'effetti_prima'    => '',
				'effetti_dopo'     => '',
				'cessazione_prima' => '',
				'cessazione_dopo'  => '',
				'fine_prima'       => array(),
				'fine_dopo'        => '2031-12-31',
			),
			$tracce[0]['dettagli']
		);

		$this->assertSame( 'invariato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$this->assertCount( 1, $this->tracce( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2027-01-01' ) )['codice'] );
		$this->assertSame( array( '2027-01-01' ), $this->decorrenze( $contenuto ) );
		$this->assertSame( array( '2032-12-31' ), $this->fini( $contenuto ) );

		$tracce = $this->tracce( $contenuto );

		$this->assertCount( 2, $tracce );
		$dettagli = array_column( $tracce, 'dettagli' );
		$this->assertContains(
			array(
				'prima'            => '2026-03-10',
				'dopo'             => '2027-01-01',
				'effetti_prima'    => '',
				'effetti_dopo'     => '',
				'cessazione_prima' => '',
				'cessazione_dopo'  => '',
				'fine_prima'       => array( '2031-12-31' ),
				'fine_dopo'        => '2032-12-31',
			),
			$dettagli
		);
	}

	/**
	 * T-65: [attacco] date non valide, cancellazione, richieste incomplete, nonce, tipo, permesso.
	 */
	public function test_t65_rifiuti(): void {
		$contenuto = $this->contenuto();
		$altro     = $this->contenuto();

		foreach ( array( '2026-02-30', '2026-2-3', '2026-02-03 ', "2026-02-03\n", '2026-02-03<b>', '9996-01-01' ) as $data ) {
			$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $data ) )['codice'], $data );
		}

		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, array( '2026-03-10' ) ) )['codice'], 'Data non testuale.' );
		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', array() ) )['codice'], 'Partenza non testuale.' );

		$senza_partenza = $this->richiesta( $contenuto, '2026-03-10' );
		unset( $senza_partenza[ SchedaPubblicazione::CAMPO_PARTENZA ] );
		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $senza_partenza )['codice'], 'Partenza mancante.' );

		$senza_nonce = $this->richiesta( $contenuto, '2026-03-10' );
		unset( $senza_nonce[ SchedaPubblicazione::CAMPO_NONCE ] );
		$this->assertSame( 'nonce', SchedaPubblicazione::applica( $contenuto, $senza_nonce )['codice'] );
		$this->assertSame( 'nonce', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $altro, '2026-03-10' ) )['codice'], 'Nonce di un altro contenuto.' );

		$voce_di_trasparenza = (int) self::factory()->post->create( array( 'post_type' => TIPO_VOCE ) );
		$this->assertSame( 'permesso', SchedaPubblicazione::applica( $voce_di_trasparenza, $this->richiesta( $voce_di_trasparenza, '2026-03-10' ) )['codice'], 'Un contenuto di un altro tipo.' );

		$amministratore = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 'permesso', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		wp_set_current_user( $amministratore );

		$this->assertSame( array(), $this->decorrenze( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ) );
		$this->assertSame( array(), get_post_meta( $voce_di_trasparenza, SchedaPubblicazione::META_DECORRENZA, false ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );

		$this->assertSame( 'invariato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '' ) )['codice'], 'Nessuna data scritta e nessuna mostrata.' );
		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$this->assertSame( 'cancellata', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '' ) )['codice'], 'La data scritta non si toglie.' );
		$this->assertSame( array( '2026-03-10' ), $this->decorrenze( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ) );
		$this->assertCount( 1, $this->tracce( $contenuto ) );
	}

	/**
	 * T-65: [attacco] due moduli aperti insieme: il secondo che cambia la data e un conflitto.
	 */
	public function test_t65_due_moduli(): void {
		$contenuto = $this->contenuto();
		$di_a      = $this->richiesta( $contenuto, '2026-03-10' );
		$di_b      = $this->richiesta( $contenuto, '2026-04-01' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $di_a )['codice'] );
		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $di_b )['codice'] );
		$this->assertSame( 'invariato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '' ) )['codice'], 'Chi scrive la data gia scritta da un altro non e in conflitto.' );

		$this->assertSame( array( '2026-03-10' ), $this->decorrenze( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ) );
		$this->assertCount( 1, $this->tracce( $contenuto ) );
	}

	/**
	 * T-65: [attacco] lettura fallita della data o della fine: niente scritto, niente nel registro.
	 */
	public function test_t65_lettura_fallita(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		foreach ( array( SchedaPubblicazione::META_DECORRENZA, conformita_core_chiave_fine_pubblicazione() ) as $chiave ) {
			$this->guasta_lettura( $chiave );
			$this->assertSame( 'lettura', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'], $chiave );
			remove_all_filters( 'query' );
			$wpdb->suppress_errors( false );
		}

		$this->assertSame( array(), $this->decorrenze( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-65: con piu date memorizzate il riquadro lo dice, e una data scritta da quel riquadro ne lascia una.
	 */
	public function test_t65_piu_date(): void {
		$contenuto = $this->contenuto();

		add_post_meta( $contenuto, SchedaPubblicazione::META_DECORRENZA, '2020-01-01' );
		add_post_meta( $contenuto, SchedaPubblicazione::META_DECORRENZA, '2021-01-01' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'più di una data memorizzata', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_PARTENZA . '" value=""', $riquadro );

		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2020-01-01' ) )['codice'], 'Il modulo mostrava una data: e stato aperto prima.' );
		$this->assertSame( array( '2020-01-01', '2021-01-01' ), $this->decorrenze( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2021-01-01', '' ) )['codice'], 'Anche una delle date trovate rimette una riga sola.' );
		$this->assertSame( array( '2021-01-01' ), $this->decorrenze( $contenuto ) );
		$this->assertSame( array( '2026-12-31' ), $this->fini( $contenuto ) );

		$tracce = $this->tracce( $contenuto );

		$this->assertCount( 1, $tracce );
		$this->assertSame( array( '2020-01-01', '2021-01-01' ), $tracce[0]['dettagli']['trovate'] );
		$this->assertSame( '', $tracce[0]['dettagli']['prima'] );
	}

	/**
	 * T-65: guasti: albero, registro, data non scritta, fine rifiutata, blocco tenuto.
	 */
	public function test_t65_guasti(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		$this->albero_in_uso( __DIR__ . '/dati/inesistente.json' );
		$this->assertSame( 'albero', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$this->albero_con_durata( null );
		$this->assertSame( 'albero', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'], 'Albero senza durata.' );
		$this->albero_in_uso( FILE_ALBERATURA );

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		add_filter(
			'query',
			static function ( $sql ) use ( $tabella ) {
				return 0 === strpos( ltrim( (string) $sql ), 'INSERT INTO `' . $tabella . '`' ) ? 'INSERT INTO `' . $tabella . '_inesistente` VALUES (1)' : $sql;
			}
		);
		$wpdb->suppress_errors( true );
		$this->assertSame( 'registro', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$wpdb->suppress_errors( false );
		remove_all_filters( 'query' );

		$this->assertSame( array(), $this->decorrenze( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );

		add_filter(
			'update_post_metadata',
			static function ( $esito, $id, $chiave ) {
				return SchedaPubblicazione::META_DECORRENZA === $chiave ? false : $esito;
			},
			10,
			3
		);

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'cambio che non è avvenuto', $esito['messaggio'] );
		$this->assertSame( array(), $this->decorrenze( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ), 'Data non scritta: la fine non si tocca.' );

		remove_all_filters( 'update_post_metadata' );

		list( $host, $porta, $socket ) = $wpdb->parse_db_host( DB_HOST );

		$altra = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- serve una seconda connessione, che $wpdb non offre.
		$this->assertTrue( $altra->real_connect( (string) $host, DB_USER, DB_PASSWORD, DB_NAME, $porta ? (int) $porta : null, $socket ? (string) $socket : null ) );

		try {
			$this->assertSame( '1', (string) $altra->query( "SELECT GET_LOCK('" . $altra->real_escape_string( Blocco::nome() ) . "', 0)" )->fetch_row()[0] );

			$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) );
		} finally {
			$altra->close();
		}

		$this->assertSame( 'occupato', $esito['codice'] );
		$this->assertSame( array(), $this->decorrenze( $contenuto ) );
	}

	/**
	 * T-65: fine rifiutata da core dopo la data scritta: l'esito lo dice, il riquadro mostra la differenza, un nuovo salvataggio riallinea.
	 */
	public function test_t65_fine_rifiutata_e_riallineata(): void {
		$contenuto = $this->contenuto();

		add_filter(
			'update_post_metadata',
			static function ( $esito, $id, $chiave ) {
				return conformita_core_chiave_fine_pubblicazione() === $chiave ? false : $esito;
			},
			10,
			3
		);

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) );

		remove_all_filters( 'update_post_metadata' );

		$this->assertSame( 'fine', $esito['codice'] );
		$this->assertStringContainsString( 'non compare al pubblico', $esito['messaggio'] );
		$this->assertSame( array( '2026-03-10' ), $this->decorrenze( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ) );
		$this->assertStringContainsString( 'Salvando il contenuto si riallinea', SchedaPubblicazione::riquadro( get_post( $contenuto ) ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ) );
		$this->assertCount( 2, $this->tracce( $contenuto ) );

		// Una versione dell'albero con un'altra durata: il riquadro lo dice e il salvataggio riallinea.
		$this->albero_con_durata( array( 'anni' => 3 ) );
		$this->assertStringContainsString( 'Salvando il contenuto si riallinea', SchedaPubblicazione::riquadro( get_post( $contenuto ) ) );
		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10' ) )['codice'] );
		$this->assertSame( array( '2029-12-31' ), $this->fini( $contenuto ) );
	}

	/**
	 * T-65: dal salvataggio del contenuto, l'esito si mostra una volta; senza il riquadro non si tocca niente.
	 */
	public function test_t65_dal_salvataggio(): void {
		$contenuto = $this->contenuto();

		$_POST = $this->richiesta( $contenuto, '2026-03-10' );
		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Titolo cambiato',
			)
		);
		$_POST = array();

		$this->assertSame( array( '2026-03-10' ), $this->decorrenze( $contenuto ) );

		$secondo = $this->contenuto();
		$_POST   = $this->richiesta( $secondo, '2026-02-30' );
		wp_update_post(
			array(
				'ID'         => $secondo,
				'post_title' => 'Secondo',
			)
		);
		$_POST = array();

		set_current_screen( 'edit-' . TIPO_CONTENUTO );
		$GLOBALS['post'] = get_post( $contenuto ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- il contenuto della schermata, come lo mette WordPress.
		$this->assertStringNotContainsString( 'Data della pubblicazione', $this->avvisi_in_bacheca(), 'Solo sulla schermata del contenuto, non nell\'elenco.' );

		set_current_screen( TIPO_CONTENUTO );
		$GLOBALS['post'] = get_post( $secondo ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.
		$avvisi          = $this->avvisi_in_bacheca();

		$this->assertStringContainsString( 'non esiste nel calendario', $avvisi );
		$this->assertStringNotContainsString( 'Data della pubblicazione salvata.', $avvisi, 'L\'esito del primo non compare sul secondo.' );

		$GLOBALS['post'] = get_post( $contenuto ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- come sopra.

		$this->assertStringContainsString( 'Data della pubblicazione salvata.', $this->avvisi_in_bacheca() );
		$this->assertStringNotContainsString( 'Data della pubblicazione salvata.', $this->avvisi_in_bacheca(), 'L\'esito si mostra una volta.' );

		unset( $GLOBALS['post'] );
		set_current_screen( 'front' );

		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Senza riquadro',
			)
		);

		$this->assertSame( array( '2026-03-10' ), $this->decorrenze( $contenuto ) );
		$this->assertCount( 1, $this->tracce( $contenuto ) );
		$this->assertFalse( get_transient( SchedaPubblicazione::chiave_esito( $contenuto ) ), 'Senza riquadro nessun esito.' );
	}

	/**
	 * T-66: senza data il contenuto pubblicato e scaduto per il componente comune; con la data e visibile fino alla fine; dopo la fine e scaduto.
	 */
	public function test_t66_visibilita_dal_componente_comune(): void {
		$oggi      = new \DateTimeImmutable( 'now', wp_timezone() );
		$contenuto = $this->contenuto( 'publish' );

		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Senza data non compare.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $oggi->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertFalse( conformita_core_scaduto( $contenuto ), 'Con la data di oggi compare.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $oggi->modify( '-5 years' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertSame( $oggi->format( 'Y' ) . '-12-31', conformita_core_fine_pubblicazione( $contenuto ), 'Decorrenza di cinque anni fa: compare fino al 31 dicembre di quest\'anno.' );
		$this->assertFalse( conformita_core_scaduto( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $oggi->modify( '-6 years' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertSame( ( (int) $oggi->format( 'Y' ) - 1 ) . '-12-31', conformita_core_fine_pubblicazione( $contenuto ) );
		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Decorrenza di sei anni fa: finita il 31 dicembre dell\'anno scorso.' );
	}
}
