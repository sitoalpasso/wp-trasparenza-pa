<?php
/**
 * Il riquadro in cui si scrive da quando decorre l'obbligo di pubblicare un contenuto.
 *
 * Righe di collaudo T-64..T-66, T-81..T-83 e T-86..T-88.
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
 * Lo stesso comma fa salvi i termini diversi degli artt. 14, c. 2, e 15,
 * c. 4: nelle voci che il file dell'albero lega alla cessazione, una terza
 * data, la cessazione del mandato o dell'incarico, da la fine: tanti anni
 * dopo quanti ne dice l'albero, al posto della regola generale.
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
	 * Metadato che porta la cessazione del mandato o dell'incarico, AAAA-MM-GG; facoltativo.
	 */
	const META_CESSAZIONE = '_trasparenza_pa_cessazione';

	/**
	 * Campo del modulo con la cessazione scritta.
	 */
	const CAMPO_CESSAZIONE = 'trasparenza_pa_cessazione';

	/**
	 * Campo nascosto con la cessazione mostrata quando il modulo e stato aperto.
	 */
	const CAMPO_CESSAZIONE_PARTENZA = 'trasparenza_pa_cessazione_partenza';

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
		// Dopo la voce, che decide se la cessazione e ammessa: nello stesso salvataggio la voce si scrive prima.
		add_action( 'save_post_' . TIPO_CONTENUTO, array( self::class, 'da_save_post' ), 20, 2 );
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
	 * La fine della pubblicazione contata dalla cessazione: lo stesso giorno, tanti anni dopo.
	 *
	 * Dal 29 febbraio, quando quel giorno manca nel calendario di arrivo, la fine cade il 1 marzo: fra
	 * le due letture e quella che non accorcia la pubblicazione.
	 *
	 * @param string $cessazione Data di cessazione, gia controllata.
	 * @param int    $anni       Anni dopo la cessazione.
	 * @return string|null La data AAAA-MM-GG, o nullo se andrebbe oltre il 9999.
	 */
	public static function fine_da_cessazione( string $cessazione, int $anni ): ?string {
		$giorno = DateTimeImmutable::createFromFormat( '!Y-m-d', $cessazione, wp_timezone() );

		if ( false === $giorno ) {
			return null;
		}

		$fine = $giorno->modify( '+' . $anni . ' years' );

		return (int) $fine->format( 'Y' ) <= 9999 ? $fine->format( 'Y-m-d' ) : null;
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
		$collocate  = get_post_meta( $contenuto->ID, SchedaContenuto::META_COLLOCAZIONE, false );
		$regola     = null !== $albero && is_array( $collocate ) && 1 === count( $collocate ) && is_string( $collocate[0] ) ? $albero->cessazione( $collocate[0] ) : null;
		$cessazioni = get_post_meta( $contenuto->ID, self::META_CESSAZIONE, false );
		$cessazioni = is_array( $cessazioni ) ? $cessazioni : array();
		$c_attuale  = 1 === count( $cessazioni ) && self::data_valida( $cessazioni[0] ) ? $cessazioni[0] : '';
		$c_partenza = 1 === count( $cessazioni ) && is_string( $cessazioni[0] ) ? $cessazioni[0] : '';

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

		$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_CESSAZIONE_PARTENZA ) . '" value="' . esc_attr( $c_partenza ) . '">';

		if ( null !== $regola || array() !== $cessazioni ) {
			$html .= '<p><label for="trasparenza-pa-cessazione">' . esc_html__( 'Data di cessazione del mandato o dell\'incarico', 'trasparenza-pa' ) . '</label></p>';
			$html .= '<input type="date" id="trasparenza-pa-cessazione" name="' . esc_attr( self::CAMPO_CESSAZIONE ) . '" value="' . esc_attr( $c_attuale ) . '" class="widefat">';

			if ( null === $regola ) {
				$html .= '<p>' . esc_html__( 'La voce del contenuto non prevede la data di cessazione: toglierla e salvare, e la fine torna quella della regola generale.', 'trasparenza-pa' ) . '</p>';
			} else {
				/* translators: 1: norma che fissa il termine; 2: anni dopo la cessazione. */
				$html .= '<p>' . esc_html( sprintf( __( 'Per i dati di un titolare di incarico o di un consulente vale l\'%1$s: la pubblicazione dura %2$d anni dopo la cessazione. Se l\'incarico è in corso, scrivere la fine prevista dall\'atto di nomina e aggiornarla se cambia. Senza data vale la regola generale, che per i dati di una persona non basta.', 'trasparenza-pa' ), $regola['nome'], $regola['anni'] ) ) . '</p>';

				if ( '' === $c_attuale ) {
					$html .= '<p>' . esc_html__( 'Per i dati di una persona serve la data di cessazione.', 'trasparenza-pa' ) . '</p>';
				}
			}

			if ( count( $cessazioni ) > 1 ) {
				$html .= '<p>' . esc_html__( 'Il contenuto ha più di una data di cessazione memorizzata. Salvando, resta solo quella scritta, o nessuna; il registro conserva quelle trovate.', 'trasparenza-pa' ) . '</p>';
			}
		} else {
			$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_CESSAZIONE ) . '" value="">';
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

		if ( '' !== $attuale && '' !== $c_attuale && null !== $regola ) {
			$attesa = self::fine_da_cessazione( $c_attuale, $regola['anni'] );
			$html  .= '<p>' . esc_html__( 'La fine viene dalla data di cessazione.', 'trasparenza-pa' ) . '</p>';
		} elseif ( null !== $legge && '' !== $e_attuale ) {
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
		$cessazione = $richiesta[ self::CAMPO_CESSAZIONE ] ?? null;
		$c_partenza = $richiesta[ self::CAMPO_CESSAZIONE_PARTENZA ] ?? null;

		if ( ! is_string( $scelta ) || ! is_string( $partenza ) || ! is_string( $effetti ) || ! is_string( $e_partenza ) || ! is_string( $cessazione ) || ! is_string( $c_partenza ) ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la richiesta non diceva quali date erano scritte e quali erano mostrate. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		if ( '' === $scelta ) {
			if ( '' !== $partenza ) {
				return self::esito( 'cancellata', __( 'Data della pubblicazione non cancellata: una volta scritta si può cambiare ma non togliere, e la fine della pubblicazione resta quella registrata.', 'trasparenza-pa' ) );
			}

			return '' === $effetti && '' === $cessazione
				? self::esito( 'invariato', '' )
				: self::esito( 'dati', __( 'Data della pubblicazione non salvata: per la fine degli effetti e per la cessazione serve la data da cui decorre l\'obbligo, senza la quale non c\'è una pubblicazione da far finire.', 'trasparenza-pa' ) );
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

		if ( '' !== $cessazione && ! self::data_valida( $cessazione ) ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la data di cessazione non esiste nel calendario o non è scritta per intero.', 'trasparenza-pa' ) );
		}

		if ( '' !== $cessazione && strcmp( $cessazione, $scelta ) < 0 ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la data di cessazione viene prima della data da cui decorre l\'obbligo.', 'trasparenza-pa' ) );
		}

		if ( '' !== $cessazione && '' !== $effetti ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: con la data di cessazione la fine viene dalla regola sui titolari di incarichi e sui consulenti, e la fine degli effetti non si usa. Lasciarne vuota una delle due.', 'trasparenza-pa' ) );
		}

		$albero = PaginaPubblica::albero();
		$anni   = null === $albero ? null : $albero->durata_anni();

		if ( null === $albero || null === $anni ) {
			return self::esito( 'albero', __( 'Data della pubblicazione non salvata: l\'albero delle voci in vigore non è disponibile o non dice la durata della pubblicazione, e senza non si può calcolare la fine.', 'trasparenza-pa' ) );
		}

		$fine = self::fine( $scelta, $effetti, $anni );

		if ( null === $fine && '' === $cessazione ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la fine della pubblicazione cadrebbe oltre il 9999.', 'trasparenza-pa' ) );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Data della pubblicazione non salvata: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			return self::scrivi(
				$id,
				$albero,
				array(
					'scelta'     => $scelta,
					'partenza'   => $partenza,
					'effetti'    => $effetti,
					'e_partenza' => $e_partenza,
					'cessazione' => $cessazione,
					'c_partenza' => $c_partenza,
				),
				$fine,
				$anni
			);
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
	 * La fine degli effetti e la cessazione seguono le stesse regole, con una
	 * differenza: si possono togliere, e con piu righe il riquadro mostrato con
	 * la partenza vuota le sostituisce con quella scritta, o le toglie tutte.
	 *
	 * La cessazione e ammessa solo se la voce del contenuto, letta qui sotto
	 * il blocco, e una di quelle che l'albero lega alla cessazione; allora la
	 * fine viene dalla cessazione e non dalla regola generale.
	 *
	 * @param int                   $id     Identificativo del contenuto.
	 * @param Alberatura            $albero Albero in vigore.
	 * @param array<string, string> $date   Date scritte e mostrate, gia controllate: scelta, partenza, effetti, e_partenza, cessazione, c_partenza.
	 * @param string|null           $fine   Fine per la regola generale, nullo se oltre il 9999.
	 * @param int                   $anni   Anni di pubblicazione per legge.
	 * @return array{codice: string, messaggio: string}
	 */
	private static function scrivi( int $id, Alberatura $albero, array $date, ?string $fine, int $anni ): array {
		$scelta     = $date['scelta'];
		$effetti    = $date['effetti'];
		$cessazione = $date['cessazione'];
		$righe      = Metadato::leggi( $id, self::META_DECORRENZA );
		$fini       = Metadato::leggi( $id, conformita_core_chiave_fine_pubblicazione() );
		$memoria_e  = Metadato::leggi( $id, self::META_EFFETTI );
		$memoria_c  = Metadato::leggi( $id, self::META_CESSAZIONE );
		$collocate  = '' === $cessazione ? array() : Metadato::leggi( $id, SchedaContenuto::META_COLLOCAZIONE );

		if ( is_wp_error( $righe ) || is_wp_error( $fini ) || is_wp_error( $memoria_e ) || is_wp_error( $memoria_c ) || is_wp_error( $collocate ) ) {
			return self::esito( 'lettura', __( 'Data della pubblicazione non salvata: le date attuali del contenuto non si sono potute leggere dalla banca dati, e senza conoscerle il registro direbbe cambiato quello che non lo è. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
		}

		$regola = null;

		if ( '' !== $cessazione ) {
			$regola = 1 === count( $collocate ) ? $albero->cessazione( (string) $collocate[0] ) : null;

			if ( null === $regola ) {
				return self::esito( 'voce', __( 'Data della pubblicazione non salvata: la voce del contenuto non prevede la data di cessazione, che vale solo nelle voci dei titolari di incarichi e dei consulenti. Toglierla, oppure scegliere prima la voce giusta e salvare.', 'trasparenza-pa' ) );
			}

			$fine = self::fine_da_cessazione( $cessazione, $regola['anni'] );
		}

		if ( null === $fine ) {
			return self::esito( 'dati', __( 'Data della pubblicazione non salvata: la fine della pubblicazione cadrebbe oltre il 9999.', 'trasparenza-pa' ) );
		}

		$piu       = count( $righe ) > 1;
		$attuale   = $piu ? '' : ( $righe[0] ?? '' );
		$piu_e     = count( $memoria_e ) > 1;
		$e_attuale = $piu_e ? '' : ( $memoria_e[0] ?? '' );
		$piu_c     = count( $memoria_c ) > 1;
		$c_attuale = $piu_c ? '' : ( $memoria_c[0] ?? '' );

		if ( ! $piu && ! $piu_e && ! $piu_c && $attuale === $scelta && $e_attuale === $effetti && $c_attuale === $cessazione && array( $fine ) === $fini ) {
			return self::esito( 'invariato', '' );
		}

		if ( $attuale !== $date['partenza'] || $e_attuale !== $date['e_partenza'] || $c_attuale !== $date['c_partenza'] ) {
			return self::esito( 'conflitto', __( 'Data della pubblicazione non salvata: dopo l\'apertura di questa schermata la data del contenuto è cambiata. Ricaricare la schermata, che mostra la data attuale, e riprovare.', 'trasparenza-pa' ) );
		}

		$dettagli = array(
			'prima'            => $attuale,
			'dopo'             => $scelta,
			'effetti_prima'    => $e_attuale,
			'effetti_dopo'     => $effetti,
			'cessazione_prima' => $c_attuale,
			'cessazione_dopo'  => $cessazione,
			'fine_prima'       => array_values( $fini ),
			'fine_dopo'        => $fine,
		);

		if ( $piu ) {
			$dettagli['trovate'] = array_values( $righe );
		}

		if ( $piu_e ) {
			$dettagli['effetti_trovati'] = array_values( $memoria_e );
		}

		if ( $piu_c ) {
			$dettagli['cessazione_trovate'] = array_values( $memoria_c );
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

		if ( $piu_c || ( '' === $cessazione && '' !== $c_attuale ) ) {
			delete_post_meta( $id, self::META_CESSAZIONE );
		}

		if ( '' !== $cessazione && ( $piu_c || '' === $c_attuale ) ) {
			add_post_meta( $id, self::META_CESSAZIONE, $cessazione, true );
		} elseif ( '' !== $cessazione && $c_attuale !== $cessazione ) {
			update_post_meta( $id, self::META_CESSAZIONE, $cessazione );
		}

		if ( Metadato::leggi( $id, self::META_CESSAZIONE ) !== ( '' === $cessazione ? array() : array( $cessazione ) ) ) {
			return self::esito( 'scrittura', __( 'La data della pubblicazione è salvata, ma la data di cessazione non si è potuta scrivere o togliere, e la fine della pubblicazione non è stata toccata. Il registro delle modifiche contiene un cambio che non è avvenuto del tutto.', 'trasparenza-pa' ) );
		}

		$scritta = conformita_core_imposta_fine_pubblicazione( $id, $fine );

		if ( is_wp_error( $scritta ) || Metadato::leggi( $id, conformita_core_chiave_fine_pubblicazione() ) !== array( $fine ) ) {
			return self::esito( 'fine', __( 'La data della pubblicazione è salvata, ma la fine della pubblicazione non si è potuta scrivere: il riquadro mostra la differenza, e salvando di nuovo si riallinea. Finché il contenuto non ha una fine valida, non compare al pubblico.', 'trasparenza-pa' ) );
		}

		if ( null !== $regola ) {
			/* translators: 1: data di fine della pubblicazione; 2: anni dopo la cessazione; 3: norma che fissa il termine. */
			return self::esito( 'salvato', sprintf( __( 'Data della pubblicazione salvata. Fine della pubblicazione: %1$s, %2$d anni dopo la cessazione secondo l\'%3$s.', 'trasparenza-pa' ), self::leggibile( $fine ), $regola['anni'], $regola['nome'] ) );
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
