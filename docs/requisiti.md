# Requisiti: cosa deve fare l'amministrazione trasparente

Questo documento spiega in linguaggio semplice cosa il plugin deve fare e perché. Ogni
requisito ha un identificativo (TRASP-01 fino a TRASP-29), usato anche nei test e nei
commit: dal codice si risale sempre al motivo. La tabella di riferimento è in fondo.

## L'idea in dieci righe

"Amministrazione trasparente" è la sezione che ogni ente pubblico deve avere sul proprio
sito (d.lgs. 33/2013): dentro ci stanno le informazioni che il decreto obbliga a
pubblicare, organizzate in un albero di sezioni e sottosezioni fissato dall'Allegato A
del decreto. Serve al controllo diffuso: chiunque deve poter vedere come l'ente usa le
risorse pubbliche, senza registrarsi e senza chiedere permesso.

I due fatti che decidono il progetto:

1. **Non è più un albero di pagine con dei PDF dentro: è un insieme di dati
   strutturati.** Con la delibera 495/2024 (integrata dalla 481/2025) ANAC ha approvato
   schemi standard di pubblicazione, obbligatori dal 22 gennaio 2026 per tre obblighi
   (artt. 4-bis, 13 e 31 del decreto). I dati si compilano in campi precisi e devono
   validare contro i JSON Schema ufficiali. Altri schemi sono disponibili o in
   sperimentazione e diventeranno rilevanti nel tempo: gli schemi cambiano spesso,
   quindi il plugin li tratta come **file di dati** aggiornabili, mai come codice.
2. **Qui l'indicizzazione è obbligatoria.** L'art. 9 del decreto vieta di ostacolare i
   motori di ricerca: l'esatto contrario dell'albo pretorio, dove l'indicizzazione va
   impedita. Due sezioni dello stesso sito con regole invertite: per questo la politica
   è sempre un parametro dichiarato, mai un default.

## I requisiti, a gruppi

### La sezione e il suo accesso (TRASP-01, TRASP-02, TRASP-03, TRASP-24)

La sezione si chiama esattamente "Amministrazione trasparente" ed è raggiungibile dalla
home (TRASP-01). Tutto è consultabile da chiunque, senza registrazione né
identificazione (TRASP-02). Nessun noindex, nessun blocco nel robots, pagine in sitemap
(TRASP-03). Tutto servito in HTTPS, senza filtri (TRASP-24).

### L'albero e gli schemi (TRASP-04, TRASP-05, TRASP-06, TRASP-26, TRASP-27)

- La struttura segue l'Allegato A voce per voce, con le denominazioni previste, non
  modificabili a piacere (TRASP-04). L'Allegato A non e fermo: il d.lgs. 103/2024 ha
  inserito l'art. 23-bis e ha sostituito la sottosezione "Controlli sulle imprese" con
  "Controlli sulle attività economiche". Anche l'albero, quindi, e un file di dati con
  la sua versione.
- I tre obblighi con schema obbligatorio si compilano **solo** attraverso campi
  strutturati conformi ai modelli ANAC, e il dato prodotto valida contro i JSON Schema
  ufficiali (TRASP-05).
- Un nuovo schema si aggiunge con un **file di definizione**, senza toccare il codice
  (TRASP-06): è la scelta che permette di reggere gli aggiornamenti ANAC, già avvenuti
  due volte in diciotto mesi.
- Gli schemi sono **versionati con la data di approvazione ANAC**: una versione nuova si
  affianca, non sovrascrive, e i dati già pubblicati restano legati alla versione con
  cui sono stati raccolti (TRASP-26).
- Ogni obbligo ha uno **stato dichiarato**: applicabile, non applicabile, sospeso. Il
  "non applicabile" stampa carattere per carattere la dicitura prevista dagli schemi:
  "L'obbligo di pubblicazione non è applicabile all'amministrazione". Una sottosezione
  vuota senza stato non è conforme (TRASP-27).

### La vita del dato (TRASP-08, TRASP-09, TRASP-10, TRASP-28)

- I dati restano pubblicati **cinque anni**, contati dal 1° gennaio dell'anno successivo
  a quello dell'obbligo, e comunque finché l'atto produce effetti (TRASP-08).
- Alla scadenza **escono dalla sezione ma non si cancellano**: restano ottenibili con
  l'accesso civico (TRASP-09). Anche qui la scadenza è una proprietà del dato letto,
  con il motore a tre strati di core: la conformità non dipende dal cron.
- Ogni sottosezione e ogni dato espongono la **data di ultimo aggiornamento**
  (TRASP-10).
- Prima della pubblicazione il dato passa per **stati editoriali** presi dalle
  istruzioni operative ANAC: bozza, validato, pubblicabile provvisoriamente, non
  pubblicabile. Nei due stati di difformità il sistema registra la **segnalazione al
  RPCT**, il responsabile della prevenzione della corruzione e della trasparenza
  (TRASP-28).

### File e formati (TRASP-07, TRASP-20, TRASP-21)

- Tutto ciò che si pubblica è in **formato aperto** riutilizzabile (JSON, CSV, XML,
  PDF accessibile); vietati i PDF fatti di scansioni (TRASP-07, TRASP-20). L'export
  dichiara le condizioni di riutilizzo come parametro configurabile.
- I file generati hanno **nomi parlanti** che contengono anno, data di prima
  pubblicazione, data di ultima modifica e codice della sottosezione, nella forma
  suggerita da ANAC, per esempio `art.31-cc-20260101-20260817.v1.0.csv` (TRASP-21).

### Collegamenti invece di duplicati (TRASP-11, TRASP-22, TRASP-23, TRASP-25, TRASP-29)

- Un obbligo può essere assolto **linkando** il dato dove già esiste, purché il link
  porti al dato vero e non a una pagina intermedia vuota (TRASP-11).
- Il sito pubblica un file **`at_map`**: la mappa che associa ogni voce dell'albero al
  suo URL, validata contro lo schema ufficiale ANAC (TRASP-22). La riga `ATmap` nel
  robots è una raccomandazione, attivabile a scelta (TRASP-23).
- Quando l'obbligo si assolve rinviando a una banca dati nazionale, il **rinvio è un
  dato**: con data, etichetta, e **verifica automatica periodica** che il collegamento
  risponda. Un rinvio rotto è un obbligo non assolto, e non si vede a occhio
  (TRASP-25).
- Ogni voce-rinvio dice anche **chi risponde all'accesso civico**: se la pubblicazione
  nella banca dati è omessa, la richiesta va al RPCT dell'amministrazione titolare
  della banca dati, non a quello dell'ente (TRASP-29).

### Persone e responsabilità (TRASP-12, TRASP-13, TRASP-14, TRASP-16, TRASP-19)

- Dalla sezione si raggiunge il modulo di **accesso civico**, con destinatario e
  titolare del potere sostitutivo, e ogni richiesta finisce in un registro (TRASP-12).
- Il **RPCT** è indicato con nome e recapiti dove previsto (TRASP-13); la sottosezione
  del piano anticorruzione ospita il PIAO (TRASP-14).
- Prima di pubblicare, un controllo sui **dati personali** analogo a quello dell'albo:
  la trasparenza non autorizza a pubblicare dati eccedenti (TRASP-16).
- Ogni pubblicazione e rimozione è tracciata nel registro di core (TRASP-19).

### Convivenza e accessibilità (TRASP-15, TRASP-17, TRASP-18)

I documenti pubblicati seguono i requisiti di accessibilità, con l'avviso euristico sui
PDF sospetti (TRASP-15). La sezione convive con l'albo pretorio: cicli di vita
indipendenti, configurazioni di indicizzazione opposte, stesso documento pubblicabile in
entrambe senza duplicare il file (TRASP-17). Esiste la sottosezione "Altri contenuti -
Accessibilità" con obiettivi annuali e dichiarazione (TRASP-18).

## Cosa resta all'ente

Il plugin fornisce la struttura conforme e i controlli; **i contenuti e la loro
esattezza restano dell'ente**: nomina e recapiti del RPCT, adozione del PIAO,
popolamento dei dati, risposte all'accesso civico, valutazione sui dati personali.

## Tabella di riferimento

La colonna **Stato** dice a che punto è il lavoro su ogni requisito, e ha tre valori
soltanto:

- **da fare**: non ancora implementato. Il requisito è scritto e collaudabile, il codice
  non c'è.
- **in corso**: il codice esiste ma i suoi test non sono tutti verdi in CI. Non conta
  come fatto.
- **fatto**: implementato e con tutti i suoi test verdi in CI. È l'unico valore che
  autorizza a dire che il plugin fa quella cosa.

Lo stato lo aggiorna la stessa modifica che lo fa cambiare: passare a **fatto** senza il
test verde è la sola scorrettezza che rende inutile tutta la tabella.

| ID | Stato | In una riga | Fonte principale |
|---|---|---|---|
| TRASP-01 | da fare | Denominazione esatta e link in home | d.lgs. 33/2013 art. 9 |
| TRASP-02 | da fare | Accesso senza autenticazione | art. 9 |
| TRASP-03 | da fare | Indicizzazione consentita e non ostacolata | art. 9 |
| TRASP-04 | fatto | Albero conforme all'Allegato A | Allegato A, delibera ANAC 1310/2016, d.lgs. 103/2024 |
| TRASP-05 | in corso | Schemi obbligatori per artt. 4-bis, 13, 31 | art. 48, delibere ANAC 495/2024 e 481/2025 |
| TRASP-06 | in corso | Estensibilità dichiarativa ai nuovi schemi | delibere ANAC 495/2024 e 497/2025 |
| TRASP-07 | da fare | Formato aperto e riutilizzabile | art. 7 |
| TRASP-08 | in corso | Durata quinquennale calcolata dall'obbligo | art. 8 |
| TRASP-09 | da fare | Scaduto: fuori dalla sezione, mai cancellato | art. 8 |
| TRASP-10 | in corso | Data di ultimo aggiornamento visibile | art. 6 |
| TRASP-11 | da fare | Collegamento ammesso se porta al dato vero | art. 9 |
| TRASP-12 | da fare | Accesso civico con registro | art. 5 |
| TRASP-13 | fatto | RPCT indicato | art. 43 |
| TRASP-14 | da fare | PIAO al posto del vecchio piano autonomo | d.l. 80/2021 art. 6 |
| TRASP-15 | da fare | Documenti accessibili | l. 4/2004 |
| TRASP-16 | da fare | Minimizzazione dei dati personali | GDPR art. 5 |
| TRASP-17 | da fare | Separazione dall'albo, politiche opposte | d.lgs. 33/2013 |
| TRASP-18 | da fare | Sottosezione accessibilità | d.lgs. 179/2016 art. 9 |
| TRASP-19 | in corso | Tracciamento delle modifiche | art. 6 |
| TRASP-20 | da fare | Formato aperto, niente PDF da scansione | specifiche tecniche ANAC |
| TRASP-21 | da fare | Nomi dei file con date e versione | specifiche tecniche ANAC |
| TRASP-22 | da fare | File at_map validato | specifiche tecniche ANAC |
| TRASP-23 | da fare | Direttiva ATmap nel robots, opzionale | specifiche tecniche ANAC (raccomandazione) |
| TRASP-24 | da fare | HTTPS ovunque, senza filtri | specifiche tecniche ANAC |
| TRASP-25 | da fare | Rinvii tracciati e verificati | art. 9-bis, d.l. 19/2026 art. 8 |
| TRASP-26 | in corso | Schemi versionati con la data ANAC | delibere ANAC 495/2024, 481/2025 |
| TRASP-27 | fatto | Stato dell'obbligo a tre valori, dicitura esatta | schemi ANAC |
| TRASP-28 | da fare | Stati editoriali con segnalazione al RPCT | istruzioni operative ANAC |
| TRASP-29 | da fare | Titolare dell'accesso civico sulle voci-rinvio | art. 9-bis commi 3 e 4 |
