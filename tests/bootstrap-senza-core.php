<?php
/**
 * Avvio della suite senza il componente comune.
 *
 * Serve alla riga T-03: il caso da provare e "le funzioni pubbliche del
 * componente comune non esistono", e in un processo dove quel componente e
 * caricato quel caso non esiste. Non e una simulazione: il componente non viene
 * caricato.
 *
 * @package TrasparenzaPa
 */

declare( strict_types = 1 );

require_once __DIR__ . '/avvio-comune.php';

trasparenza_pa_prepara_suite( false );
