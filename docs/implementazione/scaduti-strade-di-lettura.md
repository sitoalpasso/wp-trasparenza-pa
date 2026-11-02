# Scheda di implementazione: il contenuto scaduto fuori da ogni strada di lettura

Scheda scritta prima delle prove e completata a lavoro finito. Serve TRASP-09 per la parte "fuori
dalla sezione, su ogni strada, anche senza il compito pianificato". La parte "mai cancellato" è
un'unità a sé.

## In parole semplici

**Cosa dice la norma.** L'art. 8, c. 3, del d.lgs. 33/2013 dice che, finito il periodo di
pubblicazione, i dati escono dalla sezione e restano ottenibili con l'accesso civico. Quindi
non si vedono più, ma non si buttano.

**Cosa c'è già.** Il componente comune nasconde i contenuti scaduti su un elenco chiuso di strade
di lettura (le righe C-10..C-21 del suo collaudo), e lo fa leggendo la data sul dato, non
aspettando un compito pianificato. La pagina della sezione li toglie già (T-71) e il punto
pubblico non consegna i loro documenti (T-79).

**Cosa manca.** Una prova, da questo componente, che metta un contenuto scaduto con il suo
documento davanti a tutte quelle strade insieme, senza che nessun compito pianificato sia mai
passato, e controlli che alla fine il dato sia ancora intero nella banca dati.

**Come si prova.** Riga T-156.

## 1. File toccati

- `tests/ScadutiPercorsiTest.php` (nuovo).
- `docs/collaudo.md`, `docs/requisiti.md`, questa scheda.

Nessun codice: se la prova passa, il comportamento c'è già e la prova lo tiene fermo.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-09 | fuori dalla sezione su ogni strada di lettura, a compito pianificato fermo, con il dato intero |

## 3. Le strade provate (T-156)

Un contenuto pubblicato, collocato in una voce, con un documento, e con la fine della
pubblicazione passata; accanto, un contenuto uguale in vigore, per vedere che la strada funziona.

| Strada | Riga del componente comune | Cosa si controlla |
|---|---|---|
| Pagina della sezione | | lo scaduto non c'è, quello in vigore sì |
| Punto pubblico dei documenti | | il documento dello scaduto risponde 404 |
| Contenuto singolo, elenchi, interrogazioni di terzi | C-10, C-11, C-21 | lo scaduto non si trova, quello in vigore sì |
| Ricerca | C-12 | lo scaduto non si trova, nemmeno chiedendo il tipo |
| Feed e ricerca dall'indirizzo del sito | C-13 | lo scaduto non c'è |
| Mappa del sito | C-14 | il tipo non ha una mappa |
| Interfaccia informatica | C-15, C-16 | il tipo non c'è; il documento risponde 404; la ricerca non lo trova |
| Anteprima incorporata | C-17 | nessuna anteprima |
| XML-RPC | C-18 | anche chi amministra, da lì, non legge lo scaduto; legge quello in vigore |
| Pagina dell'allegato | C-19 | il documento non ha pagina |

Alla fine: il contenuto è ancora pubblicato, con il suo testo, il suo documento e il file.

## 4. Cosa deliberatamente non fa

- Non prova la navigazione fra contenuti vicini (C-20) e i commenti: il tipo non ha una pagina
  propria e non accetta commenti, quindi quelle strade non esistono.
- Non prova le strade che il componente comune dichiara scoperte: `get_post()` sul singolo
  identificativo, interrogazioni SQL dirette, `suppress_filters`, memorie di pagina davanti a
  WordPress.
- Non impedisce la cancellazione: è l'unità successiva di TRASP-09.

## Com'è andata

La prova è passata alla prima esecuzione senza toccare il codice: il comportamento c'era già,
messo insieme dal componente comune e dalle scelte di registrazione del tipo (non pubblico, non
interrogabile dall'indirizzo, fuori dall'interfaccia informatica). Ora una prova lo tiene fermo
da questo lato. Lungo la strada: il documento chiesto all'interfaccia informatica risponde 404,
come un contenuto che non esiste, e non 401; XML-RPC restituisce un dato vuoto anche a chi
amministra.

TRASP-09 resta in corso: manca la parte "mai cancellato".

**Rotture volute**, una per volta.

| Rottura | Prova rossa |
|---|---|
| Il componente comune non considera mai scaduto niente | T-156 |
| Tolto il secondo strato del componente comune (`the_posts`) | T-156 |
| Tolto il filtro di XML-RPC del componente comune | T-156 |
| Tolto il filtro sul singolo dell'interfaccia informatica del componente comune | T-156 |
| Tipo dichiarato nell'interfaccia informatica | T-156 |
| Tipo interrogabile dall'indirizzo | T-156 |

Equivalenze, rotture rimaste verdi per una ragione detta:

- **Tolto il primo strato del componente comune (`pre_get_posts`).** Il secondo strato toglie lo
  scaduto dai risultati: è la ragione per cui gli strati sono due.
- **Tolto il filtro delle anteprime incorporate, o quello della mappa del sito.** Il tipo non è
  consultabile dall'indirizzo: WordPress non fa anteprime né mappe dei tipi che non lo sono.
- **Tipo dichiarato pubblico, con le altre voci invariate.** Le voci scritte una per una
  (non interrogabile, fuori dalla ricerca, fuori dall'interfaccia informatica) prevalgono.

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Alla scadenza i dati escono dalla sezione e restano ottenibili con l'accesso civico | d.lgs. 33/2013, art. 8, c. 3 (TRASP-09) | `docs/requisiti.md`; testo dell'articolo citato a memoria |
| Elenco delle strade di lettura | righe C-10..C-21 del componente comune | scheda del motore di scadenza del componente comune, letta |
