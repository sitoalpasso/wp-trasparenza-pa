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

		return $html;
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
			set_transient( self::ESITO . get_current_user_id(), $esito, MINUTE_IN_SECONDS );
		}
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

		if ( is_wp_error( $valori ) || count( $valori ) > 1 ) {
			return self::esito( 'lettura', __( 'Voce della sezione non salvata: la voce attuale del contenuto non si è potuta leggere dalla banca dati, e senza conoscerla il registro direbbe cambiato quello che non lo è. Riprovare fra qualche minuto.', 'trasparenza-pa' ) );
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
	 * Mostra l'esito della scelta della voce, una volta, a chi ha salvato.
	 */
	public static function mostra_esito(): void {
		$chiave = self::ESITO . get_current_user_id();
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
