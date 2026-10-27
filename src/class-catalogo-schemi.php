<?php
/**
 * Il catalogo degli schemi: quali versioni esistono, da quando valgono, da dove vengono.
 *
 * Righe di collaudo T-27 e T-28.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Un catalogo controllato per intero, oppure nessun catalogo.
 *
 * Il catalogo elenca i file degli schemi e i file di definizioni comuni a cui
 * gli schemi rimandano. Questa classe conosce la forma del catalogo e la forma
 * minima di un file di schema (un JSON con il proprio identificativo), e non
 * il contenuto di nessuno schema: i campi stanno solo nei file.
 *
 * Come per l'albero, il catalogo si accetta tutto o niente. Un rimando che non
 * trova il suo file si scopre qui, al caricamento, e non al primo dato.
 *
 * Una versione si riconosce dal numero e dalla data di approvazione, il suo
 * codice: l'Autorita ha gia ripubblicato file diversi con lo stesso numero.
 * Ogni versione ha i suoi file, cioe il suo schema e i file comuni che usa, e
 * l'identificativo di ogni file deve essere unico solo fra quelli: due versioni
 * possono quindi avere file diversi con lo stesso identificativo.
 */
final class CatalogoSchemi {

	/**
	 * Versione del formato del catalogo che questa classe sa leggere.
	 */
	const FORMATO = 1;

	/**
	 * Versioni per obbligo, in ordine di entrata in vigore.
	 *
	 * @var array<string, array<int, array{obbligo: string, versione: string, approvato_il: string, in_vigore_dal: string, codice: string, file: string, comuni: array<int, string>, fonte: array<string, string>, id: string}>>
	 */
	private $versioni;

	/**
	 * Contenuto decodificato dei file di ogni versione, per identificativo.
	 *
	 * @var array<string, array<string, array<string, object>>>
	 */
	private $contenuti;

	/**
	 * Costruito solo da `carica()`, a controlli superati.
	 *
	 * @param array<string, array<int, array<string, mixed>>>     $versioni  Versioni per obbligo.
	 * @param array<string, array<string, array<string, object>>> $contenuti Contenuti per obbligo, codice e identificativo.
	 */
	private function __construct( array $versioni, array $contenuti ) {
		$this->versioni  = $versioni;
		$this->contenuti = $contenuti;
	}

	/**
	 * Legge e controlla un catalogo e tutti i file che elenca.
	 *
	 * @param string $file Percorso del catalogo.
	 * @return CatalogoSchemi|WP_Error
	 */
	public static function carica( string $file ) {
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'trasparenza_catalogo_assente', __( 'Il catalogo degli schemi non esiste o non è leggibile.', 'trasparenza-pa' ) );
		}

		$dati = self::decodifica( $file );

		if ( ! is_array( $dati ) ) {
			return new WP_Error( 'trasparenza_catalogo_illeggibile', __( 'Il catalogo degli schemi non è un JSON valido.', 'trasparenza-pa' ) );
		}

		if ( ( $dati['formato'] ?? null ) !== self::FORMATO ) {
			return new WP_Error( 'trasparenza_catalogo_formato', __( 'Il catalogo degli schemi usa un formato che questa versione del componente non sa leggere.', 'trasparenza-pa' ) );
		}

		$comuni = $dati['comuni'] ?? null;
		$schemi = $dati['schemi'] ?? null;

		if ( ! is_array( $comuni ) || ! is_array( $schemi ) || array() === $schemi ) {
			return new WP_Error( 'trasparenza_catalogo_incompleto', __( 'Il catalogo degli schemi non elenca le definizioni comuni e almeno uno schema.', 'trasparenza-pa' ) );
		}

		$cartella = dirname( $file );
		$letti    = array();
		$versioni = array();
		$insiemi  = array();

		foreach ( $comuni as $voce ) {
			$esito = self::leggi_voce( $voce, $cartella, false, $letti );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}
		}

		$nomi_comuni = array_keys( $letti );

		foreach ( $schemi as $voce ) {
			$esito = self::leggi_voce( $voce, $cartella, true, $letti );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}

			foreach ( $versioni[ $esito['obbligo'] ] ?? array() as $altra ) {
				if ( $altra['codice'] === $esito['codice'] ) {
					return new WP_Error(
						'trasparenza_catalogo_versione_doppia',
						/* translators: 1: versione, 2: data di approvazione, 3: obbligo. */
						sprintf( __( 'La versione %1$s approvata il %2$s dell\'obbligo %3$s compare due volte nel catalogo.', 'trasparenza-pa' ), $esito['versione'], $esito['approvato_il'], $esito['obbligo'] )
					);
				}
			}

			$esito['comuni'] = $esito['comuni'] ?? $nomi_comuni;

			foreach ( $esito['comuni'] as $nome ) {
				if ( ! in_array( $nome, $nomi_comuni, true ) ) {
					return new WP_Error(
						'trasparenza_catalogo_comune_sconosciuto',
						/* translators: 1: file comune, 2: file dello schema. */
						sprintf( __( 'Il file comune %1$s, indicato per %2$s, non è fra i file comuni del catalogo.', 'trasparenza-pa' ), $nome, $esito['file'] )
					);
				}
			}

			$insieme = self::insieme( array_merge( array( $esito['file'] ), $esito['comuni'] ), $letti );

			if ( is_wp_error( $insieme ) ) {
				return $insieme;
			}

			$versioni[ $esito['obbligo'] ][]                  = $esito;
			$insiemi[ $esito['obbligo'] ][ $esito['codice'] ] = $insieme;
		}

		foreach ( $versioni as $obbligo => $elenco ) {
			usort(
				$elenco,
				static function ( array $a, array $b ): int {
					return strcmp( $a['in_vigore_dal'], $b['in_vigore_dal'] );
				}
			);

			$versioni[ $obbligo ] = $elenco;
		}

		return new self( $versioni, $insiemi );
	}

	/**
	 * Obblighi presenti nel catalogo.
	 *
	 * @return array<int, string>
	 */
	public function obblighi(): array {
		return array_keys( $this->versioni );
	}

	/**
	 * Versioni di un obbligo, in ordine di entrata in vigore.
	 *
	 * @param string $obbligo Identificativo dell'obbligo.
	 * @return array<int, array<string, mixed>>
	 */
	public function versioni( string $obbligo ): array {
		return $this->versioni[ $obbligo ] ?? array();
	}

	/**
	 * Una versione di un obbligo, o nullo se non esiste.
	 *
	 * @param string $obbligo Identificativo dell'obbligo.
	 * @param string $codice  Codice della versione, numero e data di approvazione.
	 * @return array<string, mixed>|null
	 */
	public function versione( string $obbligo, string $codice ): ?array {
		foreach ( $this->versioni( $obbligo ) as $voce ) {
			if ( $voce['codice'] === $codice ) {
				return $voce;
			}
		}

		return null;
	}

	/**
	 * Contenuto decodificato dei file di una versione, per identificativo.
	 *
	 * @param string $obbligo Identificativo dell'obbligo.
	 * @param string $codice  Codice della versione.
	 * @return array<string, object>
	 */
	public function contenuti( string $obbligo, string $codice ): array {
		return $this->contenuti[ $obbligo ][ $codice ] ?? array();
	}

	/**
	 * I file di una versione, con identificativi unici e rimandi tutti risolti.
	 *
	 * @param array<int, string>                                  $nomi  File della versione.
	 * @param array<string, array{id: string, contenuto: object}> $letti File letti, per nome.
	 * @return array<string, object>|WP_Error
	 */
	private static function insieme( array $nomi, array $letti ) {
		$insieme = array();

		foreach ( $nomi as $nome ) {
			$id = $letti[ $nome ]['id'];

			if ( isset( $insieme[ $id ] ) ) {
				/* translators: %s: identificativo dello schema. */
				return new WP_Error( 'trasparenza_catalogo_id_doppio', sprintf( __( 'Due file della stessa versione dichiarano lo stesso identificativo %s.', 'trasparenza-pa' ), $id ) );
			}

			$insieme[ $id ] = $letti[ $nome ]['contenuto'];
		}

		foreach ( $insieme as $id => $contenuto ) {
			foreach ( self::rimandi( $contenuto ) as $rimando ) {
				$file_rimando = self::senza_frammento( $rimando );

				if ( '' !== $file_rimando && ! isset( $insieme[ $file_rimando ] ) ) {
					return new WP_Error(
						'trasparenza_catalogo_rimando',
						/* translators: 1: identificativo dello schema, 2: rimando. */
						sprintf( __( 'Lo schema %1$s rimanda a %2$s, che non è fra i file della sua versione.', 'trasparenza-pa' ), $id, $rimando )
					);
				}

				// Il frammento, se e un puntatore, deve indicare qualcosa che nel file esiste.
				$posizione = strpos( $rimando, '#' );
				$frammento = false === $posizione ? '' : rawurldecode( substr( $rimando, $posizione + 1 ) );

				if ( str_starts_with( $frammento, '/' ) && ! self::punta( $insieme[ '' === $file_rimando ? $id : $file_rimando ], $frammento ) ) {
					return new WP_Error(
						'trasparenza_catalogo_frammento',
						/* translators: 1: identificativo dello schema, 2: rimando. */
						sprintf( __( 'Lo schema %1$s rimanda a %2$s, che nel file indicato non esiste.', 'trasparenza-pa' ), $id, $rimando )
					);
				}
			}
		}

		return $insieme;
	}

	/**
	 * Il puntatore JSON indica un nodo che esiste.
	 *
	 * @param mixed  $nodo      File o nodo da cui partire.
	 * @param string $puntatore Puntatore, per esempio `/definitions/Nome`.
	 * @return bool
	 */
	private static function punta( $nodo, string $puntatore ): bool {
		foreach ( array_slice( explode( '/', $puntatore ), 1 ) as $pezzo ) {
			$pezzo = str_replace( array( '~1', '~0' ), array( '/', '~' ), $pezzo );

			if ( is_object( $nodo ) && property_exists( $nodo, $pezzo ) ) {
				$nodo = $nodo->{$pezzo};
			} elseif ( is_array( $nodo ) && ctype_digit( $pezzo ) && array_key_exists( (int) $pezzo, $nodo ) ) {
				$nodo = $nodo[ (int) $pezzo ];
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * Controlla una voce del catalogo e legge il suo file.
	 *
	 * @param mixed                $voce      Voce del catalogo.
	 * @param string               $cartella  Cartella del catalogo.
	 * @param bool                 $schema    La voce e una versione di un obbligo, non un file comune.
	 * @param array<string, array> $letti     File gia letti, per nome, aggiornati qui.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function leggi_voce( $voce, string $cartella, bool $schema, array &$letti ) {
		if ( ! is_array( $voce ) || ! is_string( $voce['file'] ?? null ) || '' === $voce['file'] ) {
			return new WP_Error( 'trasparenza_catalogo_incompleto', __( 'Una voce del catalogo non indica il suo file.', 'trasparenza-pa' ) );
		}

		$nome = $voce['file'];

		if ( $schema ) {
			$obbligo  = $voce['obbligo'] ?? null;
			$versione = $voce['versione'] ?? null;

			if ( ! is_string( $obbligo ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,80}$/', $obbligo ) || ! is_string( $versione ) || '' === $versione ) {
				/* translators: %s: nome del file. */
				return new WP_Error( 'trasparenza_catalogo_incompleto', sprintf( __( 'La voce del catalogo per %s non indica obbligo e versione validi.', 'trasparenza-pa' ), $nome ) );
			}

			foreach ( array( 'approvato_il', 'in_vigore_dal' ) as $chiave ) {
				if ( ! self::data_valida( $voce[ $chiave ] ?? null ) ) {
					/* translators: 1: nome del campo, 2: nome del file. */
					return new WP_Error( 'trasparenza_catalogo_data', sprintf( __( 'La data %1$s della voce per %2$s non è nella forma AAAA-MM-GG.', 'trasparenza-pa' ), $chiave, $nome ) );
				}
			}

			$elenco = $voce['comuni'] ?? null;

			if ( null !== $elenco && ( ! is_array( $elenco ) || array_filter( $elenco, 'is_string' ) !== $elenco ) ) {
				/* translators: %s: nome del file. */
				return new WP_Error( 'trasparenza_catalogo_incompleto', sprintf( __( 'La voce del catalogo per %s elenca i file comuni in una forma non valida.', 'trasparenza-pa' ), $nome ) );
			}
		}

		$fonte = $voce['fonte'] ?? null;

		foreach ( array( 'titolo', 'indirizzo' ) as $chiave ) {
			if ( ! is_array( $fonte ) || ! is_string( $fonte[ $chiave ] ?? null ) || '' === trim( $fonte[ $chiave ] ) ) {
				/* translators: %s: nome del file. */
				return new WP_Error( 'trasparenza_catalogo_fonte', sprintf( __( 'La voce del catalogo per %s non dichiara la fonte con titolo e indirizzo.', 'trasparenza-pa' ), $nome ) );
			}
		}

		if ( ! self::data_valida( $fonte['consultato_il'] ?? null ) ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'trasparenza_catalogo_fonte', sprintf( __( 'La fonte della voce per %s non dice quando è stata consultata.', 'trasparenza-pa' ), $nome ) );
		}

		$percorso = $cartella . '/' . $nome;

		if ( str_contains( $nome, '..' ) || ! is_readable( $percorso ) ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'trasparenza_catalogo_file_assente', sprintf( __( 'Il file %s elencato nel catalogo non esiste o non è leggibile.', 'trasparenza-pa' ), $nome ) );
		}

		$contenuto = self::decodifica( $percorso, false );
		$id        = is_object( $contenuto ) ? self::senza_frammento( (string) ( $contenuto->{'$id'} ?? '' ) ) : '';

		if ( '' === $id ) {
			/* translators: %s: nome del file. */
			return new WP_Error( 'trasparenza_catalogo_schema_illeggibile', sprintf( __( 'Il file %s non è uno schema JSON con il proprio identificativo.', 'trasparenza-pa' ), $nome ) );
		}

		$letti[ $nome ] = array(
			'id'        => $id,
			'contenuto' => $contenuto,
		);

		if ( ! $schema ) {
			return array();
		}

		return array(
			'obbligo'       => $obbligo,
			'versione'      => $versione,
			'approvato_il'  => $voce['approvato_il'],
			'in_vigore_dal' => $voce['in_vigore_dal'],
			'codice'        => $versione . '@' . $voce['approvato_il'],
			'file'          => $nome,
			'comuni'        => $elenco,
			'fonte'         => $fonte,
			'id'            => $id,
		);
	}

	/**
	 * Decodifica un file JSON, togliendo il segno BOM iniziale se c'e.
	 *
	 * I file ufficiali cominciano con quel segno, e il decodificatore di PHP
	 * li rifiuterebbe.
	 *
	 * @param string $file        Percorso.
	 * @param bool   $associativo Restituisce elenchi associativi invece di oggetti.
	 * @return mixed
	 */
	private static function decodifica( string $file, bool $associativo = true ) {
		$testo = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		if ( str_starts_with( $testo, "\xEF\xBB\xBF" ) ) {
			$testo = substr( $testo, 3 );
		}

		return json_decode( $testo, $associativo );
	}

	/**
	 * La data e nella forma AAAA-MM-GG ed esiste nel calendario.
	 *
	 * @param mixed $data Valore.
	 * @return bool
	 */
	private static function data_valida( $data ): bool {
		if ( ! is_string( $data ) ) {
			return false;
		}

		$letta = \DateTimeImmutable::createFromFormat( '!Y-m-d', $data );

		return false !== $letta && $letta->format( 'Y-m-d' ) === $data;
	}

	/**
	 * Tutti i rimandi di uno schema, a qualunque profondita.
	 *
	 * @param mixed $nodo Parte dello schema.
	 * @return array<int, string>
	 */
	private static function rimandi( $nodo ): array {
		$trovati = array();

		if ( is_object( $nodo ) || is_array( $nodo ) ) {
			foreach ( (array) $nodo as $chiave => $valore ) {
				if ( '$ref' === $chiave && is_string( $valore ) ) {
					$trovati[] = $valore;
				} else {
					$trovati = array_merge( $trovati, self::rimandi( $valore ) );
				}
			}
		}

		return $trovati;
	}

	/**
	 * La parte di un indirizzo che indica il file, senza il frammento.
	 *
	 * Vuota per un rimando interno allo stesso schema.
	 *
	 * @param string $indirizzo Indirizzo o rimando.
	 * @return string
	 */
	private static function senza_frammento( string $indirizzo ): string {
		$posizione = strpos( $indirizzo, '#' );

		return false === $posizione ? $indirizzo : substr( $indirizzo, 0, $posizione );
	}
}
