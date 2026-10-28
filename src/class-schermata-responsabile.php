<?php
/**
 * La schermata in cui l'ente indica il responsabile della trasparenza.
 *
 * Righe di collaudo T-57 e T-58.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Responsabile: modulo, salvataggio con traccia nel registro, avviso se manca.
 *
 * Stessa forma della schermata degli stati: il salvataggio e una funzione che
 * riceve la richiesta e restituisce l'esito, e le prove la chiamano come la
 * chiamerebbe il modulo.
 */
final class SchermataResponsabile {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-responsabile';

	/**
	 * Azione del modulo e del nonce.
	 */
	const AZIONE = 'trasparenza_pa_responsabile';

	/**
	 * Nome del campo che porta i dati, una coppia campo e valore.
	 */
	const CAMPO = 'responsabile';

	/**
	 * Nome del campo nascosto che porta i valori mostrati quando il modulo e stato aperto.
	 */
	const CAMPO_PARTENZA = 'partenza';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'responsabile_trasparenza';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 */
	const ESITO = 'trasparenza_pa_esito_responsabile_';

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		// Dopo la voce principale, che la schermata degli stati aggiunge con la priorita normale.
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ), 11 );
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
			__( 'Responsabile della trasparenza', 'trasparenza-pa' ),
			__( 'Responsabile', 'trasparenza-pa' ),
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
	 * Salva i dati del responsabile.
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
	 * Il registro dice quali campi sono cambiati, non i valori: nome e recapiti
	 * sono dati personali, e il registro li conserverebbe anche dopo che
	 * l'ente li ha cambiati.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, campi: array<int, string>, inviati: array<string, string>}
	 */
	public static function salva( array $richiesta ): array {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di indicare il responsabile della trasparenza.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: niente salvato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$inviati = $richiesta[ self::CAMPO ] ?? null;

		if ( ! is_array( $inviati ) ) {
			return self::esito( 'dati', __( 'Niente salvato: la richiesta non conteneva i dati del responsabile.', 'trasparenza-pa' ) );
		}

		$dati = Responsabile::controlla( $inviati );

		if ( is_wp_error( $dati ) ) {
			return self::esito( 'dati', __( 'Niente salvato.', 'trasparenza-pa' ) . ' ' . $dati->get_error_message(), array(), self::da_rimostrare( $inviati ) );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Niente salvato: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ), array(), $dati );
		}

		try {
			return self::scrivi( $dati, $richiesta[ self::CAMPO_PARTENZA ] ?? null );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Registra e scrive i dati controllati, con il blocco gia preso.
	 *
	 * @param array{nome: string, email: string, telefono: string, nomina: string} $dati      Dati controllati.
	 * @param mixed                                                                $partenze  Valori mostrati dal modulo all'apertura.
	 * @return array{codice: string, messaggio: string, campi: array<int, string>, inviati: array<string, string>}
	 */
	private static function scrivi( array $dati, $partenze ): array {
		if ( ! is_array( $partenze ) ) {
			$partenze = array();
		}

		foreach ( Responsabile::CAMPI as $campo ) {
			if ( ! isset( $partenze[ $campo ] ) || ! is_string( $partenze[ $campo ] ) ) {
				return self::esito( 'dati', __( 'Niente salvato: la richiesta non diceva quali dati mostrava il modulo. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ), array(), $dati );
			}
		}

		$prima = Responsabile::conservati();

		if ( is_wp_error( $prima ) ) {
			return self::esito( 'lettura', __( 'Niente salvato: i dati attuali del responsabile non si sono potuti leggere dalla banca dati, e senza conoscerli il registro direbbe cambiato quello che non lo è. Riprovare fra qualche minuto.', 'trasparenza-pa' ), array(), $dati );
		}

		$attuali = $prima ?? array_fill_keys( Responsabile::CAMPI, '' );
		$nuovi   = $attuali;

		foreach ( Responsabile::CAMPI as $campo ) {
			if ( $dati[ $campo ] === $partenze[ $campo ] || $dati[ $campo ] === $attuali[ $campo ] ) {
				continue;
			}

			if ( $attuali[ $campo ] !== $partenze[ $campo ] ) {
				return self::esito( 'conflitto', __( 'Niente salvato: dopo l\'apertura di questa schermata qualcun altro ha cambiato gli stessi dati del responsabile. Ricaricare la schermata, che mostra i dati attuali, e riprovare.', 'trasparenza-pa' ), array(), $dati );
			}

			$nuovi[ $campo ] = $dati[ $campo ];
		}

		$controllati = Responsabile::controlla( $nuovi );

		if ( is_wp_error( $controllati ) ) {
			return self::esito( 'conflitto', __( 'Niente salvato: i dati attuali del responsabile sono cambiati dopo l\'apertura di questa schermata, e uniti a quelli inviati non sarebbero completi. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ), array(), $dati );
		}

		$dati  = $controllati;
		$campi = array();

		foreach ( Responsabile::CAMPI as $campo ) {
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

		update_option( Responsabile::OPZIONE, $dati, false );

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( Responsabile::OPZIONE, 'options' );

		if ( Responsabile::conservati() !== $dati ) {
			return self::esito(
				'scrittura',
				__( 'I dati del responsabile non si sono potuti scrivere: restano quelli di prima. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ),
				array(),
				$dati
			);
		}

		return self::esito( 'salvato', __( 'Dati del responsabile salvati.', 'trasparenza-pa' ), $campi );
	}

	/**
	 * Disegna la schermata.
	 */
	public static function mostra(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			wp_die( esc_html__( 'Non avete il permesso di indicare il responsabile della trasparenza.', 'trasparenza-pa' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Responsabile della trasparenza', 'trasparenza-pa' ) . '</h1>';

		$chiave = self::ESITO . get_current_user_id();
		$esito  = get_transient( $chiave );
		$valori = Responsabile::dati() ?? array_fill_keys( Responsabile::CAMPI, '' );
		// I valori della banca dati all'apertura, anche quando il modulo rimostra quanto inviato.
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
			}
		}

		$albero = PaginaPubblica::albero();
		$voce   = null === $albero ? null : $albero->collocazione( Responsabile::DATO );

		echo '<p>' . esc_html__( 'L\'art. 43 del d.lgs. 33/2013 chiede che la sezione indichi il responsabile della prevenzione della corruzione e della trasparenza. Nome e indirizzo email sono obbligatori; telefono e indirizzo dell\'atto di nomina sono facoltativi.', 'trasparenza-pa' ) . '</p>';

		if ( null !== $voce ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: nome della voce della sezione. */
					__( 'I dati compaiono sulla pagina pubblica, nella voce "%s".', 'trasparenza-pa' ),
					(string) ( $albero->voce( $voce )['nome'] ?? $voce )
				)
			) . '</p>';
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'L\'albero delle voci non indica dove mostrare il responsabile: i dati si salvano, ma la pagina pubblica non li mostra.', 'trasparenza-pa' ) . '</p></div>';
		}

		$etichette = array(
			'nome'     => array( __( 'Nome e cognome', 'trasparenza-pa' ), 'text', true ),
			'email'    => array( __( 'Indirizzo email', 'trasparenza-pa' ), 'email', true ),
			'telefono' => array( __( 'Telefono', 'trasparenza-pa' ), 'tel', false ),
			'nomina'   => array( __( 'Indirizzo dell\'atto di nomina', 'trasparenza-pa' ), 'url', false ),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE ) . '">';
		wp_nonce_field( self::AZIONE );

		foreach ( Responsabile::CAMPI as $campo ) {
			printf(
				'<input type="hidden" name="%1$s" value="%2$s">',
				esc_attr( self::CAMPO_PARTENZA . '[' . $campo . ']' ),
				esc_attr( (string) $partenze[ $campo ] )
			);
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $etichette as $campo => list( $etichetta, $tipo, $obbligatorio ) ) {
			$id = 'trasparenza-pa-responsabile-' . $campo;

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
	 * Avviso quando il responsabile non e indicato.
	 *
	 * A chi ha il permesso della sezione, che e chi puo rimediare, e solo sulla
	 * bacheca e sulle schermate della sezione: e un obbligo di legge mancante,
	 * ma ripeterlo su ogni pagina del pannello sarebbe rumore.
	 */
	public static function mostra_avviso(): void {
		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) || ! Avvio::registrata() || null !== Responsabile::dati() ) {
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
			esc_html__( 'Amministrazione trasparente: il responsabile della prevenzione della corruzione e della trasparenza non è indicato, e la sezione non lo mostra.', 'trasparenza-pa' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGINA ) ),
			esc_html__( 'Indicarlo', 'trasparenza-pa' )
		);
	}

	/**
	 * I campi inviati che si possono rimostrare nel modulo, solo testi.
	 *
	 * @param array<mixed> $inviati Campi inviati.
	 * @return array<string, string>
	 */
	private static function da_rimostrare( array $inviati ): array {
		$valori = array();

		foreach ( Responsabile::CAMPI as $campo ) {
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
