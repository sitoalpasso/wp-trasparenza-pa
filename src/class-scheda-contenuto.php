<?php
/**
 * Il riquadro in cui si sceglie la voce di un contenuto.
 *
 * Riga di collaudo T-61.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Voce della sezione: scelta, salvataggio con traccia nel registro, esito.
 *
 * Le regole delle altre schermate della sezione: nonce legato al contenuto e
 * permesso di modificarlo; valore controllato contro l'albero; un salvataggio
 * alla volta; la voce attuale letta dalla banca dati distinguendo "assente" da
 * "lettura fallita"; la voce mostrata all'apertura del modulo portata nel
 * modulo, cosi un cambio fatto da un altro nel frattempo e un conflitto e non
 * si cancella; prima il registro, poi il dato, e il dato riletto.
 */
final class SchedaContenuto {

	/**
	 * Metadato che porta l'identificativo della voce.
	 */
	const META_COLLOCAZIONE = '_trasparenza_pa_collocazione';

	/**
	 * Campo del modulo con la voce scelta.
	 */
	const CAMPO = 'trasparenza_pa_collocazione';

	/**
	 * Campo nascosto con la voce mostrata quando il modulo e stato aperto.
	 */
	const CAMPO_PARTENZA = 'trasparenza_pa_collocazione_partenza';

	/**
	 * Campo del nonce.
	 */
	const CAMPO_NONCE = 'trasparenza_pa_nonce_collocazione';

	/**
	 * Nome dell'operazione nel registro delle modifiche.
	 */
	const AZIONE_REGISTRO = 'collocazione_contenuto';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 *
	 * Seguono l'utente e il contenuto: l'esito di un salvataggio si mostra solo
	 * a chi ha salvato e solo sulla schermata di quel contenuto.
	 */
	const ESITO = 'trasparenza_pa_esito_collocazione_';

	/**
	 * Identificativo del riquadro.
	 */
	const ID_RIQUADRO = 'trasparenza-pa-collocazione';

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
		return 'trasparenza_pa_collocazione_' . $contenuto;
	}

	/**
	 * Le voci in cui si puo collocare un contenuto: quelle senza sottovoci.
	 *
	 * La radice resta fuori anche in un albero di una sola voce.
	 *
	 * @param Alberatura $albero Albero.
	 * @return array<int, string>
	 */
	public static function voci_ammesse( Alberatura $albero ): array {
		$ammesse = array();

		foreach ( $albero->identificativi() as $id ) {
			if ( $id !== $albero->radice() && array() === $albero->figli( $id ) ) {
				$ammesse[] = $id;
			}
		}

		return $ammesse;
	}

	/**
	 * La voce valida di un contenuto, oppure nullo.
	 *
	 * Per mostrare, non per decidere una scrittura: legge con `get_post_meta()`.
	 * Una voce che l'albero in vigore non ha piu tra quelle ammesse vale come
	 * nessuna voce.
	 *
	 * @param int $contenuto Identificativo del contenuto.
	 * @return string|null
	 */
	public static function collocazione( int $contenuto ): ?string {
		$albero = PaginaPubblica::albero();
		$valori = get_post_meta( $contenuto, self::META_COLLOCAZIONE, false );

		if ( null === $albero || ! is_array( $valori ) || 1 !== count( $valori ) || ! is_string( $valori[0] ) ) {
			return null;
		}

		return in_array( $valori[0], self::voci_ammesse( $albero ), true ) ? $valori[0] : null;
	}

	/**
	 * Aggiunge il riquadro alla schermata del contenuto.
	 */
	public static function aggiungi_riquadro(): void {
		add_meta_box( self::ID_RIQUADRO, __( 'Voce della sezione', 'trasparenza-pa' ), array( self::class, 'disegna' ), TIPO_CONTENUTO, 'side', 'high' );
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
		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return '<p>' . esc_html__( 'L\'albero delle voci non è disponibile: la voce non si può scegliere, e il contenuto non compare nella sezione.', 'trasparenza-pa' ) . '</p>';
		}

		$valori   = get_post_meta( $contenuto->ID, self::META_COLLOCAZIONE, false );
		$partenza = is_array( $valori ) && 1 === count( $valori ) && is_string( $valori[0] ) ? $valori[0] : '';
		$attuale  = self::collocazione( $contenuto->ID );

		$html  = wp_nonce_field( self::azione_nonce( $contenuto->ID ), self::CAMPO_NONCE, false, false );
		$html .= '<input type="hidden" name="' . esc_attr( self::CAMPO_PARTENZA ) . '" value="' . esc_attr( $partenza ) . '">';
		$html .= '<p><label for="trasparenza-pa-collocazione-scelta">' . esc_html__( 'Voce in cui compare il contenuto', 'trasparenza-pa' ) . '</label></p>';
		$html .= '<select id="trasparenza-pa-collocazione-scelta" name="' . esc_attr( self::CAMPO ) . '" class="widefat">';

		if ( null === $attuale ) {
			$html .= '<option value="" selected>' . esc_html__( 'Da scegliere', 'trasparenza-pa' ) . '</option>';
		}

		foreach ( self::voci_ammesse( $albero ) as $id ) {
			$nomi = array();

			foreach ( $albero->percorso( $id ) as $passo ) {
				if ( $passo !== $albero->radice() ) {
					$nomi[] = (string) ( $albero->voce( $passo )['nome'] ?? $passo );
				}
			}

			$html .= '<option value="' . esc_attr( $id ) . '"' . selected( $attuale, $id, false ) . '>' . esc_html( implode( ' > ', $nomi ) ) . '</option>';
		}

		$html .= '</select>';

		if ( null === $attuale ) {
			$html .= '<p>' . esc_html__( 'Finché non ha una voce, il contenuto non compare nella sezione, anche se è pubblicato.', 'trasparenza-pa' ) . '</p>';
		}

		$modulo = null === $attuale ? null : $albero->modulo( $attuale );

		if ( null !== $modulo ) {
			$html .= self::avviso_modulo( $modulo );
		}

		if ( is_array( $valori ) && count( $valori ) > 1 ) {
			$html .= '<p>' . esc_html__( 'Il contenuto ha più di una voce memorizzata. Scegliendo una voce e salvando, resta solo quella; il registro conserva quelle trovate.', 'trasparenza-pa' ) . '</p>';
		}

		return $html;
	}

	/**
	 * L'avviso per una voce coperta da un modulo ANAC.
	 *
	 * Scelta del 28 ottobre 2026: il testo libero resta ammesso finché la
	 * compilazione sul modulo non è obbligatoria, e l'addetto sa che il modulo
	 * esiste. Oggi la pubblicazione dal modulo non è aperta, e l'avviso dice
	 * che al pubblico arriva il testo.
	 *
	 * @param array{obbligo: string, nome: string} $modulo Modulo che copre la voce.
	 * @return string
	 */
	public static function avviso_modulo( array $modulo ): string {
		$motore = SchedaVoce::motore();

		if ( is_wp_error( $motore ) ) {
			/* translators: %s: articolo di legge del modulo. */
			return '<div class="trasparenza-pa-modulo"><p>' . esc_html( sprintf( __( 'Per i dati dell\'%s questa voce ha un modulo ANAC, ma il catalogo dei moduli non si è potuto leggere.', 'trasparenza-pa' ), $modulo['nome'] ) ) . '</p></div>';
		}

		$versione = $motore->versione_in_vigore( $modulo['obbligo'] );

		if ( null === $versione ) {
			/* translators: %s: articolo di legge del modulo. */
			return '<div class="trasparenza-pa-modulo"><p>' . esc_html( sprintf( __( 'Per i dati dell\'%s ANAC prevede un modulo, ma nessuna sua versione è ancora in vigore: per ora si usa il testo di questo contenuto.', 'trasparenza-pa' ), $modulo['nome'] ) ) . '</p></div>';
		}

		/* translators: 1: articolo di legge del modulo, 2: versione e data di approvazione. */
		$html  = '<div class="trasparenza-pa-modulo"><p>' . esc_html( sprintf( __( 'Per i dati dell\'%1$s questa voce ha un modulo ANAC, %2$s.', 'trasparenza-pa' ), $modulo['nome'], (string) $motore->descrivi( $modulo['obbligo'], $versione ) ) ) . '</p>';
		$html .= '<p>' . esc_html__( 'Oggi i dati compilati sul modulo restano in bozza, perché la pubblicazione dal modulo non è ancora aperta: al pubblico arriva il testo di questo contenuto. Il testo resta ammesso finché la compilazione sul modulo non è obbligatoria; compilare anche il modulo aiuta ad abituarsi allo standard.', 'trasparenza-pa' ) . '</p>';

		$tipo = get_post_type_object( TIPO_VOCE );

		if ( null !== $tipo && current_user_can( $tipo->cap->create_posts ) ) {
			$html .= '<p><a href="' . esc_url( admin_url( 'post-new.php?post_type=' . TIPO_VOCE ) ) . '">' . esc_html__( 'Compila il modulo', 'trasparenza-pa' ) . '</a></p>';
		}

		return $html . '</div>';
	}

	/**
	 * Aggancio a `save_post`: applica la scelta e conserva l'esito per la schermata.
	 *
	 * Si fa qualcosa solo se la richiesta viene dal riquadro: le altre strade di
	 * salvataggio non portano la voce e non la toccano.
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
	 * Applica la voce scelta a un contenuto.
	 *
	 * @param int                  $id        Identificativo del contenuto.
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string}
	 */
	public static function applica( int $id, array $richiesta ): array {
		$nonce = $richiesta[ self::CAMPO_NONCE ] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::azione_nonce( $id ) ) ) {
			return self::esito( 'nonce', __( 'Voce della sezione non salvata: la richiesta è scaduta o non viene da questo contenuto. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$contenuto = get_post( $id );

		if ( ! $contenuto instanceof WP_Post || TIPO_CONTENUTO !== $contenuto->post_type || ! current_user_can( 'edit_post', $id ) ) {
			return self::esito( 'permesso', __( 'Voce della sezione non salvata: non avete il permesso di modificare questo contenuto.', 'trasparenza-pa' ) );
		}

		$scelta   = $richiesta[ self::CAMPO ] ?? null;
		$partenza = $richiesta[ self::CAMPO_PARTENZA ] ?? null;

		if ( ! is_string( $scelta ) || ! is_string( $partenza ) ) {
			return self::esito( 'dati', __( 'Voce della sezione non salvata: la richiesta non diceva quale voce era scelta e quale era mostrata. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		// Nessuna scelta, o la stessa voce che il modulo mostrava: chi ha salvato non ha cambiato la voce.
		if ( '' === $scelta || $scelta === $partenza ) {
			return self::esito( 'invariato', '' );
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return self::esito( 'albero', __( 'Voce della sezione non salvata: l\'albero delle voci non è disponibile, e senza non si può controllare la voce scelta.', 'trasparenza-pa' ) );
		}

		if ( ! in_array( $scelta, self::voci_ammesse( $albero ), true ) ) {
			return self::esito( 'dati', __( 'Voce della sezione non salvata: la voce scelta non è fra quelle in cui si può collocare un contenuto.', 'trasparenza-pa' ) );
		}

		if ( ! Blocco::prendi() ) {
			return self::esito( 'occupato', __( 'Voce della sezione non salvata: un altro salvataggio della sezione è in corso. Riprovare fra qualche secondo.', 'trasparenza-pa' ) );
		}

		try {
			return self::scrivi( $id, $scelta, $partenza );
		} finally {
			Blocco::rilascia();
		}
	}

	/**
	 * Registra e scrive la voce, con il blocco gia preso.
	 *
	 * @param int    $id       Identificativo del contenuto.
	 * @param string $scelta   Voce scelta, gia controllata.
	 * @param string $partenza Voce mostrata quando il modulo e stato aperto.
	 * @return array{codice: string, messaggio: string}
	 */
	private static function scrivi( int $id, string $scelta, string $partenza ): array {
		$valori = Metadato::leggi( $id, self::META_COLLOCAZIONE );

		if ( is_wp_error( $valori ) ) {
			return self::esito( 'lettura', __( 'Voce della sezione non salvata: la voce attuale del contenuto non si è potuta leggere dalla banca dati, e senza conoscerla il registro direbbe cambiato quello che non lo è. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
		}

		if ( count( $valori ) > 1 ) {
			return self::riordina( $id, $scelta, $partenza, $valori );
		}

		$attuale = $valori[0] ?? '';

		if ( $attuale === $scelta ) {
			return self::esito( 'invariato', '' );
		}

		if ( $attuale !== $partenza ) {
			return self::esito( 'conflitto', __( 'Voce della sezione non salvata: dopo l\'apertura di questa schermata qualcun altro ha cambiato la voce del contenuto. Ricaricare la schermata, che mostra la voce attuale, e riprovare.', 'trasparenza-pa' ) );
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $id,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => array(
					'prima' => $attuale,
					'dopo'  => $scelta,
				),
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return self::esito(
				'registro',
				/* translators: %s: motivo riportato dal registro. */
				sprintf( __( 'Voce della sezione non salvata: il registro delle modifiche non ha accettato il cambio, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() )
			);
		}

		update_post_meta( $id, self::META_COLLOCAZIONE, $scelta );

		if ( Metadato::leggi( $id, self::META_COLLOCAZIONE ) !== array( $scelta ) ) {
			return self::esito( 'scrittura', __( 'La voce della sezione non si è potuta scrivere: resta quella di prima. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ) );
		}

		return self::esito( 'salvato', __( 'Voce della sezione salvata.', 'trasparenza-pa' ) );
	}

	/**
	 * Rimette una sola voce su un contenuto che ne ha memorizzate piu di una.
	 *
	 * Piu righe con la stessa chiave non nascono dal riquadro: arrivano da una
	 * scrittura diretta o da un'importazione. Il contenuto non compare nella
	 * sezione finche non ha una voce sola, e il riquadro mostra "Da scegliere"
	 * con partenza vuota. Una scelta fatta da quel riquadro sostituisce tutte le
	 * righe con una, dopo aver scritto nel registro le voci trovate. Se il
	 * modulo mostrava una voce, e stato aperto prima che le righe diventassero
	 * piu di una: e un conflitto.
	 *
	 * @param int                $id       Identificativo del contenuto.
	 * @param string             $scelta   Voce scelta, gia controllata.
	 * @param string             $partenza Voce mostrata quando il modulo e stato aperto.
	 * @param array<int, string> $trovate  Voci memorizzate.
	 * @return array{codice: string, messaggio: string}
	 */
	private static function riordina( int $id, string $scelta, string $partenza, array $trovate ): array {
		if ( '' !== $partenza ) {
			return self::esito( 'conflitto', __( 'Voce della sezione non salvata: dopo l\'apertura di questa schermata la voce del contenuto è cambiata. Ricaricare la schermata, che mostra la voce attuale, e riprovare.', 'trasparenza-pa' ) );
		}

		$traccia = conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $id,
				'azione'    => self::AZIONE_REGISTRO,
				'dettagli'  => array(
					'prima'   => '',
					'dopo'    => $scelta,
					'trovate' => array_values( $trovate ),
				),
			)
		);

		if ( is_wp_error( $traccia ) ) {
			return self::esito(
				'registro',
				/* translators: %s: motivo riportato dal registro. */
				sprintf( __( 'Voce della sezione non salvata: il registro delle modifiche non ha accettato il cambio, e un cambio senza traccia nel registro non si fa. Motivo: %s', 'trasparenza-pa' ), $traccia->get_error_message() )
			);
		}

		delete_post_meta( $id, self::META_COLLOCAZIONE );
		add_post_meta( $id, self::META_COLLOCAZIONE, $scelta, true );

		if ( Metadato::leggi( $id, self::META_COLLOCAZIONE ) !== array( $scelta ) ) {
			return self::esito( 'scrittura', __( 'La voce della sezione non si è potuta scrivere: il contenuto non ha una voce valida e non compare nella sezione. Il registro delle modifiche contiene un cambio che non è avvenuto.', 'trasparenza-pa' ) );
		}

		return self::esito( 'salvato', __( 'Voce della sezione salvata. Il contenuto aveva più voci memorizzate: ora ha solo questa, e il registro conserva quelle trovate.', 'trasparenza-pa' ) );
	}

	/**
	 * Mostra l'esito della scelta della voce, una volta, a chi ha salvato, sulla schermata di quel contenuto.
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
