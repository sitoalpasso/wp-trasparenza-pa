<?php
/**
 * Aiuti alle prove che depositano documenti veri.
 *
 * Il deposito lo fa il componente comune, e lo rifiuta finche la protezione
 * della cartella non risulta verificata. La verifica e una richiesta HTTP al
 * sito stesso: qui la si intercetta con `pre_http_request` e le si fa
 * rispondere un server finto, che nega l'accesso all'esca come un server
 * configurato bene. I file partono da file temporanei veri e si depositano con
 * l'origine "percorso locale", perche dentro la suite nessun file arriva da una
 * richiesta HTTP.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

namespace TrasparenzaPa\Tests;

/**
 * Server finto, file temporanei, pulizia del disco.
 */
trait DepositoDiProva {

	/**
	 * Quello che il server finto risponde.
	 *
	 * @var array<string, mixed>
	 */
	private $risposta_del_server = array();

	/**
	 * File temporanei da togliere alla fine.
	 *
	 * @var array<int, string>
	 */
	private $file_temporanei = array();

	/**
	 * Aggancia il server finto che nega l'esca e verifica la protezione.
	 */
	protected function aggancia_server_finto(): void {
		\Conformita_Core_Allegati::azzera();

		$this->risposta_del_server = array(
			'response' => array(
				'code'    => 403,
				'message' => 'Forbidden',
			),
			'body'     => '<html><body>Forbidden</body></html>',
		);

		add_filter( 'pre_http_request', array( $this, 'server_finto' ), 10, 3 );

		$stato = conformita_core_verifica_protezione_allegati();

		$this->assertSame( 'verificata', $stato['copertura'], 'Precondizione: con il server finto la protezione deve risultare verificata.' );
	}

	/**
	 * Da qui in poi il server serve l'esca, come un server che non protegge la cartella.
	 */
	protected function server_che_serve_l_esca(): void {
		$this->risposta_del_server = array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => \Conformita_Core_Allegati::contenuto_esca(),
		);

		$stato = conformita_core_verifica_protezione_allegati();

		$this->assertSame( 'non_coperta', $stato['copertura'], 'Precondizione: la protezione deve risultare non coperta.' );
	}

	/**
	 * Toglie il server finto, i file temporanei e i documenti depositati.
	 */
	protected function sgancia_server_finto(): void {
		remove_filter( 'pre_http_request', array( $this, 'server_finto' ), 10 );

		foreach ( $this->file_temporanei as $percorso ) {
			wp_delete_file( $percorso );
		}

		$this->file_temporanei = array();

		foreach ( $this->documenti_sul_disco() as $percorso ) {
			wp_delete_file( $percorso );
		}

		\Conformita_Core_Allegati::azzera();
	}

	/**
	 * Aggancio di `pre_http_request`: la risposta finta.
	 *
	 * @param mixed                $esito     Esito proposto.
	 * @param array<string, mixed> $argomenti Argomenti della richiesta.
	 * @param string               $indirizzo Indirizzo chiesto.
	 * @return array<string, mixed>
	 */
	public function server_finto( $esito, $argomenti = array(), $indirizzo = '' ): array {
		unset( $esito, $argomenti, $indirizzo );

		return $this->risposta_del_server;
	}

	/**
	 * Un file temporaneo vero, nella forma di `$_FILES`.
	 *
	 * @param string      $nome      Nome del file.
	 * @param string|null $contenuto Contenuto, un PDF minimo se nullo.
	 * @return array<string, mixed>
	 */
	protected function file_temporaneo( string $nome = 'documento.pdf', ?string $contenuto = null ): array {
		if ( null === $contenuto ) {
			$contenuto = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n% " . wp_generate_password( 12, false ) . "\n";
		}

		$percorso = wp_tempnam( $nome );

		file_put_contents( $percorso, $contenuto ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- prova: il file deve esistere sul disco.

		$this->file_temporanei[] = $percorso;

		return array(
			'name'     => $nome,
			'tmp_name' => $percorso,
			'type'     => '',
			'size'     => strlen( $contenuto ),
			'error'    => UPLOAD_ERR_OK,
		);
	}

	/**
	 * I documenti sul disco nella cartella protetta, senza i file di regole e le esche.
	 *
	 * @return array<int, string>
	 */
	protected function documenti_sul_disco(): array {
		$cartella = \Conformita_Core_Allegati::cartella();
		$trovati  = array();

		if ( ! is_dir( $cartella ) ) {
			return array();
		}

		$voci = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $cartella, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $voci as $voce ) {
			$nome = $voce->getFilename();

			if ( $voce->isDir() || in_array( $nome, array( '.htaccess', 'web.config', 'index.php' ), true ) || 0 === strpos( $nome, \Conformita_Core_Allegati::ESCA_PREFISSO ) ) {
				continue;
			}

			$trovati[] = $voce->getPathname();
		}

		sort( $trovati );

		return $trovati;
	}
}
