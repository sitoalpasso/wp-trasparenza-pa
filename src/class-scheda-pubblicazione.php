<?php
/**
 * Il riquadro in cui si scrive da quando decorre l'obbligo di pubblicare un contenuto.
 *
 * Righe di collaudo T-64..T-66 e T-81..T-83.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use DateTimeImmutable;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Pubblicazione: decorrenza dell'obbligo, fine calcolata per legge, traccia nel registro.
 *
 * L'art. 8, c. 3, del d.lgs. 33/2013 vuole la pubblicazione per un numero di
 * anni che decorre dal 1 gennaio successivo alla data da cui decorre
 * l'obbligo: la fine e quindi il 31 dicembre di tanti anni dopo la decorrenza
 * quanti ne dice quel numero. Il numero sta nel file dell'albero, con la sua fonte, e non qui. La
 * fine la scrive il componente comune, che la usa per nascondere il contenuto
 * da quel momento in ogni percorso di lettura.
 *
 * Lo stesso comma aggiunge "e comunque fino a che gli atti pubblicati
 * producono i loro effetti": una seconda data, facoltativa, dice fino a quando
 * l'atto produce effetti, e la fine e la piu lontana delle due. La seconda
 * data prolunga e non accorcia.
 *
 * Le regole delle altre schermate della sezione: nonce legato al contenuto e
 * permesso di modificarlo; data controllata; un salvataggio alla volta; i dati
 * attuali letti dalla banca dati distinguendo "assente" da "lettura fallita";
 * la data mostrata all'apertura del modulo portata nel modulo, cosi un cambio
 * fatto da un altro nel frattempo e un conflitto; prima il registro, poi i
 * dati, e i dati riletti.
 */
final class SchedaPubblicazione {

	/**
	 * Metadato che porta la data da cui decorre l'obbligo, AAAA-MM-GG.
	 */
	const META_DECORRENZA = '_trasparenza_pa_decorrenza';

	/**
	 * Campo del modulo con la data scritta.
	 */
	const CAMPO = 'trasparenza_pa_decorrenza';

	/**
	 * Campo nascosto con la data mostrata quando il modulo e stato aperto.
	 */
	const CAMPO_PARTENZA = 'trasparenza_pa_decorrenza_partenza';

	/**
	 * Metadato che porta l'ultimo giorno in cui l'atto produce effetti, AAAA-MM-GG; facoltativo.
	 */
	const META_EFFETTI = '_trasparenza_pa_effetti';

	/**
	 * Campo del modulo con la fine degli effetti scritta.
	 */
	const CAMPO_EFFETTI = 'trasparenza_pa_effetti';

	/**
	 * Campo nascosto con la fine degli effetti mostrata quando il modulo e stato aperto.
	 */
	const CAMPO_EFFETTI_PARTENZA = 'trasparenza_pa_effetti_partenza';

	/**
	 * Campo del nonce.
	 */
	const CAMPO_NONCE = 'trasparenza_pa_nonce_pubblicazione';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'pubblicazione_contenuto';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata; seguono utente e contenuto.
	 */
	const ESITO = 'trasparenza_pa_esito_pubblicazione_';

	/**
	 * Identificativo del riquadro.
	 */
	const ID_RIQUADRO = 'trasparenza-pa-pubblicazione';

	/**
	 * Aggancia il riquadro, il salvataggio e l'esito.
	 */
	public static function aggancia(): void {
		add_action( 'add_meta_boxes_' . TIPO_CONTENUTO, array( self::class, 'aggiungi_riquadro' ) );
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
		return 'trasparenza_pa_pubblicazione_' . $contenuto;
	}

	/**
	 * La data e una data vera del calendario, scritta AAAA-MM-GG e nient'altro.
	 *
	 * @param mixed $valore Valore da controllare.
	 * @return bool
	 */
	public static function data_valida( $valore ): bool {
		if ( ! is_string( $valore ) || 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}\z/', $valore ) ) {
			return false;
		}

		$data = DateTimeImmutable::createFromFormat( '!Y-m-d', $valore, wp_timezone() );

		return false !== $data && $data->format( 'Y-m-d' ) === $valore;
	}

	/**
	 * La fine della pubblicazione per legge: il 31 dicembre di tanti anni dopo la decorrenza quanti sono gli anni.
	 *
	 * Gli anni contano dal 1 gennaio successivo alla decorrenza, quindi l'ultimo
	 * giorno e il 31 dicembre di tanti anni dopo la decorrenza.
	 *
	 * @param string $decorrenza Data da cui decorre l'obbligo, gia controllata.
	 * @param int    $anni       Anni di pubblicazione.
	 * @return string|null La data AAAA-MM-GG, o nullo se andrebbe oltre il 9999.
	 */
	public static function fine_di_legge( string $decorrenza, int $anni ): ?string {
		$ultimo = (int) substr( $decorrenza, 0, 4 ) + $anni;

		return $ultimo <= 9999 ? sprintf( '%04d-12-31', $ultimo ) : null;
	}

	/**
	 * La fine della pubblicazione: la piu lontana fra quella di legge e la fine degli effetti.
	 *
	 * @param string $decorrenza Data da cui decorre l'obbligo, gia controllata.
	 * @param string $effetti    Fine degli effetti, gia controllata, o vuota.
	 * @param int    $anni       Anni di pubblicazione.
	 * @return string|null La data AAAA-MM-GG, o nullo se quella di legge andrebbe oltre il 9999.
	 */
	public static function fine( string $decorrenza, string $effetti, int $anni ): ?string {
		$legge = self::fine_di_legge( $decorrenza, $anni );

		if ( null === $legge ) {
			return null;
		}

		return strcmp( $effetti, $legge ) > 0 ? $effetti : $legge;
	}

	/**
	 * Aggiunge il riquadro alla schermata del contenuto.
	 */
	public static function aggiungi_riquadro(): void {
		add_meta_box( self::ID_RIQUADRO, __( 'Pubblicazione', 'trasparenza-pa' ), array( self::class, 'disegna' ), TIPO_CONTENUTO, 'side', 'high' );
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
	 * Per mostrare, non per decidere una scrittura: legge con `get_post_meta()`.
	 *
	 * @param WP_Post $contenuto Contenuto.
	 * @return string
	 */
	public static function riquadro( WP_Post $contenuto ): string {
		$valori     = get_post_meta( $contenuto->ID, self::META_DECORRENZA, false );
		$attuale    = is_array( $valori ) && 1 === count( $valori ) && self::data_valida( $valori[0] ) ? $valori[0] : '';
		$partenza   = is_array( $valori ) && 1 === count( $valori ) && is_string( $valori[0] ) ? $valori[0] : '';
		$albero     = PaginaPubblica::albero();
		$anni       = null === $albero ? null : $albero->durata_anni();
		$effetti    = get_post_meta( $contenuto->ID, self::META_EFFETTI, false );
		$e_attuale  = is_array( $effetti ) && 1 === count( $effetti ) && self::data_valida( $effetti[0] ) ? $effetti[0] : '';
		$e_partenza = is_array( $effetti ) && 1 === count( $effetti ) && is_string( $effetti[0] ) ? $effetti[0] : '';

		$html  = wp_nonce_field( self::azione_nonce( $contenuto->ID ), self::CAMPO_NONCE, false, false );
		$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_PARTENZA ) . '" value="' . esc_attr( $partenza ) . '">';
		$html .= '<p><label for="trasparenza-pa-decorrenza">' . esc_html__( 'Data da cui decorre l\'obbligo di pubblicazione', 'trasparenza-pa' ) . '</label></p>';
		$html .= '<input type="date" id="trasparenza-pa-decorrenza" name="' . esc_attr( self::CAMPO ) . '" value="' . esc_attr( $attuale ) . '" class="widefat">';

		if ( null === $anni ) {
			$html .= '<p>' . esc_html__( 'L\'albero delle voci in vigore non è disponibile o non dice la durata della pubblicazione: la fine non si può calcolare e la data non si può salvare.', 'trasparenza-pa' ) . '</p>';
		} else {
			/* translators: %d: anni di pubblicazione. */
			$html .= '<p>' . esc_html( sprintf( __( 'La pubblicazione dura %d anni dal 1° gennaio successivo alla data e finisce il 31 dicembre.', 'trasparenza-pa' ), $anni ) ) . '</p>';
		}

		if ( is_array( $valori ) && count( $valori ) > 1 ) {
			$html .= '<p>' . esc_html__( 'Il contenuto ha più di una data memorizzata. Scrivendo una data e salvando, resta solo quella; il registro conserva quelle trovate.', 'trasparenza-pa' ) . '</p>';
		}

		$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_EFFETTI_PARTENZA ) . '" value="' . esc_attr( $e_partenza ) . '">';
		$html .= '<p><label for="trasparenza-pa-effetti">' . esc_html__( 'L\'atto produce effetti fino al (facoltativa)', 'trasparenza-pa' ) . '</label></p>';
		$html .= '<input type="date" id="trasparenza-pa-effetti" name="' . esc_attr( self::CAMPO_EFFETTI ) . '" value="' . esc_attr( $e_attuale ) . '" class="widefat">';
		$html .= '<p>' . esc_html__( 'Se l\'atto produce effetti oltre la fine di legge, la pubblicazione dura fino all\'ultimo giorno degli effetti. Una data più vicina non accorcia la pubblicazione.', 'trasparenza-pa' ) . '</p>';

		if ( is_array( $effetti ) && count( $effetti ) > 1 ) {
			$html .= '<p>' . esc_html__( 'Il contenuto ha più di una data di fine degli effetti memorizzata. Salvando, resta solo quella scritta, o nessuna; il registro conserva quelle trovate.', 'trasparenza-pa' ) . '</p>';
		}

		$fine = conformita_core_fine_pubblicazione( $contenuto->ID );
		$fine = is_string( $fine ) && '' !== $fine ? $fine : '';

		if ( '' === $fine ) {
			$html .= '<p>' . esc_html__( 'Il contenuto non ha una fine della pubblicazione valida: non compare al pubblico, anche se è pubblicato.', 'trasparenza-pa' ) . '</p>';
		} else {
			/* translators: %s: data di fine della pubblicazione. */
			$html .= '<p>' . esc_html( sprintf( __( 'Fine della pubblicazione: %s.', 'trasparenza-pa' ), self::leggibile( $fine ) ) ) . '</p>';
		}

		$legge  = '' !== $attuale && null !== $anni ? self::fine_di_legge( $attuale, $anni ) : null;
		$attesa = null !== $legge ? self::fine( $attuale, $e_attuale, (int) $anni ) : null;

		if ( null !== $legge && '' !== $e_attuale ) {
			$html .= '<p>' . ( $attesa !== $legge
				? esc_html__( 'La fine viene dalla data degli effetti, oltre la durata di legge.', 'trasparenza-pa' )
				: esc_html__( 'La data degli effetti non prolunga la pubblicazione: la fine resta quella di legge.', 'trasparenza-pa' ) ) . '</p>';
		}

		if ( null !== $attesa && $attesa !== $fine ) {
			/* translators: %s: data di fine calcolata. */
			$html .= '<p>' . esc_html( sprintf( __( 'La fine registrata non è quella che la data dà con l\'albero in vigore, %s. Salvando il contenuto si riallinea.', 'trasparenza-pa' ), self::leggibile( $attesa ) ) ) . '</p>';
		}

		return $html;
	}

	/**
	 * Aggancio a `save_post`: applica la data e conserva l'esito per la schermata.
	 *
	 * Si fa qualcosa solo se la richiesta viene dal riquadro: le altre strade di
	 * salvataggio non portano la data e non la toccano.
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- come sopra.
		$esito = self::applica( $id, wp_unslash( $_POST ) );

		if ( 'invariato' !== $esito['codice'] ) {
			set_transient( self::chiave_esito( $id ), $esito, MINUTE_IN_SECONDS );
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
	 * Applica la data scritta a un contenuto.
	 *
	 * @param int                  $id        Identificativo del contenuto.
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string}
	 */
	public static function applica( int $id, array $richiesta ): array {
		$nonce = $richiesta[ self::CAMPO_NONCE ] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::azione_nonce( $id ) ) ) {
			return self::esito( 'nonce', __( 'Data della pubblicazione non salvata: la richiesta è scaduta o non viene da questo contenuto. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$contenuto = get_post( $id );

		if ( ! $contenuto instanceof WP_Post || TIPO_CONTENUTO !== $contenuto->post_type || ! current_user_can( 'edit_post', $id ) ) {
			return self::esito( 'permesso', __( 'Data della pubblicazione non salvata: non avete il permesso di modificare questo contenuto.', 'trasparenza-pa' ) );
		}

		$scelta     = $richiesta[ self::CAMPO ] ?? null;
		$partenza   = $richiesta[ self::CAMPO_PARTENZA ] ?? null;
		$effetti    = $richiesta[ self::CAMPO_EFFETTI ] ?? null;
		$e_partenza = $richiesta[ self::CAMPO_EFFETTI_PARTENZA ] ?? null;

		if ( ! is_string( $scelta ) || ! is_string( $partenza ) || ! is_string( $effetti ) || ! is_string( $e_partenza ) ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la richiesta non diceva quali date erano scritte e quali erano mostrate. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		if ( '' === $scelta ) {
			if ( '' !== $partenza ) {
				return self::esito( 'cancellata', __( 'Data della pubblicazione non cancellata: una volta scritta si può cambiare ma non togliere, e la fine della pubblicazione resta quella registrata.', 'trasparenza-pa' ) );
			}

			return '' === $effetti
				? self::esito( 'invariato', '' )
				: self::esito( 'dati', __( 'Data della pubblicazione non salvata: per la fine degli effetti serve la data da cui decorre l\'obbligo, senza la quale non c\'è una fine da prolungare.', 'trasparenza-pa' ) );
		}

		if ( ! self::data_valida( $scelta ) ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la data non esiste nel calendario o non è scritta per intero.', 'trasparenza-pa' ) );
		}

		if ( '' !== $effetti && ! self::data_valida( $effetti ) ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la fine degli effetti non esiste nel calendario o non è scritta per intero.', 'trasparenza-pa' ) );
		}

		if ( '' !== $effetti && strcmp( $effetti, $scelta ) < 0 ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la fine degli effetti viene prima della data da cui decorre l\'obbligo, e un atto non smette di produrre effetti prima che decorra l\'obbligo di pubblicarlo.', 'trasparenza-pa' ) );
		}

		$albero = PaginaPubblica::albero();
		$anni   = null === $albero ? null : $albero->durata_anni();

		if ( null === $anni ) {
			return self::esito( 'albero', __( 'Data della pubblicazione non salvata: l\'albero delle voci in vigore non è disponibile o non dice la durata della pubblicazione, e senza non si può calcolare la fine.', 'trasparenza-pa' ) );
		}

		$fine = self::fine( $scelta, $effetti, $anni );

		if ( null === $fine ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la fine della pubblicazione cadrebbe oltre il 9999.', 'trasparenza-pa' ) );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Data della pubblicazione non salvata: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			return self::scrivi( $id, $scelta, $partenza, $effetti, $e_partenza, $fine, $anni );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Registra e scrive decorrenza e fine, con il blocco gia preso.
	 *
	 * Piu righe di decorrenza non nascono dal riquadro: arrivano da una
	 * scrittura diretta o da un'importazione. Il riquadro le segnala e mostra
	 * una partenza vuota; una data scritta da li le sostituisce con una, dopo
	 * una voce di registro che conserva quelle trovate. Se il modulo mostrava
	 * una data, e stato aperto prima che le righe diventassero piu di una: e un
	 * conflitto.
	 *
	 * Con la stessa data e la fine gia giusta non c'e niente da fare. Con la
	 * stessa data e una fine diversa, per esempio dopo una fine rifiutata o una
	 * versione nuova dell'albero, il salvataggio riallinea la fine.
	 *
	 * La fine degli effetti segue le stesse regole, con una differenza: si puo
	 * togliere, e con piu righe il riquadro mostrato con la partenza vuota le
	 * sostituisce con quella scritta, o le toglie tutte.
	 *
	 * @param int    $id         Identificativo del contenuto.
	 * @param string $scelta     Data scritta, gia controllata.
	 * @param string $partenza   Data mostrata quando il modulo e stato aperto.
	 * @param string $effetti    Fine degli effetti scritta, gia controllata, o vuota.
	 * @param string $e_partenza Fine degli effetti mostrata quando il modulo e stato aperto.
	 * @param string $fine       Fine calcolata.
	 * @param int    $anni       Anni di pubblicazione per legge.
	 * @return array{codice: string, messaggio: string}
	 */
	private static function scrivi( int $id, string $scelta, string $partenza, string $effetti, string $e_partenza, string $fine, int $anni ): array {
		$date      = Metadato::leggi( $id, self::META_DECORRENZA );
		$fini      = Metadato::leggi( $id, conformita_core_chiave_fine_pubblicazione() );
		$memoria_e = Metadato::leggi( $id, self::META_EFFETTI );

		if ( is_wp_error( $date ) || is_wp_error( $fini ) || is_wp_error( $memoria_e ) ) {
			return self::esito( 'lettura', __( 'Data della pubblicazione non salvata: le date attuali del contenuto non si sono potute leggere dalla banca dati, e senza conoscerle il registro direbbe cambiato quello che non lo è. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
		}

		$piu       = count( $date ) > 1;
		$attuale   = $piu ? '' : ( $date[0] ?? '' );
		$piu_e     = count( $memoria_e ) > 1;
		$e_attuale = $piu_e ? '' : ( $memoria_e[0] ?? '' );

		if ( ! $piu && ! $piu_e && $attuale === $scelta && $e_attuale === $effetti && array( $fine ) === $fini ) {
			return self::esito( 'invariato', '' );
		}

		if ( $attuale !== $partenza || $e_attuale !== $e_partenza ) {
			return self::esito( 'conflitto', __( 'Data della pubblicazione non salvata: dopo l\'apertura di questa schermata la data del contenuto è cambiata. Ricaricare la schermata, che mostra la data attuale, e riprovare.', 'trasparenza-pa' ) );
		}

		$dettagli = array(
			'prima'         => $attuale,
			'dopo'          => $scelta,
			'effetti_prima' => $e_attuale,
			'effetti_dopo'  => $effetti,
			'fine_prima'    => array_values( $fini ),
			'fine_dopo'     => $fine,
		);

		if ( $piu ) {
			$dettagli['trovate'] = array_values( $date );
		}

		if ( $piu_e ) {
			$dettagli['effetti_trovati'] = array_values( $memoria_e );
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $id,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => $dettagli,
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return self::esito(
				'registro',
				/* translators: %s: motivo riportato dal registro. */
				sprintf( __( 'Data della pubblicazione non salvata: il registro delle modifiche non ha accettato il cambio, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() )
			);
		}

		if ( $piu ) {
			delete_post_meta( $id, self::META_DECORRENZA );
			add_post_meta( $id, self::META_DECORRENZA, $scelta, true );
		} elseif ( $attuale !== $scelta ) {
			update_post_meta( $id, self::META_DECORRENZA, $scelta );
		}

		if ( Metadato::leggi( $id, self::META_DECORRENZA ) !== array( $scelta ) ) {
			return self::esito( 'scrittura', __( 'La data della pubblicazione non si è potuta scrivere, e la fine non è stata toccata. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ) );
		}

		if ( $piu_e || ( '' === $effetti && '' !== $e_attuale ) ) {
			delete_post_meta( $id, self::META_EFFETTI );
		}

		if ( '' !== $effetti && ( $piu_e || '' === $e_attuale ) ) {
			add_post_meta( $id, self::META_EFFETTI, $effetti, true );
		} elseif ( '' !== $effetti && $e_attuale !== $effetti ) {
			update_post_meta( $id, self::META_EFFETTI, $effetti );
		}

		if ( Metadato::leggi( $id, self::META_EFFETTI ) !== ( '' === $effetti ? array() : array( $effetti ) ) ) {
			return self::esito( 'scrittura', __( 'La data della pubblicazione è salvata, ma la fine degli effetti non si è potuta scrivere o togliere, e la fine della pubblicazione non è stata toccata. Il registro delle modifiche contiene un cambio che non è avvenuto del tutto.', 'trasparenza-pa' ) );
		}

		$scritta = conformita_core_imposta_fine_pubblicazione( $id, $fine );

		if ( is_wp_error( $scritta ) || Metadato::leggi( $id, conformita_core_chiave_fine_pubblicazione() ) !== array( $fine ) ) {
			return self::esito( 'fine', __( 'La data della pubblicazione è salvata, ma la fine della pubblicazione non si è potuta scrivere: il riquadro mostra la differenza, e salvando di nuovo si riallinea. Finché il contenuto non ha una fine valida, non compare al pubblico.', 'trasparenza-pa' ) );
		}

		if ( self::fine_di_legge( $scelta, $anni ) !== $fine ) {
			/* translators: 1: data di fine della pubblicazione; 2: anni di pubblicazione per legge. */
			return self::esito( 'salvato', sprintf( __( 'Data della pubblicazione salvata. Fine della pubblicazione: %1$s, oltre i %2$d anni di legge perché l\'atto produce effetti fino a quel giorno.', 'trasparenza-pa' ), self::leggibile( $fine ), $anni ) );
		}

		/* translators: %s: data di fine della pubblicazione. */
		return self::esito( 'salvato', sprintf( __( 'Data della pubblicazione salvata. Fine della pubblicazione: %s.', 'trasparenza-pa' ), self::leggibile( $fine ) ) );
	}

	/**
	 * Mostra l'esito, una volta, a chi ha salvato, sulla schermata di quel contenuto.
	 */
	public static function mostra_esito(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$contenuto = get_post();

		if ( null === $schermata || 'post' !== $schermata->base || TIPO_CONTENUTO !== $schermata->post_type || ! $contenuto instanceof WP_Post || TIPO_CONTENUTO !== $contenuto->post_type ) {
			return;
		}

		$chiave = self::chiave_esito( $contenuto->ID );
		$esito  = get_transient( $chiave );

		if ( ! is_array( $esito ) || ! isset( $esito['codice'], $esito['messaggio'] ) ) {
			return;
		}

		delete_transient( $chiave );

		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			'salvato' === $esito['codice'] ? 'notice-success' : 'notice-error',
			esc_html( (string) $esito['messaggio'] )
		);
	}

	/**
	 * Una data AAAA-MM-GG nella forma del sito.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function leggibile( string $data ): string {
		$giorno = DateTimeImmutable::createFromFormat( '!Y-m-d', $data, wp_timezone() );

		return false === $giorno ? $data : (string) wp_date( (string) get_option( 'date_format' ), $giorno->getTimestamp() );
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
