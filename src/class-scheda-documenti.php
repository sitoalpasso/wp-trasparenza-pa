<?php
/**
 * Il riquadro dei documenti allegati a un contenuto.
 *
 * Righe di collaudo T-67..T-70.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Documenti: deposito attraverso il componente comune, rimozione, traccia nel registro.
 *
 * I file non li scrive questo componente: li deposita il componente comune,
 * nella sua cartella protetta, dopo aver verificato che il server neghi
 * l'accesso diretto. I documenti di un contenuto sono gli allegati depositati
 * cosi con quel contenuto come padre, e non c'e un secondo elenco da tenere
 * allineato. Aggiungere si puo sempre; togliere solo mentre il contenuto non
 * e pubblicato, perche un allegato gia esposto non sparisce con un clic.
 *
 * Le regole delle altre schermate della sezione: nonce legato al contenuto e
 * permesso di modificarlo; un salvataggio alla volta; letture dalla banca
 * dati che distinguono "nessuno" da "lettura fallita"; nessun cambio senza
 * la sua voce di registro.
 */
final class SchedaDocumenti {

	/**
	 * Campo del modulo con il file da aggiungere.
	 */
	const CAMPO_FILE = 'trasparenza_pa_documento';

	/**
	 * Campo del modulo con i documenti da togliere.
	 */
	const CAMPO_TOGLI = 'trasparenza_pa_documenti_togli';

	/**
	 * Campo del nonce.
	 */
	const CAMPO_NONCE = 'trasparenza_pa_nonce_documenti';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'documento_contenuto';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata; seguono utente e contenuto.
	 */
	const ESITO = 'trasparenza_pa_esito_documenti_';

	/**
	 * Identificativo del riquadro.
	 */
	const ID_RIQUADRO = 'trasparenza-pa-documenti';

	/**
	 * Aggancia il riquadro, il modulo che porta i file, il salvataggio e l'esito.
	 */
	public static function aggancia(): void {
		add_action( 'add_meta_boxes_' . TIPO_CONTENUTO, array( self::class, 'aggiungi_riquadro' ) );
		add_action( 'post_edit_form_tag', array( self::class, 'modulo' ) );
		add_action( 'save_post_' . TIPO_CONTENUTO, array( self::class, 'da_save_post' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'mostra_esito' ) );
	}

	/**
	 * Azione del nonce, legata al contenuto.
	 *
	 * @param int $contenuto Identificativo del contenuto.
	 * @return string
	 */
	public static function azione_nonce( int $contenuto ): string {
		return 'trasparenza_pa_documenti_' . $contenuto;
	}

	/**
	 * Aggancio a `post_edit_form_tag`: il modulo della schermata del contenuto porta i file.
	 *
	 * @param WP_Post|mixed $contenuto Contenuto della schermata.
	 */
	public static function modulo( $contenuto ): void {
		if ( $contenuto instanceof WP_Post && TIPO_CONTENUTO === $contenuto->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/**
	 * I documenti di un contenuto, dal piu vecchio.
	 *
	 * Gli allegati con il contenuto come padre e la marca di deposito del
	 * componente comune. Un allegato caricato per altre vie, che il componente
	 * comune non protegge, non e un allegato del contenuto.
	 *
	 * @param int $contenuto Identificativo del contenuto.
	 * @return array<int, int>|WP_Error
	 */
	public static function documenti( int $contenuto ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve sapere se la lettura e fallita, cosa che get_children() non dice.
		$righe = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment' ORDER BY ID", $contenuto ) );

		if ( '' !== $wpdb->last_error ) {
			return new WP_Error( 'trasparenza_lettura_fallita', __( 'I documenti del contenuto non si sono potuti leggere dalla banca dati.', 'trasparenza-pa' ) );
		}

		return array_values( array_filter( array_map( 'intval', $righe ), 'conformita_core_allegato_protetto' ) );
	}

	/**
	 * Aggiunge il riquadro alla schermata del contenuto.
	 */
	public static function aggiungi_riquadro(): void {
		add_meta_box( self::ID_RIQUADRO, __( 'Documenti', 'trasparenza-pa' ), array( self::class, 'disegna' ), TIPO_CONTENUTO, 'normal', 'default' );
	}

	/**
	 * Stampa il riquadro.
	 *
	 * @param WP_Post $contenuto Contenuto.
	 */
	public static function disegna( WP_Post $contenuto ): void {
		echo self::riquadro( $contenuto ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escaping in riquadro().
	}

	/**
	 * Il riquadro come testo, con escaping di ogni valore.
	 *
	 * @param WP_Post $contenuto Contenuto.
	 * @return string
	 */
	public static function riquadro( WP_Post $contenuto ): string {
		$documenti = self::documenti( $contenuto->ID );
		$togliere  = 'publish' !== $contenuto->post_status;

		$html = wp_nonce_field( self::azione_nonce( $contenuto->ID ), self::CAMPO_NONCE, false, false );

		if ( is_wp_error( $documenti ) ) {
			$html .= '<p>' . esc_html( $documenti->get_error_message() ) . '</p>';
		} elseif ( array() === $documenti ) {
			$html .= '<p>' . esc_html__( 'Nessun allegato.', 'trasparenza-pa' ) . '</p>';
		} else {
			$html .= '<ul>';

			foreach ( $documenti as $allegato ) {
				$nome      = wp_basename( (string) get_attached_file( $allegato ) );
				$indirizzo = conformita_core_indirizzo_consegna_amministrativa( $allegato );
				$html     .= '<li>';
				$html     .= is_string( $indirizzo ) ? '<a href="' . esc_url( $indirizzo ) . '">' . esc_html( $nome ) . '</a>' : esc_html( $nome );

				if ( $togliere ) {
					$html .= ' <label><input type="checkbox" name="' . esc_attr( self::CAMPO_TOGLI ) . '[]" value="' . esc_attr( (string) $allegato ) . '"> ' . esc_html__( 'Togli', 'trasparenza-pa' ) . '</label>';
				}

				$html .= '</li>';
			}

			$html .= '</ul>';
		}

		if ( ! $togliere && is_array( $documenti ) && array() !== $documenti ) {
			$html .= '<p>' . esc_html__( 'Il contenuto è pubblicato: i documenti non si tolgono. Per togliere un allegato pubblicato per errore, rimettere il contenuto in bozza.', 'trasparenza-pa' ) . '</p>';
		}

		$html .= '<p><label for="trasparenza-pa-allegato">' . esc_html__( 'Aggiungi un allegato', 'trasparenza-pa' ) . '</label></p>';
		$html .= '<input type="file" id="trasparenza-pa-allegato" name="' . esc_attr( self::CAMPO_FILE ) . '">';
		$html .= '<p>' . esc_html__( 'L\'allegato si aggiunge salvando il contenuto.', 'trasparenza-pa' ) . '</p>';

		return $html;
	}

	/**
	 * Aggancio a `save_post`: aggiunge e toglie, e conserva l'esito per la schermata.
	 *
	 * Si fa qualcosa solo se la richiesta viene dal riquadro.
	 *
	 * @param int     $id        Identificativo del contenuto.
	 * @param WP_Post $contenuto Contenuto.
	 */
	public static function da_save_post( int $id, WP_Post $contenuto ): void {
		unset( $contenuto );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce lo verifica applica(), prima di leggere qualunque altro campo.
		if ( ! isset( $_POST[ self::CAMPO_NONCE ] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- nonce come sopra; il file lo controlla il componente comune.
		$file  = isset( $_FILES[ self::CAMPO_FILE ] ) && is_array( $_FILES[ self::CAMPO_FILE ] ) ? $_FILES[ self::CAMPO_FILE ] : array();
		$esiti = self::applica( $id, wp_unslash( $_POST ), $file ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- come sopra.

		if ( array() !== $esiti ) {
			set_transient( self::chiave_esito( $id ), $esiti, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Chiave del dato temporaneo dell'esito, per l'utente corrente e un contenuto.
	 *
	 * @param int $contenuto Identificativo del contenuto.
	 * @return string
	 */
	public static function chiave_esito( int $contenuto ): string {
		return self::ESITO . get_current_user_id() . '_' . $contenuto;
	}

	/**
	 * Applica una richiesta del riquadro: prima le rimozioni, poi l'aggiunta.
	 *
	 * @param int                  $id        Identificativo del contenuto.
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @param array<string, mixed> $file      Il file caricato, nella forma di `$_FILES`, o vuoto.
	 * @return array<int, array{codice: string, messaggio: string}> Un esito per operazione; vuoto se non si e chiesto niente.
	 */
	public static function applica( int $id, array $richiesta, array $file ): array {
		$nonce = $richiesta[ self::CAMPO_NONCE ] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::azione_nonce( $id ) ) ) {
			return array( self::esito( 'nonce', __( 'Documenti non modificati: la richiesta è scaduta o non viene da questo contenuto. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) ) );
		}

		$togli = $richiesta[ self::CAMPO_TOGLI ] ?? array();

		if ( ! is_array( $togli ) ) {
			return array( self::esito( 'dati', __( 'Documenti non modificati: la richiesta non diceva quali documenti togliere. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) ) );
		}

		$esiti  = array();
		$numeri = array_map(
			static function ( $allegato ): int {
				return is_string( $allegato ) && 1 === preg_match( '/\A[1-9]\d{0,18}\z/', $allegato ) ? (int) $allegato : 0;
			},
			$togli
		);

		foreach ( array_unique( $numeri ) as $numero ) {
			$esiti[] = 0 === $numero
				? self::esito( 'dati', __( 'Allegato non tolto: la richiesta non indicava un allegato valido.', 'trasparenza-pa' ) )
				: self::togli( $id, $numero );
		}

		$errore_caricamento = $file['error'] ?? UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE !== $errore_caricamento ) {
			$esiti[] = UPLOAD_ERR_OK === $errore_caricamento
				? self::deposita( $id, $file, 'caricamento' )
				: self::esito( 'caricamento', __( 'Allegato non aggiunto: il caricamento del file non è riuscito, per esempio perché il file supera la dimensione ammessa dal server.', 'trasparenza-pa' ) );
		}

		return $esiti;
	}

	/**
	 * Aggiunge un allegato a un contenuto.
	 *
	 * Il registro non puo venire prima del file, perche nome e impronta li
	 * conosce solo il deposito. Quindi il file si deposita, la voce di registro
	 * si scrive, e se il registro rifiuta l'allegato si cancella: nessun
	 * allegato resta senza la sua traccia.
	 *
	 * @param int                  $id      Identificativo del contenuto.
	 * @param array<string, mixed> $file    Il file, nella forma di `$_FILES`.
	 * @param string               $origine `caricamento` oppure `percorso_locale`, come per il componente comune.
	 * @return array{codice: string, messaggio: string}
	 */
	public static function deposita( int $id, array $file, string $origine ): array {
		$permesso = self::permesso( $id );

		if ( null !== $permesso ) {
			return $permesso;
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Allegato non aggiunto: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			$allegato = conformita_core_deposita_allegato( $id, $file, array( 'origine' => $origine ) );

			if ( is_wp_error( $allegato ) ) {
				/* translators: %s: motivo riportato dal componente comune. */
				return self::esito( 'deposito', sprintf( __( 'Allegato non aggiunto. %s', 'trasparenza-pa' ), $allegato->get_error_message() ) );
			}

			$allegato = (int) $allegato;
			$nome     = wp_basename( (string) get_attached_file( $allegato ) );
			$traccia  = self::registra( $id, 'aggiunto', $allegato, $nome );

			if ( is_wp_error( $traccia ) ) {
				$motivo = $traccia->get_error_message();
				$esito  = self::cancella( $allegato );

				if ( 'riga' === $esito ) {
					/* translators: %s: motivo riportato dal registro. */
					return self::esito( 'registro', sprintf( __( 'Il registro delle modifiche non ha accettato l\'allegato, e l\'allegato non si è potuto togliere: resta senza traccia nel registro. Toglierlo a mano. Motivo: %s', 'trasparenza-pa' ), $motivo ) );
				}

				if ( 'file' === $esito ) {
					/* translators: 1: nome del file, 2: motivo riportato dal registro. */
					return self::esito( 'registro', sprintf( __( 'Allegato non aggiunto: il registro delle modifiche non ha accettato l\'aggiunta. L\'allegato è tolto dall\'elenco, ma il file %1$s è rimasto nella cartella protetta, dove nessuno lo scarica: va tolto a mano dal server. Motivo: %2$s', 'trasparenza-pa' ), $nome, $motivo ) );
				}

				/* translators: %s: motivo riportato dal registro. */
				return self::esito( 'registro', sprintf( __( 'Allegato non aggiunto: il registro delle modifiche non ha accettato l\'aggiunta, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $motivo ) );
			}

			/* translators: %s: nome del file. */
			return self::esito( 'salvato', sprintf( __( 'Allegato aggiunto: %s.', 'trasparenza-pa' ), $nome ) );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Toglie un allegato da un contenuto non pubblicato, file compreso.
	 *
	 * @param int $id        Identificativo del contenuto.
	 * @param int $allegato Identificativo dell'allegato.
	 * @return array{codice: string, messaggio: string}
	 */
	public static function togli( int $id, int $allegato ): array {
		$permesso = self::permesso( $id );

		if ( null !== $permesso ) {
			return $permesso;
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Allegato non tolto: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			$stato     = self::stato( $id );
			$documenti = self::documenti( $id );

			if ( null === $stato || is_wp_error( $documenti ) ) {
				return self::esito( 'lettura', __( 'Allegato non tolto: lo stato del contenuto o i suoi documenti non si sono potuti leggere dalla banca dati. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
			}

			if ( 'publish' === $stato ) {
				return self::esito( 'pubblicato', __( 'Allegato non tolto: il contenuto è pubblicato. Per togliere un allegato pubblicato per errore, rimettere il contenuto in bozza.', 'trasparenza-pa' ) );
			}

			if ( ! in_array( $allegato, $documenti, true ) ) {
				return self::esito( 'dati', __( 'Allegato non tolto: non è fra i documenti di questo contenuto, forse perché qualcun altro lo ha già tolto. Ricaricare la schermata.', 'trasparenza-pa' ) );
			}

			$nome    = wp_basename( (string) get_attached_file( $allegato ) );
			$traccia = self::registra( $id, 'tolto', $allegato, $nome );

			if ( is_wp_error( $traccia ) ) {
				return self::esito(
					'registro',
					/* translators: %s: motivo riportato dal registro. */
					sprintf( __( 'Allegato non tolto: il registro delle modifiche non ha accettato la rimozione, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() )
				);
			}

			$esito = self::cancella( $allegato );

			if ( 'riga' === $esito ) {
				return self::esito( 'scrittura', __( 'Non risulta che l\'allegato sia stato tolto: controllare l\'elenco dei documenti. Il registro delle modifiche contiene una rimozione che potrebbe non essere avvenuta.', 'trasparenza-pa' ) );
			}

			if ( 'file' === $esito ) {
				/*
				 * La voce di prima dice "tolto", ed e vero per l'elenco. Il file
				 * rimasto si annota con una voce che la richiama, cosi il
				 * registro dice tutto quello che e successo.
				 */
				$annotata = conformita_core_registra_voce(
					array(
						'sezione'     => SEZIONE,
						'contenuto'   => $id,
						'azione'      => self::AZIONE_REGISTRO,
						'riferimento' => (int) $traccia,
						'dettagli'    => array(
							'operazione' => 'file_rimasto',
							'allegato'   => $allegato,
							'nome'       => $nome,
						),
					)
				);

				return self::esito(
					'file',
					sprintf(
						/* translators: 1: nome del file, 2: frase sul registro, oppure vuoto. */
						__( 'L\'allegato è tolto dall\'elenco, ma il file %1$s è rimasto nella cartella protetta, dove nessuno lo scarica: va tolto a mano dal server.%2$s', 'trasparenza-pa' ),
						$nome,
						is_wp_error( $annotata ) ? '' : ' ' . __( 'Il registro delle modifiche lo annota.', 'trasparenza-pa' )
					)
				);
			}

			/* translators: %s: nome del file. */
			return self::esito( 'salvato', sprintf( __( 'Allegato tolto: %s.', 'trasparenza-pa' ), $nome ) );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Il rifiuto se il contenuto non e del tipo o l'utente non puo modificarlo, altrimenti nullo.
	 *
	 * @param int $id Identificativo del contenuto.
	 * @return array{codice: string, messaggio: string}|null
	 */
	private static function permesso( int $id ): ?array {
		$contenuto = get_post( $id );

		if ( ! $contenuto instanceof WP_Post || TIPO_CONTENUTO !== $contenuto->post_type || ! current_user_can( 'edit_post', $id ) ) {
			return self::esito( 'permesso', __( 'Documenti non modificati: non avete il permesso di modificare questo contenuto.', 'trasparenza-pa' ) );
		}

		return null;
	}

	/**
	 * La voce di registro di un'aggiunta o di una rimozione.
	 *
	 * @param int    $id         Identificativo del contenuto.
	 * @param string $operazione `aggiunto` oppure `tolto`.
	 * @param int    $allegato  Identificativo dell'allegato.
	 * @param string $nome       Nome del file.
	 * @return int|WP_Error
	 */
	private static function registra( int $id, string $operazione, int $allegato, string $nome ) {
		$impronta = conformita_core_impronta_allegato( $allegato );

		return conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $id,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => array(
					'operazione' => $operazione,
					'allegato'   => $allegato,
					'nome'       => $nome,
					'impronta'   => is_array( $impronta ) ? $impronta['algoritmo'] . ':' . $impronta['valore'] : '',
				),
			)
		);
	}

	/**
	 * Lo stato del contenuto letto dalla banca dati, o nullo se la lettura e fallita.
	 *
	 * @param int $id Identificativo del contenuto.
	 * @return string|null
	 */
	private static function stato( int $id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- lo stato decide una cancellazione: si legge dalla banca dati, non dalla memoria.
		$stato = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );

		return '' === $wpdb->last_error && is_string( $stato ) ? $stato : null;
	}

	/**
	 * Cancella un allegato con il suo file, e controlla che siano spariti tutti e due.
	 *
	 * WordPress puo cancellare la riga e non il file, per esempio con la
	 * cartella non scrivibile: senza la riga nessuna schermata ritrova piu quel
	 * file, quindi il percorso si prende prima e il file si controlla dopo.
	 *
	 * @param int $allegato Identificativo dell'allegato.
	 * @return string `tolto`; `riga` se la riga c'e ancora; `file` se la riga e sparita e il file no.
	 */
	private static function cancella( int $allegato ): string {
		$percorso = (string) get_attached_file( $allegato, true );

		wp_delete_attachment( $allegato, true );

		if ( self::esiste( $allegato ) ) {
			return 'riga';
		}

		clearstatcache();

		return '' !== $percorso && file_exists( $percorso ) ? 'file' : 'tolto';
	}

	/**
	 * L'allegato c'e ancora nella banca dati.
	 *
	 * Una lettura fallita vale come "c'e ancora": e il caso che chiede di
	 * controllare a mano.
	 *
	 * @param int $allegato Identificativo dell'allegato.
	 * @return bool
	 */
	private static function esiste( int $allegato ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- serve sapere se la cancellazione e avvenuta davvero.
		$trovato = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $allegato ) );

		return '' !== $wpdb->last_error || '0' !== (string) $trovato;
	}

	/**
	 * Mostra gli esiti, una volta, a chi ha salvato, sulla schermata di quel contenuto.
	 */
	public static function mostra_esito(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$contenuto = get_post();

		if ( null === $schermata || 'post' !== $schermata->base || TIPO_CONTENUTO !== $schermata->post_type || ! $contenuto instanceof WP_Post || TIPO_CONTENUTO !== $contenuto->post_type ) {
			return;
		}

		$chiave = self::chiave_esito( $contenuto->ID );
		$esiti  = get_transient( $chiave );

		if ( ! is_array( $esiti ) ) {
			return;
		}

		delete_transient( $chiave );

		foreach ( $esiti as $esito ) {
			if ( is_array( $esito ) && isset( $esito['codice'], $esito['messaggio'] ) ) {
				printf(
					'<div class="notice %1$s"><p>%2$s</p></div>',
					'salvato' === $esito['codice'] ? 'notice-success' : 'notice-error',
					esc_html( (string) $esito['messaggio'] )
				);
			}
		}
	}

	/**
	 * Un esito, nella forma che la schermata legge.
	 *
	 * @param string $codice    Codice dell'esito.
	 * @param string $messaggio Messaggio per chi ha salvato.
	 * @return array{codice: string, messaggio: string}
	 */
	private static function esito( string $codice, string $messaggio ): array {
		return array(
			'codice'    => $codice,
			'messaggio' => $messaggio,
		);
	}
}
