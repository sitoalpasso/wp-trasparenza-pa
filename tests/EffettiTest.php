<?php
/**
 * Prove della pubblicazione finché l'atto produce effetti: righe T-81..T-83.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaPubblicazione;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\FILE_ALBERATURA;
use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * La seconda data del riquadro "Pubblicazione": la fine degli effetti, che prolunga e non accorcia.
 */
class EffettiTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

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

		PaginaPubblica::fissa_file( FILE_ALBERATURA );
		PaginaPubblica::da_init();
		update_option( 'date_format', 'Y-m-d' );

		delete_option( OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione deve assegnare i permessi.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Toglie i guasti e rimette l'albero finto.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_all_filters( 'query' );
		remove_all_filters( 'update_post_metadata' );
		remove_all_filters( 'add_post_metadata' );
		remove_all_filters( 'delete_post_metadata' );
		$wpdb->suppress_errors( false );
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

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
	 * Il valore attuale di un metadato a una riga, o vuoto.
	 *
	 * @param int    $contenuto Contenuto.
	 * @param string $chiave    Chiave.
	 * @return string
	 */
	private function attuale( int $contenuto, string $chiave ): string {
		$valori = get_post_meta( $contenuto, $chiave, false );

		return 1 === count( $valori ) ? (string) $valori[0] : '';
	}

	/**
	 * Una richiesta come la manderebbe il riquadro.
	 *
	 * @param int        $contenuto  Contenuto.
	 * @param mixed      $decorrenza Decorrenza scritta.
	 * @param mixed      $effetti    Fine degli effetti scritta.
	 * @param mixed|null $partenza   Fine degli effetti mostrata all'apertura; se non indicata, quella attuale.
	 * @return array<string, mixed>
	 */
	private function richiesta( int $contenuto, $decorrenza, $effetti, $partenza = null ): array {
		return array(
			SchedaPubblicazione::CAMPO_NONCE               => wp_create_nonce( SchedaPubblicazione::azione_nonce( $contenuto ) ),
			SchedaPubblicazione::CAMPO                     => $decorrenza,
			SchedaPubblicazione::CAMPO_PARTENZA            => $this->attuale( $contenuto, SchedaPubblicazione::META_DECORRENZA ),
			SchedaPubblicazione::CAMPO_EFFETTI             => $effetti,
			SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA    => $partenza ?? $this->attuale( $contenuto, SchedaPubblicazione::META_EFFETTI ),
			SchedaPubblicazione::CAMPO_CESSAZIONE          => '',
			SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA => '',
		);
	}

	/**
	 * Le voci di registro della pubblicazione di un contenuto, piu vecchia prima.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, array<string, mixed>>
	 */
	private function tracce( int $contenuto ): array {
		$tracce = conformita_core_voci_registro(
			array(
				'contenuto' => $contenuto,
				'azione'    => SchedaPubblicazione::AZIONE_REGISTRO,
			)
		);

		usort(
			$tracce,
			static function ( $a, $b ) {
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);

		return $tracce;
	}

	/**
	 * Le date degli effetti memorizzate, tutte.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>
	 */
	private function effetti( int $contenuto ): array {
		return array_map( 'strval', get_post_meta( $contenuto, SchedaPubblicazione::META_EFFETTI, false ) );
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
	 * T-81: la fine e la piu lontana fra quella di legge e la fine degli effetti; gli effetti non accorciano.
	 */
	public function test_t81_fine_con_effetti(): void {
		$casi = array(
			array( '2026-03-10', '', 5, '2031-12-31' ),
			array( '2026-03-10', '2026-03-10', 5, '2031-12-31' ),
			array( '2026-03-10', '2031-12-30', 5, '2031-12-31' ),
			array( '2026-03-10', '2031-12-31', 5, '2031-12-31' ),
			array( '2026-03-10', '2032-01-01', 5, '2032-01-01' ),
			array( '2019-03-10', '2034-06-30', 5, '2034-06-30' ),
			array( '2026-03-10', '9999-12-31', 5, '9999-12-31' ),
			array( '2026-03-10', '2030-06-30', 3, '2030-06-30' ),
		);

		foreach ( $casi as list( $decorrenza, $effetti, $anni, $attesa ) ) {
			$this->assertSame( $attesa, SchedaPubblicazione::fine( $decorrenza, $effetti, $anni ), $decorrenza . ' / ' . $effetti . ' / ' . $anni );
		}

		$this->assertNull( SchedaPubblicazione::fine( '9995-01-01', '9999-12-31', 5 ), 'Oltre il 9999 per legge non c\'e una fine, anche con gli effetti.' );
	}

	/**
	 * T-81: [attacco] date degli effetti non valide, senza decorrenza, prima della decorrenza; campi mancanti o non testuali.
	 */
	public function test_t81_rifiuti(): void {
		$contenuto = $this->contenuto();

		foreach ( array( '2032-02-30', '2032-2-3', '2032-02-03 ', "2032-02-03\n", '2032-02-03<b>', 'mai' ) as $data ) {
			$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', $data ) )['codice'], $data );
		}

		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', array( '2032-01-01' ) ) )['codice'], 'Data non testuale.' );
		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2032-01-01', array() ) )['codice'], 'Partenza non testuale.' );

		foreach ( array( SchedaPubblicazione::CAMPO_EFFETTI, SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA ) as $campo ) {
			$senza = $this->richiesta( $contenuto, '2026-03-10', '' );
			unset( $senza[ $campo ] );
			$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $senza )['codice'], $campo . ' mancante.' );
		}

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '', '2032-01-01' ) );
		$this->assertSame( 'dati', $esito['codice'], 'Effetti senza decorrenza.' );
		$this->assertStringContainsString( 'serve la data da cui decorre', $esito['messaggio'] );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2026-03-09' ) );
		$this->assertSame( 'dati', $esito['codice'], 'Effetti prima della decorrenza.' );
		$this->assertStringContainsString( 'prima della data da cui decorre', $esito['messaggio'] );

		$this->assertSame( array(), $this->effetti( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ) );
		$this->assertSame( array(), get_post_meta( $contenuto, SchedaPubblicazione::META_DECORRENZA, false ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2026-03-10' ) )['codice'], 'Lo stesso giorno della decorrenza si accetta.' );
	}

	/**
	 * T-82: riquadro, salvataggio, registro, cambio, data che non prolunga, rimozione.
	 */
	public function test_t82_salvataggio_e_registro(): void {
		$contenuto = $this->contenuto();
		$riquadro  = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI . '" value=""', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA . '" value=""', $riquadro );
		$this->assertStringContainsString( esc_html( 'L\'atto produce effetti fino al' ), $riquadro );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2035-06-30' ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertStringContainsString( 'Fine della pubblicazione: 2035-06-30, oltre i 5 anni di legge', $esito['messaggio'] );
		$this->assertSame( array( '2035-06-30' ), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2035-06-30' ), $this->fini( $contenuto ) );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI . '" value="2035-06-30"', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA . '" value="2035-06-30"', $riquadro );
		$this->assertStringContainsString( 'Fine della pubblicazione: 2035-06-30.', $riquadro );
		$this->assertStringContainsString( 'La fine viene dalla data degli effetti', $riquadro );
		$this->assertStringNotContainsString( 'Salvando il contenuto si riallinea', $riquadro );

		$this->assertSame( 'invariato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2035-06-30' ) )['codice'] );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2030-06-30' ) )['codice'] );
		$this->assertSame( array( '2030-06-30' ), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ), 'Gli effetti non accorciano la fine di legge.' );
		$this->assertStringContainsString( 'non prolunga', SchedaPubblicazione::riquadro( get_post( $contenuto ) ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2040-01-31' ) )['codice'] );
		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '' ) )['codice'], 'La data degli effetti si toglie.' );
		$this->assertSame( array(), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ) );

		$dettagli = array_column( $this->tracce( $contenuto ), 'dettagli' );

		$this->assertSame(
			array(
				array( '', '2035-06-30', array(), '2035-06-30' ),
				array( '2035-06-30', '2030-06-30', array( '2035-06-30' ), '2031-12-31' ),
				array( '2030-06-30', '2040-01-31', array( '2031-12-31' ), '2040-01-31' ),
				array( '2040-01-31', '', array( '2040-01-31' ), '2031-12-31' ),
			),
			array_map(
				static function ( $voce ) {
					return array( $voce['effetti_prima'], $voce['effetti_dopo'], $voce['fine_prima'], $voce['fine_dopo'] );
				},
				$dettagli
			)
		);
	}

	/**
	 * T-82: un cambio della data degli effetti che non cambia la fine si salva e si registra lo stesso.
	 */
	public function test_t82_cambio_senza_cambio_di_fine(): void {
		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2030-06-30' ) )['codice'] );
		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2029-06-30' ) )['codice'] );
		$this->assertSame( array( '2029-06-30' ), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ) );
		$this->assertCount( 2, $this->tracce( $contenuto ) );
	}

	/**
	 * T-82: [attacco] due moduli aperti insieme, un valore con codice, lettura fallita degli effetti.
	 */
	public function test_t82_conflitto_escaping_lettura(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '' ) )['codice'] );

		$di_a = $this->richiesta( $contenuto, '2026-03-10', '2035-06-30' );
		$di_b = $this->richiesta( $contenuto, '2026-03-10', '2036-06-30' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $di_a )['codice'] );
		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $di_b )['codice'] );
		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '', '' ) )['codice'], 'Togliere una data che non si e vista e un conflitto.' );
		$this->assertSame( array( '2035-06-30' ), $this->effetti( $contenuto ) );
		$this->assertCount( 2, $this->tracce( $contenuto ) );

		$wpdb->suppress_errors( true );
		add_filter(
			'query',
			static function ( $sql ) use ( $wpdb ) {
				$sql = (string) $sql;

				return 0 === strpos( $sql, "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id" ) && false !== strpos( $sql, "'" . SchedaPubblicazione::META_EFFETTI . "'" )
					? 'SELECT meta_value FROM ' . $wpdb->postmeta . '_inesistente'
					: $sql;
			}
		);
		$this->assertSame( 'lettura', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2037-06-30' ) )['codice'] );
		remove_all_filters( 'query' );
		$wpdb->suppress_errors( false );

		$this->assertSame( array( '2035-06-30' ), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2035-06-30' ), $this->fini( $contenuto ) );
		$this->assertCount( 2, $this->tracce( $contenuto ) );

		$altro = $this->contenuto();
		add_post_meta( $altro, SchedaPubblicazione::META_EFFETTI, '"><script>alert(1)</script>' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $altro ) );

		$this->assertStringNotContainsString( '<script>', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI . '" value=""', $riquadro, 'Un valore che non e una data non si mostra come data.' );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA . '" value="&quot;&gt;&lt;script&gt;', $riquadro );
	}

	/**
	 * T-82: piu date degli effetti memorizzate: il riquadro lo dice; dal riquadro restano una o nessuna, con quelle trovate nel registro.
	 */
	public function test_t82_piu_date_degli_effetti(): void {
		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '' ) )['codice'] );
		add_post_meta( $contenuto, SchedaPubblicazione::META_EFFETTI, '2033-01-01' );
		add_post_meta( $contenuto, SchedaPubblicazione::META_EFFETTI, '2034-01-01' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'più di una data di fine degli effetti', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA . '" value=""', $riquadro );

		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2035-01-01', '2033-01-01' ) )['codice'] );
		$this->assertSame( array( '2033-01-01', '2034-01-01' ), $this->effetti( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2034-01-01', '' ) )['codice'], 'Anche una delle date trovate rimette una riga sola.' );
		$this->assertSame( array( '2034-01-01' ), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2034-01-01' ), $this->fini( $contenuto ) );

		$tracce = $this->tracce( $contenuto );

		$this->assertCount( 2, $tracce );
		$this->assertSame( array( '2033-01-01', '2034-01-01' ), $tracce[1]['dettagli']['effetti_trovati'] );
		$this->assertSame( '', $tracce[1]['dettagli']['effetti_prima'] );

		add_post_meta( $contenuto, SchedaPubblicazione::META_EFFETTI, '2035-01-01' );
		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '', '' ) )['codice'], 'Senza data, dal riquadro le righe si tolgono tutte.' );
		$this->assertSame( array(), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ) );
	}

	/**
	 * T-82: [guasto] data degli effetti non scritta o non tolta: l'esito lo dice e la fine non si tocca.
	 */
	public function test_t82_effetti_non_scritti(): void {
		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '' ) )['codice'] );

		$rifiuta = static function ( $esito, $id, $chiave ) {
			return SchedaPubblicazione::META_EFFETTI === $chiave ? false : $esito;
		};

		add_filter( 'update_post_metadata', $rifiuta, 10, 3 );
		add_filter( 'add_post_metadata', $rifiuta, 10, 3 );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2035-06-30' ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'fine degli effetti non si è potuta scrivere', $esito['messaggio'] );
		$this->assertSame( array(), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2031-12-31' ), $this->fini( $contenuto ), 'Effetti non scritti: la fine non si tocca.' );

		remove_all_filters( 'update_post_metadata' );
		remove_all_filters( 'add_post_metadata' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '2035-06-30' ) )['codice'] );

		add_filter( 'delete_post_metadata', $rifiuta, 10, 3 );

		$this->assertSame( 'scrittura', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2026-03-10', '' ) )['codice'] );
		$this->assertSame( array( '2035-06-30' ), $this->effetti( $contenuto ) );
		$this->assertSame( array( '2035-06-30' ), $this->fini( $contenuto ), 'Effetti non tolti: la fine non si tocca.' );
	}

	/**
	 * T-83: per il componente comune, un contenuto oltre i cinque anni compare finche producono effetti, e sparisce quando la data si toglie.
	 */
	public function test_t83_visibilita_dal_componente_comune(): void {
		$oggi       = new \DateTimeImmutable( 'now', wp_timezone() );
		$contenuto  = $this->contenuto( 'publish' );
		$decorrenza = $oggi->modify( '-6 years' )->format( 'Y-m-d' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, '' ) )['codice'] );
		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Decorrenza di sei anni fa, senza effetti: scaduto.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, $oggi->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertFalse( conformita_core_scaduto( $contenuto ), 'Effetti fino a oggi compreso: compare.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, $oggi->modify( '-1 day' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Effetti finiti ieri: scaduto.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, $oggi->modify( '+1 year' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertFalse( conformita_core_scaduto( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, '' ) )['codice'] );
		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Data degli effetti tolta: scaduto di nuovo.' );
	}
}
