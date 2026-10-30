<?php
/**
 * La schermata in cui l'ente dichiara i rinvii alle banche dati.
 *
 * Righe di collaudo T-95 e T-96.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Rinvii: un modulo per voce, salvataggio con traccia nel registro.
 *
 * Stessa forma della schermata del responsabile: il salvataggio e una funzione
 * che riceve la richiesta e restituisce l'esito, e le prove la chiamano come
 * la chiamerebbe il modulo.
 */
final class SchermataRinvii {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-rinvii';

	/**
	 * Azione del modulo e del nonce.
	 */
	const AZIONE = 'trasparenza_pa_rinvii';

	/**
	 * Nome del campo che porta la voce.
	 */
	const CAMPO_VOCE = 'voce';

	/**
	 * Nome del campo che porta il rinvio, una coppia campo e valore.
	 */
	const CAMPO = 'rinvio';

	/**
	 * Nome del campo nascosto che porta il rinvio mostrato quando il modulo e stato aperto.
	 */
	const CAMPO_PARTENZA = 'partenza';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'rinvio_banca_dati';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 */
	const ESITO = 'trasparenza_pa_esito_rinvii_';

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		// Dopo la voce principale, che la schermata degli stati aggiunge con la priorita normale.
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ), 12 );
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
			__( 'Rinvii alle banche dati', 'trasparenza-pa' ),
			__( 'Rinvii alle banche dati', 'trasparenza-pa' ),
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
	 * Salva il rinvio di una voce.
	 *
	 * Le regole della schermata del responsabile: permesso e nonce prima di
	 * tutto; tutti i campi controllati prima di scrivere; un salvataggio alla
	 * volta; prima il registro, poi il dato, che si rilegge dopo averlo scritto.
	 * Se il rinvio della voce e stato cambiato da un altro salvataggio dopo
	 * l'apertura del modulo, e un conflitto e non si salva niente.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, voce: string, inviati: array<string, string>, partenza: array<string, string>|null}
	 */
	public static function salva( array $richiesta ): array {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di dichiarare i rinvii alle banche dati.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: niente salvato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$voce     = $richiesta[ self::CAMPO_VOCE ] ?? null;
		$inviati  = $richiesta[ self::CAMPO ] ?? null;
		$partenza = self::rinvio_testuale( $richiesta[ self::CAMPO_PARTENZA ] ?? null );

		if ( ! is_string( $voce ) || ! is_array( $inviati ) ) {
			return self::esito( 'dati', __( 'Niente salvato: la richiesta non conteneva la voce e il rinvio.', 'trasparenza-pa' ) );
		}

		$rimostrare = self::rinvio_testuale( $inviati, true ) ?? array();
		$albero     = PaginaPubblica::albero();

		if ( null === $albero ) {
			return self::esito( 'albero', __( 'Niente salvato: l\'albero delle voci non è disponibile, e senza non si sa quali banche dati coprono la voce.', 'trasparenza-pa' ), $voce, $rimostrare, $partenza );
		}

		$dati = Rinvii::controlla( $albero, $voce, $inviati );

		if ( is_wp_error( $dati ) ) {
			return self::esito( 'dati', __( 'Niente salvato.', 'trasparenza-pa' ) . ' ' . $dati->get_error_message(), $voce, $rimostrare, $partenza );
		}

		if ( null === $partenza ) {
			return self::esito( 'dati', __( 'Niente salvato: la richiesta non diceva quale rinvio mostrava il modulo. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ), $voce, $dati );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Niente salvato: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ), $voce, $dati, $partenza );
		}

		try {
			return self::scrivi( $voce, $dati, $partenza );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Registra e scrive il rinvio controllato, con il blocco gia preso.
	 *
	 * @param string                $voce     Voce.
	 * @param array<string, string> $dati     Rinvio controllato.
	 * @param array<string, string> $partenza Rinvio mostrato dal modulo all'apertura.
	 * @return array{codice: string, messaggio: string, voce: string, inviati: array<string, string>, partenza: array<string, string>|null}
	 */
	private static function scrivi( string $voce, array $dati, array $partenza ): array {
		$conservati = Rinvii::conservati();

		if ( is_wp_error( $conservati ) ) {
			return self::esito( 'lettura', __( 'Niente salvato: i rinvii attuali non si sono potuti leggere dalla banca dati, e senza conoscerli il salvataggio cancellerebbe quelli delle altre voci. Riprovare fra qualche minuto.', 'trasparenza-pa' ), $voce, $dati, $partenza );
		}

		$attuale = $conservati[ $voce ] ?? Rinvii::vuoto();

		if ( $attuale === $dati ) {
			return self::esito( 'salvato', __( 'Nessun cambio nel rinvio.', 'trasparenza-pa' ), $voce );
		}

		if ( $attuale !== $partenza ) {
			return self::esito( 'conflitto', __( 'Niente salvato: dopo l\'apertura di questa schermata qualcun altro ha cambiato il rinvio di questa voce. Ricaricare la schermata, che mostra il rinvio attuale, e riprovare.', 'trasparenza-pa' ), $voce, $dati, $partenza );
		}

		$vuoto    = Rinvii::vuoto();
		$dettagli = array( 'voce' => $voce );

		/*
		 * Il registro accetta coppie di nome e valore semplice: ogni campo del
		 * rinvio ha una sua coppia per prima e una per dopo, nulla se il
		 * rinvio non c'era o non c'e piu.
		 */
		foreach ( array(
			'prima' => $attuale,
			'dopo'  => $dati,
		) as $quando => $rinvio ) {
			foreach ( Rinvii::CAMPI as $campo ) {
				$dettagli[ $campo . '_' . $quando ] = $vuoto === $rinvio ? null : $rinvio[ $campo ];
			}
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'  => SEZIONE,
				'azione'   => self::AZIONE_REGISTRO,
				'dettagli' => $dettagli,
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return self::esito(
				'registro',
				/* translators: %s: motivo riportato dal registro. */
				sprintf( __( 'Niente salvato: il registro delle modifiche non ha accettato il cambio, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() ),
				$voce,
				$dati,
				$partenza
			);
		}

		$nuovi = $conservati;

		if ( $vuoto === $dati ) {
			unset( $nuovi[ $voce ] );
		} else {
			$nuovi[ $voce ] = $dati;
		}

		update_option( Rinvii::OPZIONE, $nuovi, false );

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( Rinvii::OPZIONE, 'options' );

		$riletti = Rinvii::conservati();

		if ( is_wp_error( $riletti ) || ( $riletti[ $voce ] ?? $vuoto ) !== $dati ) {
			return self::esito( 'scrittura', __( 'Il rinvio non si è potuto scrivere: resta quello di prima. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ), $voce, $dati, $partenza );
		}

		return self::esito( 'salvato', $vuoto === $dati ? __( 'Rinvio tolto.', 'trasparenza-pa' ) : __( 'Rinvio salvato.', 'trasparenza-pa' ), $voce );
	}

	/**
	 * Disegna la schermata.
	 */
	public static function mostra(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			wp_die( esc_html__( 'Non avete il permesso di dichiarare i rinvii alle banche dati.', 'trasparenza-pa' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Rinvii alle banche dati', 'trasparenza-pa' ) . '</h1>';

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

		echo '<p>' . esc_html__( 'Per alcuni obblighi la sezione può rinviare alla banca dati nazionale a cui l\'ente ha comunicato i dati, invece di ripubblicarli (art. 9-bis del d.lgs. 33/2013). Il rinvio vale solo per i dati che si trovano davvero nella banca dati, e l\'indirizzo deve portare al dato dell\'ente, non alla pagina iniziale della banca dati.', 'trasparenza-pa' ) . '</p>';

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'L\'albero delle voci non è disponibile: i rinvii non si possono dichiarare.', 'trasparenza-pa' ) . '</p></div></div>';
			return;
		}

		$conservati = Rinvii::dati();

		foreach ( $albero->identificativi() as $voce ) {
			$banche = $albero->rinvii( $voce );

			if ( array() === $banche ) {
				continue;
			}

			$attuale  = $conservati[ $voce ] ?? Rinvii::vuoto();
			$valori   = $attuale;
			$partenza = $attuale;

			// Dopo un rifiuto si rimostra quello che si era scritto, con la partenza di allora.
			if ( ( $esito['voce'] ?? null ) === $voce && 'salvato' !== $esito['codice'] ) {
				$valori   = array_merge( $valori, self::rinvio_testuale( $esito['inviati'] ?? null, true ) ?? array() );
				$partenza = self::rinvio_testuale( $esito['partenza'] ?? null ) ?? $partenza;
			}

			echo self::modulo( $albero, $voce, $banche, $valori, $partenza, $attuale ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escaping in modulo().
		}

		echo '</div>';
	}

	/**
	 * Il modulo di una voce, con escaping di ogni valore.
	 *
	 * @param Alberatura                       $albero   Albero in vigore.
	 * @param string                           $voce     Voce.
	 * @param array<int, array<string, mixed>> $banche   Banche dati della voce.
	 * @param array<string, string>            $valori   Valori da mostrare nei campi.
	 * @param array<string, string>            $partenza Rinvio da portare come partenza.
	 * @param array<string, string>            $attuale  Rinvio conservato.
	 * @return string
	 */
	private static function modulo( Alberatura $albero, string $voce, array $banche, array $valori, array $partenza, array $attuale ): string {
		$id   = 'trasparenza-pa-rinvio-' . $voce;
		$html = '<h2>' . esc_html( self::percorso( $albero, $voce ) ) . '</h2>';

		if ( Rinvii::vuoto() !== $attuale && is_wp_error( Rinvii::controlla( $albero, $voce, $attuale ) ) ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'Il rinvio conservato per questa voce non vale più con l\'albero in vigore, e la pagina pubblica non lo mostra: sceglierne un altro o toglierlo.', 'trasparenza-pa' ) . '</p></div>';
		}

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE ) . '">';
		$html .= wp_nonce_field( self::AZIONE, '_wpnonce', true, false );
		$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_VOCE ) . '" value="' . esc_attr( $voce ) . '">';

		foreach ( Rinvii::CAMPI as $campo ) {
			$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_PARTENZA . '[' . $campo . ']' ) . '" value="' . esc_attr( $partenza[ $campo ] ) . '">';
		}

		$html .= '<table class="form-table" role="presentation"><tbody>';
		$html .= '<tr><th scope="row"><label for="' . esc_attr( $id . '-banca' ) . '">' . esc_html__( 'Banca dati', 'trasparenza-pa' ) . '</label></th><td>';
		$html .= '<select id="' . esc_attr( $id . '-banca' ) . '" name="' . esc_attr( self::CAMPO . '[banca]' ) . '">';
		$html .= '<option value=""' . ( '' === $valori['banca'] ? ' selected' : '' ) . '>' . esc_html__( 'Nessun rinvio', 'trasparenza-pa' ) . '</option>';

		foreach ( $banche as $banca ) {
			$nome = (string) $banca['nome'];

			if ( is_string( $banca['titolare'] ) ) {
				/* translators: 1: nome della banca dati, 2: chi la tiene. */
				$nome = sprintf( __( '%1$s (tenuta da %2$s)', 'trasparenza-pa' ), $nome, $banca['titolare'] );
			}

			if ( is_string( $banca['limite'] ) ) {
				/* translators: 1: nome della banca dati, 2: limite. */
				$nome = sprintf( __( '%1$s, %2$s', 'trasparenza-pa' ), $nome, $banca['limite'] );
			}

			$html .= '<option value="' . esc_attr( (string) $banca['banca'] ) . '"' . ( $banca['banca'] === $valori['banca'] ? ' selected' : '' ) . '>' . esc_html( $nome ) . '</option>';
		}

		$html .= '</select></td></tr>';

		$campi = array(
			'indirizzo' => array( __( 'Indirizzo del dato nella banca dati', 'trasparenza-pa' ), 'url' ),
			'etichetta' => array( __( 'Cosa si trova dall\'altra parte', 'trasparenza-pa' ), 'text' ),
			'data'      => array( __( 'Rinvio in vigore dal', 'trasparenza-pa' ), 'date' ),
		);

		foreach ( $campi as $campo => list( $etichetta, $tipo ) ) {
			$html .= '<tr><th scope="row"><label for="' . esc_attr( $id . '-' . $campo ) . '">' . esc_html( $etichetta ) . '</label></th>';
			$html .= '<td><input type="' . esc_attr( $tipo ) . '" id="' . esc_attr( $id . '-' . $campo ) . '" name="' . esc_attr( self::CAMPO . '[' . $campo . ']' ) . '" value="' . esc_attr( $valori[ $campo ] ) . '" class="regular-text"></td></tr>';
		}

		$html .= '</tbody></table>';
		$html .= get_submit_button( __( 'Salva il rinvio', 'trasparenza-pa' ), 'primary', 'submit-' . $voce );

		return $html . '</form>';
	}

	/**
	 * Il percorso della voce, dal primo livello sotto la radice.
	 *
	 * @param Alberatura $albero Albero.
	 * @param string     $voce   Voce.
	 * @return string
	 */
	private static function percorso( Alberatura $albero, string $voce ): string {
		$nomi  = array();
		$passo = $voce;

		while ( is_string( $passo ) && $passo !== $albero->radice() ) {
			$dati = $albero->voce( $passo );

			if ( null === $dati ) {
				break;
			}

			array_unshift( $nomi, (string) $dati['nome'] );
			$passo = $dati['padre'];
		}

		return implode( ' > ', $nomi );
	}

	/**
	 * Un rinvio di soli testi, o nullo se non lo e.
	 *
	 * @param mixed $valore  Valore ricevuto.
	 * @param bool  $parziale Vero per tenere i soli campi testuali presenti.
	 * @return array<string, string>|null
	 */
	private static function rinvio_testuale( $valore, bool $parziale = false ): ?array {
		if ( ! is_array( $valore ) ) {
			return null;
		}

		$rinvio = array();

		foreach ( Rinvii::CAMPI as $campo ) {
			if ( isset( $valore[ $campo ] ) && is_string( $valore[ $campo ] ) ) {
				$rinvio[ $campo ] = $valore[ $campo ];
			} elseif ( ! $parziale ) {
				return null;
			}
		}

		return $rinvio;
	}

	/**
	 * Un esito, nella forma che la schermata legge.
	 *
	 * @param string                     $codice    Codice dell'esito.
	 * @param string                     $messaggio Messaggio per chi ha salvato.
	 * @param string                     $voce      Voce del modulo.
	 * @param array<string, string>      $inviati   Valori da rimostrare nel modulo dopo un rifiuto.
	 * @param array<string, string>|null $partenza  Partenza della richiesta, da riportare nel modulo dopo un rifiuto.
	 * @return array{codice: string, messaggio: string, voce: string, inviati: array<string, string>, partenza: array<string, string>|null}
	 */
	private static function esito( string $codice, string $messaggio, string $voce = '', array $inviati = array(), ?array $partenza = null ): array {
		return array(
			'codice'    => $codice,
			'messaggio' => $messaggio,
			'voce'      => $voce,
			'inviati'   => $inviati,
			'partenza'  => $partenza,
		);
	}
}
