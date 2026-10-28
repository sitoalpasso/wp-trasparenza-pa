<?php
/**
 * Il riquadro dei dati nella schermata della voce, e il suo salvataggio.
 *
 * Righe di collaudo T-41..T-45.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Dati della voce: scelta dell'obbligo, modulo, esito del controllo.
 *
 * La voce si lega a un obbligo e alla versione del suo schema in vigore il
 * giorno della scelta, e da quel momento il modulo non li cambia piu: un dato
 * si ricontrolla sempre con la versione con cui e nato. Il dato si salva anche
 * quando non rispetta lo schema, insieme ai suoi difetti, perche la voce resta
 * in bozza e chi compila deve poterlo correggere.
 */
final class SchedaVoce {

	/**
	 * Azione del nonce del salvataggio.
	 */
	const NONCE = 'trasparenza_pa_salva_voce';

	/**
	 * Campo inviato con il nonce.
	 */
	const CAMPO_NONCE = 'trasparenza_pa_nonce';

	/**
	 * Campo inviato con l'obbligo scelto.
	 */
	const CAMPO_OBBLIGO = 'trasparenza_pa_obbligo';

	/**
	 * Nome di base dei campi del modulo.
	 */
	const CAMPO_DATO = 'trasparenza_pa_dato';

	/**
	 * Campo marcatore, l'ultimo del riquadro: se arriva, l'invio e arrivato intero.
	 */
	const CAMPO_INTERO = 'trasparenza_pa_invio_intero';

	/**
	 * Prefisso del dato temporaneo che conserva l'avviso di invio incompleto per l'utente.
	 */
	const AVVISO_INCOMPLETO = 'trasparenza_pa_invio_incompleto_';

	/**
	 * Quota del limite di campi del server oltre la quale il riquadro avvisa prima dell'invio.
	 */
	const QUOTA_LIMITE = 0.8;

	/**
	 * Metadato con l'obbligo della voce.
	 */
	const META_OBBLIGO = '_trasparenza_pa_obbligo';

	/**
	 * Metadato con il codice della versione dello schema, numero e data.
	 */
	const META_VERSIONE = '_trasparenza_pa_versione';

	/**
	 * Metadato con il dato compilato, in JSON.
	 */
	const META_DATO = '_trasparenza_pa_dato';

	/**
	 * Metadato con l'esito dell'ultimo controllo.
	 */
	const META_ESITO = '_trasparenza_pa_esito';

	/**
	 * Identificativo del riquadro.
	 */
	const ID_RIQUADRO = 'trasparenza-pa-dati';

	/**
	 * File del catalogo da caricare; vuoto per quello distribuito.
	 *
	 * @var string
	 */
	private static $file = '';

	/**
	 * Motore caricato, o il motivo per cui non si e potuto caricare.
	 *
	 * @var MotoreSchemi|WP_Error|null
	 */
	private static $motore = null;

	/**
	 * Limite di campi fissato dalle prove, o nullo per quello del server.
	 *
	 * @var int|null
	 */
	private static $limite_campi = null;

	/**
	 * L'azione del nonce di una voce: un nonce vale per una voce sola.
	 *
	 * @param int $voce Voce.
	 * @return string
	 */
	public static function azione_nonce( int $voce ): string {
		return self::NONCE . ':' . $voce;
	}

	/**
	 * Aggancia il riquadro e il salvataggio a WordPress.
	 */
	public static function aggancia(): void {
		add_action( 'add_meta_boxes_' . TIPO_VOCE, array( self::class, 'aggiungi_riquadro' ) );
		add_action( 'save_post_' . TIPO_VOCE, array( self::class, 'salva' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'mostra_avviso' ) );
	}

	/**
	 * Sceglie il file del catalogo.
	 *
	 * @internal Le prove lo puntano su cataloghi finti.
	 *
	 * @param string $file Percorso del catalogo.
	 */
	public static function fissa_file( string $file ): void {
		self::$file   = $file;
		self::$motore = null;
	}

	/**
	 * Riporta la classe allo stato iniziale, con il catalogo distribuito.
	 */
	public static function azzera(): void {
		self::$file         = '';
		self::$motore       = null;
		self::$limite_campi = null;
	}

	/**
	 * Fissa il limite di campi al posto di quello del server.
	 *
	 * @internal Le prove lo abbassano: il limite del server non si cambia mentre PHP gira.
	 *
	 * @param int|null $limite Limite, o nullo per quello del server.
	 */
	public static function fissa_limite_campi( ?int $limite ): void {
		self::$limite_campi = $limite;
	}

	/**
	 * Quanti campi il server accetta in un invio (max_input_vars di PHP).
	 *
	 * @return int
	 */
	public static function limite_campi(): int {
		if ( null !== self::$limite_campi ) {
			return self::$limite_campi;
		}

		$limite = (int) ini_get( 'max_input_vars' );

		// Il valore predefinito di PHP, se la configurazione non ne dichiara uno.
		return $limite > 0 ? $limite : 1000;
	}

	/**
	 * Mostra una volta, a chi ha salvato, l'avviso di invio incompleto.
	 */
	public static function mostra_avviso(): void {
		$chiave = self::AVVISO_INCOMPLETO . get_current_user_id();
		$limite = get_transient( $chiave );

		if ( false === $limite ) {
			return;
		}

		delete_transient( $chiave );

		printf(
			'<div class="notice notice-error trasparenza-invio-incompleto"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: numero massimo di campi che il server accetta in un invio. */
					__( 'I dati della voce non sono stati salvati: l\'invio è arrivato incompleto, perché il modulo ha più campi di quanti il server ne accetta (max_input_vars, oggi %d). Il dato salvato prima è rimasto com\'era. Per salvare serve alzare quel limite nella configurazione di PHP.', 'trasparenza-pa' ),
					(int) $limite
				)
			)
		);
	}

	/**
	 * Il motore sul catalogo, caricato una volta.
	 *
	 * @return MotoreSchemi|WP_Error
	 */
	public static function motore() {
		if ( null === self::$motore ) {
			$catalogo     = CatalogoSchemi::carica( '' === self::$file ? FILE_CATALOGO_SCHEMI : self::$file );
			self::$motore = is_wp_error( $catalogo ) ? $catalogo : new MotoreSchemi( $catalogo );
		}

		return self::$motore;
	}

	/**
	 * Il dato salvato della voce, decodificato, o nullo.
	 *
	 * @param int $voce Voce.
	 * @return mixed
	 */
	public static function dato( int $voce ) {
		$testo = (string) get_post_meta( $voce, self::META_DATO, true );

		return '' === $testo ? null : json_decode( $testo, false );
	}

	/**
	 * L'esito dell'ultimo controllo, o nullo se il dato salvato non e stato controllato.
	 *
	 * L'esito porta l'impronta del dato da cui e nato: se il dato salvato e
	 * cambiato per un'altra strada, per esempio due salvataggi intrecciati,
	 * l'esito non gli appartiene e il dato risulta non controllato.
	 *
	 * @param int $voce Voce.
	 * @return array{conforme: bool, messaggio: string, difetti: array<int, array{percorso: string, regola: string, messaggio: string}>}|null
	 */
	public static function esito( int $voce ): ?array {
		$esito = get_post_meta( $voce, self::META_ESITO, true );

		if ( ! is_array( $esito ) || ! is_string( $esito['impronta'] ?? null ) || ! hash_equals( $esito['impronta'], self::impronta( (string) get_post_meta( $voce, self::META_DATO, true ) ) ) ) {
			return null;
		}

		return array(
			'conforme'  => true === ( $esito['conforme'] ?? null ),
			'messaggio' => is_string( $esito['messaggio'] ?? null ) ? $esito['messaggio'] : '',
			'difetti'   => is_array( $esito['difetti'] ?? null ) ? $esito['difetti'] : array(),
		);
	}

	/**
	 * Aggiunge il riquadro alla schermata della voce.
	 */
	public static function aggiungi_riquadro(): void {
		add_meta_box( self::ID_RIQUADRO, __( 'Dati della voce', 'trasparenza-pa' ), array( self::class, 'disegna' ), TIPO_VOCE, 'normal', 'high' );
	}

	/**
	 * Disegna il riquadro.
	 *
	 * @param WP_Post $voce Voce.
	 */
	public static function disegna( WP_Post $voce ): void {
		echo self::riquadro( $voce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ogni pezzo e protetto dove si costruisce.
	}

	/**
	 * Il contenuto del riquadro, in HTML.
	 *
	 * @param WP_Post $voce Voce.
	 * @return string
	 */
	public static function riquadro( WP_Post $voce ): string {
		$html   = wp_nonce_field( self::azione_nonce( $voce->ID ), self::CAMPO_NONCE, true, false );
		$motore = self::motore();

		if ( is_wp_error( $motore ) ) {
			return $html . self::mancante( $motore->get_error_message() );
		}

		$obbligo = (string) get_post_meta( $voce->ID, self::META_OBBLIGO, true );

		if ( '' === $obbligo ) {
			return $html . self::scelta_obbligo( $motore );
		}

		$codice   = (string) get_post_meta( $voce->ID, self::META_VERSIONE, true );
		$versione = $motore->catalogo()->versione( $obbligo, $codice );

		if ( null === $versione ) {
			/* translators: 1: obbligo, 2: codice della versione. */
			return $html . self::mancante( sprintf( __( 'Lo schema di questa voce (obbligo %1$s, versione %2$s) non è più nel catalogo del componente: il dato salvato resta com\'è e non si può modificare da qui.', 'trasparenza-pa' ), $obbligo, $codice ) );
		}

		$esito  = self::esito( $voce->ID );
		$modulo = new ModuloSchema( $motore->catalogo()->contenuti( $obbligo, $codice ), $versione['id'] );
		$campi  = $modulo->campi( self::CAMPO_DATO, self::dato( $voce->ID ), null === $esito ? array() : $esito['difetti'] );

		$html .= sprintf(
			'<p class="trasparenza-versione">%s</p>',
			/* translators: 1: titolo dello schema, 2: versione detta a parole, per esempio "versione 1.0 del 10/06/2026". */
			esc_html( sprintf( __( 'Dato raccolto con lo schema "%1$s", %2$s.', 'trasparenza-pa' ), $modulo->titolo(), (string) $motore->descrivi( $obbligo, $codice ) ) )
		);

		/*
		 * Il marcatore va dopo tutti i campi: PHP tiene i campi nell'ordine
		 * dell'invio e scarta quelli oltre il limite, quindi se il marcatore
		 * arriva sono arrivati anche tutti i campi prima di lui.
		 */
		$campi .= sprintf( '<input type="hidden" name="%s" value="1" />', esc_attr( self::CAMPO_INTERO ) );

		return $html . self::riassunto( $esito, $modulo->restanti() ) . self::avviso_limite( substr_count( $campi, 'name="' ) ) . $campi;
	}

	/**
	 * Salva la scelta dell'obbligo o il dato del modulo.
	 *
	 * @param int     $id   Identificativo della voce.
	 * @param WP_Post $voce Voce.
	 */
	public static function salva( int $id, WP_Post $voce ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || TIPO_VOCE !== $voce->post_type ) {
			return;
		}

		if ( ! isset( $_POST[ self::CAMPO_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_NONCE ] ) ), self::azione_nonce( $id ) ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return;
		}

		$motore = self::motore();

		if ( is_wp_error( $motore ) ) {
			return;
		}

		$obbligo = (string) get_post_meta( $id, self::META_OBBLIGO, true );

		/*
		 * Il legame si scrive una volta sola: dopo, nessun valore inviato lo
		 * cambia. Si aggiunge come unico, cosi una richiesta che ha letto i
		 * metadati vuoti mentre un'altra li scriveva non li sovrascrive.
		 */
		if ( '' === $obbligo ) {
			$scelto = isset( $_POST[ self::CAMPO_OBBLIGO ] ) ? sanitize_key( wp_unslash( $_POST[ self::CAMPO_OBBLIGO ] ) ) : '';
			$codice = '' === $scelto ? null : $motore->versione_in_vigore( $scelto );

			if ( null !== $codice && false !== add_post_meta( $id, self::META_OBBLIGO, $scelto, true ) ) {
				add_post_meta( $id, self::META_VERSIONE, $codice, true );
			}

			return;
		}

		$codice   = (string) get_post_meta( $id, self::META_VERSIONE, true );
		$versione = $motore->catalogo()->versione( $obbligo, $codice );

		if ( null === $versione ) {
			return;
		}

		// Senza il marcatore l'invio e stato tagliato: un dato parziale non sostituisce quello salvato.
		if ( ! isset( $_POST[ self::CAMPO_INTERO ] ) ) {
			set_transient( self::AVVISO_INCOMPLETO . get_current_user_id(), self::limite_campi(), 5 * MINUTE_IN_SECONDS );

			return;
		}

		if ( ! isset( $_POST[ self::CAMPO_DATO ] ) ) {
			return;
		}

		$modulo = new ModuloSchema( $motore->catalogo()->contenuti( $obbligo, $codice ), $versione['id'] );
		$dato   = $modulo->dato( wp_unslash( $_POST[ self::CAMPO_DATO ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ogni valore si ripulisce nel modulo, secondo lo schema.
		$esito  = $motore->valida( $obbligo, $codice, $dato );
		$testo  = (string) wp_json_encode( $dato, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		update_post_meta( $id, self::META_DATO, wp_slash( $testo ) );
		update_post_meta(
			$id,
			self::META_ESITO,
			wp_slash(
				array(
					'impronta'  => self::impronta( $testo ),
					'conforme'  => true === $esito,
					'messaggio' => is_wp_error( $esito ) ? $esito->get_error_message() : '',
					'difetti'   => is_wp_error( $esito ) && is_array( $esito->get_error_data() ) ? array_values( $esito->get_error_data() ) : array(),
				)
			)
		);
	}

	/**
	 * L'avviso preventivo, quando i campi del modulo si avvicinano al limite del server.
	 *
	 * @param int $campi Campi del modulo.
	 * @return string
	 */
	private static function avviso_limite( int $campi ): string {
		$limite = self::limite_campi();

		if ( $campi < $limite * self::QUOTA_LIMITE ) {
			return '';
		}

		return sprintf(
			'<div class="notice notice-warning inline trasparenza-limite-campi"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: campi del modulo, 2: limite del server. */
					__( 'Il modulo ha %1$d campi e il server ne accetta %2$d per invio (max_input_vars): oltre il limite il salvataggio non riesce. Conviene alzare il limite nella configurazione di PHP prima di aggiungere altri elementi.', 'trasparenza-pa' ),
					$campi,
					$limite
				)
			)
		);
	}

	/**
	 * L'impronta del dato salvato, per legargli l'esito.
	 *
	 * @param string $testo Dato in JSON, come salvato.
	 * @return string
	 */
	private static function impronta( string $testo ): string {
		return hash( 'sha256', $testo );
	}

	/**
	 * La scelta dell'obbligo, fra quelli che hanno una versione in vigore.
	 *
	 * @param MotoreSchemi $motore Motore.
	 * @return string
	 */
	private static function scelta_obbligo( MotoreSchemi $motore ): string {
		$opzioni = '<option value=""></option>';

		foreach ( $motore->catalogo()->obblighi() as $obbligo ) {
			$codice   = $motore->versione_in_vigore( $obbligo );
			$versione = null === $codice ? null : $motore->catalogo()->versione( $obbligo, $codice );

			if ( null === $versione ) {
				continue;
			}

			$titolo   = ( new ModuloSchema( $motore->catalogo()->contenuti( $obbligo, $codice ), $versione['id'] ) )->titolo();
			$opzioni .= sprintf( '<option value="%1$s">%2$s</option>', esc_attr( $obbligo ), esc_html( $titolo ) );
		}

		return sprintf(
			'<p><label for="%1$s">%2$s</label><br /><select id="%1$s" name="%1$s">%3$s</select></p><p class="description">%4$s</p>',
			esc_attr( self::CAMPO_OBBLIGO ),
			esc_html__( 'Obbligo di pubblicazione', 'trasparenza-pa' ),
			$opzioni,
			esc_html__( 'Scegliere l\'obbligo e salvare la bozza: compare il modulo con i campi del suo schema. Dopo la scelta l\'obbligo e la versione dello schema non si cambiano più.', 'trasparenza-pa' )
		);
	}

	/**
	 * L'esito dell'ultimo controllo, con i difetti che non hanno un campo.
	 *
	 * @param array{conforme: bool, messaggio: string, difetti: array}|null $esito    Esito.
	 * @param array<string, array<int, string>>                             $restanti Messaggi non mostrati accanto a un campo, per percorso.
	 * @return string
	 */
	private static function riassunto( ?array $esito, array $restanti ): string {
		if ( null === $esito ) {
			return '<p class="trasparenza-esito">' . esc_html__( 'Il dato non è ancora stato controllato: compilare i campi e salvare la bozza.', 'trasparenza-pa' ) . '</p>';
		}

		if ( $esito['conforme'] ) {
			return '<div class="notice notice-success inline trasparenza-esito trasparenza-esito-conforme"><p>' . esc_html__( 'Il dato rispetta lo schema.', 'trasparenza-pa' ) . '</p></div>';
		}

		$elenco = '';

		foreach ( $restanti as $percorso => $messaggi ) {
			foreach ( $messaggi as $messaggio ) {
				$elenco .= '<li><code>' . esc_html( (string) $percorso ) . '</code> ' . esc_html( $messaggio ) . '</li>';
			}
		}

		return sprintf(
			'<div class="notice notice-error inline trasparenza-esito trasparenza-esito-difforme"><p>%1$s</p>%2$s</div>',
			esc_html( '' === $esito['messaggio'] ? __( 'Il dato non rispetta lo schema.', 'trasparenza-pa' ) : $esito['messaggio'] ),
			'' === $elenco ? '' : '<ul>' . $elenco . '</ul>'
		);
	}

	/**
	 * L'avviso di schema mancante.
	 *
	 * @param string $motivo Motivo.
	 * @return string
	 */
	private static function mancante( string $motivo ): string {
		return '<div class="notice notice-warning inline trasparenza-schema-mancante"><p>' . esc_html( $motivo ) . '</p></div>';
	}
}
