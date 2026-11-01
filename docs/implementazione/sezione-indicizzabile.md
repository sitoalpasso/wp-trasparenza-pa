# Scheda di implementazione: ogni voce della sezione visibile ai motori di ricerca

Scheda scritta prima del codice e completata a lavoro finito. Serve TRASP-03 per le voci della
sezione: indirizzo canonico e mappa del sito.

## In parole semplici

**Cosa dice la norma.** L'art. 9, c. 1, del d.lgs. 33/2013 vieta di usare filtri o soluzioni
tecniche che impediscano ai motori di ricerca di indicizzare la sezione e di cercarci dentro.

**Cosa succede oggi.** La sezione sta in una pagina di WordPress con il codice breve, e ogni voce
si apre sulla stessa pagina con il parametro `voce` nell'indirizzo. Il blocco dei motori non c'è
(T-18), ma ci sono due ostacoli silenziosi:

1. WordPress dichiara come indirizzo canonico di ogni voce l'indirizzo della pagina **senza** il
   parametro. Per un motore, quindi, le circa novanta voci sono copie della pagina d'ingresso e
   non vanno indicizzate per conto loro: si trova solo l'ingresso.
2. La mappa del sito che WordPress produce elenca la pagina d'ingresso e nessuna voce.

**Cosa costruiamo.** L'indirizzo canonico di una voce diventa quello della voce stessa, e una
mappa del sito della sezione elenca ogni voce di ogni pagina della sezione.

**Come si prova.** Righe T-152 e T-153, con una rottura voluta per regola.

## 1. File toccati

- `src/class-indicizzazione-voci.php` (nuovo), agganciato da `trasparenza-pa.php`.
- `tests/IndicizzazioneVociTest.php` (nuovo).
- `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, questa scheda.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-03 | indirizzo canonico di ogni voce, voci nella mappa del sito |

## 3. Le regole

**Indirizzo canonico (T-152).** Sul filtro `get_canonical_url` di WordPress, solo per una pagina
pubblicata che contiene il codice breve della sezione:

- voce chiesta esistente e diversa dalla radice: l'indirizzo canonico è quello della voce
  (`PaginaPubblica::indirizzo()`), lo stesso che la sezione usa nei suoi collegamenti;
- radice, o nessun parametro: resta quello di WordPress;
- voce sconosciuta: la pagina risponde già "non trovata" (T-17), e l'indirizzo non si tocca;
- pagine senza il codice breve: niente cambia.

Il componente continua a non agganciare `wp_robots` e a non aggiungere intestazioni (T-18): il
canonico è un collegamento nel sorgente, non un divieto.

**Mappa del sito (T-153).** Un fornitore della mappa del sito di WordPress, di nome
`trasparenza`, con un elenco solo: per ogni pagina pubblicata, senza password, che contiene il
codice breve, l'indirizzo di ogni voce dell'albero tranne la radice, che è già nella mappa delle
pagine. Se l'albero non è disponibile la mappa è vuota. Se il sito chiede ai motori di non
indicizzarlo, WordPress spegne tutte le mappe, compresa questa, e l'avviso di T-18 resta.

## 4. Dati letti

Le pagine con il codice breve, lette dalla banca dati; l'albero in vigore. Nessuna scrittura.

## 5. Permessi

Nessuno: sono indirizzi di pagine pubbliche, che chiunque può già aprire.

## 6. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Albero non disponibile | nessun canonico cambiato, mappa vuota |
| Lettura delle pagine con il codice breve fallita | mappa vuota: un motore non trova le voci per quella volta, ma nessun indirizzo sbagliato |

## 7. Cosa deliberatamente non fa

- Non toglie un `noindex` messo da un altro componente o dal tema sulla pagina della sezione: la
  scelta di non agganciare `wp_robots` è di T-18 e cambiarla è una decisione dell'utente.
- Non controlla il file robots.txt: una prova a parte, perché il file può essere fisico e fuori
  dalla portata di WordPress.
- Non dà un titolo proprio a ogni voce nella scheda del browser.

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Nessuna soluzione tecnica che impedisca di indicizzare la sezione | d.lgs. 33/2013, art. 9, c. 1 (TRASP-03) | `docs/requisiti.md`; testo dell'articolo citato a memoria |
| Il canonico verso un'altra pagina fa trattare la voce come copia | nessuna norma: comportamento documentato dei motori di ricerca | citato a memoria |
