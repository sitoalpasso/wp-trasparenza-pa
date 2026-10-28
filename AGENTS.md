# Istruzioni per gli agenti che leggono questo repository

Questo file vale per gli strumenti automatici di revisione e di scrittura del codice. Le
regole di sviluppo sono in `CLAUDE.md`: questo file non le ripete, le presuppone. Dove i due
testi sembrano dire cose diverse, vale `CLAUDE.md`, e il dubbio va segnalato.

Scrivi sempre in italiano: commenti di revisione, sintesi, proposte di correzione.

## Che cosa è il repository

Plugin WordPress per la sezione "Amministrazione trasparente" (slug `trasparenza-pa`).
Versioni minime: WordPress 6.5, PHP 8.1, sito singolo. Si appoggia al componente comune
`conformita-core` (repository a parte) e gli dichiara le proprie politiche, che sono
**l'opposto di quelle dell'albo pretorio**: qui l'indicizzazione è obbligatoria, e un dato
scaduto esce dalla sezione ma non si cancella, resta in archivio per l'accesso civico.

Il principio che regge tutto il plugin: **l'alberatura e gli schemi di pubblicazione sono
dati, non codice**. Il PHP è un motore che legge le definizioni; un aggiornamento degli
schemi si fa aggiungendo un file, non toccando il codice. Gli schemi entreranno in
`schemas/`, e con loro il test statico che verifica che i nomi dei loro campi non compaiano
mai nei sorgenti PHP: fino ad allora quel controllo esiste solo nella rilettura.

## Dove sono le cose

| Percorso | Contenuto |
|---|---|
| `trasparenza-pa.php` | intestazione del plugin, costanti di versione, avvio |
| `src/` | una classe per parte; la tabella "Le parti del plugin" in `docs/architettura.md` dice cosa fa ciascuna |
| `dati/` | l'alberatura della sezione e gli altri dati dichiarativi |
| `tests/` | PHPUnit sulla suite di test di WordPress; un file `*Test.php` per parte; dati di prova in `tests/dati/` |
| `docs/requisiti.md` | i requisiti TRASP-nn, con fonte normativa e criterio di verifica |
| `docs/collaudo.md` | il catalogo di collaudo: una riga T-nn per ogni cosa da dimostrare, con la prova che la dimostra |
| `docs/architettura.md` | la vita di un dato, le parti del plugin, il confine con il componente comune |
| `docs/sicurezza.md` | checklist di sicurezza |
| `docs/implementazione/` | una scheda per funzione: cosa fa, cosa non fa, limiti dichiarati, righe di collaudo |
| `docs/dati.md` | dati scritti e letti |

## Come girano le prove

- `composer install`, poi `composer test` (PHPUnit, con il componente comune procurato da
  `bin/installa-core.sh`) e `composer lint` (PHPCS con le WordPress Coding Standards).
- Due avvii separati con un componente comune diverso: `composer test:senza-core` e
  `composer test:core-incompatibile`. Le loro prove non girano nella suite principale.
- Ambiente locale: `npx wp-env start` (configurazione in `.wp-env.json`).
- La CI è in `.github/workflows/ci.yml`.

## Review guidelines

### Prima di tutto: la materia della PR

Leggi la descrizione della pull request. Dice quale unità di lavoro si revisiona, quali
requisiti (TRASP-nn) e quali righe di collaudo (T-nn) tocca, quali rilievi di giri
precedenti sono già stati accolti e quale codice è stato scritto per ultimo. **Rileggi per
primo il codice scritto per ultimo**: le correzioni dei giri precedenti sono il punto dove
nascono i difetti nuovi.

Poi leggi la scheda in `docs/implementazione/` che la PR modifica o aggiunge: dice che cosa
il codice deve fare e che cosa **deliberatamente non fa**. Un comportamento dichiarato come
limite o come unità futura non è un difetto di questa PR.

### Che cosa cercare

1. **Dati, non codice.** Un nome di campo degli schemi, una voce dell'alberatura o una
   dicitura obbligata scritti dentro il PHP sono un difetto. Finché il test statico sugli
   schemi non esiste, questo controllo dipende solo da chi rilegge. Uno schema o un'alberatura non si sovrascrivono: una versione nuova si
   aggiunge accanto alla vecchia.

2. **Politiche dichiarate a core.** L'indicizzazione della sezione è obbligatoria e va
   dichiarata esplicitamente; nessuna strada deve renderla dipendente da un'impostazione o
   lasciarla al valore di qualcun altro. Lo stesso per la politica di scadenza.

3. **Durata e scadenza come proprietà del dato letto.** La durata dei dati (cinque anni dal
   1 gennaio dell'anno successivo, dove la norma lo dice) si calcola alla lettura: nessun
   percorso dipende dal compito pianificato. Un dato scaduto esce dalla sezione pubblica su
   ogni strada (pagina, elenco, ricerca, REST, feed, documenti) ma non si cancella.
   Aggiornare un dato produce una versione nuova con data nuova, non una sovrascrittura muta.

4. **Stato dell'obbligo.** I tre valori (applicabile, non applicabile, sospeso) e la
   dicitura obbligata per le sottosezioni non applicabili. Nessuno stato presunto: una
   scelta che spetta all'amministrazione non ha risposta preselezionata.

5. **Permessi e nonce.** Ogni operazione che cambia dati controlla una capability dedicata
   con `current_user_can()`, mai il nome di un ruolo. Il nonce accompagna la capability,
   non la sostituisce. Ogni superficie registrata ha `permission_callback` o nonce più
   capability. Ogni ID dall'esterno si verifica: esiste, è del tipo giusto, è in uno stato
   lecito, l'utente ha titolo. Ogni tipo registrato dichiara `show_in_rest` esplicitamente.

6. **Date in ora italiana.** Sempre il fuso del sito (`wp_timezone()`), mai UTC per errore e
   mai un fuso cablato. Controlla fine anno, mezzanotte e cambio dell'ora legale.

7. **Letture che falliscono.** Un file di dati che non si legge o non è valido, o una
   lettura della banca dati che non risponde, non valgono come "vuoto": l'operazione si
   ferma e lo dice.

8. **Valori d'ente cablati.** Denominazioni, soglie, durate che variano fra
   amministrazioni sono configurazione con validazione.

9. **Prove vuote o che non arrivano alla guardia.** Per ogni prova nuova o modificata
   chiediti che cosa dovrebbe rompersi perché diventi rossa. Segnala:
   - il valore atteso calcolato con la funzione sotto esame invece che scritto per esteso;
   - la prova al negativo che non dimostra prima che il caso esiste (precondizione);
   - la prova che aspetta un rifiuto senza dimostrare quale controllo l'ha prodotto;
   - la prova che usa i dati veri dove serve un caso costruito apposta, o viceversa;
   - l'operazione che risponde "riuscito" senza che lo stato risultante venga riletto;
   - la prova che passerebbe anche togliendo la riga che dovrebbe proteggere.

10. **Righe di collaudo coerenti.** Ogni riga T-nn citata dalla PR esiste in
    `docs/collaudo.md`, non si sovrappone a righe già usate in `main`, punta a una prova che
    esiste e verifica ciò che la riga promette. Se il codice cambia un comportamento
    descritto in `docs/`, il documento cambia nella stessa PR.

11. **Sicurezza di base.** Sanitizzazione in ingresso, escaping nel punto in cui il dato
    incontra l'HTML, SQL solo con `$wpdb->prepare()`, nessun percorso di file costruito da
    input esterno, documenti serviti solo dal punto di consegna del componente comune.
    Elenco completo in `docs/sicurezza.md`.

### Che cosa non segnalare

- Stile, nomi, lingua dei commenti, formattazione: li controlla PHPCS.
- Rilievi fuori dalla materia della PR, salvo un difetto grave di sicurezza o di scadenza,
  o un difetto sfruttabile senza poteri da amministratore: in quel caso segnalalo una volta,
  dicendo che è fuori materia.
- Il codice di WordPress o del componente comune in sé.
- Le scelte già prese e scritte in `docs/requisiti.md`, `docs/architettura.md` e nelle
  schede.
- I limiti dichiarati come tali nelle schede o in `docs/sicurezza.md`, e le parti indicate
  come unità future. Eccezione: un limite dichiarato che si rivela sfruttabile senza poteri
  da amministratore si segnala sempre.

### Come scrivere ogni rilievo

Un rilievo per difetto concreto e riproducibile, con il file, la riga, lo scenario che lo fa
emergere e la correzione precisa. Nel testo di ogni rilievo aggiungi tre righe:

- **Realismo:** "configurazione comune" se basta un'installazione o un uso ordinario di
  WordPress; "componente insolito" se serve un altro plugin o un tema che fa qualcosa di non
  comune, e dici quale.
- **Causa comune:** se il difetto ha la stessa causa di un altro rilievo di questa revisione,
  dilla e nomina l'altro rilievo; altrimenti scrivi "isolato".
- **Chi può sfruttarlo:** "visitatore anonimo", "utente con un ruolo basso" oppure "solo chi
  può già installare plugin o scrivere nella banca dati". Un difetto sfruttabile senza
  poteri da amministratore va sempre corretto, per quanto raro sia il caso: non si dichiara
  come limite.

Priorità: un dato che resta pubblico oltre la sua durata, un dato cancellato invece che
archiviato, la sezione resa non indicizzabile, un campo degli schemi cablato nel PHP, un
dato personale esposto o un permesso aggirato sono sempre la priorità più alta. Una prova
che passerebbe con il comportamento sbagliato su uno di questi punti è alta anche lei.

Se non trovi difetti concreti, dillo in una riga. Non riempire la revisione di suggerimenti.

## Vincoli sui testi del repository

Tutto ciò che entra nel ramo principale diventa pubblico. In nessun file, commento o commit
devono comparire nomi di enti reali, loro domini, o frasi che attribuiscano un requisito a
una singola amministrazione invece che alla norma. I requisiti derivano dalla normativa e
si citano per identificativo (TRASP-nn) ed estremi di legge. Niente trattini lunghi nei
testi. Se una PR viola questi vincoli, segnalalo con priorità alta.
