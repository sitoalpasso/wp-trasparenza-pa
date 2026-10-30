<?php
/**
 * La schermata in cui l'ente indica a chi si chiede l'accesso civico.
 *
 * Righe di collaudo T-101 e T-102.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Accesso civico: modulo, salvataggio con traccia nel registro, avviso se manca.
 *
 * Stessa forma della schermata del responsabile, campo per campo: le stesse
 * regole sul permesso, sul nonce, sul blocco, sul registro e sui moduli aperti
 * insieme.
 */
final class SchermataAccessoCivico {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-accesso-civico';

	/**
	 * Azione del modulo e del nonce.
	 */
	const AZIONE = 'trasparenza_pa_accesso_civico';

	/**
	 * Nome del campo che porta i dati, una coppia campo e valore.
	 */
	const CAMPO = 'accesso_civico';

	/**
	 * Nome del campo nascosto che porta i valori mostrati quando il modulo e stato aperto.
	 */
	const CAMPO_PARTENZA = 'partenza';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'accesso_civico';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 */
	const ESITO = 'trasparenza_pa_esito_accesso_civico_';

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		// Dopo il responsabile e i rinvii.
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ), 13 );
		add_action( 'admin_post_' . self::AZIONE, array( self::class, 'da_admin_post' ) );
		add_action( 'admin_notices', array( self::class, 'mostra_avviso' ) );
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
			__( 'Accesso civico', 'trasparenza-pa' ),
			__( 'Accesso civico', 'trasparenza-pa' ),
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
	 * Salva i dati dell'accesso civico.
	 *
	 * Le regole della schermata degli stati: permesso e nonce prima di tutto;
	 * tutti i campi controllati prima di scrivere, e un solo difetto non fa
	 * scrivere niente; un salvataggio alla volta; prima il registro, poi i dati,
	 * e i dati si rileggono dopo averli scritti.
	 *
	 * Si applicano solo i campi cambiati da chi ha salvato, cioe diversi dai
	 * valori che il modulo mostrava quando e stato aperto. Gli altri restano
	 * come sono in banca dati: due moduli aperti insieme non si cancellano a
	 * vicenda. Se un campo cambiato e stato cambiato anche da un altro
	 * salvataggio dopo l'apertura del modulo, e un conflitto e non si salva
	 * niente.
	 *
	 * Il registro dice quali campi sono cambiati, non i valori: il nome del
	 * titolare del potere sostitutivo e un dato personale, e il registro lo
	 * conserverebbe anche dopo che l'ente lo ha cambiato.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, campi: array<int, string>, inviati: array<string, string>}
	 */
	public static function salva( array $richiesta ): array {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di indicare a chi si chiede l\'accesso civico.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: niente salvato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$partenze = self::partenze_valide( $richiesta[ self::CAMPO_PARTENZA ] ?? null );
		$esito    = self::controlla_e_scrivi( $richiesta, $partenze );

		/*
		 * Dopo un rifiuto il modulo rimostra quanto inviato, e deve rimostrarlo
		 * con le partenze di quando era stato aperto: rilette dalla banca dati
		 * direbbero "cambiato dall'utente" un campo che nel frattempo ha
		 * cambiato qualcun altro, e il reinvio lo riporterebbe indietro.
		 */
		if ( 'salvato' !== $esito['codice'] && null !== $partenze ) {
			$esito['partenze'] = $partenze;
		}

		return $esito;
	}

	/**
	 * Controlla i dati inviati e li scrive, con permesso e nonce gia verificati.
	 *
	 * @param array<string, mixed>       $richiesta Campi del modulo.
	 * @param array<string, string>|null $partenze  Valori mostrati dal modulo all'apertura, se validi.
	 * @return array{codice: string, messaggio: string, campi: array<int, string>, inviati: array<string, string>}
	 */
	private static function controlla_e_scrivi( array $richiesta, ?array $partenze ): array {
		$inviati = $richiesta[ self::CAMPO ] ?? null;

		if ( ! is_array( $inviati ) ) {
			return self::esito( 'dati', __( 'Niente salvato: la richiesta non conteneva i dati dell\'accesso civico.', 'trasparenza-pa' ) );
		}

		$dati = AccessoCivico::controlla( $inviati );

		if ( is_wp_error( $dati ) ) {
			return self::esito( 'dati', __( 'Niente salvato.', 'trasparenza-pa' ) . ' ' . $dati->get_error_message(), array(), self::da_rimostrare( $inviati ) );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Niente salvato: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ), array(), $dati );
		}

		try {
			return self::scrivi( $dati, $partenze );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Registra e scrive i dati controllati, con il blocco gia preso.
	 *
	 * @param array<string, string>      $dati      Dati controllati.
	 * @param array<string, string>|null $partenze  Valori mostrati dal modulo all'apertura, se validi.
	 * @return array{codice: string, messaggio: string, campi: array<int, string>, inviati: array<string, string>}
	 */
	private static function scrivi( array $dati, ?array $partenze ): array {
		if ( null === $partenze ) {
			return self::esito( 'dati', __( 'Niente salvato: la richiesta non diceva quali dati mostrava il modulo. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ), array(), $dati );
		}

		$prima = AccessoCivico::conservati();

		if ( is_wp_error( $prima ) ) {
			return self::esito( 'lettura', __( 'Niente salvato: i dati attuali dell\'accesso civico non si sono potuti leggere dalla banca dati, e senza conoscerli il registro direbbe cambiato quello che non lo è. Riprovare fra qualche minuto.', 'trasparenza-pa' ), array(), $dati );
		}

		$attuali = $prima ?? array_fill_keys( AccessoCivico::CAMPI, '' );
		$nuovi   = $attuali;

		foreach ( AccessoCivico::CAMPI as $campo ) {
			if ( $dati[ $campo ] === $partenze[ $campo ] || $dati[ $campo ] === $attuali[ $campo ] ) {
				continue;
			}

			if ( $attuali[ $campo ] !== $partenze[ $campo ] ) {
				return self::esito( 'conflitto', __( 'Niente salvato: dopo l\'apertura di questa schermata qualcun altro ha cambiato gli stessi dati dell\'accesso civico. Ricaricare la schermata, che mostra i dati attuali, e riprovare.', 'trasparenza-pa' ), array(), $dati );
			}

			$nuovi[ $campo ] = $dati[ $campo ];
		}

		$controllati = AccessoCivico::controlla( $nuovi );

		if ( is_wp_error( $controllati ) ) {
			return self::esito( 'conflitto', __( 'Niente salvato: i dati attuali dell\'accesso civico sono cambiati dopo l\'apertura di questa schermata, e uniti a quelli inviati non sarebbero completi. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ), array(), $dati );
		}

		$dati  = $controllati;
		$campi = array();

		foreach ( AccessoCivico::CAMPI as $campo ) {
			if ( null === $prima || $prima[ $campo ] !== $dati[ $campo ] ) {
				$campi[] = $campo;
			}
		}

		if ( array() === $campi ) {
			return self::esito( 'salvato', __( 'Nessun dato cambiato.', 'trasparenza-pa' ) );
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'  => SEZIONE,
				'azione'   => self::AZIONE_REGISTRO,
				'dettagli' => array(
					'campi' => $campi,
				),
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return self::esito(
				'registro',
				/* translators: %s: motivo riportato dal registro. */
				sprintf( __( 'Niente salvato: il registro delle modifiche non ha accettato il cambio, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() ),
				array(),
				$dati
			);
		}

		update_option( AccessoCivico::OPZIONE, $dati, false );

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( AccessoCivico::OPZIONE, 'options' );

		if ( AccessoCivico::conservati() !== $dati ) {
			return self::esito(
				'scrittura',
				__( 'I dati dell\'accesso civico non si sono potuti scrivere: restano quelli di prima. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ),
				array(),
				$dati
			);
		}

		return self::esito( 'salvato', __( 'Dati dell\'accesso civico salvati.', 'trasparenza-pa' ), $campi );
	}

	/**
	 * Disegna la schermata.
	 */
	public static function mostra(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			wp_die( esc_html__( 'Non avete il permesso di indicare a chi si chiede l\'accesso civico.', 'trasparenza-pa' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Accesso civico', 'trasparenza-pa' ) . '</h1>';

		$chiave = self::ESITO . get_current_user_id();
		$esito  = get_transient( $chiave );
		$valori = AccessoCivico::dati() ?? array_fill_keys( AccessoCivico::CAMPI, '' );
		// I valori della banca dati all'apertura; dopo un rifiuto, quelli della richiesta rifiutata.
		$partenze = $valori;

		if ( is_array( $esito ) && isset( $esito['codice'], $esito['messaggio'] ) ) {
			delete_transient( $chiave );

			printf(
				'<div class="notice %1$s"><p>%2$s</p></div>',
				'salvato' === $esito['codice'] ? 'notice-success' : 'notice-error',
				esc_html( (string) $esito['messaggio'] )
			);

			// Dopo un rifiuto si rimostra quello che si era scritto, per correggerlo senza riscriverlo.
			if ( 'salvato' !== $esito['codice'] && is_array( $esito['inviati'] ?? null ) && array() !== $esito['inviati'] ) {
				$valori = array_merge( $valori, self::da_rimostrare( $esito['inviati'] ) );

				// Con le partenze della richiesta rifiutata, non con quelle rilette.
				$partenze = self::partenze_valide( $esito['partenze'] ?? null ) ?? $partenze;
			}
		}

		$albero = PaginaPubblica::albero();
		$voce   = null === $albero ? null : $albero->collocazione( AccessoCivico::DATO );

		echo '<p>' . esc_html__( 'La richiesta di accesso civico generalizzato si presenta all\'ufficio che l\'ente indica nella sezione (art. 5, c. 3, del d.lgs. 33/2013); chi non riceve risposta nei termini si rivolge al titolare del potere sostitutivo (art. 2, c. 9-bis, della l. 241/1990). La delibera ANAC 1310/2016 chiede di pubblicare per entrambi telefono ed email: tutto è obbligatorio, salvo l\'indirizzo del modulo che l\'ente mette a disposizione. La richiesta semplice va al responsabile della trasparenza, che si indica nella sua schermata.', 'trasparenza-pa' ) . '</p>';

		if ( null !== $voce ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: nome della voce della sezione. */
					__( 'I dati compaiono sulla pagina pubblica, nella voce "%s".', 'trasparenza-pa' ),
					(string) ( $albero->voce( $voce )['nome'] ?? $voce )
				)
			) . '</p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'L\'albero delle voci non indica dove mostrare i dati dell\'accesso civico: i dati si salvano, ma la pagina pubblica non li mostra.', 'trasparenza-pa' ) . '</p></div>';
		}

		$etichette = array(
			'ufficio'            => array( __( 'Ufficio a cui si presenta la richiesta generalizzata', 'trasparenza-pa' ), 'text', true ),
			'ufficio_email'      => array( __( 'Email dell\'ufficio', 'trasparenza-pa' ), 'email', true ),
			'ufficio_telefono'   => array( __( 'Telefono dell\'ufficio', 'trasparenza-pa' ), 'tel', true ),
			'sostituto'          => array( __( 'Titolare del potere sostitutivo', 'trasparenza-pa' ), 'text', true ),
			'sostituto_email'    => array( __( 'Email del titolare del potere sostitutivo', 'trasparenza-pa' ), 'email', true ),
			'sostituto_telefono' => array( __( 'Telefono del titolare del potere sostitutivo', 'trasparenza-pa' ), 'tel', true ),
			'modulo'             => array( __( 'Indirizzo del modulo per la richiesta', 'trasparenza-pa' ), 'url', false ),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE ) . '">';
		wp_nonce_field( self::AZIONE );

		foreach ( AccessoCivico::CAMPI as $campo ) {
			printf(
				'<input type="hidden" name="%1$s" value="%2$s">',
				esc_attr( self::CAMPO_PARTENZA . '[' . $campo . ']' ),
				esc_attr( (string) $partenze[ $campo ] )
			);
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $etichette as $campo => list( $etichetta, $tipo, $obbligatorio ) ) {
			$id = 'trasparenza-pa-accesso-civico-' . $campo;

			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s%3$s</label></th><td><input type="%4$s" id="%1$s" name="%5$s" value="%6$s" class="regular-text"%7$s></td></tr>',
				esc_attr( $id ),
				esc_html( $etichetta ),
				$obbligatorio ? ' ' . esc_html__( '(obbligatorio)', 'trasparenza-pa' ) : '',
				esc_attr( $tipo ),
				esc_attr( self::CAMPO . '[' . $campo . ']' ),
				esc_attr( (string) ( $valori[ $campo ] ?? '' ) ),
				$obbligatorio ? ' required' : ''
			);
		}

		echo '</tbody></table>';
		submit_button( __( 'Salva', 'trasparenza-pa' ) );
		echo '</form></div>';
	}

	/**
	 * Avviso quando i dati dell'accesso civico non sono indicati.
	 *
	 * A chi ha il permesso della sezione, che e chi puo rimediare, e solo sulla
	 * bacheca e sulle schermate della sezione: e un obbligo di legge mancante,
	 * ma ripeterlo su ogni pagina del pannello sarebbe rumore.
	 */
	public static function mostra_avviso(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) || ! Avvio::registrata() || null !== AccessoCivico::dati() ) {
			return;
		}

		$schermata = get_current_screen();

		/*
		 * La sottovoce prende il prefisso dal titolo tradotto della voce
		 * principale: si riconosce dalla fine del nome, che non cambia.
		 */
		if ( null === $schermata || ! ( in_array( $schermata->id, array( 'dashboard', 'toplevel_page_' . SchermataStati::PAGINA ), true ) || str_ends_with( $schermata->id, '_page_' . self::PAGINA ) ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Amministrazione trasparente: la sezione non dice a chi si chiede l\'accesso civico, né a chi rivolgersi se la risposta non arriva.', 'trasparenza-pa' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGINA ) ),
			esc_html__( 'Indicarlo', 'trasparenza-pa' )
		);
	}

	/**
	 * I valori di partenza di una richiesta, se ci sono tutti i campi e sono tutti testi.
	 *
	 * @param mixed $partenze Valori di partenza ricevuti.
	 * @return array<string, string>|null
	 */
	private static function partenze_valide( $partenze ): ?array {
		if ( ! is_array( $partenze ) ) {
			return null;
		}

		$valide = array();

		foreach ( AccessoCivico::CAMPI as $campo ) {
			if ( ! isset( $partenze[ $campo ] ) || ! is_string( $partenze[ $campo ] ) ) {
				return null;
			}

			$valide[ $campo ] = $partenze[ $campo ];
		}

		return $valide;
	}

	/**
	 * I campi inviati che si possono rimostrare nel modulo, solo testi.
	 *
	 * @param array<mixed> $inviati Campi inviati.
	 * @return array<string, string>
	 */
	private static function da_rimostrare( array $inviati ): array {
		$valori = array();

		foreach ( AccessoCivico::CAMPI as $campo ) {
			if ( isset( $inviati[ $campo ] ) && is_string( $inviati[ $campo ] ) ) {
				$valori[ $campo ] = $inviati[ $campo ];
			}
		}

		return $valori;
	}

	/**
	 * Un esito, nella forma che la schermata legge.
	 *
	 * @param string                $codice    Codice dell'esito.
	 * @param string                $messaggio Messaggio per chi ha salvato.
	 * @param array<int, string>    $campi     Campi cambiati e salvati.
	 * @param array<string, string> $inviati   Valori da rimostrare nel modulo dopo un rifiuto.
	 * @return array{codice: string, messaggio: string, campi: array<int, string>, inviati: array<string, string>}
	 */
	private static function esito( string $codice, string $messaggio, array $campi = array(), array $inviati = array() ): array {
		return array(
			'codice'    => $codice,
			'messaggio' => $messaggio,
			'campi'     => $campi,
			'inviati'   => $inviati,
		);
	}
}
