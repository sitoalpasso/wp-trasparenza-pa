<?php
/**
 * La schermata in cui l'ente dichiara lo stato di ogni voce.
 *
 * Righe di collaudo T-48..T-53.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stato delle voci: elenco, scelta, salvataggio con traccia nel registro.
 *
 * Il salvataggio e una funzione che riceve la richiesta e restituisce l'esito,
 * separata dall'aggancio che risponde al modulo: cosi le prove la chiamano
 * come la chiamerebbe il modulo, nonce e permesso compresi.
 */
final class SchermataStati {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-stati';

	/**
	 * Azione del modulo e del nonce.
	 */
	const AZIONE = 'trasparenza_pa_stati';

	/**
	 * Nome del campo che porta gli stati scelti, una coppia voce e stato.
	 */
	const CAMPO = 'stati';

	/**
	 * Nome del campo che porta lo stato di ogni voce come il modulo lo ha mostrato.
	 */
	const CAMPO_PARTENZA = 'partenza';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'stato_obbligo';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 */
	const ESITO = 'trasparenza_pa_esito_stati_';

	/**
	 * Secondi di attesa del blocco prima di rinunciare.
	 */
	const ATTESA_BLOCCO = 5;

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ) );
		add_action( 'admin_post_' . self::AZIONE, array( self::class, 'da_admin_post' ) );
	}

	/**
	 * Voce di menu, solo se la sezione e nostra.
	 *
	 * WordPress mostra la voce soltanto a chi ha il permesso, e a chi apre
	 * l'indirizzo senza averlo risponde che non e consentito.
	 */
	public static function da_admin_menu(): void {
		if ( ! Avvio::registrata() ) {
			return;
		}

		add_menu_page(
			__( 'Stato delle voci', 'trasparenza-pa' ),
			__( 'Amministrazione trasparente', 'trasparenza-pa' ),
			Permessi::AMMINISTRAZIONE,
			self::PAGINA,
			array( self::class, 'mostra' ),
			'dashicons-visibility'
		);
	}

	/**
	 * Risposta al modulo.
	 *
	 * Senza permesso o senza nonce valido la richiesta si ferma qui con un
	 * rifiuto: rimandarla alla schermata, che quell'utente non puo aprire,
	 * nasconderebbe il motivo.
	 */
	public static function da_admin_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce lo verifica salva(), prima di leggere qualunque altro campo.
		$esito = self::salva( wp_unslash( $_POST ) );

		if ( in_array( $esito['codice'], array( 'permesso', 'nonce' ), true ) ) {
			wp_die( esc_html( $esito['messaggio'] ), '', array( 'response' => 403 ) );
		}

		set_transient( self::ESITO . get_current_user_id(), $esito, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGINA ) );
		exit;
	}

	/**
	 * Salva gli stati scelti.
	 *
	 * Quattro regole, nell'ordine:
	 *
	 * 1. Permesso e nonce prima di tutto: senza, non si legge nemmeno il resto.
	 * 2. **Tutto controllato prima di scrivere.** Una sola voce inesistente o un
	 *    solo valore fuori dai tre e non si scrive niente: una richiesta mezza
	 *    buona e una richiesta sbagliata, e salvarne meta lascerebbe credere di
	 *    aver salvato tutto. Un campo vuoto vuol dire "non cambiare": una voce
	 *    gia dichiarata non torna non dichiarata.
	 * 3. **Un salvataggio alla volta**: un blocco della banca dati tiene fuori
	 *    gli altri finche questo non ha finito.
	 * 4. **Prima il registro, poi lo stato**, una voce alla volta. Se il
	 *    registro rifiuta un cambio, quel cambio e i successivi non si fanno:
	 *    un cambio senza traccia non deve esistere. I precedenti restano, ognuno
	 *    con la sua traccia, e l'esito dice quali sono.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, salvate: array<int, string>}
	 */
	public static function salva( array $richiesta ): array {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di dichiarare lo stato delle voci.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: nessuno stato salvato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return self::esito( 'albero', __( 'Nessuno stato salvato: l\'albero delle voci non è disponibile.', 'trasparenza-pa' ) );
		}

		if ( ! self::blocca() ) {
			return self::esito( 'occupato', __( 'Nessuno stato salvato: un altro salvataggio degli stati è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			return self::scrivi( $albero, $richiesta[ self::CAMPO ] ?? array(), $richiesta[ self::CAMPO_PARTENZA ] ?? array() );
		} finally {
			self::sblocca();
		}
	}

	/**
	 * Controlla e scrive i cambi, con il blocco gia preso.
	 *
	 * Gli stati attuali si rileggono dalla banca dati e non dalla memoria della
	 * richiesta: chi ha salvato un attimo prima, da un'altra richiesta, ha gia
	 * cambiato il punto di partenza, e il "prima" scritto nel registro deve
	 * essere quello vero. Se la lettura fallisce non si scrive niente: partire
	 * da un elenco vuoto cancellerebbe le dichiarazioni delle altre voci.
	 *
	 * @param Alberatura $albero   Albero di riferimento.
	 * @param mixed      $inviati  Coppie voce e stato, come arrivano dal modulo.
	 * @param mixed      $partenze Coppie voce e stato mostrato dal modulo.
	 * @return array{codice: string, messaggio: string, salvate: array<int, string>}
	 */
	private static function scrivi( Alberatura $albero, $inviati, $partenze ): array {
		$attuali = StatiObbligo::conservati();

		if ( is_wp_error( $attuali ) ) {
			return self::esito( 'lettura', __( 'Nessuno stato salvato: gli stati attuali non si sono potuti leggere dalla banca dati, e salvare senza conoscerli potrebbe cancellare quelli delle altre voci. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
		}

		$cambi = self::cambi( $albero, $inviati, $partenze, $attuali );

		if ( is_wp_error( $cambi ) ) {
			return self::esito( 'trasparenza_stati_conflitto' === $cambi->get_error_code() ? 'conflitto' : 'dati', $cambi->get_error_message() );
		}

		$salvate = array();

		foreach ( $cambi as $voce => list( $prima, $dopo ) ) {
			$traccia = conformita_core_registra_voce(
				array(
					'sezione'  => SEZIONE,
					'azione'   => self::AZIONE_REGISTRO,
					'dettagli' => array(
						'voce'  => $voce,
						'prima' => $prima,
						'dopo'  => $dopo,
					),
				)
			);

			if ( is_wp_error( $traccia ) ) {
				return self::esito(
					'registro',
					sprintf(
						/* translators: 1: cambi salvati, 2: cambi chiesti, 3: nome della voce, 4: motivo riportato dal registro. */
						__( 'Salvati %1$d cambi su %2$d. Il registro delle modifiche non ha accettato il cambio della voce "%3$s", e da lì in poi non si è salvato niente, perché un cambio senza traccia nel registro non si fa. Motivo: %4$s', 'trasparenza-pa' ),
						count( $salvate ),
						count( $cambi ),
						(string) ( $albero->voce( $voce )['nome'] ?? $voce ),
						$traccia->get_error_message()
					),
					$salvate
				);
			}

			$scritto = StatiObbligo::imposta( $albero, $voce, $dopo );

			if ( is_wp_error( $scritto ) ) {
				return self::esito(
					'stato',
					sprintf(
						/* translators: 1: cambi salvati, 2: cambi chiesti, 3: nome della voce. */
						__( 'Salvati %1$d cambi su %2$d. Lo stato della voce "%3$s" non si è potuto scrivere, e da lì in poi non si è salvato niente. Per quella voce il registro delle modifiche contiene un cambio che non è avvenuto: lo stato resta quello di prima.', 'trasparenza-pa' ),
						count( $salvate ),
						count( $cambi ),
						(string) ( $albero->voce( $voce )['nome'] ?? $voce )
					),
					$salvate
				);
			}

			$salvate[] = $voce;
		}

		return self::esito(
			'salvato',
			array() === $salvate
				? __( 'Nessuno stato cambiato.', 'trasparenza-pa' )
				/* translators: %d: numero di cambi. */
				: sprintf( _n( 'Salvato %d cambio di stato.', 'Salvati %d cambi di stato.', count( $salvate ), 'trasparenza-pa' ), count( $salvate ) ),
			$salvate
		);
	}

	/**
	 * I cambi chiesti, controllati tutti, oppure l'errore del primo difetto.
	 *
	 * La radice non porta obblighi propri e non si dichiara. Un valore uguale
	 * allo stato attuale non e un cambio e non va nel registro.
	 *
	 * **Si applica solo quello che l'utente ha cambiato.** Il modulo manda tutte
	 * le voci, anche quelle che nessuno ha toccato, e con ciascuna lo stato che
	 * mostrava quando e stato aperto. Una voce rimandata com'era mostrata non e
	 * un cambio: senza questa regola, chi salva un modulo aperto prima di un
	 * altro salvataggio riporterebbe indietro le voci cambiate nel frattempo.
	 * Una voce cambiata dall'utente, ma che nel frattempo qualcun altro ha
	 * cambiato in modo diverso, e un conflitto: non si salva niente e si dice
	 * quale voce ricontrollare.
	 *
	 * @param Alberatura           $albero   Albero di riferimento.
	 * @param mixed                $inviati  Coppie voce e stato, come arrivano dal modulo.
	 * @param mixed                $partenze Coppie voce e stato mostrato dal modulo.
	 * @param array<string, mixed> $attuali  Stati conservati, letti dalla banca dati.
	 * @return array<string, array{0: string|null, 1: string}>|WP_Error
	 */
	private static function cambi( Alberatura $albero, $inviati, $partenze, array $attuali ) {
		$difetto = new WP_Error( 'trasparenza_stati_dati', __( 'Nessuno stato salvato: la richiesta conteneva una voce che non esiste o un valore che non è fra i tre ammessi.', 'trasparenza-pa' ) );

		if ( ! is_array( $inviati ) || ! is_array( $partenze ) ) {
			return $difetto;
		}

		$cambi     = array();
		$conflitti = array();

		foreach ( $inviati as $voce => $stato ) {
			if ( ! is_string( $voce ) || ! is_string( $stato ) || ! $albero->esiste( $voce ) || $voce === $albero->radice() ) {
				return $difetto;
			}

			$partenza = $partenze[ $voce ] ?? null;

			if ( ! is_string( $partenza ) || ( '' !== $partenza && null === StatiObbligo::valido( $partenza ) ) ) {
				return $difetto;
			}

			if ( '' === $stato ) {
				continue;
			}

			if ( null === StatiObbligo::valido( $stato ) ) {
				return $difetto;
			}

			$mostrato = '' === $partenza ? null : $partenza;
			$attuale  = StatiObbligo::valido( $attuali[ $voce ] ?? null );

			if ( $stato === $mostrato || $stato === $attuale ) {
				continue;
			}

			if ( $attuale !== $mostrato ) {
				$conflitti[] = (string) ( $albero->voce( $voce )['nome'] ?? $voce );
				continue;
			}

			$cambi[ $voce ] = array( $attuale, $stato );
		}

		if ( array() !== $conflitti ) {
			return new WP_Error(
				'trasparenza_stati_conflitto',
				sprintf(
					/* translators: %s: nomi delle voci, separati da virgole. */
					__( 'Nessuno stato salvato: mentre la schermata era aperta, qualcun altro ha cambiato lo stato di queste voci: %s. Ricaricare la schermata, ricontrollarle e salvare di nuovo.', 'trasparenza-pa' ),
					implode( ', ', $conflitti )
				)
			);
		}

		return $cambi;
	}

	/**
	 * Disegna la schermata.
	 */
	public static function mostra(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			wp_die( esc_html__( 'Non avete il permesso di dichiarare lo stato delle voci.', 'trasparenza-pa' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Stato delle voci', 'trasparenza-pa' ) . '</h1>';

		$chiave = self::ESITO . get_current_user_id();
		$esito  = get_transient( $chiave );

		if ( is_array( $esito ) && isset( $esito['codice'], $esito['messaggio'] ) ) {
			delete_transient( $chiave );

			printf(
				'<div class="notice %1$s"><p>%2$s</p></div>',
				'salvato' === $esito['codice'] ? 'notice-success' : 'notice-error',
				esc_html( (string) $esito['messaggio'] )
			);
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'L\'albero delle voci non è disponibile: non si può dichiarare nessuno stato finché non lo è.', 'trasparenza-pa' ) . '</p></div></div>';

			return;
		}

		self::spiegazione( $albero );
		self::non_dichiarate( $albero );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE ) . '">';
		wp_nonce_field( self::AZIONE );

		echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Stato di ogni voce della sezione', 'trasparenza-pa' ) . '</caption>';
		echo '<thead><tr><th scope="col">' . esc_html__( 'Voce', 'trasparenza-pa' ) . '</th><th scope="col">' . esc_html__( 'Riferimento', 'trasparenza-pa' ) . '</th><th scope="col">' . esc_html__( 'Stato', 'trasparenza-pa' ) . '</th></tr></thead><tbody>';

		foreach ( $albero->figli( $albero->radice() ) as $voce ) {
			self::riga( $albero, $voce, 0 );
		}

		echo '</tbody></table>';

		submit_button( __( 'Salva gli stati', 'trasparenza-pa' ) );

		echo '</form></div>';
	}

	/**
	 * Cosa dice la norma, le tre scelte e i loro effetti.
	 *
	 * Le frasi che la pagina pubblica stampa si leggono dall'albero, che e la
	 * loro sola fonte: scritte anche qui potrebbero divergere.
	 *
	 * @param Alberatura $albero Albero di riferimento.
	 */
	private static function spiegazione( Alberatura $albero ): void {
		echo '<p>' . esc_html__( 'Per ogni voce della sezione l\'ente dichiara se l\'obbligo di pubblicazione la riguarda. Gli schemi di pubblicazione ANAC prevedono tre stati, e nessuno è scelto in partenza: una voce senza stato non è conforme, e resta nell\'elenco qui sotto finché qualcuno non sceglie.', 'trasparenza-pa' ) . '</p><ul class="ul-disc">';

		printf( '<li><strong>%1$s</strong>: %2$s</li>', esc_html__( 'Applicabile', 'trasparenza-pa' ), esc_html__( 'l\'obbligo vale, e la voce va riempita con i dati.', 'trasparenza-pa' ) );

		/* translators: %s: frase stampata sulla pagina pubblica. */
		printf( '<li><strong>%1$s</strong>: %2$s</li>', esc_html__( 'Non applicabile', 'trasparenza-pa' ), esc_html( sprintf( __( 'la pagina pubblica della voce mostra la frase "%s".', 'trasparenza-pa' ), $albero->dicitura( StatiObbligo::NON_APPLICABILE ) ) ) );

		/* translators: %s: frase stampata sulla pagina pubblica. */
		printf( '<li><strong>%1$s</strong>: %2$s</li>', esc_html__( 'Sospeso', 'trasparenza-pa' ), esc_html( sprintf( __( 'l\'obbligo è sospeso da una norma o da un atto dell\'Autorità; la pagina pubblica della voce mostra la frase "%s".', 'trasparenza-pa' ), $albero->dicitura( StatiObbligo::SOSPESO ) ) ) );

		echo '</ul><p>' . esc_html__( 'Uno stato scelto si può cambiare ma non togliere. Ogni cambio finisce nel registro delle modifiche, con chi l\'ha fatto e quando.', 'trasparenza-pa' ) . '</p>';
	}

	/**
	 * L'elenco delle voci ancora senza stato.
	 *
	 * @param Alberatura $albero Albero di riferimento.
	 */
	private static function non_dichiarate( Alberatura $albero ): void {
		$voci = StatiObbligo::non_dichiarate( $albero );

		if ( array() === $voci ) {
			echo '<p class="trasparenza-pa-non-dichiarate">' . esc_html__( 'Tutte le voci hanno uno stato dichiarato.', 'trasparenza-pa' ) . '</p>';

			return;
		}

		/* translators: %d: numero di voci. */
		echo '<div class="notice notice-warning inline trasparenza-pa-non-dichiarate"><p>' . esc_html( sprintf( _n( '%d voce ancora senza stato:', '%d voci ancora senza stato:', count( $voci ), 'trasparenza-pa' ), count( $voci ) ) ) . '</p><ul class="ul-disc">';

		foreach ( $voci as $voce ) {
			echo '<li>' . esc_html( (string) ( $albero->voce( $voce )['nome'] ?? $voce ) ) . '</li>';
		}

		echo '</ul></div>';
	}

	/**
	 * Una riga della tabella, poi quelle delle voci figlie.
	 *
	 * Per una voce non dichiarata la scelta parte da "Da scegliere", che non e
	 * uno stato; per una voce dichiarata quella scelta non c'e, perche lo
	 * stato non si toglie.
	 *
	 * @param Alberatura $albero  Albero di riferimento.
	 * @param string     $voce    Voce da disegnare.
	 * @param int        $livello Profondita sotto la radice, da zero.
	 */
	private static function riga( Alberatura $albero, string $voce, int $livello ): void {
		$dati   = (array) $albero->voce( $voce );
		$nome   = (string) ( $dati['nome'] ?? $voce );
		$stato  = StatiObbligo::stato( $voce );
		$campo  = 'trasparenza-pa-stato-' . $voce;
		$scelte = array(
			StatiObbligo::APPLICABILE     => __( 'Applicabile', 'trasparenza-pa' ),
			StatiObbligo::NON_APPLICABILE => __( 'Non applicabile', 'trasparenza-pa' ),
			StatiObbligo::SOSPESO         => __( 'Sospeso', 'trasparenza-pa' ),
		);

		echo '<tr><th scope="row"' . ( $livello > 0 ? ' style="padding-left:' . esc_attr( (string) ( 10 + 24 * $livello ) ) . 'px"' : '' ) . '><label for="' . esc_attr( $campo ) . '">' . esc_html( $nome ) . '</label></th>';
		echo '<td>' . esc_html( (string) ( $dati['riferimento'] ?? '' ) ) . '</td>';
		echo '<td><select id="' . esc_attr( $campo ) . '" name="' . esc_attr( self::CAMPO . '[' . $voce . ']' ) . '">';

		if ( null === $stato ) {
			echo '<option value="" selected>' . esc_html__( 'Da scegliere', 'trasparenza-pa' ) . '</option>';
		}

		foreach ( $scelte as $valore => $etichetta ) {
			echo '<option value="' . esc_attr( $valore ) . '"' . selected( $stato, $valore, false ) . '>' . esc_html( $etichetta ) . '</option>';
		}

		echo '</select>';
		echo '<input type="hidden" name="' . esc_attr( self::CAMPO_PARTENZA . '[' . $voce . ']' ) . '" value="' . esc_attr( (string) $stato ) . '">';
		echo '</td></tr>';

		foreach ( $albero->figli( $voce ) as $figlio ) {
			self::riga( $albero, $figlio, $livello + 1 );
		}
	}

	/**
	 * Prende il blocco degli stati, aspettando al massimo qualche secondo.
	 *
	 * Due salvataggi contemporanei leggerebbero entrambi gli stati di partenza
	 * e l'ultimo cancellerebbe i cambi del primo, che pure sono nel registro.
	 * Il blocco e della banca dati, quindi vale fra richieste diverse; il nome
	 * porta la banca dati e il prefisso, perche lo stesso server puo ospitare
	 * piu siti.
	 *
	 * @return bool
	 */
	private static function blocca(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- un blocco della banca dati non passa da nessuna funzione di WordPress e non si mette in memoria.
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::nome_blocco(), self::ATTESA_BLOCCO ) );
	}

	/**
	 * Rilascia il blocco degli stati.
	 */
	private static function sblocca(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- come sopra.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::nome_blocco() ) );
	}

	/**
	 * Nome del blocco degli stati per questo sito.
	 *
	 * @internal Pubblico per le prove, che prendono il blocco da un'altra connessione.
	 *
	 * @return string
	 */
	public static function nome_blocco(): string {
		global $wpdb;

		return 'trasparenza_pa_' . md5( DB_NAME . '|' . $wpdb->prefix );
	}

	/**
	 * Un esito, nella forma che la schermata legge.
	 *
	 * @param string             $codice    Codice dell'esito.
	 * @param string             $messaggio Messaggio per chi ha salvato.
	 * @param array<int, string> $salvate   Voci il cui stato e stato salvato.
	 * @return array{codice: string, messaggio: string, salvate: array<int, string>}
	 */
	private static function esito( string $codice, string $messaggio, array $salvate = array() ): array {
		return array(
			'codice'    => $codice,
			'messaggio' => $messaggio,
			'salvate'   => $salvate,
		);
	}
}
