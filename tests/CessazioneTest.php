<?php
/**
 * Prove dei tre anni dalla cessazione per titolari e consulenti: righe T-86..T-88.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

use TrasparenzaPa\Avvio;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\PaginaPubblica;
use TrasparenzaPa\SchedaContenuto;
use TrasparenzaPa\SchedaPubblicazione;
use TrasparenzaPa\TipoContenuto;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\FILE_ALBERATURA;
use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_CONTENUTO;

/**
 * La data di cessazione del riquadro "Pubblicazione", nelle voci che l'albero lega alla cessazione.
 */
class CessazioneTest extends WP_UnitTestCase {

	use AmbienteTrasparenza;

	/**
	 * Una voce che segue la cessazione, nell'albero distribuito.
	 */
	const VOCE_CESSAZIONE = 'titolari_incarichi_collaborazione_consulenza';

	/**
	 * Una voce che non la segue.
	 */
	const VOCE_GENERALE = 'bandi_di_concorso';

	/**
	 * Sezione e tipi registrati, permessi assegnati, albero in vigore, amministratore corrente.
	 */
	public function set_up(): void {
		parent::set_up();

		// Da sola anche quando i tipi sono gia registrati: una prova precedente puo aver azzerato l'avvio.
		if ( ! Avvio::registrata() ) {
			\Conformita_Core_Sezioni::azzera();
			Avvio::azzera();
			update_option( 'active_plugins', array( plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE ) ) );
			Avvio::esegui();
		}

		if ( ! TipoVoce::registrato() || ! TipoContenuto::registrato() ) {
			\Conformita_Core_Tipi::azzera();
			TipoVoce::azzera();
			TipoContenuto::azzera();

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
		$_POST = array();
		PaginaPubblica::fissa_file( __DIR__ . '/dati/albero-finto.json' );
		PaginaPubblica::da_init();

		parent::tear_down();
	}

	/**
	 * Un contenuto, collocato in una voce o in nessuna.
	 *
	 * @param string|null $voce  Voce.
	 * @param string      $stato Stato del contenuto.
	 * @return int
	 */
	private function contenuto( ?string $voce = self::VOCE_CESSAZIONE, string $stato = 'draft' ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_CONTENUTO,
				'post_status' => $stato,
				'post_title'  => 'Contenuto di prova',
			)
		);

		if ( null !== $voce ) {
			add_post_meta( $id, SchedaContenuto::META_COLLOCAZIONE, $voce );
		}

		return $id;
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
	 * @param mixed      $cessazione Cessazione scritta.
	 * @param mixed|null $partenza   Cessazione mostrata all'apertura; se non indicata, quella attuale.
	 * @param mixed      $effetti    Fine degli effetti scritta.
	 * @return array<string, mixed>
	 */
	private function richiesta( int $contenuto, $decorrenza, $cessazione, $partenza = null, $effetti = '' ): array {
		return array(
			SchedaPubblicazione::CAMPO_NONCE               => wp_create_nonce( SchedaPubblicazione::azione_nonce( $contenuto ) ),
			SchedaPubblicazione::CAMPO                     => $decorrenza,
			SchedaPubblicazione::CAMPO_PARTENZA            => $this->attuale( $contenuto, SchedaPubblicazione::META_DECORRENZA ),
			SchedaPubblicazione::CAMPO_EFFETTI             => $effetti,
			SchedaPubblicazione::CAMPO_EFFETTI_PARTENZA    => $this->attuale( $contenuto, SchedaPubblicazione::META_EFFETTI ),
			SchedaPubblicazione::CAMPO_CESSAZIONE          => $cessazione,
			SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA => $partenza ?? $this->attuale( $contenuto, SchedaPubblicazione::META_CESSAZIONE ),
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
	 * Le date di cessazione memorizzate, tutte.
	 *
	 * @param int $contenuto Contenuto.
	 * @return array<int, string>
	 */
	private function cessazioni( int $contenuto ): array {
		return array_map( 'strval', get_post_meta( $contenuto, SchedaPubblicazione::META_CESSAZIONE, false ) );
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
	 * T-86: la fine e tanti anni dopo la cessazione quanti ne dice la regola, lo stesso giorno.
	 */
	public function test_t86_fine_da_cessazione(): void {
		$casi = array(
			array( '2026-03-10', 3, '2029-03-10' ),
			array( '2026-01-01', 3, '2029-01-01' ),
			array( '2026-12-31', 3, '2029-12-31' ),
			array( '2024-02-29', 3, '2027-03-01' ),
			array( '2024-02-29', 4, '2028-02-29' ),
			array( '2026-06-15', 1, '2027-06-15' ),
			array( '9996-12-31', 3, '9999-12-31' ),
		);

		foreach ( $casi as list( $cessazione, $anni, $attesa ) ) {
			$this->assertSame( $attesa, SchedaPubblicazione::fine_da_cessazione( $cessazione, $anni ), $cessazione . ' + ' . $anni );
		}

		$this->assertNull( SchedaPubblicazione::fine_da_cessazione( '9997-01-01', 3 ), 'Oltre l\'anno 9999 non c\'e una fine.' );
	}

	/**
	 * T-87: riquadro nella voce della cessazione e fuori; salvataggio, registro, cambio, rimozione.
	 */
	public function test_t87_salvataggio_e_registro(): void {
		$fuori = SchedaPubblicazione::riquadro( get_post( $this->contenuto( self::VOCE_GENERALE ) ) );

		$this->assertStringContainsString( '<input type="hidden" name="' . SchedaPubblicazione::CAMPO_CESSAZIONE . '" value="">', $fuori, 'Fuori dalle voci della cessazione il campo non si vede.' );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA . '" value=""', $fuori );
		$this->assertStringNotContainsString( 'Data di cessazione', $fuori );

		$contenuto = $this->contenuto();
		$riquadro  = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( '<input type="date" id="trasparenza-pa-cessazione" name="' . SchedaPubblicazione::CAMPO_CESSAZIONE . '" value=""', $riquadro );
		$this->assertStringContainsString( 'Data di cessazione del mandato o dell', $riquadro );
		$this->assertStringContainsString( esc_html( 'art. 15, c. 4, del d.lgs. 33/2013' ), $riquadro );
		$this->assertStringContainsString( '3 anni dopo la cessazione', $riquadro );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) );

		$this->assertSame( 'salvato', $esito['codice'] );
		$this->assertStringContainsString( 'Fine della pubblicazione: 2029-01-31, 3 anni dopo la cessazione', $esito['messaggio'] );
		$this->assertSame( array( '2026-01-31' ), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2029-01-31' ), $this->fini( $contenuto ), 'Con la cessazione la regola dei cinque anni non vale.' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_CESSAZIONE . '" value="2026-01-31"', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA . '" value="2026-01-31"', $riquadro );
		$this->assertStringContainsString( 'La fine viene dalla data di cessazione', $riquadro );
		$this->assertStringNotContainsString( 'Salvando il contenuto si riallinea', $riquadro );

		$this->assertSame( 'invariato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) )['codice'] );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2027-06-30' ) )['codice'], 'Proroga.' );
		$this->assertSame( array( '2030-06-30' ), $this->fini( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '' ) )['codice'], 'La data di cessazione si toglie.' );
		$this->assertSame( array(), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2030-12-31' ), $this->fini( $contenuto ), 'Senza cessazione torna la regola generale.' );
		$this->assertStringContainsString( 'serve la data di cessazione', SchedaPubblicazione::riquadro( get_post( $contenuto ) ) );

		$this->assertSame(
			array(
				array( '', '2026-01-31', array(), '2029-01-31' ),
				array( '2026-01-31', '2027-06-30', array( '2029-01-31' ), '2030-06-30' ),
				array( '2027-06-30', '', array( '2030-06-30' ), '2030-12-31' ),
			),
			array_map(
				static function ( $voce ) {
					$dettagli = $voce['dettagli'];

					return array( $dettagli['cessazione_prima'], $dettagli['cessazione_dopo'], $dettagli['fine_prima'], $dettagli['fine_dopo'] );
				},
				$this->tracce( $contenuto )
			)
		);
	}

	/**
	 * T-87: [attacco] date non valide, prima della decorrenza, con la fine degli effetti, voce che non la prevede o assente, campi mancanti.
	 */
	public function test_t87_rifiuti(): void {
		$contenuto = $this->contenuto();

		foreach ( array( '2026-02-30', '2026-2-3', '2026-02-03 ', "2026-02-03\n", '2026-02-03<b>', 'mai' ) as $data ) {
			$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', $data ) )['codice'], $data );
		}

		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', array( '2026-01-31' ) ) )['codice'], 'Data non testuale.' );
		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31', array() ) )['codice'], 'Partenza non testuale.' );

		foreach ( array( SchedaPubblicazione::CAMPO_CESSAZIONE, SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA ) as $campo ) {
			$senza = $this->richiesta( $contenuto, '2025-02-01', '' );
			unset( $senza[ $campo ] );
			$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $senza )['codice'], $campo . ' mancante.' );
		}

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2025-01-31' ) );
		$this->assertSame( 'dati', $esito['codice'], 'Cessazione prima della decorrenza.' );
		$this->assertStringContainsString( 'prima della data da cui decorre', $esito['messaggio'] );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31', null, '2035-01-01' ) );
		$this->assertSame( 'dati', $esito['codice'], 'Cessazione con la fine degli effetti.' );
		$this->assertStringContainsString( 'la fine degli effetti non si usa', $esito['messaggio'] );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '', '2026-01-31' ) );
		$this->assertSame( 'dati', $esito['codice'], 'Cessazione senza decorrenza.' );

		$this->assertSame( 'dati', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '9997-01-01' ) )['codice'], 'Fine oltre il 9999.' );

		foreach ( array( self::VOCE_GENERALE, null ) as $voce ) {
			$altro = $this->contenuto( $voce );
			$esito = SchedaPubblicazione::applica( $altro, $this->richiesta( $altro, '2025-02-01', '2026-01-31' ) );

			$this->assertSame( 'voce', $esito['codice'], (string) $voce );
			$this->assertStringContainsString( 'non prevede la data di cessazione', $esito['messaggio'] );
			$this->assertSame( array(), get_post_meta( $altro, SchedaPubblicazione::META_DECORRENZA, false ) );
			$this->assertSame( array(), $this->cessazioni( $altro ) );
			$this->assertCount( 0, $this->tracce( $altro ) );
		}

		$doppia = $this->contenuto();
		add_post_meta( $doppia, SchedaContenuto::META_COLLOCAZIONE, self::VOCE_GENERALE );
		$this->assertSame( 'voce', SchedaPubblicazione::applica( $doppia, $this->richiesta( $doppia, '2025-02-01', '2026-01-31' ) )['codice'], 'Con piu voci la cessazione non e ammessa.' );

		$this->assertSame( array(), $this->cessazioni( $contenuto ) );
		$this->assertSame( array(), $this->fini( $contenuto ) );
		$this->assertCount( 0, $this->tracce( $contenuto ) );
	}

	/**
	 * T-87: voce cambiata dopo la cessazione: il riquadro chiede di toglierla, e finche resta il salvataggio si rifiuta.
	 */
	public function test_t87_voce_cambiata(): void {
		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) )['codice'] );
		update_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, self::VOCE_GENERALE );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( '<input type="date" id="trasparenza-pa-cessazione" name="' . SchedaPubblicazione::CAMPO_CESSAZIONE . '" value="2026-01-31"', $riquadro );
		$this->assertStringContainsString( 'La voce del contenuto non prevede la data di cessazione', $riquadro );
		$this->assertStringContainsString( 'Salvando il contenuto si riallinea', $riquadro );

		$this->assertSame( 'voce', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) )['codice'] );
		$this->assertSame( array( '2029-01-31' ), $this->fini( $contenuto ) );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '' ) )['codice'] );
		$this->assertSame( array(), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2030-12-31' ), $this->fini( $contenuto ) );
	}

	/**
	 * T-87: [attacco] due moduli aperti insieme, valore con codice, lettura fallita della cessazione o della voce, piu righe.
	 */
	public function test_t87_conflitto_escaping_lettura_piu_righe(): void {
		global $wpdb;

		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '' ) )['codice'] );

		$di_a = $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' );
		$di_b = $this->richiesta( $contenuto, '2025-02-01', '2026-02-28' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $di_a )['codice'] );
		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $di_b )['codice'] );
		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '', '' ) )['codice'], 'Togliere una data che non si e vista e un conflitto.' );
		$this->assertSame( array( '2026-01-31' ), $this->cessazioni( $contenuto ) );

		foreach ( array( SchedaPubblicazione::META_CESSAZIONE, SchedaContenuto::META_COLLOCAZIONE ) as $chiave ) {
			$this->guasta_lettura( $chiave );
			$this->assertSame( 'lettura', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2027-01-31' ) )['codice'], $chiave );
			remove_all_filters( 'query' );
			$wpdb->suppress_errors( false );
		}

		$this->assertSame( array( '2026-01-31' ), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2029-01-31' ), $this->fini( $contenuto ) );
		$this->assertCount( 2, $this->tracce( $contenuto ) );

		add_post_meta( $contenuto, SchedaPubblicazione::META_CESSAZIONE, '2027-01-31' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $contenuto ) );

		$this->assertStringContainsString( 'più di una data di cessazione', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_CESSAZIONE_PARTENZA . '" value=""', $riquadro );

		$this->assertSame( 'conflitto', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2027-01-31', '2026-01-31' ) )['codice'] );
		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2027-01-31', '' ) )['codice'] );
		$this->assertSame( array( '2027-01-31' ), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2030-01-31' ), $this->fini( $contenuto ) );

		$tracce = $this->tracce( $contenuto );

		$this->assertSame( array( '2026-01-31', '2027-01-31' ), $tracce[2]['dettagli']['cessazione_trovate'] );
		$this->assertSame( '', $tracce[2]['dettagli']['cessazione_prima'] );

		$altro = $this->contenuto();
		add_post_meta( $altro, SchedaPubblicazione::META_CESSAZIONE, '"><script>alert(1)</script>' );

		$riquadro = SchedaPubblicazione::riquadro( get_post( $altro ) );

		$this->assertStringNotContainsString( '<script>', $riquadro );
		$this->assertStringContainsString( 'name="' . SchedaPubblicazione::CAMPO_CESSAZIONE . '" value=""', $riquadro, 'Un valore che non e una data non si mostra come data.' );
	}

	/**
	 * T-87: [guasto] cessazione non scritta o non tolta: l'esito lo dice e la fine non si tocca.
	 */
	public function test_t87_cessazione_non_scritta(): void {
		$contenuto = $this->contenuto();

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '' ) )['codice'] );

		$rifiuta = static function ( $esito, $id, $chiave ) {
			return SchedaPubblicazione::META_CESSAZIONE === $chiave ? false : $esito;
		};

		add_filter( 'update_post_metadata', $rifiuta, 10, 3 );
		add_filter( 'add_post_metadata', $rifiuta, 10, 3 );

		$esito = SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) );

		$this->assertSame( 'scrittura', $esito['codice'] );
		$this->assertStringContainsString( 'data di cessazione non si è potuta scrivere', $esito['messaggio'] );
		$this->assertSame( array(), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2030-12-31' ), $this->fini( $contenuto ) );

		remove_all_filters( 'update_post_metadata' );
		remove_all_filters( 'add_post_metadata' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) )['codice'] );

		add_filter( 'delete_post_metadata', $rifiuta, 10, 3 );

		$this->assertSame( 'scrittura', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, '2025-02-01', '' ) )['codice'] );
		$this->assertSame( array( '2026-01-31' ), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2029-01-31' ), $this->fini( $contenuto ) );
	}

	/**
	 * T-87: nello stesso salvataggio la voce si scrive prima, e la cessazione la trova.
	 */
	public function test_t87_voce_e_cessazione_nello_stesso_salvataggio(): void {
		$this->assertSame( 20, has_action( 'save_post_' . TIPO_CONTENUTO, array( SchedaPubblicazione::class, 'da_save_post' ) ) );
		$this->assertSame( 10, has_action( 'save_post_' . TIPO_CONTENUTO, array( SchedaContenuto::class, 'da_save_post' ) ) );

		$contenuto = $this->contenuto( null );

		$_POST = $this->richiesta( $contenuto, '2025-02-01', '2026-01-31' ) + array(
			SchedaContenuto::CAMPO_NONCE    => wp_create_nonce( SchedaContenuto::azione_nonce( $contenuto ) ),
			SchedaContenuto::CAMPO          => self::VOCE_CESSAZIONE,
			SchedaContenuto::CAMPO_PARTENZA => '',
		);
		wp_update_post(
			array(
				'ID'         => $contenuto,
				'post_title' => 'Titolo cambiato',
			)
		);
		$_POST = array();

		$this->assertSame( array( self::VOCE_CESSAZIONE ), get_post_meta( $contenuto, SchedaContenuto::META_COLLOCAZIONE, false ) );
		$this->assertSame( array( '2026-01-31' ), $this->cessazioni( $contenuto ) );
		$this->assertSame( array( '2029-01-31' ), $this->fini( $contenuto ) );
	}

	/**
	 * T-88: per il componente comune, i dati di un titolare compaiono fino a tre anni dopo la cessazione, anche oltre i cinque anni.
	 */
	public function test_t88_visibilita_dal_componente_comune(): void {
		$oggi       = new \DateTimeImmutable( 'now', wp_timezone() );
		$contenuto  = $this->contenuto( self::VOCE_CESSAZIONE, 'publish' );
		$decorrenza = $oggi->modify( '-10 years' )->format( 'Y-m-d' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, '' ) )['codice'] );
		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Decorrenza di dieci anni fa, senza cessazione: scaduto.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, $oggi->modify( '-3 years' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertFalse( conformita_core_scaduto( $contenuto ), 'Cessazione di tre anni fa oggi: compare fino a oggi compreso.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, $oggi->modify( '-3 years -1 day' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertTrue( conformita_core_scaduto( $contenuto ), 'Tre anni e un giorno: scaduto.' );

		$this->assertSame( 'salvato', SchedaPubblicazione::applica( $contenuto, $this->richiesta( $contenuto, $decorrenza, $oggi->modify( '+1 year' )->format( 'Y-m-d' ) ) )['codice'] );
		$this->assertFalse( conformita_core_scaduto( $contenuto ), 'Incarico in corso con fine prevista fra un anno: compare.' );
	}
}
