<?php
/**
 * Prove del modulo di compilazione: righe T-41..T-45.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

// I nomi delle proprieta degli esempi ufficiali sono quelli dell'Autorita, non nomi PHP.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace TrasparenzaPa\Tests;

use DOMDocument;
use DOMXPath;
use TrasparenzaPa\Avvio;
use TrasparenzaPa\Installazione;
use TrasparenzaPa\ModuloSchema;
use TrasparenzaPa\SchedaVoce;
use TrasparenzaPa\TipoVoce;
use WP_UnitTestCase;

use const TrasparenzaPa\OPZIONE_VERSIONE;
use const TrasparenzaPa\TIPO_VOCE;

/**
 * Dallo schema al modulo, dal modulo al dato, con la versione attaccata.
 */
class ModuloTest extends WP_UnitTestCase {

	/**
	 * Codice della versione in vigore dei tre schemi ufficiali.
	 *
	 * @var string
	 */
	private static $in_vigore = '1.0@2026-06-10';

	/**
	 * Amministratore delle prove.
	 *
	 * @var int
	 */
	private $amministratore;

	/**
	 * Cartelle temporanee da togliere a fine prova.
	 *
	 * @var array<int, string>
	 */
	private $cartelle = array();

	/**
	 * Tipo registrato, permessi assegnati, amministratore corrente, catalogo vero.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! TipoVoce::registrato() ) {
			\Conformita_Core_Tipi::azzera();
			TipoVoce::azzera();

			if ( ! Avvio::registrata() ) {
				\Conformita_Core_Sezioni::azzera();
				Avvio::azzera();
				update_option( 'active_plugins', array( plugin_basename( \TrasparenzaPa\FILE_PRINCIPALE ) ) );
				Avvio::esegui();
			}

			$this->assertTrue( TipoVoce::registra() );
		}

		delete_option( OPZIONE_VERSIONE );
		Installazione::aggiorna_se_serve();
		update_option( 'date_format', 'd/m/Y' );

		SchedaVoce::azzera();

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $this->amministratore );
	}

	/**
	 * Catalogo vero, cartelle tolte.
	 */
	public function tear_down(): void {
		SchedaVoce::azzera();

		$_POST = array();

		foreach ( $this->cartelle as $cartella ) {
			foreach ( (array) glob( $cartella . '/*' ) as $file ) {
				unlink( $file );
			}

			rmdir( $cartella );
		}

		parent::tear_down();
	}

	/**
	 * Una voce in bozza.
	 *
	 * @return int
	 */
	private function voce(): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'   => TIPO_VOCE,
				'post_status' => 'draft',
				'post_title'  => 'Voce di prova',
			)
		);
	}

	/**
	 * Salva la voce come la salva la schermata, con i campi inviati.
	 *
	 * @param int                  $voce  Voce.
	 * @param array<string, mixed> $campi Campi inviati, senza il nonce.
	 * @param string|null          $nonce Nonce; nullo per uno valido.
	 */
	private function salva( int $voce, array $campi, ?string $nonce = null ): void {
		$campi[ SchedaVoce::CAMPO_NONCE ] = $nonce ?? wp_create_nonce( SchedaVoce::NONCE );

		// WordPress aggiunge le barre ai valori inviati: il salvataggio deve toglierle.
		$_POST = wp_slash( $campi );

		wp_update_post(
			array(
				'ID'         => $voce,
				'post_title' => 'Voce di prova',
			)
		);

		$_POST = array();
	}

	/**
	 * Lega la voce a un obbligo, come al primo salvataggio.
	 *
	 * @param int    $voce    Voce.
	 * @param string $obbligo Obbligo.
	 */
	private function lega( int $voce, string $obbligo ): void {
		$this->salva( $voce, array( SchedaVoce::CAMPO_OBBLIGO => $obbligo ) );

		$this->assertSame( $obbligo, get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ), 'La voce deve legarsi all\'obbligo.' );
	}

	/**
	 * Un esempio ufficiale, decodificato.
	 *
	 * @param string $file Nome del file.
	 * @return object
	 */
	private static function esempio( string $file ): object {
		$testo = (string) file_get_contents( __DIR__ . '/dati/esempi-anac/' . $file );

		return json_decode( str_starts_with( $testo, "\xEF\xBB\xBF" ) ? substr( $testo, 3 ) : $testo, false );
	}

	/**
	 * L'obbligo di un esempio, dal nome del file.
	 *
	 * @param string $file Nome del file.
	 * @return string
	 */
	private static function obbligo_di( string $file ): string {
		preg_match( '/^art\.([0-9]+(?:-bis)?)-/', $file, $pezzi );

		return 'art_' . str_replace( '-', '_', $pezzi[1] );
	}

	/**
	 * I sette esempi ufficiali.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function esempi(): array {
		$esempi = array();

		foreach ( (array) glob( __DIR__ . '/dati/esempi-anac/*.json' ) as $file ) {
			$esempi[ basename( $file ) ] = array( basename( $file ) );
		}

		return $esempi;
	}

	/**
	 * Un dato tradotto nei valori che un modulo invia: tutto testo.
	 *
	 * @param mixed $valore Dato.
	 * @return mixed
	 */
	private static function inviato( $valore ) {
		if ( is_object( $valore ) ) {
			$valore = get_object_vars( $valore );
		}

		if ( is_array( $valore ) ) {
			return array_map( array( self::class, 'inviato' ), $valore );
		}

		return is_bool( $valore ) ? ( $valore ? '1' : '' ) : (string) $valore;
	}

	/**
	 * Forma canonica di un dato: chiavi in ordine, tipi rispettati.
	 *
	 * Il confronto e stretto apposta: il numero 2026 e il testo "2026" sono
	 * dati diversi per lo schema.
	 *
	 * @param mixed $valore Dato.
	 * @return string
	 */
	private static function canonico( $valore ): string {
		$ordina = static function ( $nodo ) use ( &$ordina ) {
			if ( is_object( $nodo ) ) {
				$campi = get_object_vars( $nodo );

				ksort( $campi );

				return (object) array_map( $ordina, $campi );
			}

			return is_array( $nodo ) ? array_map( $ordina, $nodo ) : $nodo;
		};

		return (string) wp_json_encode( $ordina( $valore ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	}

	/**
	 * Il riquadro della voce, letto come lo legge il navigatore.
	 *
	 * @param int $voce Voce.
	 * @return DOMXPath
	 */
	private static function riquadro( int $voce ): DOMXPath {
		$documento = new DOMDocument();

		libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="utf-8"?><div>' . SchedaVoce::riquadro( get_post( $voce ) ) . '</div>' );
		libxml_clear_errors();

		return new DOMXPath( $documento );
	}

	/**
	 * I valori che il navigatore invierebbe dal riquadro, come li riceve PHP.
	 *
	 * @param DOMXPath $xpath Riquadro.
	 * @return array<string, mixed>
	 */
	private static function invio_del_riquadro( DOMXPath $xpath ): array {
		$coppie = array();

		foreach ( $xpath->query( '//input[@name]' ) as $campo ) {
			$coppie[] = rawurlencode( $campo->getAttribute( 'name' ) ) . '=' . rawurlencode( $campo->getAttribute( 'value' ) );
		}

		foreach ( $xpath->query( '//select[@name]' ) as $scelta ) {
			$scelto = $xpath->query( './/option[@selected]', $scelta );

			$coppie[] = rawurlencode( $scelta->getAttribute( 'name' ) ) . '=' . rawurlencode( $scelto->length ? $scelto->item( 0 )->getAttribute( 'value' ) : '' );
		}

		parse_str( implode( '&', $coppie ), $inviato );

		return $inviato;
	}

	/**
	 * Le foglie di un dato con il nome del campo che le porta.
	 *
	 * @param mixed                 $valore Dato.
	 * @param string                $nome   Nome del campo.
	 * @param array<string, string> $foglie Raccolta.
	 */
	private static function foglie( $valore, string $nome, array &$foglie ): void {
		if ( is_object( $valore ) || is_array( $valore ) ) {
			foreach ( ( is_object( $valore ) ? get_object_vars( $valore ) : $valore ) as $chiave => $figlio ) {
				if ( '$schema' !== $chiave ) {
					self::foglie( $figlio, $nome . '[' . $chiave . ']', $foglie );
				}
			}

			return;
		}

		$foglie[ $nome ] = (string) $valore;
	}

	/**
	 * Il valore mostrato da un campo del riquadro, o nullo se il campo non c'e.
	 *
	 * @param DOMXPath $xpath Riquadro.
	 * @param string   $nome  Nome del campo.
	 * @return string|null
	 */
	private static function valore_mostrato( DOMXPath $xpath, string $nome ): ?string {
		foreach ( $xpath->query( '//input[@name] | //select[@name]' ) as $campo ) {
			if ( $campo->getAttribute( 'name' ) !== $nome ) {
				continue;
			}

			if ( 'input' === $campo->nodeName ) {
				return $campo->getAttribute( 'value' );
			}

			$scelto = $xpath->query( './/option[@selected]', $campo );

			return $scelto->length ? $scelto->item( 0 )->getAttribute( 'value' ) : '';
		}

		return null;
	}

	/**
	 * Il dato salvato, decodificato.
	 *
	 * @param int $voce Voce.
	 * @return mixed
	 */
	private static function salvato( int $voce ) {
		return json_decode( (string) get_post_meta( $voce, SchedaVoce::META_DATO, true ), false );
	}

	/**
	 * Un catalogo vero con un quarto schema finto accanto, aggiunto solo come file.
	 *
	 * @return string Percorso del catalogo.
	 */
	private function catalogo_con_quarto(): string {
		$cartella = get_temp_dir() . 'schemi-quarto-' . wp_generate_password( 12, false );

		mkdir( $cartella );

		$this->cartelle[] = $cartella;

		foreach ( (array) glob( dirname( __DIR__ ) . '/schemas/*.json' ) as $file ) {
			copy( $file, $cartella . '/' . basename( $file ) );
		}

		$schema = array(
			'$schema'              => 'http://json-schema.org/draft-07/schema#',
			'$id'                  => 'https://schemi.esempio.invalid/json/quarto-finto-v1.0.schema.json',
			'title'                => 'Quarto schema finto',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'noceMoscata'      => array(
					'$comment' => 'Noce moscata grattugiata',
					'type'     => 'string',
				),
				'chiodiDiGarofano' => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'macisForma'       => array( 'enum' => array( 'intera', 'macinata' ) ),
			),
			'required'             => array( 'noceMoscata' ),
		);

		file_put_contents( $cartella . '/quarto-finto-v1.0.schema.json', wp_json_encode( $schema ) );

		$catalogo = json_decode( (string) file_get_contents( $cartella . '/catalogo.json' ), true );

		$catalogo['schemi'][] = array(
			'obbligo'       => 'quarto_finto',
			'versione'      => '1.0',
			'approvato_il'  => '2019-12-01',
			'in_vigore_dal' => '2020-01-01',
			'file'          => 'quarto-finto-v1.0.schema.json',
			'comuni'        => array(),
			'fonte'         => array(
				'titolo'        => 'Quarto schema finto',
				'indirizzo'     => 'https://schemi.esempio.invalid/',
				'consultato_il' => '2026-10-27',
			),
		);

		file_put_contents( $cartella . '/catalogo.json', wp_json_encode( $catalogo ) );

		return $cartella . '/catalogo.json';
	}

	/**
	 * T-41: il riquadro e agganciato alla schermata della voce.
	 */
	public function test_t41_riquadro_nella_schermata_della_voce(): void {
		global $wp_meta_boxes;

		$voce = $this->voce();

		do_action( 'add_meta_boxes_' . TIPO_VOCE, get_post( $voce ) );

		$trovato = false;

		foreach ( (array) ( $wp_meta_boxes[ TIPO_VOCE ] ?? array() ) as $contesti ) {
			foreach ( (array) $contesti as $riquadri ) {
				$trovato = $trovato || isset( $riquadri[ SchedaVoce::ID_RIQUADRO ] );
			}
		}

		$this->assertTrue( $trovato, 'Il riquadro dei dati deve comparire nella schermata della voce.' );
	}

	/**
	 * T-41: prima della scelta dell'obbligo, il riquadro offre gli obblighi del catalogo.
	 */
	public function test_t41_scelta_dell_obbligo(): void {
		$xpath  = self::riquadro( $this->voce() );
		$valori = array();

		foreach ( $xpath->query( '//select[@name="' . SchedaVoce::CAMPO_OBBLIGO . '"]/option' ) as $opzione ) {
			$valori[] = $opzione->getAttribute( 'value' );
		}

		sort( $valori );

		$this->assertSame( array( '', 'art_13', 'art_31', 'art_4_bis' ), $valori );
		$this->assertSame( 0, $xpath->query( '//input[starts-with(@name, "' . SchedaVoce::CAMPO_DATO . '")]' )->length, 'Senza obbligo non ci sono campi.' );
	}

	/**
	 * T-41: per l'art. 13 un campo per ogni proprieta, con gruppi, serie, scelte ed etichette dallo schema.
	 */
	public function test_t41_modulo_dallo_schema_ufficiale(): void {
		$voce = $this->voce();

		$this->lega( $voce, 'art_13' );

		$vuoto = self::riquadro( $voce );

		// Scelta per l'elenco chiuso dei tipi di ufficio, con i valori dello schema.
		$tipi = array();

		foreach ( $vuoto->query( '//select[@name="' . SchedaVoce::CAMPO_DATO . '[orgPubblicheAmministrazioni][organi][0][uffici][0][tipologia]"]/option' ) as $opzione ) {
			$tipi[] = $opzione->getAttribute( 'value' );
		}

		$this->assertContains( 'Ufficio dirigenziale', $tipi );
		$this->assertContains( 'Ufficio non dirigenziale', $tipi );

		// Gruppo con l'etichetta presa dal commento dello schema.
		$legende = array();

		foreach ( $vuoto->query( '//fieldset/legend' ) as $legenda ) {
			$legende[] = $legenda->textContent;
		}

		$this->assertContains( 'Pubbliche amministrazioni e Autorità amministrative indipendenti (ex art. 1, c. 2, d.lgs. n. 165/2001)', $legende );

		// Serie per gli elenchi: vuota, un elemento da compilare.
		$this->assertGreaterThan( 0, $vuoto->query( '//fieldset[@class="trasparenza-elenco"][@data-percorso="/orgPubblicheAmministrazioni/organi"]' )->length );
		$this->assertSame( 0, $vuoto->query( '//input[@name="' . SchedaVoce::CAMPO_DATO . '[orgPubblicheAmministrazioni][organi][1][denominazione]"]' )->length );

		// Con i tre esempi caricati, ogni foglia ha il suo campo con il suo valore.
		foreach ( array( 'art.13-pa-YYYYMMDD-YYYYMMDD.v1.0.json', 'art.13-oc-YYYYMMDD-YYYYMMDD.v1.0.json', 'art.13-se-YYYYMMDD-YYYYMMDD.v1.0.json' ) as $file ) {
			$esempio = self::esempio( $file );
			$foglie  = array();

			update_post_meta( $voce, SchedaVoce::META_DATO, wp_slash( (string) wp_json_encode( $esempio ) ) );
			self::foglie( $esempio, SchedaVoce::CAMPO_DATO, $foglie );

			$xpath = self::riquadro( $voce );

			$this->assertGreaterThan( 3, count( $foglie ) );

			foreach ( $foglie as $nome => $valore ) {
				$this->assertSame( $valore, self::valore_mostrato( $xpath, $nome ), "{$file}: campo {$nome}" );
			}
		}
	}

	/**
	 * T-41: un quarto schema, aggiunto come solo file, produce il suo modulo.
	 */
	public function test_t41_quarto_schema_solo_file(): void {
		SchedaVoce::fissa_file( $this->catalogo_con_quarto() );

		$voce = $this->voce();

		$this->assertGreaterThan( 0, self::riquadro( $voce )->query( '//select[@name="' . SchedaVoce::CAMPO_OBBLIGO . '"]/option[@value="quarto_finto"]' )->length );

		$this->lega( $voce, 'quarto_finto' );

		$xpath = self::riquadro( $voce );
		$base  = SchedaVoce::CAMPO_DATO;

		$this->assertSame( 1, $xpath->query( '//input[@type="text"][@name="' . $base . '[noceMoscata]"]' )->length );
		$this->assertSame( 1, $xpath->query( '//input[@type="number"][@name="' . $base . '[chiodiDiGarofano]"]' )->length );
		$this->assertSame( 2, $xpath->query( '//select[@name="' . $base . '[macisForma]"]/option[@value!=""]' )->length );
		$this->assertSame( 'Noce moscata grattugiata', trim( $xpath->query( '//label[@for="' . $xpath->query( '//input[@name="' . $base . '[noceMoscata]"]' )->item( 0 )->getAttribute( 'id' ) . '"]' )->item( 0 )->textContent ) );

		$this->salva(
			$voce,
			array(
				SchedaVoce::CAMPO_DATO => array(
					'noceMoscata'      => 'Un pizzico',
					'chiodiDiGarofano' => '3',
					'macisForma'       => 'intera',
				),
			)
		);

		$this->assertSame( '{"chiodiDiGarofano":3,"macisForma":"intera","noceMoscata":"Un pizzico"}', (string) wp_json_encode( json_decode( self::canonico( self::salvato( $voce ) ) ) ) );
		$this->assertTrue( SchedaVoce::esito( $voce )['conforme'] );
	}

	/**
	 * T-42: ogni esempio ufficiale, inserito nel modulo, torna identico e conforme.
	 *
	 * @dataProvider esempi
	 *
	 * @param string $file Nome del file dell'esempio.
	 */
	public function test_t42_giro_completo( string $file ): void {
		$esempio = self::esempio( $file );
		$obbligo = self::obbligo_di( $file );
		$voce    = $this->voce();

		$this->lega( $voce, $obbligo );
		$this->assertSame( self::$in_vigore, get_post_meta( $voce, SchedaVoce::META_VERSIONE, true ), 'La voce si lega alla versione in vigore.' );

		// Il modulo non invia l'identificativo dello schema: lo riempie il componente.
		$campi = self::inviato( $esempio );

		unset( $campi['$schema'] );

		$this->salva( $voce, array( SchedaVoce::CAMPO_DATO => $campi ) );

		$this->assertSame( self::canonico( $esempio ), self::canonico( self::salvato( $voce ) ), 'Il dato salvato deve essere identico all\'esempio.' );
		$this->assertTrue( SchedaVoce::esito( $voce )['conforme'], wp_json_encode( SchedaVoce::esito( $voce ) ) );

		// Il giro come lo fa il navigatore: quello che il riquadro mostra, inviato di nuovo.
		$xpath = self::riquadro( $voce );

		$this->salva( $voce, self::invio_del_riquadro( $xpath ) );

		$this->assertSame( self::canonico( $esempio ), self::canonico( self::salvato( $voce ) ), 'Il dato rimandato dal riquadro deve restare identico.' );
		$this->assertSame( $obbligo, get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ) );
		$this->assertSame( self::$in_vigore, get_post_meta( $voce, SchedaVoce::META_VERSIONE, true ) );

		$xpath = self::riquadro( $voce );

		$this->assertStringContainsString( 'versione 1.0 del 10/06/2026', $xpath->query( '//*[@class="trasparenza-versione"]' )->item( 0 )->textContent );
		$this->assertSame( 1, $xpath->query( '//*[contains(@class, "trasparenza-esito-conforme")]' )->length );
		$this->assertSame( 0, $xpath->query( '//*[@class="trasparenza-difetto"]' )->length );
		$this->assertSame( 'draft', get_post_status( $voce ) );
	}

	/**
	 * T-43: un dato difforme si salva con i difetti, ciascuno accanto al suo campo.
	 */
	public function test_t43_difetti_accanto_ai_campi(): void {
		$voce  = $this->voce();
		$campi = self::inviato( self::esempio( 'art.13-pa-YYYYMMDD-YYYYMMDD.v1.0.json' ) );

		unset( $campi['$schema'] );

		$campi['intestazione']['dataPrimaPubblicazione'] = '2026-05-22';

		$this->lega( $voce, 'art_13' );
		$this->salva( $voce, array( SchedaVoce::CAMPO_DATO => $campi ) );

		$esito = SchedaVoce::esito( $voce );

		$this->assertFalse( $esito['conforme'] );
		$this->assertContains( '/intestazione/dataPrimaPubblicazione', array_column( $esito['difetti'], 'percorso' ) );
		$this->assertSame( '2026-05-22', self::salvato( $voce )->intestazione->dataPrimaPubblicazione, 'Il dato difforme si salva comunque.' );
		$this->assertSame( 'draft', get_post_status( $voce ) );

		$xpath   = self::riquadro( $voce );
		$accanto = $xpath->query( '//*[@data-percorso="/intestazione/dataPrimaPubblicazione"]//*[@class="trasparenza-difetto"]' );

		$this->assertSame( 1, $accanto->length, 'L\'errore compare accanto al campo della data.' );

		$difetto = array_values( array_filter( $esito['difetti'], static fn ( $d ) => '/intestazione/dataPrimaPubblicazione' === $d['percorso'] ) )[0];

		$this->assertSame( ModuloSchema::messaggio( $difetto ), $accanto->item( 0 )->textContent );
		$this->assertStringStartsWith( 'Il valore non', $accanto->item( 0 )->textContent, 'Il messaggio e in italiano.' );
		$this->assertSame( 0, $xpath->query( '//*[@data-percorso="/intestazione/dataUltimaModifica"]//*[@class="trasparenza-difetto"]' )->length, 'Il campo giusto non ha errori.' );
		$this->assertSame( 1, $xpath->query( '//*[contains(@class, "trasparenza-esito-difforme")]' )->length );
	}

	/**
	 * T-43: un campo lasciato vuoto non entra nel dato.
	 */
	public function test_t43_campo_vuoto_assente(): void {
		$voce  = $this->voce();
		$campi = self::inviato( self::esempio( 'art.13-pa-YYYYMMDD-YYYYMMDD.v1.0.json' ) );

		unset( $campi['$schema'] );

		$campi['orgPubblicheAmministrazioni']['organi'][0]['uffici'][0]['contatti']['recapitoTelefonico'] = '';
		$campi['orgPubblicheAmministrazioni']['organi'][0]['uffici'][]                                    = array( 'denominazione' => '   ' );

		$this->lega( $voce, 'art_13' );
		$this->salva( $voce, array( SchedaVoce::CAMPO_DATO => $campi ) );

		$dato   = self::salvato( $voce );
		$uffici = $dato->orgPubblicheAmministrazioni->organi[0]->uffici;

		$this->assertFalse( property_exists( $uffici[0]->contatti, 'recapitoTelefonico' ), 'Un campo vuoto non diventa un testo vuoto.' );
		$this->assertCount( count( self::esempio( 'art.13-pa-YYYYMMDD-YYYYMMDD.v1.0.json' )->orgPubblicheAmministrazioni->organi[0]->uffici ), $uffici, 'Un elemento tutto vuoto non entra.' );
	}

	/**
	 * T-44: la voce resta con la versione con cui e nata, e il modulo non la cambia.
	 */
	public function test_t44_versione_fissata_alla_raccolta(): void {
		SchedaVoce::fissa_file( __DIR__ . '/dati/schemi/catalogo-ripubblicato.json' );

		// Una voce raccolta quando valeva la prima pubblicazione.
		$vecchia = $this->voce();

		update_post_meta( $vecchia, SchedaVoce::META_OBBLIGO, 'finto' );
		update_post_meta( $vecchia, SchedaVoce::META_VERSIONE, '1.0@2019-12-01' );

		// Valido per la prima, non per la ripubblicata, che oggi e in vigore.
		$campi = array(
			'zafferanoTitolo'   => 'Titolo lungo',
			'zafferanoQuantita' => '50',
		);

		$this->salva(
			$vecchia,
			array(
				SchedaVoce::CAMPO_OBBLIGO => 'altro',
				'trasparenza_pa_versione' => '1.0@2026-06-10',
				SchedaVoce::CAMPO_DATO    => $campi,
			)
		);

		$this->assertSame( 'finto', get_post_meta( $vecchia, SchedaVoce::META_OBBLIGO, true ), 'Il modulo non cambia l\'obbligo.' );
		$this->assertSame( '1.0@2019-12-01', get_post_meta( $vecchia, SchedaVoce::META_VERSIONE, true ), 'Il modulo non cambia la versione.' );
		$this->assertTrue( SchedaVoce::esito( $vecchia )['conforme'], 'Si controlla con la versione della voce.' );

		$xpath = self::riquadro( $vecchia );

		$this->assertStringContainsString( 'versione 1.0 del 01/12/2019', $xpath->query( '//*[@class="trasparenza-versione"]' )->item( 0 )->textContent );
		$this->assertSame( 0, $xpath->query( '//select[@name="' . SchedaVoce::CAMPO_OBBLIGO . '"]' )->length, 'Una voce legata non offre la scelta dell\'obbligo.' );

		// Una voce nuova si lega alla versione in vigore oggi, e lo stesso dato non le basta.
		$nuova = $this->voce();

		$this->lega( $nuova, 'finto' );
		$this->assertSame( '1.0@2026-06-10', get_post_meta( $nuova, SchedaVoce::META_VERSIONE, true ) );

		$this->salva( $nuova, array( SchedaVoce::CAMPO_DATO => $campi ) );

		$this->assertFalse( SchedaVoce::esito( $nuova )['conforme'] );
	}

	/**
	 * T-44: un obbligo che non e nel catalogo non lega la voce.
	 */
	public function test_t44_obbligo_sconosciuto(): void {
		$voce = $this->voce();

		$this->salva( $voce, array( SchedaVoce::CAMPO_OBBLIGO => 'inventato' ) );

		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ) );
		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_VERSIONE, true ) );
	}

	/**
	 * T-44: se la versione della voce sparisce dal catalogo, il dato resta com'e.
	 */
	public function test_t44_versione_non_piu_nel_catalogo(): void {
		$voce = $this->voce();

		update_post_meta( $voce, SchedaVoce::META_OBBLIGO, 'art_13' );
		update_post_meta( $voce, SchedaVoce::META_VERSIONE, '0.9@2001-01-01' );
		update_post_meta( $voce, SchedaVoce::META_DATO, '{"prima":"com era"}' );

		$this->salva( $voce, array( SchedaVoce::CAMPO_DATO => array( 'altro' => 'nuovo' ) ) );

		$this->assertSame( '{"prima":"com era"}', get_post_meta( $voce, SchedaVoce::META_DATO, true ) );
		$this->assertSame( 0, self::riquadro( $voce )->query( '//input[starts-with(@name, "' . SchedaVoce::CAMPO_DATO . '")]' )->length );
		$this->assertSame( 1, self::riquadro( $voce )->query( '//*[contains(@class, "trasparenza-schema-mancante")]' )->length );
	}

	/**
	 * T-45: senza nonce valido o senza permesso non si scrive niente.
	 */
	public function test_t45_nonce_e_permesso(): void {
		$voce  = $this->voce();
		$campi = array( SchedaVoce::CAMPO_OBBLIGO => 'art_13' );

		$this->salva( $voce, $campi, 'nonce-sbagliato' );
		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ), 'Nonce sbagliato.' );

		// Senza il nonce del tutto.
		$_POST = wp_slash( $campi );
		wp_update_post(
			array(
				'ID'         => $voce,
				'post_title' => 'Voce di prova',
			)
		);
		$_POST = array();

		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ), 'Nonce assente.' );

		// Un Redattore di WordPress, con un nonce valido suo, non ha il permesso sulla voce.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->salva( $voce, $campi );
		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ), 'Senza permesso.' );

		// La prova di controllo: con nonce e permesso si scrive.
		wp_set_current_user( $this->amministratore );

		$this->salva( $voce, $campi );
		$this->assertSame( 'art_13', get_post_meta( $voce, SchedaVoce::META_OBBLIGO, true ) );
	}

	/**
	 * T-45: senza nonce valido non si scrive nemmeno il dato di una voce gia legata.
	 */
	public function test_t45_dato_senza_nonce(): void {
		$voce = $this->voce();

		$this->lega( $voce, 'art_13' );
		$this->salva( $voce, array( SchedaVoce::CAMPO_DATO => array( 'intestazione' => array( 'dataUltimaModifica' => '01/01/2026' ) ) ), 'nonce-sbagliato' );

		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_DATO, true ) );
		$this->assertSame( '', get_post_meta( $voce, SchedaVoce::META_ESITO, true ) );
	}

	/**
	 * T-45: i marcatori HTML escono come testo, non come marcatori.
	 */
	public function test_t45_marcatori_come_testo(): void {
		$voce     = $this->voce();
		$attacco  = '<script>alert(1)</script>';
		$percorso = SchedaVoce::CAMPO_DATO . '[intestazione][amministrazione][denominazione]';

		$this->lega( $voce, 'art_13' );

		// In ingresso dal modulo, i marcatori si tolgono.
		$this->salva( $voce, array( SchedaVoce::CAMPO_DATO => array( 'intestazione' => array( 'amministrazione' => array( 'denominazione' => $attacco . 'Ente di prova' ) ) ) ) );

		$this->assertStringNotContainsString( '<script', (string) get_post_meta( $voce, SchedaVoce::META_DATO, true ) );

		// Un dato arrivato in altro modo, con i marcatori dentro, si mostra come testo.
		update_post_meta( $voce, SchedaVoce::META_DATO, wp_slash( (string) wp_json_encode( array( 'intestazione' => array( 'amministrazione' => array( 'denominazione' => $attacco ) ) ) ) ) );

		$html  = SchedaVoce::riquadro( get_post( $voce ) );
		$xpath = self::riquadro( $voce );

		$this->assertStringNotContainsString( $attacco, $html );
		$this->assertSame( 0, $xpath->query( '//script' )->length );
		$this->assertSame( $attacco, self::valore_mostrato( $xpath, $percorso ) );
	}
}
