<?php
/**
 * La schermata in cui gli addetti tengono il registro degli accessi.
 *
 * Righe di collaudo T-106 e T-107.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Registro degli accessi: aggiunta, correzione e rimozione di una riga alla volta.
 *
 * Le regole delle altre schermate della sezione: permesso e nonce prima di
 * tutto, un salvataggio alla volta, prima il registro delle modifiche e poi il
 * dato, che si rilegge. Il modulo di una riga porta la riga come la mostrava
 * all'apertura, e una riga cambiata o tolta da altri nel frattempo e un
 * conflitto.
 */
final class SchermataRegistroAccessi {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-registro-accessi';

	/**
	 * Azione del modulo e del nonce.
	 */
	const AZIONE = 'trasparenza_pa_registro_accessi';

	/**
	 * Nome del campo che porta i campi della riga.
	 */
	const CAMPO = 'riga';

	/**
	 * Nome del campo che porta l'operazione.
	 */
	const CAMPO_OPERAZIONE = 'operazione';

	/**
	 * Nome del campo che porta il numero della riga.
	 */
	const CAMPO_NUMERO = 'numero';

	/**
	 * Nome del campo nascosto che porta la riga mostrata quando il modulo e stato aperto.
	 */
	const CAMPO_PARTENZA = 'partenza';

	/**
	 * Le operazioni.
	 */
	const OPERAZIONI = array( 'aggiungi', 'modifica', 'elimina' );

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'registro_accessi';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 */
	const ESITO = 'trasparenza_pa_esito_registro_accessi_';

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		// Dopo la schermata dell'accesso civico.
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ), 14 );
		add_action( 'admin_post_' . self::AZIONE, array( self::class, 'da_admin_post' ) );
	}

	/**
	 * Sottovoce di menu, solo se la sezione e nostra.
	 */
	public static function da_admin_menu(): void {
		if ( ! Avvio::registrata() ) {
			return;
		}

		add_submenu_page(
			SchermataStati::PAGINA,
			__( 'Registro degli accessi', 'trasparenza-pa' ),
			__( 'Registro degli accessi', 'trasparenza-pa' ),
			Permessi::AMMINISTRAZIONE,
			self::PAGINA,
			array( self::class, 'mostra' )
		);
	}

	/**
	 * Risposta al modulo.
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
	 * Aggiunge, corregge o toglie una riga.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, operazione: string, numero: int, inviati: array<string, string>, partenza: array<string, string>|null}
	 */
	public static function salva( array $richiesta ): array {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di tenere il registro degli accessi.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: niente salvato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$operazione = $richiesta[ self::CAMPO_OPERAZIONE ] ?? null;

		if ( ! is_string( $operazione ) || ! in_array( $operazione, self::OPERAZIONI, true ) ) {
			return self::esito( 'operazione', __( 'Niente salvato: la richiesta non diceva se aggiungere, correggere o togliere una riga.', 'trasparenza-pa' ) );
		}

		$numero = 0;

		if ( 'aggiungi' !== $operazione ) {
			$inviato = $richiesta[ self::CAMPO_NUMERO ] ?? null;

			if ( ! is_string( $inviato ) || 1 !== preg_match( '/\A[1-9][0-9]{0,8}\z/', $inviato ) ) {
				return self::esito( 'operazione', __( 'Niente salvato: la richiesta non diceva quale riga correggere o togliere.', 'trasparenza-pa' ), $operazione );
			}

			$numero = (int) $inviato;
		}

		$partenza   = 'aggiungi' === $operazione ? null : self::riga_testuale( $richiesta[ self::CAMPO_PARTENZA ] ?? null );
		$inviati    = $richiesta[ self::CAMPO ] ?? null;
		$rimostrare = is_array( $inviati ) ? ( self::riga_testuale( $inviati, true ) ?? array() ) : array();
		$riga       = null;

		if ( 'elimina' !== $operazione ) {
			if ( ! is_array( $inviati ) ) {
				return self::esito( 'dati', __( 'Niente salvato: la richiesta non conteneva i campi della riga.', 'trasparenza-pa' ), $operazione, $numero, array(), $partenza );
			}

			$riga = RegistroAccessi::controlla( $inviati );

			if ( is_wp_error( $riga ) ) {
				return self::esito( 'dati', __( 'Niente salvato.', 'trasparenza-pa' ) . ' ' . $riga->get_error_message(), $operazione, $numero, $rimostrare, $partenza );
			}
		}

		if ( 'aggiungi' !== $operazione && null === $partenza ) {
			return self::esito( 'conflitto', __( 'Niente salvato: la richiesta non diceva come era la riga quando il modulo è stato aperto. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ), $operazione, $numero, $rimostrare );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Niente salvato: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ), $operazione, $numero, $rimostrare, $partenza );
		}

		try {
			return self::scrivi( $operazione, $numero, $riga, $partenza, $rimostrare );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Registra e scrive, con il blocco gia preso.
	 *
	 * @param string                     $operazione Operazione.
	 * @param int                        $numero     Numero della riga; zero per l'aggiunta.
	 * @param array<string, string>|null $riga       Riga controllata; nulla per la rimozione.
	 * @param array<string, string>|null $partenza   Riga mostrata all'apertura; nulla per l'aggiunta.
	 * @param array<string, string>      $rimostrare Campi da rimostrare dopo un rifiuto.
	 * @return array{codice: string, messaggio: string, operazione: string, numero: int, inviati: array<string, string>, partenza: array<string, string>|null}
	 */
	private static function scrivi( string $operazione, int $numero, ?array $riga, ?array $partenza, array $rimostrare ): array {
		$conservato = RegistroAccessi::conservato();

		if ( is_wp_error( $conservato ) ) {
			return self::esito( 'lettura', __( 'Niente salvato: il registro attuale non si è potuto leggere dalla banca dati, e senza conoscerlo il salvataggio cancellerebbe le altre righe. Riprovare fra qualche minuto.', 'trasparenza-pa' ), $operazione, $numero, $rimostrare, $partenza );
		}

		$righe = $conservato['righe'];
		$campi = RegistroAccessi::CAMPI;

		if ( 'aggiungi' === $operazione ) {
			$numero           = $conservato['ultimo'] + 1;
			$righe[ $numero ] = $riga;
		} else {
			if ( ! isset( $righe[ $numero ] ) || $righe[ $numero ] !== $partenza ) {
				return self::esito( 'conflitto', __( 'Niente salvato: dopo l\'apertura di questa schermata qualcun altro ha cambiato o tolto questa riga. Ricaricare la schermata, che mostra il registro attuale, e riprovare.', 'trasparenza-pa' ), $operazione, $numero, $rimostrare, $partenza );
			}

			if ( 'elimina' === $operazione ) {
				unset( $righe[ $numero ] );
			} else {
				$campi = array_values( array_filter( RegistroAccessi::CAMPI, static fn( string $campo ): bool => $righe[ $numero ][ $campo ] !== $riga[ $campo ] ) );

				if ( array() === $campi ) {
					return self::esito( 'salvato', __( 'Nessun campo cambiato.', 'trasparenza-pa' ), $operazione, $numero );
				}

				$righe[ $numero ] = $riga;
			}
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'  => SEZIONE,
				'azione'   => self::AZIONE_REGISTRO,
				'dettagli' => array(
					'operazione' => $operazione,
					'numero'     => $numero,
					'campi'      => $campi,
				),
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return self::esito(
				'registro',
				/* translators: %s: motivo riportato dal registro. */
				sprintf( __( 'Niente salvato: il registro delle modifiche non ha accettato il cambio, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() ),
				$operazione,
				$numero,
				$rimostrare,
				$partenza
			);
		}

		ksort( $righe );

		$nuovo = array(
			'ultimo' => max( $conservato['ultimo'], $numero ),
			'righe'  => $righe,
		);

		update_option( RegistroAccessi::OPZIONE, $nuovo, false );

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( RegistroAccessi::OPZIONE, 'options' );

		if ( RegistroAccessi::conservato() !== $nuovo ) {
			return self::esito( 'scrittura', __( 'Il registro non si è potuto scrivere: resta quello di prima. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ), $operazione, $numero, $rimostrare, $partenza );
		}

		$messaggi = array(
			'aggiungi' => __( 'Richiesta aggiunta al registro.', 'trasparenza-pa' ),
			'modifica' => __( 'Richiesta corretta.', 'trasparenza-pa' ),
			'elimina'  => __( 'Richiesta tolta dal registro.', 'trasparenza-pa' ),
		);

		return self::esito( 'salvato', $messaggi[ $operazione ], $operazione, $numero );
	}

	/**
	 * Disegna la schermata.
	 */
	public static function mostra(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			wp_die( esc_html__( 'Non avete il permesso di tenere il registro degli accessi.', 'trasparenza-pa' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Registro degli accessi', 'trasparenza-pa' ) . '</h1>';

		$chiave = self::ESITO . get_current_user_id();
		$esito  = get_transient( $chiave );

		if ( is_array( $esito ) && isset( $esito['codice'], $esito['messaggio'] ) ) {
			delete_transient( $chiave );

			printf(
				'<div class="notice %1$s"><p>%2$s</p></div>',
				'salvato' === $esito['codice'] ? 'notice-success' : 'notice-error',
				esc_html( (string) $esito['messaggio'] )
			);
		} else {
			$esito = array();
		}

		echo '<p>' . esc_html__( 'Una riga per ogni richiesta di accesso ricevuta: agli atti, civico semplice o civico generalizzato, con l\'argomento, la data della richiesta, l\'esito e la data della decisione (delibera ANAC 1310/2016, Allegato 1). Il registro è pubblico: nell\'argomento non scrivere nomi o altri dati di chi chiede.', 'trasparenza-pa' ) . '</p>';

		$rifiutata = 'salvato' !== ( $esito['codice'] ?? 'salvato' );
		$inviati   = $rifiutata ? ( self::riga_testuale( $esito['inviati'] ?? null, true ) ?? array() ) : array();

		echo '<h2>' . esc_html__( 'Aggiungere una richiesta', 'trasparenza-pa' ) . '</h2>';
		echo self::modulo( 'aggiungi', 0, array_merge( array_fill_keys( RegistroAccessi::CAMPI, '' ), $rifiutata && 'aggiungi' === ( $esito['operazione'] ?? '' ) ? $inviati : array() ), null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escaping in modulo().

		$righe = RegistroAccessi::righe();

		uksort(
			$righe,
			static function ( int $a, int $b ) use ( $righe ): int {
				$ordine = strcmp( $righe[ $b ]['richiesta'], $righe[ $a ]['richiesta'] );
				return 0 !== $ordine ? $ordine : $b <=> $a;
			}
		);

		echo '<h2>' . esc_html__( 'Richieste nel registro', 'trasparenza-pa' ) . '</h2>';

		if ( array() === $righe ) {
			echo '<p>' . esc_html__( 'Nessuna richiesta nel registro.', 'trasparenza-pa' ) . '</p>';
		}

		foreach ( $righe as $numero => $riga ) {
			$valori   = $riga;
			$partenza = $riga;

			// Dopo un rifiuto si rimostra quello che si era scritto, con la partenza di allora.
			if ( $rifiutata && 'aggiungi' !== ( $esito['operazione'] ?? '' ) && ( $esito['numero'] ?? 0 ) === $numero ) {
				$valori   = array_merge( $valori, $inviati );
				$partenza = self::riga_testuale( $esito['partenza'] ?? null ) ?? $partenza;
			}

			/* translators: %d: numero della riga. */
			echo '<h3>' . esc_html( sprintf( __( 'Richiesta n. %d', 'trasparenza-pa' ), $numero ) ) . '</h3>';

			if ( is_wp_error( RegistroAccessi::controlla( $riga ) ) ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Questa riga non supera il controllo e la pagina pubblica non la mostra: correggerla o toglierla.', 'trasparenza-pa' ) . '</p></div>';
			}

			echo self::modulo( 'modifica', $numero, $valori, $partenza ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escaping in modulo().
		}

		echo '</div>';
	}

	/**
	 * Il modulo di una riga, o di aggiunta.
	 *
	 * @param string                     $operazione Aggiungi o modifica; la riga ha anche il pulsante per toglierla.
	 * @param int                        $numero     Numero della riga; zero per l'aggiunta.
	 * @param array<string, string>      $valori     Valori nei campi.
	 * @param array<string, string>|null $partenza   Riga da portare come partenza.
	 * @return string
	 */
	private static function modulo( string $operazione, int $numero, array $valori, ?array $partenza ): string {
		$prefisso = 'trasparenza-pa-registro-' . ( 0 === $numero ? 'nuova' : $numero ) . '-';
		$html     = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html    .= '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE ) . '">';
		$html    .= wp_nonce_field( self::AZIONE, '_wpnonce', true, false );

		if ( 0 === $numero ) {
			$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_OPERAZIONE ) . '" value="aggiungi">';
		} else {
			$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_NUMERO ) . '" value="' . esc_attr( (string) $numero ) . '">';

			foreach ( RegistroAccessi::CAMPI as $campo ) {
				$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_PARTENZA . '[' . $campo . ']' ) . '" value="' . esc_attr( (string) ( $partenza[ $campo ] ?? '' ) ) . '">';
			}
		}

		$html .= '<table class="form-table" role="presentation"><tbody>';

		$html .= '<tr><th scope="row"><label for="' . esc_attr( $prefisso . 'tipo' ) . '">' . esc_html__( 'Tipo', 'trasparenza-pa' ) . '</label></th><td><select id="' . esc_attr( $prefisso . 'tipo' ) . '" name="' . esc_attr( self::CAMPO . '[tipo]' ) . '">';

		foreach ( array_keys( RegistroAccessi::TIPI ) as $tipo ) {
			$html .= '<option value="' . esc_attr( $tipo ) . '"' . selected( $valori['tipo'], $tipo, false ) . '>' . esc_html( RegistroAccessi::etichetta_tipo( $tipo ) ) . '</option>';
		}

		$html .= '</select></td></tr>';

		$testi = array(
			'argomento' => array( __( 'Argomento', 'trasparenza-pa' ), 'text' ),
			'richiesta' => array( __( 'Data della richiesta', 'trasparenza-pa' ), 'date' ),
		);

		foreach ( $testi as $campo => list( $etichetta, $tipo ) ) {
			$html .= '<tr><th scope="row"><label for="' . esc_attr( $prefisso . $campo ) . '">' . esc_html( $etichetta ) . '</label></th><td><input type="' . esc_attr( $tipo ) . '" id="' . esc_attr( $prefisso . $campo ) . '" name="' . esc_attr( self::CAMPO . '[' . $campo . ']' ) . '" value="' . esc_attr( $valori[ $campo ] ) . '" class="regular-text"></td></tr>';
		}

		$html .= '<tr><th scope="row"><label for="' . esc_attr( $prefisso . 'esito' ) . '">' . esc_html__( 'Esito', 'trasparenza-pa' ) . '</label></th><td><select id="' . esc_attr( $prefisso . 'esito' ) . '" name="' . esc_attr( self::CAMPO . '[esito]' ) . '">';

		foreach ( array_keys( RegistroAccessi::ESITI ) as $esito ) {
			$html .= '<option value="' . esc_attr( $esito ) . '"' . selected( $valori['esito'], $esito, false ) . '>' . esc_html( RegistroAccessi::etichetta_esito( $esito ) ) . '</option>';
		}

		$html .= '</select></td></tr>';
		$html .= '<tr><th scope="row"><label for="' . esc_attr( $prefisso . 'decisione' ) . '">' . esc_html__( 'Data della decisione', 'trasparenza-pa' ) . '</label></th><td><input type="date" id="' . esc_attr( $prefisso . 'decisione' ) . '" name="' . esc_attr( self::CAMPO . '[decisione]' ) . '" value="' . esc_attr( $valori['decisione'] ) . '" class="regular-text"></td></tr>';
		$html .= '</tbody></table>';

		if ( 0 === $numero ) {
			return $html . get_submit_button( __( 'Aggiungi', 'trasparenza-pa' ), 'primary', 'submit', false ) . '</form>';
		}

		$html .= '<p><button type="submit" class="button button-primary" name="' . esc_attr( self::CAMPO_OPERAZIONE ) . '" value="modifica">' . esc_html__( 'Salva la correzione', 'trasparenza-pa' ) . '</button> ';
		$html .= '<button type="submit" class="button" name="' . esc_attr( self::CAMPO_OPERAZIONE ) . '" value="elimina">' . esc_html__( 'Togli dal registro', 'trasparenza-pa' ) . '</button></p>';

		return $html . '</form>';
	}

	/**
	 * Una riga fatta dei cinque campi testuali, o nulla.
	 *
	 * @param mixed $valore     Valore ricevuto.
	 * @param bool  $anche_parz Se vero, restituisce i soli campi testuali presenti.
	 * @return array<string, string>|null
	 */
	private static function riga_testuale( $valore, bool $anche_parz = false ): ?array {
		if ( ! is_array( $valore ) ) {
			return null;
		}

		$riga = array();

		foreach ( RegistroAccessi::CAMPI as $campo ) {
			if ( isset( $valore[ $campo ] ) && is_string( $valore[ $campo ] ) ) {
				$riga[ $campo ] = $valore[ $campo ];
			} elseif ( ! $anche_parz ) {
				return null;
			}
		}

		return $riga;
	}

	/**
	 * Un esito, nella forma che la schermata legge.
	 *
	 * @param string                     $codice     Codice dell'esito.
	 * @param string                     $messaggio  Messaggio per chi ha salvato.
	 * @param string                     $operazione Operazione chiesta.
	 * @param int                        $numero     Numero della riga.
	 * @param array<string, string>      $inviati    Campi da rimostrare dopo un rifiuto.
	 * @param array<string, string>|null $partenza   Partenza della richiesta, da riportare nel modulo dopo un rifiuto.
	 * @return array{codice: string, messaggio: string, operazione: string, numero: int, inviati: array<string, string>, partenza: array<string, string>|null}
	 */
	private static function esito( string $codice, string $messaggio, string $operazione = '', int $numero = 0, array $inviati = array(), ?array $partenza = null ): array {
		return array(
			'codice'     => $codice,
			'messaggio'  => $messaggio,
			'operazione' => $operazione,
			'numero'     => $numero,
			'inviati'    => $inviati,
			'partenza'   => $partenza,
		);
	}
}
