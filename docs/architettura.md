# Architettura: quali parti fanno cosa

La mappa del plugin: a cosa serve ogni parte, così una modifica a un file dice subito
che cosa sta toccando. I nomi di classe sono il contratto per il codice; se un nome deve
cambiare, si aggiorna prima questo documento, nello stesso commit.

Il plugin dipende da `conformita-core` e gli dichiara le sue politiche: indicizzazione
**obbligatoriamente consentita** e scadenza "esce dalla sezione ma resta in archivio per
l'accesso civico". Sono l'esatto contrario delle politiche dell'albo pretorio, ed è il
motivo per cui core pretende la dichiarazione esplicita e rifiuta di partire senza.

## Il principio costruttivo: dichiarativo, non cablato

La regola che governa tutto il plugin: **l'albero delle sezioni e gli schemi ANAC sono
file di dati, non codice PHP**. Il PHP è un motore che legge le definizioni e produce
moduli di inserimento, pagine, validazioni ed export. Quando ANAC aggiorna uno schema
(è già successo due volte in diciotto mesi), si aggiunge un file di definizione nuovo:
il codice non si tocca. C'è un test statico che verifica che i nomi dei campi degli
schemi non compaiano mai nei sorgenti PHP.

## La vita di un dato di trasparenza

```
DATO  (per esempio: un pagamento, una voce di organizzazione)
 |
 +--> viene compilato come bozza
 |       campi strutturati generati dallo schema in vigore
 |       [MotoreSchemi, Alberatura]
 |
 +--> viene validato
 |       contro il JSON Schema ufficiale ANAC, mai contro una copia nostra
 |       esiti possibili: validato / pubblicabile provvisoriamente /
 |       non pubblicabile. Nei due casi di difformita' parte la
 |       segnalazione al RPCT, registrata
 |       [MotoreSchemi, StatiEditoriali]
 |
 +--> viene pubblicato nella sua sottosezione
 |       pagina indicizzabile, dato esportabile in formato aperto,
 |       file con nome a date, data di aggiornamento esposta,
 |       voce nel registro di core
 |       [Alberatura, Esportazione, core: Registro]
 |
 +--> resta pubblicato cinque anni
 |       dal 1 gennaio dell'anno successivo all'obbligo
 |       aggiornarlo = nuova versione con data nuova, non sovrascrittura muta
 |       [core: MotoreScadenza]
 |
 +--> alla scadenza esce dalla sezione
         MA non si cancella: resta nell'archivio ad accesso controllato,
         ottenibile con l'accesso civico
         [PoliticaTrasparenza, core: MotoreScadenza]
```

Le voci che non contengono dati ma **rinviano** altrove (banche dati nazionali) seguono
un ciclo proprio: si registrano con etichetta, data e amministrazione titolare, e il
verificatore di collegamenti di core le ricontrolla periodicamente; un rinvio rotto
viene segnalato.

## Le parti del plugin

| Classe (in `src/`) | Responsabilità | Requisiti |
|---|---|---|
| `Alberatura` | Costruisce l'albero delle sezioni dal file di definizione conforme all'Allegato A; denominazioni esatte, data di aggiornamento per nodo, stato dell'obbligo per nodo; voci coperte da un modulo ANAC, voci legate alla cessazione e banche dati a cui una voce può rinviare, come dati del file | TRASP-01, TRASP-04, TRASP-10, TRASP-25, TRASP-27, TRASP-29 |
| `CatalogoSchemi` | Legge e controlla il catalogo `schemas/catalogo.json`: versioni per obbligo con date e fonte, file degli schemi e delle definizioni comuni, rimandi risolti in locale | TRASP-06, TRASP-26 |
| `MotoreSchemi` | Sceglie la versione in vigore, valida contro i JSON Schema ufficiali con la libreria opis/json-schema nella copia prefissata, restituisce i difetti con percorso e regola; i moduli di compilazione arrivano dopo | TRASP-05, TRASP-06, TRASP-26 |
| `TipoVoce` | Registra il tipo della voce di trasparenza attraverso il componente comune, chiuso al pubblico e fuori dall'interfaccia REST | TRASP-05 |
| `Permessi`, `Installazione` | Permessi della voce e del contenuto chiesti al componente comune e assegnati all'amministratore a ogni cambio di versione installata, con il permesso di consultare i contenuti scaduti che dalla versione 1.11.0 del componente comune serve per aprirne i file; il permesso di amministrare la sezione, dato all'amministratore una volta sola, e l'avviso quando nessun ruolo lo ha | TRASP-27 |
| `SchermataStati` | La schermata "Stato delle voci": elenco delle voci, scelta dello stato, salvataggio tutto o niente con una voce di registro per cambio; si applicano solo le voci cambiate rispetto a quanto il modulo mostrava | TRASP-19, TRASP-27 |
| `Opzione` | Lettura di un'opzione dalla banca dati che distingue l'assenza dalla lettura fallita, per chi deve riscriverla | trasversale |
| `Responsabile` | I dati del responsabile della prevenzione della corruzione e della trasparenza: controllo e riquadro pubblico nella voce che l'albero indica | TRASP-13 |
| `SchermataResponsabile` | La schermata "Responsabile": salvataggio tutto o niente con una voce di registro senza dati personali; avviso finché il responsabile manca | TRASP-13, TRASP-19 |
| `TipoContenuto` | Registra il tipo del contenuto libero della sezione, testo e documenti, attraverso il componente comune, chiuso al pubblico e fuori dall'interfaccia REST; dichiara che i suoi file seguono la pagina della sezione, con la condizione di `ContenutiPubblici` | TRASP-19 |
| `SchedaContenuto` | Il riquadro "Voce della sezione": scelta fra le voci senza sottovoci, salvataggio con una voce di registro, conflitto se un altro ha cambiato la voce dopo l'apertura; avviso sul modulo ANAC che copre la voce | TRASP-19 |
| `SchedaDocumenti` | Il riquadro "Documenti": deposito attraverso il componente comune, rimozione solo su un contenuto non pubblicato, voce di registro con nome e impronta per ogni aggiunta e rimozione | TRASP-19 |
| `ContenutiPubblici` | I contenuti che la pagina pubblica mostra in una voce: pubblicati, senza password, con una voce valida e la fine non passata, ricontrollati uno per uno, con l'avviso di indisponibilità se la lettura fallisce; data di ultimo aggiornamento di ogni contenuto e di ogni voce, la più recente fra quelle sotto; testo ripulito, codici brevi non eseguiti; i documenti sotto ogni contenuto e la condizione che il componente comune chiama prima di consegnarli: il contenuto deve comparire nella pagina | TRASP-02, TRASP-09, TRASP-10 |
| `SchedaPubblicazione` | Il riquadro "Pubblicazione": data da cui decorre l'obbligo, ultimo giorno degli effetti facoltativo, cessazione nelle voci che l'albero lega alla cessazione, fine calcolata con la durata dell'albero, prolungata dagli effetti o contata dalla cessazione, scritta attraverso il componente comune, voce di registro, conflitto, riallineamento | TRASP-08, TRASP-19 |
| `Metadato` | Lettura di un metadato dalla banca dati che distingue l'assenza dalla lettura fallita, per chi deve riscriverlo | trasversale |
| `Blocco` | Il blocco della banca dati che tiene in fila i salvataggi delle schermate di gestione | TRASP-19 |
| `ChiusuraPubblicazione` | Ogni tentativo di pubblicare una voce la lascia in bozza finche non ci sono gli stati editoriali | TRASP-28 |
| `ModuloSchema` | Costruisce i campi del modulo leggendo lo schema di una versione, e dai valori inviati ricostruisce il dato; non conosce nessun campo di nessuno schema | TRASP-05, TRASP-06 |
| `SchedaVoce` | Riquadro dei dati nella schermata della voce: scelta dell'obbligo, legame con la versione in vigore scritto una volta sola, salvataggio con nonce e permesso, esito del controllo e difetti accanto ai campi | TRASP-05, TRASP-26 |
| `PoliticaTrasparenza` | Dichiara a core: indicizzazione consentita, scadenza quinquennale con uscita senza cancellazione, calcolo della decorrenza dal 1° gennaio successivo | TRASP-03, TRASP-08, TRASP-09 |
| `StatiEditoriali` | Bozza, validato, pubblicabile provvisoriamente, non pubblicabile; segnalazione al RPCT nei casi di difformità | TRASP-28 |
| `VociRinvio` | Le voci assolte per collegamento: etichetta, data, amministrazione titolare, destinatario dell'accesso civico; registra i collegamenti presso il verificatore di core | TRASP-11, TRASP-25, TRASP-29 |
| `Esportazione` | Genera i file in formato aperto con la denominazione a date e versione; emette le condizioni di riutilizzo configurate | TRASP-07, TRASP-20, TRASP-21 |
| `AtMap` | Genera il file at_map con l'URL di ogni nodo, valido contro lo schema ufficiale; riga ATmap nel robots solo se attivata | TRASP-22, TRASP-23 |
| `AccessoCivico` | Modulo di richiesta, destinatario e titolare del potere sostitutivo, registro delle richieste | TRASP-12 |
| `ControlloPreventivo` | Passaggio obbligato sui dati personali prima della pubblicazione | TRASP-16 |
| `AvvisoPdf` | Avviso euristico sui PDF sospetti al caricamento | TRASP-15 |

Le definizioni (alberatura e schemi) vivono in una cartella di dati del repository, una
sottocartella per versione, con la fonte ANAC citata in ogni file.

## Cosa arriva da conformita-core

| Meccanismo di core | Cosa fa per la trasparenza | Requisiti |
|---|---|---|
| Motore di scadenza | Filtro in lettura più compito pianificato: il dato scaduto esce dalla sezione anche a cron fermo, e non viene mai cancellato | TRASP-08, TRASP-09 |
| Controllo indicizzazione | Applica la politica dichiarata: nessun noindex, pagine in sitemap | TRASP-03 |
| Registro delle modifiche | Chi ha pubblicato o rimosso cosa e quando | TRASP-19 |
| Verificatore di collegamenti | Ricontrolla periodicamente i rinvii e ne registra l'esito | TRASP-25 |
| Consegna allegati | I documenti scaduti restano ottenibili solo dall'archivio ad accesso controllato | TRASP-09 |
| Battito di controllo | Rileva il cron fermo e avvisa | trasversale |

## Il confine tra trasparenza e core, in una frase

Il meccanismo di scadenza, il registro e il verificatore sono di core; la conoscenza di
cosa è l'Allegato A, di come sono fatti gli schemi ANAC e di come si chiama un file
esportato è tutta qui, e per la parte che cambia nel tempo sta nei file di dati, non nel
PHP.
