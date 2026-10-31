<?php
/**
 * La schermata del rapporto di importazione dalle pagine del sito.
 *
 * Righe di collaudo T-121, T-124 e T-126.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Chiede la pagina d'ingresso della sezione vecchia e mostra il rapporto.
 *
 * Il rapporto non scrive niente, quindi il modulo va per GET; il nonce serve
 * comunque, perche il rapporto legge testi e file che chi apre un indirizzo
 * preparato da altri non ha chiesto di leggere.
 */
final class SchermataImportazione {

	/**
	 * Identificativo della schermata.
	 */
	const PAGINA = 'trasparenza-pa-importazione';

	/**
	 * Azione del nonce.
	 */
	const AZIONE = 'trasparenza_pa_importazione';

	/**
	 * Nome del campo con la pagina d'ingresso.
	 */
	const CAMPO_RADICE = 'radice';

	/**
	 * Azione del modulo che porta le pagine in bozza, e del suo nonce.
	 */
	const AZIONE_BOZZE = 'trasparenza_pa_importazione_bozze';

	/**
	 * Nome del campo con la voce scelta per ogni pagina.
	 */
	const CAMPO_VOCI = 'voce';

	/**
	 * Azione del modulo che copia i file nelle bozze, e del suo nonce.
	 */
	const AZIONE_FILE = 'trasparenza_pa_importazione_file';

	/**
	 * Nome del campo con i file scelti per ogni pagina.
	 */
	const CAMPO_COPIA = 'copia';

	/**
	 * Nome del campo con i file oltre il termine ordinario, o senza data, confermati.
	 */
	const CAMPO_VERIFICATA = 'copia_verificata';

	/**
	 * Prefisso del dato temporaneo che porta l'esito alla schermata.
	 */
	const ESITO = 'trasparenza_pa_esito_importazione_';

	/**
	 * Aggancia la schermata a WordPress.
	 */
	public static function aggancia(): void {
		// Dopo il registro degli accessi.
		add_action( 'admin_menu', array( self::class, 'da_admin_menu' ), 15 );
		add_action( 'admin_post_' . self::AZIONE_BOZZE, array( self::class, 'da_admin_post' ) );
		add_action( 'admin_post_' . self::AZIONE_FILE, array( self::class, 'da_admin_post' ) );
	}

	/**
	 * Chi puo portare le pagine in bozza: il permesso della sezione e quello di creare contenuti.
	 *
	 * @return bool
	 */
	public static function puo_portare(): bool {
		$tipo = get_post_type_object( TIPO_CONTENUTO );

		return current_user_can( Permessi::AMMINISTRAZIONE ) && null !== $tipo && current_user_can( $tipo->cap->create_posts );
	}

	/**
	 * Risposta al modulo che porta le pagine in bozza.
	 */
	public static function da_admin_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce lo verifica salva(), prima di leggere qualunque altro campo.
		$richiesta = wp_unslash( $_POST );
		$richiesta = is_array( $richiesta ) ? $richiesta : array();
		$esito     = self::AZIONE_FILE === ( $richiesta['action'] ?? '' ) ? self::salva_file( $richiesta ) : self::salva( $richiesta );

		if ( in_array( $esito['codice'], array( 'permesso', 'nonce' ), true ) ) {
			wp_die( esc_html( $esito['messaggio'] ), '', array( 'response' => 403 ) );
		}

		self::conserva_esito( $esito );

		$radice = $richiesta[ self::CAMPO_RADICE ] ?? '';
		$indice = admin_url( 'admin.php?page=' . self::PAGINA );

		if ( is_string( $radice ) && 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/', $radice ) ) {
			$indice = add_query_arg(
				array(
					self::CAMPO_RADICE => $radice,
					'_wpnonce'         => wp_create_nonce( self::AZIONE ),
				),
				$indice
			);
		}

		wp_safe_redirect( $indice );
		exit;
	}

	/**
	 * Porta in bozza le pagine scelte.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, righe: array<int, array<string, mixed>>}
	 */
	public static function salva( array $richiesta ): array {
		if ( ! self::puo_portare() ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di portare pagine nella sezione: servono il permesso della sezione e quello di creare contenuti.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE_BOZZE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: niente portato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$radice = $richiesta[ self::CAMPO_RADICE ] ?? null;

		if ( ! is_string( $radice ) || 1 !== preg_match( '/\A[1-9][0-9]{0,18}\z/', $radice ) ) {
			return self::esito( 'radice', __( 'Niente portato: la pagina d\'ingresso indicata non è una pagina pubblicata senza password.', 'trasparenza-pa' ) );
		}

		$scelte = $richiesta[ self::CAMPO_VOCI ] ?? null;

		if ( ! is_array( $scelte ) ) {
			return self::esito( 'dati', __( 'Niente portato: la richiesta non conteneva le voci scelte.', 'trasparenza-pa' ) );
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return self::esito( 'albero', __( 'Niente portato: l\'albero della sezione non è caricato.', 'trasparenza-pa' ) );
		}

		$righe = ImportazionePagine::porta( (int) $radice, $scelte, $albero, RegistroAccessi::oggi() );

		if ( is_wp_error( $righe ) ) {
			$codici = array(
				'trasparenza_importazione_radice'   => 'radice',
				'trasparenza_importazione_occupato' => 'occupato',
			);

			return self::esito( $codici[ $righe->get_error_code() ] ?? 'lettura', __( 'Niente portato.', 'trasparenza-pa' ) . ' ' . $righe->get_error_message() );
		}

		$portate = count( array_filter( $righe, static fn( $riga ) => 'portata' === $riga['esito'] ) );

		/* translators: %d: numero di pagine. */
		return self::esito( 'portato', sprintf( __( 'Pagine portate in bozza: %d.', 'trasparenza-pa' ), $portate ), $righe );
	}

	/**
	 * Copia nelle bozze i file scelti.
	 *
	 * @param array<string, mixed> $richiesta Campi del modulo, gia senza barre inverse.
	 * @return array{codice: string, messaggio: string, righe: array<int, array<string, mixed>>}
	 */
	public static function salva_file( array $richiesta ): array {
		if ( ! self::puo_portare() ) {
			return self::esito( 'permesso', __( 'Non avete il permesso di portare file nella sezione: servono il permesso della sezione e quello di creare contenuti.', 'trasparenza-pa' ) );
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE_FILE ) ) {
			return self::esito( 'nonce', __( 'La richiesta è scaduta o non viene da questa schermata: niente copiato. Ricaricare la schermata e riprovare.', 'trasparenza-pa' ) );
		}

		$radice = $richiesta[ self::CAMPO_RADICE ] ?? null;

		if ( ! is_string( $radice ) || 1 !== preg_match( '/\A[1-9][0-9]{0,18}\z/', $radice ) ) {
			return self::esito( 'radice', __( 'Niente copiato: la pagina d\'ingresso indicata non è una pagina pubblicata senza password.', 'trasparenza-pa' ) );
		}

		$normali    = $richiesta[ self::CAMPO_COPIA ] ?? array();
		$confermati = $richiesta[ self::CAMPO_VERIFICATA ] ?? array();

		if ( ! is_array( $normali ) || ! is_array( $confermati ) ) {
			return self::esito( 'dati', __( 'Niente copiato: la richiesta non conteneva i file scelti.', 'trasparenza-pa' ) );
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return self::esito( 'albero', __( 'Niente copiato: l\'albero della sezione non è caricato.', 'trasparenza-pa' ) );
		}

		$righe = ImportazionePagine::copia( (int) $radice, $normali, $confermati, $albero, RegistroAccessi::oggi() );

		if ( is_wp_error( $righe ) ) {
			$codici = array(
				'trasparenza_importazione_radice'   => 'radice',
				'trasparenza_importazione_occupato' => 'occupato',
			);

			return self::esito( $codici[ $righe->get_error_code() ] ?? 'lettura', __( 'Niente copiato.', 'trasparenza-pa' ) . ' ' . $righe->get_error_message() );
		}

		$copiati = count( array_filter( $righe, static fn( $riga ) => 'copiato' === $riga['esito'] ) );

		/* translators: %d: numero di file. */
		return self::esito( 'copiato', sprintf( __( 'File copiati: %d.', 'trasparenza-pa' ), $copiati ), $righe );
	}

	/**
	 * Un esito.
	 *
	 * @param string                           $codice    Codice.
	 * @param string                           $messaggio Messaggio.
	 * @param array<int, array<string, mixed>> $righe     Righe.
	 * @return array{codice: string, messaggio: string, righe: array<int, array<string, mixed>>}
	 */
	private static function esito( string $codice, string $messaggio, array $righe = array() ): array {
		return array(
			'codice'    => $codice,
			'messaggio' => $messaggio,
			'righe'     => $righe,
		);
	}

	/**
	 * Conserva l'esito per la schermata, un minuto, per chi ha mandato il modulo.
	 *
	 * @param array<string, mixed> $esito Esito.
	 */
	public static function conserva_esito( array $esito ): void {
		set_transient( self::ESITO . get_current_user_id(), $esito, MINUTE_IN_SECONDS );
	}

	/**
	 * L'esito conservato, una volta.
	 *
	 * @return string
	 */
	private static function esito_mostrato(): string {
		$chiave = self::ESITO . get_current_user_id();
		$esito  = get_transient( $chiave );

		if ( ! is_array( $esito ) || ! isset( $esito['codice'], $esito['messaggio'] ) ) {
			return '';
		}

		delete_transient( $chiave );

		$html = '<div class="notice ' . ( in_array( $esito['codice'], array( 'portato', 'copiato' ), true ) ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( (string) $esito['messaggio'] ) . '</p>';

		if ( isset( $esito['righe'] ) && is_array( $esito['righe'] ) && array() !== $esito['righe'] ) {
			$nomi  = array(
				'portata'     => __( 'portata in bozza', 'trasparenza-pa' ),
				'gia_portata' => __( 'già portata', 'trasparenza-pa' ),
				'scartata'    => __( 'scartata', 'trasparenza-pa' ),
				'errore'      => 'copiato' === $esito['codice'] ? __( 'non copiato', 'trasparenza-pa' ) : __( 'non portata', 'trasparenza-pa' ),
				'copiato'     => __( 'copiato', 'trasparenza-pa' ),
				'gia_copiato' => __( 'già copiato', 'trasparenza-pa' ),
				'scartato'    => __( 'scartato', 'trasparenza-pa' ),
			);
			$html .= '<ul>';

			foreach ( $esito['righe'] as $riga ) {
				$titolo = '' === (string) $riga['titolo'] ? sprintf( '#%d', (int) $riga['pagina'] ) : (string) $riga['titolo'];
				$titolo = isset( $riga['percorso'] ) ? $titolo . ', ' . (string) $riga['percorso'] : $titolo;
				$html  .= '<li>' . esc_html( $titolo . ': ' . ( $nomi[ $riga['esito'] ] ?? '' ) . ( '' === (string) $riga['motivo'] ? '' : '. ' . $riga['motivo'] ) ) . '</li>';
			}

			$html .= '</ul>';
		}

		return $html . '</div>';
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
			__( 'Importazione dalle pagine', 'trasparenza-pa' ),
			__( 'Importazione dalle pagine', 'trasparenza-pa' ),
			Permessi::AMMINISTRAZIONE,
			self::PAGINA,
			array( self::class, 'mostra' )
		);
	}

	/**
	 * Stampa la schermata.
	 */
	public static function mostra(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- il nonce lo verifica html(), prima di leggere qualunque pagina.
		$richiesta = wp_unslash( $_GET );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() fa escaping di ogni valore.
		echo self::html( is_array( $richiesta ) ? $richiesta : array() );
	}

	/**
	 * La schermata, per la richiesta data.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta, gia senza barre inverse.
	 * @return string
	 */
	public static function html( array $richiesta ): string {
		$html = '<div class="wrap"><h1>' . esc_html__( 'Importazione dalle pagine', 'trasparenza-pa' ) . '</h1>';

		if ( ! current_user_can( Permessi::AMMINISTRAZIONE ) ) {
			return $html . self::avviso( __( 'Non avete il permesso di amministrare la sezione.', 'trasparenza-pa' ) ) . '</div>';
		}

		$html .= self::esito_mostrato();

		if ( ! array_key_exists( self::CAMPO_RADICE, $richiesta ) ) {
			return $html . self::spiegazione() . self::modulo( 0 ) . '</div>';
		}

		$nonce = $richiesta['_wpnonce'] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, self::AZIONE ) ) {
			return $html . self::avviso( __( 'La richiesta è scaduta o non viene da questa schermata: nessuna pagina letta.', 'trasparenza-pa' ) )
				. '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGINA ) ) . '">' . esc_html__( 'Ricominciare', 'trasparenza-pa' ) . '</a></p></div>';
		}

		$valore = $richiesta[ self::CAMPO_RADICE ];

		if ( ! is_string( $valore ) || 1 !== preg_match( '/\A[1-9][0-9]{0,18}\z/', $valore ) ) {
			return $html . self::avviso( __( 'La pagina d\'ingresso indicata non è una pagina pubblicata.', 'trasparenza-pa' ) ) . self::modulo( 0 ) . '</div>';
		}

		$albero = PaginaPubblica::albero();

		if ( null === $albero ) {
			return $html . self::avviso( __( 'Il rapporto non si può fare: l\'albero della sezione non è caricato.', 'trasparenza-pa' ) ) . '</div>';
		}

		$radice   = (int) $valore;
		$oggi     = RegistroAccessi::oggi();
		$rapporto = ImportazionePagine::rapporto( $radice, $albero, $oggi );

		if ( is_wp_error( $rapporto ) ) {
			$modulo = 'trasparenza_importazione_radice' === $rapporto->get_error_code() ? self::modulo( 0 ) : '';

			return $html . self::avviso( $rapporto->get_error_message() ) . $modulo . '</div>';
		}

		$bozze = self::puo_portare() ? self::modulo_bozze( $rapporto, $albero ) . self::modulo_file( $rapporto ) : '';

		return $html . self::modulo( $radice ) . self::rapporto( $rapporto, $albero, $oggi ) . $bozze . '</div>';
	}

	/**
	 * Il modulo "pagina vecchia, voce nuova".
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 * @param Alberatura           $albero   Albero.
	 * @return string
	 */
	private static function modulo_bozze( array $rapporto, Alberatura $albero ): string {
		$voci = array();

		foreach ( SchedaContenuto::voci_ammesse( $albero ) as $voce ) {
			$voci[ $voce ] = implode( ' / ', self::percorso( $voce, $albero ) );
		}

		$html  = '<h2>' . esc_html__( 'Pagina vecchia, voce nuova', 'trasparenza-pa' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'Per ogni pagina scelta nasce un contenuto in bozza nella voce indicata, con il titolo della pagina e senza testo. Nessun contenuto viene pubblicato: la data da cui decorre l\'obbligo la scrive un addetto. Una pagina si porta una volta sola.', 'trasparenza-pa' ) . '</p>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE_BOZZE ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::CAMPO_RADICE ) . '" value="' . esc_attr( (string) $rapporto['radice'] ) . '">'
			. wp_nonce_field( self::AZIONE_BOZZE, '_wpnonce', false, false );
		$html .= '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Pagina', 'trasparenza-pa' ) . '</th><th scope="col">' . esc_html__( 'Voce nuova', 'trasparenza-pa' ) . '</th></tr></thead><tbody>';

		foreach ( $rapporto['pagine'] as $pagina ) {
			$html .= '<tr><td>' . self::rientro( (int) $pagina['profondita'] ) . esc_html( $pagina['titolo'] ) . '</td><td>';

			if ( null !== $pagina['portata'] ) {
				$collegamento = get_edit_post_link( (int) $pagina['portata'], 'raw' );
				$html        .= esc_html__( 'Già portata:', 'trasparenza-pa' ) . ' '
					. ( null === $collegamento ? esc_html( sprintf( '#%d', (int) $pagina['portata'] ) ) : '<a href="' . esc_url( $collegamento ) . '">' . esc_html( get_the_title( (int) $pagina['portata'] ) ) . '</a>' );
			} else {
				$scelta = 'proposta' === $pagina['esito_voce'] ? (string) $pagina['voce'] : '';
				$html  .= '<select name="' . esc_attr( self::CAMPO_VOCI . '[' . (int) $pagina['id'] . ']' ) . '" aria-label="' . esc_attr( $pagina['titolo'] ) . '">'
					. '<option value=""' . selected( $scelta, '', false ) . '>' . esc_html__( 'Non portare', 'trasparenza-pa' ) . '</option>';

				foreach ( $voci as $voce => $nome ) {
					$html .= '<option value="' . esc_attr( $voce ) . '"' . selected( $scelta, $voce, false ) . '>' . esc_html( $nome ) . '</option>';
				}

				$html .= '</select>';
			}

			$html .= '</td></tr>';
		}

		return $html . '</tbody></table><p><button type="submit" class="button button-primary">' . esc_html__( 'Porta in bozza le pagine scelte', 'trasparenza-pa' ) . '</button></p></form>';
	}

	/**
	 * Il modulo "file da copiare nelle bozze".
	 *
	 * Solo le pagine portate il cui contenuto e in bozza o in attesa. Casella
	 * spuntata per i file entro il termine ordinario; per gli altri presenti,
	 * casella non spuntata nel campo di conferma; per i rimanenti il motivo.
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 * @return string
	 */
	private static function modulo_file( array $rapporto ): string {
		$righe = '';

		foreach ( $rapporto['pagine'] as $pagina ) {
			if ( null === $pagina['portata'] || array() === $pagina['file'] || ! in_array( get_post_status( (int) $pagina['portata'] ), array( 'draft', 'pending' ), true ) ) {
				continue;
			}

			$impronte  = array();
			$documenti = SchedaDocumenti::documenti( (int) $pagina['portata'] );

			foreach ( is_array( $documenti ) ? $documenti : array() as $allegato ) {
				$impronta = conformita_core_impronta_allegato( $allegato );

				if ( is_array( $impronta ) ) {
					$impronte[] = (string) $impronta['valore'];
				}
			}

			$righe .= '<tr><td>' . self::rientro( (int) $pagina['profondita'] ) . esc_html( $pagina['titolo'] ) . '</td><td><ul>';

			foreach ( $pagina['file'] as $file ) {
				$righe .= '<li>' . self::casella( (int) $pagina['id'], $file, $impronte ) . '</li>';
			}

			$righe .= '</ul></td></tr>';
		}

		if ( '' === $righe ) {
			return '';
		}

		$html  = '<h2>' . esc_html__( 'File da copiare nelle bozze', 'trasparenza-pa' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'I file scelti si copiano fra i documenti del contenuto in bozza che ha portato la pagina, nella cartella protetta. Il file originale resta dov\'è. Un file oltre il termine ordinario di pubblicazione, o senza data, si copia solo spuntandolo dopo aver verificato che l\'atto produce ancora effetti, o che vale una durata degli artt. 14 e 15 del d.lgs. 33/2013.', 'trasparenza-pa' ) . '</p>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::AZIONE_FILE ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::CAMPO_RADICE ) . '" value="' . esc_attr( (string) $rapporto['radice'] ) . '">'
			. wp_nonce_field( self::AZIONE_FILE, '_wpnonce', false, false );
		$html .= '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Pagina', 'trasparenza-pa' ) . '</th><th scope="col">' . esc_html__( 'File', 'trasparenza-pa' ) . '</th></tr></thead><tbody>' . $righe . '</tbody></table>';

		return $html . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Copia i file scelti', 'trasparenza-pa' ) . '</button></p></form>';
	}

	/**
	 * La casella di un file nel modulo dei file, o il motivo per cui non c'e.
	 *
	 * @param int                  $pagina   Pagina.
	 * @param array<string, mixed> $file     File del rapporto.
	 * @param array<int, string>   $impronte Impronte dei documenti del contenuto.
	 * @return string
	 */
	private static function casella( int $pagina, array $file, array $impronte ): string {
		$nome = '<code>' . Testo::esatto( $file['percorso'] ) . '</code>';

		if ( 'presente' !== $file['stato'] ) {
			return $nome . ' ' . esc_html__( '(file mancante o fuori dalla cartella dei caricamenti: non si copia)', 'trasparenza-pa' );
		}

		if ( $file['protetto'] ) {
			return $nome . ' ' . esc_html__( '(già protetto dal componente comune per un altro contenuto: non si copia)', 'trasparenza-pa' );
		}

		if ( in_array( $file['impronta'], $impronte, true ) ) {
			return $nome . ' ' . esc_html__( '(già copiato)', 'trasparenza-pa' );
		}

		if ( false === $file['finita'] ) {
			return '<label><input type="checkbox" name="' . esc_attr( self::CAMPO_COPIA . '[' . $pagina . '][]' ) . '" value="' . Testo::esatto( $file['percorso'] ) . '" checked> ' . $nome . '</label>';
		}

		return '<label><input type="checkbox" name="' . esc_attr( self::CAMPO_VERIFICATA . '[' . $pagina . '][]' ) . '" value="' . Testo::esatto( $file['percorso'] ) . '"> ' . $nome . ' '
			. esc_html( true === $file['finita'] ? self::etichetta_oltre() : __( 'data non nota: da verificare come un file oltre il termine ordinario', 'trasparenza-pa' ) ) . '</label>';
	}

	/**
	 * Un avviso d'errore.
	 *
	 * @param string $testo Testo.
	 * @return string
	 */
	private static function avviso( string $testo ): string {
		return '<div class="notice notice-error"><p>' . esc_html( $testo ) . '</p></div>';
	}

	/**
	 * Cosa fa la schermata.
	 *
	 * @return string
	 */
	private static function spiegazione(): string {
		return '<p>' . esc_html__( 'Per un sito che oggi tiene la sezione come pagine: scegliere la pagina che fa da ingresso alla sezione. Il rapporto dice, per la pagina scelta e per ogni pagina pubblicata che sta sotto, a quale voce corrisponde, quanto testo ha, quali file collega e quali collegamenti portano fuori dal sito. Non scrive e non cambia niente.', 'trasparenza-pa' ) . '</p>';
	}

	/**
	 * Il modulo che chiede la pagina d'ingresso.
	 *
	 * @param int $scelta Pagina gia scelta.
	 * @return string
	 */
	private static function modulo( int $scelta ): string {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- con echo a zero restituisce l'elenco gia con escaping, non stampa.
		$elenco = wp_dropdown_pages(
			array(
				'echo'              => 0,
				'name'              => self::CAMPO_RADICE,
				'id'                => 'trasparenza-pa-radice',
				'selected'          => $scelta,
				'post_status'       => 'publish',
				'sort_column'       => 'menu_order,post_title',
				'show_option_none'  => __( 'Scegliere una pagina', 'trasparenza-pa' ),
				'option_none_value' => '',
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' === $elenco ) {
			return '<p>' . esc_html__( 'Il sito non ha pagine pubblicate.', 'trasparenza-pa' ) . '</p>';
		}

		return '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">'
			. '<input type="hidden" name="page" value="' . esc_attr( self::PAGINA ) . '">'
			. wp_nonce_field( self::AZIONE, '_wpnonce', false, false )
			. '<p><label for="trasparenza-pa-radice">' . esc_html__( 'Pagina d\'ingresso della sezione', 'trasparenza-pa' ) . '</label> '
			. $elenco
			. ' <button type="submit" class="button button-primary">' . esc_html__( 'Prepara il rapporto', 'trasparenza-pa' ) . '</button></p>'
			. '</form>';
	}

	/**
	 * Il rapporto.
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 * @param Alberatura           $albero   Albero.
	 * @param string               $oggi     Il giorno da cui si e contato, AAAA-MM-GG.
	 * @return string
	 */
	private static function rapporto( array $rapporto, Alberatura $albero, string $oggi ): string {
		$giorno = \DateTimeImmutable::createFromFormat( '!Y-m-d', $oggi, wp_timezone() );

		$conti = array(
			'presente' => 0,
			'mancante' => 0,
			'fuori'    => 0,
			'finita'   => 0,
			'vuote'    => 0,
		);

		foreach ( $rapporto['pagine'] as $pagina ) {
			$conti['vuote'] += $pagina['vuota'] ? 1 : 0;

			foreach ( $pagina['file'] as $file ) {
				++$conti[ $file['stato'] ];
				$conti['finita'] += true === $file['finita'] ? 1 : 0;
			}
		}

		$html  = '<div id="trasparenza-pa-rapporto">';
		$html .= '<h2>' . esc_html__( 'Rapporto', 'trasparenza-pa' ) . '</h2>';
		/* translators: %s: data di oggi. */
		$html .= '<p>' . esc_html( sprintf( __( 'Calcolato al %s. Non è stato scritto niente.', 'trasparenza-pa' ), false === $giorno ? $oggi : ContenutiPubblici::giorno( $giorno->getTimestamp() ) ) ) . '</p>';
		$html .= '<ul>';
		/* translators: %d: numero di pagine. */
		$html .= '<li>' . esc_html( sprintf( _n( '%d pagina', '%d pagine', count( $rapporto['pagine'] ), 'trasparenza-pa' ), count( $rapporto['pagine'] ) ) ) . '</li>';
		/* translators: 1: file presenti, 2: file mancanti, 3: file fuori dalla cartella. */
		$html .= '<li>' . esc_html( sprintf( __( 'File presenti: %1$d; mancanti: %2$d; fuori dalla cartella dei caricamenti, non aperti: %3$d', 'trasparenza-pa' ), $conti['presente'], $conti['mancante'], $conti['fuori'] ) ) . '</li>';
		/* translators: %d: numero di file. */
		$html .= '<li>' . esc_html( self::oltre( $rapporto['durata'], $conti['finita'] ) ) . '</li>';
		/* translators: %d: numero di pagine. */
		$html .= '<li>' . esc_html( sprintf( _n( '%d pagina non pubblicata o protetta da password, o sotto di loro, non letta', '%d pagine non pubblicate o protette da password, o sotto di loro, non lette', (int) $rapporto['non_lette'], 'trasparenza-pa' ), (int) $rapporto['non_lette'] ) ) . '</li>';
		/* translators: %d: numero di pagine. */
		$html .= '<li>' . esc_html( sprintf( __( 'Pagine vuote: %d', 'trasparenza-pa' ), $conti['vuote'] ) ) . '</li>';
		/* translators: %d: numero di voci. */
		$html .= '<li>' . esc_html( sprintf( __( 'Voci senza pagina: %d', 'trasparenza-pa' ), count( $rapporto['voci_senza_pagina'] ) ) ) . '</li>';
		$html .= '</ul>';

		if ( null === $rapporto['durata'] ) {
			$html .= '<p>' . esc_html__( 'L\'albero in vigore non dichiara la durata della pubblicazione: per nessun file si sa se la pubblicazione è finita.', 'trasparenza-pa' ) . '</p>';
		}

		$html .= '<table class="widefat striped"><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Pagina', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Voce', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Testo', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'File', 'trasparenza-pa' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Collegamenti esterni', 'trasparenza-pa' ) . '</th>'
			. '</tr></thead><tbody>';

		foreach ( $rapporto['pagine'] as $pagina ) {
			$html .= '<tr>'
				. '<td>' . self::rientro( (int) $pagina['profondita'] ) . '<a href="' . esc_url( $pagina['indirizzo'] ) . '">' . esc_html( $pagina['titolo'] ) . '</a>'
				. ( $pagina['vuota'] ? ' <strong>' . esc_html__( '(vuota)', 'trasparenza-pa' ) . '</strong>' : '' ) . '</td>'
				. '<td>' . esc_html( self::voce( $pagina, $albero ) ) . '</td>'
				/* translators: %d: numero di caratteri. */
				. '<td>' . esc_html( sprintf( _n( '%d carattere', '%d caratteri', (int) $pagina['caratteri'], 'trasparenza-pa' ), (int) $pagina['caratteri'] ) ) . '</td>'
				. '<td>' . self::file( $pagina['file'] ) . '</td>'
				. '<td>' . self::esterni( $pagina['esterni'] ) . '</td>'
				. '</tr>';
		}

		$html .= '</tbody></table>';

		$html .= '<h3>' . esc_html__( 'Voci senza pagina', 'trasparenza-pa' ) . '</h3>';

		if ( array() === $rapporto['voci_senza_pagina'] ) {
			$html .= '<p>' . esc_html__( 'Ogni voce ha una pagina proposta.', 'trasparenza-pa' ) . '</p>';
		} else {
			$html .= '<ul>';

			foreach ( $rapporto['voci_senza_pagina'] as $voce ) {
				$html .= '<li>' . esc_html( implode( ' / ', self::percorso( $voce, $albero ) ) ) . '</li>';
			}

			$html .= '</ul>';
		}

		return $html . '</div>';
	}

	/**
	 * La voce proposta, o perche non c'e.
	 *
	 * @param array<string, mixed> $pagina Pagina del rapporto.
	 * @param Alberatura           $albero Albero.
	 * @return string
	 */
	private static function voce( array $pagina, Alberatura $albero ): string {
		switch ( $pagina['esito_voce'] ) {
			case 'proposta':
				return implode( ' / ', self::percorso( (string) $pagina['voce'], $albero ) );
			case 'con_sottovoci':
				/* translators: %s: nome della voce. */
				return sprintf( __( '%s: ha sottovoci, sceglierne una', 'trasparenza-pa' ), implode( ' / ', self::percorso( (string) $pagina['voce'], $albero ) ) );
			case 'piu_voci':
				return __( 'Più voci hanno questo nome: sceglierne una', 'trasparenza-pa' );
			default:
				return __( 'Nessuna voce con questo nome', 'trasparenza-pa' );
		}
	}

	/**
	 * I nomi delle voci dalla prima sotto la radice fino a quella data.
	 *
	 * @param string     $voce   Voce.
	 * @param Alberatura $albero Albero.
	 * @return array<int, string>
	 */
	private static function percorso( string $voce, Alberatura $albero ): array {
		$nomi = array();

		foreach ( $albero->percorso( $voce ) as $id ) {
			$dati = $albero->voce( $id );

			if ( $id !== $albero->radice() && null !== $dati ) {
				$nomi[] = (string) $dati['nome'];
			}
		}

		return $nomi;
	}

	/**
	 * L'elenco dei file di una pagina.
	 *
	 * @param array<int, array<string, mixed>> $file File.
	 * @return string
	 */
	private static function file( array $file ): string {
		if ( array() === $file ) {
			return '';
		}

		$html = '<ul>';

		foreach ( $file as $uno ) {
			$html .= '<li><code>' . esc_html( $uno['percorso'] ) . '</code> ';

			if ( 'presente' === $uno['stato'] ) {
				/* translators: 1: dimensione in byte, 2: impronta SHA-256. */
				$html .= esc_html( sprintf( __( '%1$d byte, impronta %2$s', 'trasparenza-pa' ), $uno['byte'], $uno['impronta'] ) );
			} elseif ( 'mancante' === $uno['stato'] ) {
				$html .= '<strong>' . esc_html__( 'file mancante', 'trasparenza-pa' ) . '</strong>';
			} else {
				$html .= '<strong>' . esc_html__( 'fuori dalla cartella dei caricamenti: non aperto', 'trasparenza-pa' ) . '</strong>';
			}

			if ( true === $uno['finita'] ) {
				$html .= '; ' . esc_html( self::etichetta_oltre() );
			} elseif ( null === $uno['finita'] ) {
				$html .= '; ' . esc_html__( 'non si sa se la pubblicazione è finita', 'trasparenza-pa' );
			}

			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	/**
	 * L'etichetta di un file oltre il termine ordinario.
	 *
	 * Non dice "finita": l'art. 8, c. 3, tiene pubblicati gli atti finche
	 * producono effetti e fa salvi i termini degli artt. 14 e 15.
	 *
	 * @return string
	 */
	private static function etichetta_oltre(): string {
		return __( 'oltre il termine ordinario di pubblicazione (da verificare: atti che producono ancora effetti, durate degli artt. 14 e 15)', 'trasparenza-pa' );
	}


	/**
	 * La riga del riepilogo sui file oltre il termine ordinario.
	 *
	 * @param int|null $durata Durata dall'albero.
	 * @param int      $quanti File oltre il termine.
	 * @return string
	 */
	private static function oltre( ?int $durata, int $quanti ): string {
		if ( null === $durata ) {
			return __( 'File oltre il termine ordinario: non si sa, l\'albero non dichiara la durata.', 'trasparenza-pa' );
		}

		return sprintf(
			/* translators: 1: anni del termine ordinario, 2: numero di file. */
			__( 'File oltre il termine ordinario di %1$d anni: %2$d. Da verificare uno per uno prima di decidere: gli atti che producono ancora effetti restano pubblicati, e per titolari di incarichi e consulenti valgono le durate degli artt. 14 e 15 del d.lgs. 33/2013.', 'trasparenza-pa' ),
			$durata,
			$quanti
		);
	}

	/**
	 * L'elenco dei collegamenti esterni di una pagina.
	 *
	 * @param array<int, string> $esterni Collegamenti.
	 * @return string
	 */
	private static function esterni( array $esterni ): string {
		if ( array() === $esterni ) {
			return '';
		}

		$html = '<ul>';

		foreach ( $esterni as $indirizzo ) {
			$html .= '<li><code>' . esc_html( $indirizzo ) . '</code></li>';
		}

		return $html . '</ul>';
	}

	/**
	 * Rientro di una pagina figlia: spazi non separabili e un trattino corto.
	 *
	 * @param int $profondita Profondita sotto la pagina d'ingresso.
	 * @return string HTML.
	 */
	private static function rientro( int $profondita ): string {
		return $profondita > 0 ? str_repeat( '&nbsp;&nbsp;', $profondita ) . '- ' : '';
	}
}
