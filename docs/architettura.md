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
| `Alberatura` | Costruisce l'albero delle sezioni dal file di definizione conforme all'Allegato A; denominazioni esatte, data di aggiornamento per nodo, stato dell'obbligo per nodo | TRASP-01, TRASP-04, TRASP-10, TRASP-27 |
| `Permessi` | Il permesso di amministrare la sezione e la sua prima assegnazione all'amministratore; avviso quando nessun ruolo lo ha | TRASP-27 |
| `SchermataStati` | La schermata "Stato delle voci": elenco delle voci, scelta dello stato, salvataggio tutto o niente con una voce di registro per cambio | TRASP-19, TRASP-27 |
| `MotoreSchemi` | Legge le definizioni degli schemi, genera i moduli di compilazione, valida contro i JSON Schema ufficiali, gestisce le versioni affiancate | TRASP-05, TRASP-06, TRASP-26 |
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
