<?php
/**
 * Avvio della suite con una versione di interfaccia incompatibile.
 *
 * Serve alla riga T-05. La versione la dichiara una costante che il componente
 * comune definisce al proprio caricamento, e una costante si definisce una volta
 * sola: definendola qui prima, la guardia vera legge il valore che vogliamo,
 * senza aggiungere al componente comune un parametro che esisterebbe solo per le
 * prove. Il numero maggiore diverso e il caso piu netto.
 *
 * L'avvio automatico qui e spento: vedi `trasparenza_pa_prepara_suite()`.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

define( 'CONFORMITA_CORE_VERSIONE_API', '2.0.0' );

require_once __DIR__ . '/avvio-comune.php';

trasparenza_pa_prepara_suite( true, false );
