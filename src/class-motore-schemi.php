<?php
/**
 * Il controllo di un dato contro la versione giusta del suo schema.
 *
 * Righe di collaudo T-26, T-29..T-31 e T-33.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa;

use DateTimeImmutable;
use TrasparenzaPa\Vendor\Opis\JsonSchema\Errors\ValidationError;
use TrasparenzaPa\Vendor\Opis\JsonSchema\Validator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Sceglie la versione e controlla il dato, senza mai andare in rete.
 *
 * Il controllo lo fa la libreria di validazione, nella copia rinominata che il
 * componente distribuisce. La libreria riceve tutti i file del catalogo gia
 * letti, ciascuno con il proprio identificativo: i rimandi a indirizzi esterni
 * si risolvono cosi sui file locali, e la libreria non ha nessun altro modo
 * di procurarsi uno schema.
 */
final class MotoreSchemi {

	/**
	 * Errori raccolti al massimo per un dato.
	 *
	 * Abbastanza per correggere un modulo in una volta, non tanti da rendere
	 * illeggibile il messaggio.
	 */
	const ERRORI_MASSIMI = 50;

	/**
	 * Catalogo degli schemi.
	 *
	 * @var CatalogoSchemi
	 */
	private $catalogo;

	/**
	 * Validatore, creato al primo uso.
	 *
	 * @var Validator|null
	 */
	private $validatore = null;

	/**
	 * Motore su un catalogo gia controllato.
	 *
	 * @param CatalogoSchemi $catalogo Catalogo.
	 */
	public function __construct( CatalogoSchemi $catalogo ) {
		$this->catalogo = $catalogo;
	}

	/**
	 * Il catalogo su cui lavora il motore.
	 *
	 * @return CatalogoSchemi
	 */
	public function catalogo(): CatalogoSchemi {
		return $this->catalogo;
	}

	/**
	 * La versione in vigore di un obbligo a una data, o nullo se nessuna.
	 *
	 * Senza data vale oggi, nel fuso del sito. Una versione approvata ma non
	 * ancora in vigore non si sceglie: il dato nuovo si raccoglie con quella
	 * che vale adesso.
	 *
	 * @param string      $obbligo Identificativo dell'obbligo.
	 * @param string|null $data    Data nella forma AAAA-MM-GG.
	 * @return string|null
	 */
	public function versione_in_vigore( string $obbligo, ?string $data = null ): ?string {
		$data   = $data ?? ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
		$scelta = null;

		foreach ( $this->catalogo->versioni( $obbligo ) as $voce ) {
			if ( strcmp( $voce['in_vigore_dal'], $data ) <= 0 ) {
				$scelta = $voce['versione'];
			}
		}

		return $scelta;
	}

	/**
	 * Controlla un dato contro una versione precisa del suo schema.
	 *
	 * La versione la dice chi chiama: un dato gia raccolto si controlla con la
	 * versione con cui e nato, non con l'ultima.
	 *
	 * Il dato arriva come testo JSON o come oggetto. Un elenco PHP si rifiuta,
	 * perche vuoto non dice se e un oggetto vuoto o una lista vuota, e lo schema
	 * li distingue.
	 *
	 * @param string $obbligo  Identificativo dell'obbligo.
	 * @param string $versione Versione dello schema.
	 * @param mixed  $dato     Testo JSON o oggetto.
	 * @return true|WP_Error
	 */
	public function valida( string $obbligo, string $versione, $dato ) {
		$voce = $this->catalogo->versione( $obbligo, $versione );

		if ( null === $voce ) {
			/* translators: 1: obbligo, 2: versione. */
			return new WP_Error( 'trasparenza_schema_sconosciuto', sprintf( __( 'Il catalogo non ha la versione %2$s dello schema per l\'obbligo %1$s.', 'trasparenza-pa' ), $obbligo, $versione ) );
		}

		if ( is_array( $dato ) ) {
			return new WP_Error( 'trasparenza_dato_forma', __( 'Il dato va passato come testo JSON o come oggetto, non come elenco PHP.', 'trasparenza-pa' ) );
		}

		if ( is_string( $dato ) ) {
			$dato = json_decode( $dato, false );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'trasparenza_dato_forma', __( 'Il dato non è un JSON valido.', 'trasparenza-pa' ) );
			}
		}

		$validatore = $this->validatore();

		if ( is_wp_error( $validatore ) ) {
			return $validatore;
		}

		try {
			$errore = $validatore->validate( $dato, $voce['id'] )->error();
		} catch ( \Throwable $guasto ) {
			/*
			 * Il catalogo e gia controllato, quindi qui arriva solo uno schema che
			 * la libreria non sa interpretare. Il dato non si dichiara valido.
			 */
			return new WP_Error( 'trasparenza_schema_guasto', __( 'Lo schema non si può applicare al dato.', 'trasparenza-pa' ), $guasto->getMessage() );
		}

		if ( null === $errore ) {
			return true;
		}

		$difetti = self::difetti( $errore );

		return new WP_Error(
			'trasparenza_dato_difforme',
			/* translators: %d: numero di difetti. */
			sprintf( _n( 'Il dato non rispetta lo schema: %d difetto.', 'Il dato non rispetta lo schema: %d difetti.', count( $difetti ), 'trasparenza-pa' ), count( $difetti ) ),
			$difetti
		);
	}

	/**
	 * Il validatore, con tutti i file del catalogo registrati.
	 *
	 * @return Validator|WP_Error
	 */
	private function validatore() {
		if ( null !== $this->validatore ) {
			return $this->validatore;
		}

		if ( ! class_exists( Validator::class ) ) {
			return new WP_Error( 'trasparenza_libreria_assente', __( 'La libreria di validazione degli schemi non è installata: il pacchetto del componente è incompleto.', 'trasparenza-pa' ) );
		}

		$validatore = new Validator( null, self::ERRORI_MASSIMI, false );

		foreach ( $this->catalogo->contenuti() as $id => $contenuto ) {
			$validatore->resolver()->registerRaw( $contenuto, $id );
		}

		$this->validatore = $validatore;

		return $validatore;
	}

	/**
	 * I difetti elementari di un errore, ciascuno con percorso e regola.
	 *
	 * La libreria restituisce un albero di errori: quelli che contano per chi
	 * corregge sono le foglie, che dicono quale campo e quale regola.
	 *
	 * @param ValidationError $errore Errore della libreria.
	 * @return array<int, array{percorso: string, regola: string, messaggio: string}>
	 */
	private static function difetti( ValidationError $errore ): array {
		$figli = $errore->subErrors();

		if ( array() !== $figli ) {
			$difetti = array();

			foreach ( $figli as $figlio ) {
				$difetti = array_merge( $difetti, self::difetti( $figlio ) );
			}

			return $difetti;
		}

		$messaggio = $errore->message();

		foreach ( $errore->args() as $nome => $valore ) {
			$messaggio = str_replace( '{' . $nome . '}', is_scalar( $valore ) ? (string) $valore : (string) wp_json_encode( $valore ), $messaggio );
		}

		return array(
			array(
				'percorso'  => '/' . implode( '/', array_map( 'strval', $errore->data()->fullPath() ) ),
				'regola'    => $errore->keyword(),
				'messaggio' => $messaggio,
			),
		);
	}
}
