<?php
/**
 * Dallo schema ai campi del modulo, e dai valori inviati al dato.
 *
 * Righe di collaudo T-41..T-43.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

defined( 'ABSPATH' ) || exit;

/**
 * Un modulo uguale per tutti gli schemi, costruito leggendo lo schema.
 *
 * Questa classe conosce le parole di JSON Schema (struttura con campi, elenco, elenco
 * chiuso di valori, rimando) e nessun campo di nessuno schema: i nomi dei
 * campi arrivano dal file, al momento. Le regole sono poche apposta: una
 * struttura con campi diventa un gruppo, un elenco una serie di gruppi, un elenco chiuso
 * una scelta, un numero un campo numerico, il resto un campo di testo. Le
 * varianti alternative di una struttura con campi si mostrano unite: si compila quella che
 * serve, e lo schema controlla che sia una sola.
 */
final class ModuloSchema {

	/**
	 * Proprieta che indica lo schema del dato: si riempie da sola.
	 */
	const PROPRIETA_SCHEMA = '$schema';

	/**
	 * Profondita massima dei rimandi seguiti, contro gli schemi che rimandano a se stessi.
	 */
	const PROFONDITA_MASSIMA = 32;

	/**
	 * File della versione, per identificativo.
	 *
	 * @var array<string, object>
	 */
	private $contenuti;

	/**
	 * Identificativo dello schema della versione.
	 *
	 * @var string
	 */
	private $radice;

	/**
	 * Messaggi dei difetti ancora da mostrare, per percorso.
	 *
	 * @var array<string, array<int, string>>
	 */
	private $da_mostrare = array();

	/**
	 * Modulo sui file di una versione.
	 *
	 * @param array<string, object> $contenuti File della versione, per identificativo.
	 * @param string                $radice    Identificativo dello schema della versione.
	 */
	public function __construct( array $contenuti, string $radice ) {
		$this->contenuti = $contenuti;
		$this->radice    = $radice;
	}

	/**
	 * Il titolo dello schema, o il suo identificativo se non ne ha.
	 *
	 * @return string
	 */
	public function titolo(): string {
		$schema = $this->contenuti[ $this->radice ] ?? null;

		return is_object( $schema ) && is_string( $schema->title ?? null ) ? $schema->title : $this->radice;
	}

	/**
	 * I campi del modulo, in HTML.
	 *
	 * @param string                                                                 $nome    Nome di base dei campi inviati.
	 * @param mixed                                                                  $valore  Dato gia compilato, decodificato come oggetti.
	 * @param array<int, array{percorso: string, regola: string, messaggio: string}> $difetti Difetti dell'ultimo controllo.
	 * @return string
	 */
	public function campi( string $nome, $valore, array $difetti = array() ): string {
		$this->da_mostrare = array();

		foreach ( $difetti as $difetto ) {
			$this->da_mostrare[ $difetto['percorso'] ][] = self::messaggio( $difetto );
		}

		$schema = $this->contenuti[ $this->radice ] ?? null;

		if ( ! is_object( $schema ) ) {
			return '';
		}

		return $this->gruppo( $schema, $this->radice, $nome, '', $valore, '', 0 );
	}

	/**
	 * I messaggi dei difetti che l'ultimo modulo non ha potuto mettere accanto a un campo.
	 *
	 * Succede quando il percorso non ha un campo, per esempio una proprieta non
	 * prevista arrivata da un dato caricato in altro modo.
	 *
	 * @return array<string, array<int, string>> Messaggi per percorso.
	 */
	public function restanti(): array {
		return $this->da_mostrare;
	}

	/**
	 * Il dato costruito dai valori inviati.
	 *
	 * Un campo vuoto non entra, un gruppo tutto vuoto nemmeno, un elenco tiene
	 * solo gli elementi compilati. Un intero si converte solo se e scritto in
	 * cifre; altrimenti resta testo, e lo schema lo segnala.
	 *
	 * @param mixed $inviato Valori inviati dal modulo, gia senza barre aggiunte.
	 * @return object
	 */
	public function dato( $inviato ): object {
		$schema = $this->contenuti[ $this->radice ] ?? null;
		$dato   = is_object( $schema ) ? $this->leggi( $schema, $this->radice, $inviato, 0 ) : null;
		$dato   = is_object( $dato ) ? $dato : new \stdClass();

		if ( is_object( $schema ) && isset( $this->proprieta( $schema, $this->radice, 0 )[ self::PROPRIETA_SCHEMA ] ) ) {
			$dato = (object) array_merge( array( self::PROPRIETA_SCHEMA => $this->radice ), (array) $dato );
		}

		return $dato;
	}

	/**
	 * Messaggio in italiano per un difetto, con il dettaglio originale.
	 *
	 * @param array{percorso: string, regola: string, messaggio: string} $difetto Difetto.
	 * @return string
	 */
	public static function messaggio( array $difetto ): string {
		switch ( $difetto['regola'] ) {
			case 'required':
				$testo = __( 'Manca un campo obbligatorio.', 'trasparenza-pa' );
				break;
			case 'additionalProperties':
				$testo = __( 'Contiene un campo non previsto dallo schema.', 'trasparenza-pa' );
				break;
			case 'pattern':
				$testo = __( 'Il valore non è nella forma richiesta.', 'trasparenza-pa' );
				break;
			case 'format':
				$testo = __( 'Il valore non è nel formato richiesto.', 'trasparenza-pa' );
				break;
			case 'enum':
			case 'const':
				$testo = __( 'Il valore non è fra quelli ammessi.', 'trasparenza-pa' );
				break;
			case 'type':
				$testo = __( 'Il valore non è del tipo richiesto.', 'trasparenza-pa' );
				break;
			case 'minimum':
			case 'maximum':
			case 'exclusiveMinimum':
			case 'exclusiveMaximum':
				$testo = __( 'Il valore è fuori dall\'intervallo ammesso.', 'trasparenza-pa' );
				break;
			case 'minLength':
			case 'maxLength':
				$testo = __( 'La lunghezza del testo non è fra quelle ammesse.', 'trasparenza-pa' );
				break;
			case 'minItems':
			case 'maxItems':
				$testo = __( 'Il numero di elementi non è fra quelli ammessi.', 'trasparenza-pa' );
				break;
			case 'oneOf':
			case 'anyOf':
				$testo = __( 'Il dato non corrisponde a una sola delle varianti previste.', 'trasparenza-pa' );
				break;
			default:
				$testo = __( 'Il valore non rispetta lo schema.', 'trasparenza-pa' );
		}

		return '' === $difetto['messaggio'] ? $testo : $testo . ' (' . $difetto['messaggio'] . ')';
	}

	/**
	 * Un gruppo di campi per una struttura con campi.
	 *
	 * @param object $nodo      Schema della struttura, gia risolto.
	 * @param string $base      Identificativo del file a cui il nodo appartiene.
	 * @param string $nome      Nome dei campi inviati.
	 * @param string $percorso  Percorso del dato.
	 * @param mixed  $valore    Valore compilato.
	 * @param string $etichetta Etichetta del gruppo, vuota per la radice.
	 * @param int    $livello   Profondita.
	 * @return string
	 */
	private function gruppo( object $nodo, string $base, string $nome, string $percorso, $valore, string $etichetta, int $livello ): string {
		$html = '';

		foreach ( $this->proprieta( $nodo, $base, $livello ) as $chiave => $varianti ) {
			if ( self::PROPRIETA_SCHEMA === $chiave && '' === $percorso ) {
				continue;
			}

			$figlio = is_object( $valore ) && property_exists( $valore, $chiave ) ? $valore->{$chiave} : null;
			$html  .= $this->campo( $varianti, $nome . '[' . $chiave . ']', $percorso . '/' . $chiave, $figlio, $chiave, $livello + 1 );
		}

		$errori = $this->errori( '' === $percorso ? '/' : $percorso );

		if ( '' === $etichetta ) {
			return $errori . $html;
		}

		return sprintf(
			'<fieldset class="trasparenza-gruppo" data-percorso="%1$s"><legend>%2$s</legend>%3$s%4$s</fieldset>',
			esc_attr( $percorso ),
			esc_html( $etichetta ),
			$errori,
			$html
		);
	}

	/**
	 * Il campo di una proprieta, qualunque sia la sua forma.
	 *
	 * @param array<int, array{0: object, 1: string}> $varianti Schemi della proprieta, uno per variante, con il loro file.
	 * @param string                                  $nome     Nome del campo inviato.
	 * @param string                                  $percorso Percorso del dato.
	 * @param mixed                                   $valore   Valore compilato.
	 * @param string                                  $chiave   Nome della proprieta.
	 * @param int                                     $livello  Profondita.
	 * @return string
	 */
	private function campo( array $varianti, string $nome, string $percorso, $valore, string $chiave, int $livello ): string {
		list( $grezzo, $base_grezzo ) = $varianti[0];
		list( $nodo, $base )          = $this->risolvi( $grezzo, $base_grezzo, $livello );
		$etichetta                    = self::etichetta( $grezzo, $nodo, $chiave );
		$aiuto                        = is_string( $nodo->description ?? null ) && $nodo->description !== $etichetta ? $nodo->description : '';
		$scelte                       = $this->scelte( $varianti, $livello );

		if ( $livello > self::PROFONDITA_MASSIMA ) {
			return '';
		}

		if ( array() === $scelte && self::e_gruppo( $nodo ) ) {
			return $this->gruppo( $nodo, $base, $nome, $percorso, $valore, $etichetta, $livello );
		}

		if ( array() === $scelte && 'array' === ( $nodo->type ?? null ) ) {
			return $this->elenco( $nodo, $base, $nome, $percorso, $valore, $etichetta, $livello );
		}

		$id      = 'trasparenza-campo-' . md5( $nome );
		$testo   = is_scalar( $valore ) ? (string) $valore : '';
		$errori  = $this->errori( $percorso );
		$etichet = sprintf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $etichetta ) );

		if ( array() !== $scelte ) {
			$opzioni = '<option value=""></option>';

			foreach ( $scelte as $scelta ) {
				$opzioni .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $scelta ), selected( $testo, $scelta, false ), esc_html( $scelta ) );
			}

			$controllo = sprintf( '<select id="%1$s" name="%2$s">%3$s</select>', esc_attr( $id ), esc_attr( $nome ), $opzioni );
		} else {
			$tipo      = in_array( $nodo->type ?? null, array( 'integer', 'number' ), true ) ? 'number' : 'text';
			$controllo = sprintf( '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />', $tipo, esc_attr( $id ), esc_attr( $nome ), esc_attr( $testo ) );
		}

		return sprintf(
			'<p class="trasparenza-campo" data-percorso="%1$s">%2$s<br />%3$s%4$s%5$s</p>',
			esc_attr( $percorso ),
			$etichet,
			$controllo,
			'' === $aiuto ? '' : '<br /><span class="description">' . esc_html( $aiuto ) . '</span>',
			$errori
		);
	}

	/**
	 * Una serie di gruppi per un elenco: quelli compilati piu uno vuoto.
	 *
	 * @param object $nodo      Schema dell'elenco.
	 * @param string $base      File a cui il nodo appartiene.
	 * @param string $nome      Nome dei campi inviati.
	 * @param string $percorso  Percorso del dato.
	 * @param mixed  $valore    Valore compilato.
	 * @param string $etichetta Etichetta.
	 * @param int    $livello   Profondita.
	 * @return string
	 */
	private function elenco( object $nodo, string $base, string $nome, string $percorso, $valore, string $etichetta, int $livello ): string {
		$elementi = is_array( $valore ) ? array_values( $valore ) : array();
		$html     = '';

		foreach ( array_merge( $elementi, array( null ) ) as $indice => $elemento ) {
			$html .= $this->campo(
				array( array( self::schema_elemento( $nodo, $indice ), $base ) ),
				$nome . '[' . $indice . ']',
				$percorso . '/' . $indice,
				$elemento,
				/* translators: %d: numero dell'elemento, da 1. */
				sprintf( __( 'Elemento %d', 'trasparenza-pa' ), $indice + 1 ),
				$livello + 1
			);
		}

		return sprintf(
			'<fieldset class="trasparenza-elenco" data-percorso="%1$s"><legend>%2$s</legend>%3$s%4$s<p class="description">%5$s</p></fieldset>',
			esc_attr( $percorso ),
			esc_html( $etichetta ),
			$this->errori( $percorso ),
			$html,
			esc_html__( 'Per aggiungere un altro elemento, compilare l\'ultimo e salvare: ne compare uno nuovo vuoto.', 'trasparenza-pa' )
		);
	}

	/**
	 * Lo schema dell'elemento di un elenco in una posizione.
	 *
	 * Uno schema puo descrivere gli elementi uno per posizione, con un elenco di
	 * schemi: in quel caso le posizioni oltre l'ultima usano gli elementi
	 * aggiuntivi, se descritti, altrimenti l'ultimo schema dell'elenco, perche
	 * chi compila abbia comunque i campi. Il controllo resta quello dello schema.
	 *
	 * @param object $nodo   Schema dell'elenco.
	 * @param int    $indice Posizione, da 0.
	 * @return object
	 */
	private static function schema_elemento( object $nodo, int $indice ): object {
		$schemi = $nodo->items ?? null;

		if ( is_object( $schemi ) ) {
			return $schemi;
		}

		if ( ! is_array( $schemi ) || array() === $schemi ) {
			return new \stdClass();
		}

		if ( isset( $schemi[ $indice ] ) && is_object( $schemi[ $indice ] ) ) {
			return $schemi[ $indice ];
		}

		if ( is_object( $nodo->{'additionalItems'} ?? null ) ) {
			return $nodo->{'additionalItems'};
		}

		$ultimo = end( $schemi );

		return is_object( $ultimo ) ? $ultimo : new \stdClass();
	}

	/**
	 * Il valore di un nodo, letto dai valori inviati.
	 *
	 * @param object $grezzo  Schema del nodo, anche non risolto.
	 * @param string $base    File a cui il nodo appartiene.
	 * @param mixed  $inviato Valori inviati per il nodo.
	 * @param int    $livello Profondita.
	 * @param array  $altre   Altre varianti della stessa proprieta.
	 * @return mixed Nullo se il nodo e vuoto.
	 */
	private function leggi( object $grezzo, string $base, $inviato, int $livello, array $altre = array() ) {
		if ( $livello > self::PROFONDITA_MASSIMA ) {
			return null;
		}

		list( $nodo, $base_nodo ) = $this->risolvi( $grezzo, $base, $livello );
		$scelte                   = $this->scelte( array_merge( array( array( $grezzo, $base ) ), $altre ), $livello );

		if ( array() === $scelte && self::e_gruppo( $nodo ) ) {
			if ( ! is_array( $inviato ) ) {
				return null;
			}

			$dato = array();

			foreach ( $this->proprieta( $nodo, $base_nodo, $livello ) as $chiave => $varianti ) {
				$valore = $this->leggi( $varianti[0][0], $varianti[0][1], $inviato[ $chiave ] ?? null, $livello + 1, array_slice( $varianti, 1 ) );

				if ( null !== $valore ) {
					$dato[ $chiave ] = $valore;
				}
			}

			if ( array() === $dato ) {
				return null;
			}

			// Un elenco richiesto e lasciato vuoto resta un elenco vuoto: e un valore, non un'assenza.
			$tutte = $this->proprieta( $nodo, $base_nodo, $livello );

			foreach ( is_array( $nodo->required ?? null ) ? $nodo->required : array() as $richiesto ) {
				if ( ! is_string( $richiesto ) || isset( $dato[ $richiesto ] ) || ! isset( $tutte[ $richiesto ] ) ) {
					continue;
				}

				list( $figlio ) = $this->risolvi( $tutte[ $richiesto ][0][0], $tutte[ $richiesto ][0][1], $livello + 1 );

				if ( 'array' === ( $figlio->type ?? null ) ) {
					$dato[ $richiesto ] = array();
				}
			}

			return (object) $dato;
		}

		if ( array() === $scelte && 'array' === ( $nodo->type ?? null ) ) {
			if ( ! is_array( $inviato ) ) {
				return null;
			}

			$elementi = array();

			ksort( $inviato, SORT_NUMERIC );

			foreach ( $inviato as $elemento ) {
				$valore = $this->leggi( self::schema_elemento( $nodo, count( $elementi ) ), $base_nodo, $elemento, $livello + 1 );

				if ( null !== $valore ) {
					$elementi[] = $valore;
				}
			}

			return array() === $elementi ? null : $elementi;
		}

		if ( ! is_scalar( $inviato ) ) {
			return null;
		}

		$testo = self::pulisci( (string) $inviato );

		if ( '' === $testo ) {
			return null;
		}

		if ( 'integer' === ( $nodo->type ?? null ) && 1 === preg_match( '/^-?\d{1,18}$/', $testo ) ) {
			return (int) $testo;
		}

		if ( 'number' === ( $nodo->type ?? null ) && is_numeric( $testo ) ) {
			return (float) $testo;
		}

		return $testo;
	}

	/**
	 * Un valore inviato, ripulito.
	 *
	 * Toglie i marcatori HTML, i caratteri non validi e gli spazi in eccesso,
	 * ma lascia i caratteri codificati come %20: in un indirizzo fanno parte
	 * del valore, e toglierli lo cambierebbe senza che lo schema se ne accorga.
	 *
	 * @param string $valore Valore.
	 * @return string
	 */
	private static function pulisci( string $valore ): string {
		$testo = wp_strip_all_tags( wp_check_invalid_utf8( $valore ), true );

		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $testo ) );
	}

	/**
	 * Le proprieta di una struttura con campi, unite su tutte le sue varianti.
	 *
	 * @param object $nodo    Schema della struttura, gia risolto.
	 * @param string $base    File a cui il nodo appartiene.
	 * @param int    $livello Profondita.
	 * @return array<string, array<int, array{0: object, 1: string}>>
	 */
	private function proprieta( object $nodo, string $base, int $livello ): array {
		$unite = array();
		$fonti = array( array( $nodo, $base ) );

		foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $parola ) {
			foreach ( is_array( $nodo->{$parola} ?? null ) ? $nodo->{$parola} : array() as $variante ) {
				if ( is_object( $variante ) ) {
					$fonti[] = $this->risolvi( $variante, $base, $livello + 1 );
				}
			}
		}

		foreach ( $fonti as list( $fonte, $base_fonte ) ) {
			foreach ( is_object( $fonte->properties ?? null ) ? get_object_vars( $fonte->properties ) : array() as $chiave => $figlio ) {
				if ( is_object( $figlio ) ) {
					$unite[ (string) $chiave ][] = array( $figlio, $base_fonte );
				}
			}
		}

		return $unite;
	}

	/**
	 * I valori ammessi di una proprieta, uniti su tutte le sue varianti.
	 *
	 * @param array<int, array{0: object, 1: string}> $varianti Schemi della proprieta.
	 * @param int                                     $livello  Profondita.
	 * @return array<int, string>
	 */
	private function scelte( array $varianti, int $livello ): array {
		$scelte = array();

		foreach ( $varianti as list( $grezzo, $base ) ) {
			list( $nodo ) = $this->risolvi( $grezzo, $base, $livello );

			if ( property_exists( $nodo, 'const' ) && is_scalar( $nodo->const ) ) {
				$scelte[] = (string) $nodo->const;
			}

			foreach ( is_array( $nodo->enum ?? null ) ? $nodo->enum : array() as $valore ) {
				if ( is_scalar( $valore ) ) {
					$scelte[] = (string) $valore;
				}
			}
		}

		return array_values( array_unique( $scelte ) );
	}

	/**
	 * Segue i rimandi fino a uno schema vero, con il file a cui appartiene.
	 *
	 * @param object $nodo    Schema, anche un rimando.
	 * @param string $base    File a cui il nodo appartiene.
	 * @param int    $livello Profondita.
	 * @return array{0: object, 1: string}
	 */
	private function risolvi( object $nodo, string $base, int $livello ): array {
		$passi = 0;

		while ( is_string( $nodo->{'$ref'} ?? null ) && $passi < self::PROFONDITA_MASSIMA && $livello <= self::PROFONDITA_MASSIMA ) {
			$rimando     = $nodo->{'$ref'};
			$posizione   = strpos( $rimando, '#' );
			$file        = false === $posizione ? $rimando : substr( $rimando, 0, $posizione );
			$puntatore   = false === $posizione ? '' : substr( $rimando, $posizione + 1 );
			$base        = '' === $file ? $base : $file;
			$file_schema = $this->contenuti[ $base ] ?? null;
			$trovato     = is_object( $file_schema ) ? self::punta( $file_schema, $puntatore ) : null;
			$nodo        = is_object( $trovato ) ? $trovato : new \stdClass();
			++$passi;
		}

		return array( $nodo, $base );
	}

	/**
	 * Il nodo indicato da un puntatore JSON dentro un file di schema.
	 *
	 * @param object $file_schema File di schema.
	 * @param string $puntatore Puntatore, per esempio `/definitions/Nome`.
	 * @return mixed
	 */
	private static function punta( object $file_schema, string $puntatore ) {
		$nodo = $file_schema;

		foreach ( array_filter( explode( '/', $puntatore ), 'strlen' ) as $pezzo ) {
			$pezzo = str_replace( array( '~1', '~0' ), array( '/', '~' ), rawurldecode( $pezzo ) );

			if ( is_object( $nodo ) && property_exists( $nodo, $pezzo ) ) {
				$nodo = $nodo->{$pezzo};
			} elseif ( is_array( $nodo ) && isset( $nodo[ (int) $pezzo ] ) ) {
				$nodo = $nodo[ (int) $pezzo ];
			} else {
				return null;
			}
		}

		return $nodo;
	}

	/**
	 * Il nodo descrive una struttura con campi propri o nelle sue varianti.
	 *
	 * @param object $nodo Schema risolto.
	 * @return bool
	 */
	private static function e_gruppo( object $nodo ): bool {
		return 'object' === ( $nodo->type ?? null ) || is_object( $nodo->properties ?? null );
	}

	/**
	 * L'etichetta: il commento della proprieta, la sua descrizione, quella della definizione, il nome.
	 *
	 * @param object $grezzo Schema della proprieta come scritto.
	 * @param object $nodo   Schema risolto.
	 * @param string $chiave Nome della proprieta.
	 * @return string
	 */
	private static function etichetta( object $grezzo, object $nodo, string $chiave ): string {
		foreach ( array( $grezzo->{'$comment'} ?? null, $grezzo->description ?? null, $nodo->description ?? null, $nodo->title ?? null ) as $testo ) {
			if ( is_string( $testo ) && '' !== trim( $testo ) ) {
				return $testo;
			}
		}

		return $chiave;
	}

	/**
	 * I messaggi dei difetti di un percorso, una volta sola.
	 *
	 * @param string $percorso Percorso.
	 * @return string
	 */
	private function errori( string $percorso ): string {
		if ( ! isset( $this->da_mostrare[ $percorso ] ) ) {
			return '';
		}

		$html = '';

		foreach ( $this->da_mostrare[ $percorso ] as $messaggio ) {
			$html .= '<span class="trasparenza-difetto" role="alert">' . esc_html( $messaggio ) . '</span>';
		}

		unset( $this->da_mostrare[ $percorso ] );

		return '<span class="trasparenza-difetti">' . $html . '</span>';
	}
}
