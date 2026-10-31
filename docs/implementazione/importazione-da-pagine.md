# Scheda di implementazione: importazione dalle pagine del sito

Scheda scritta prima del codice e completata a lavoro finito, passo per passo. Serve il
passaggio di un ente che oggi tiene la sezione "Amministrazione trasparente" come pagine
WordPress, con i documenti caricati nella libreria dei media. Non aggiunge requisiti: protegge
quelli che il passaggio può rompere, TRASP-09 (un documento scaduto non torna nella sezione) e
TRASP-19 (ogni cosa portata dentro lascia traccia).

## In parole semplici

**Cosa costruiamo.** Tre passi, uno per pull request, sul modello dell'importazione dell'albo.

1. **Il rapporto, senza scrivere niente.** Chi amministra la sezione sceglie la pagina che oggi
   fa da ingresso alla sezione. Il componente legge le pagine pubblicate che stanno sotto, e
   per ciascuna dice: a quale voce dell'albero corrisponde, se il titolo lo permette; quanto
   testo contiene; quali file collega o ha allegati, se il file c'è davvero, quanto pesa e la
   sua impronta; quali collegamenti portano fuori dal sito. Poi dice quali voci dell'albero non
   hanno nessuna pagina, e quali pagine sono vuote. Per ogni file dice anche se, dalla data in
   cui è stato caricato, la sua pubblicazione è finita di sicuro. È la lista da mostrare
   all'ente prima di portare dentro qualunque cosa.
2. **Il passaggio vero.** Da una tabella "pagina vecchia, voce nuova" confermata da chi
   amministra, per ogni pagina un contenuto in bozza nella voce scelta, con i file copiati nella
   cartella protetta del componente comune. Nessun contenuto esce pubblicato da solo: la data
   da cui decorre l'obbligo la scrive un addetto, e un file la cui pubblicazione è finita di
   sicuro non entra fra i documenti pubblicabili.
3. **I vecchi indirizzi.** L'indirizzo di una pagina vecchia porta alla voce nuova, così i
   collegamenti già in giro non si rompono.

**Come la costruiamo (passo 1).** Il rapporto legge le pagine e gli allegati con interrogazioni
che distinguono "nessuno" da "lettura fallita", e i file solo dalla cartella dei caricamenti del
sito, dal disco: non scarica niente da internet, quindi un indirizzo scritto in una pagina non
può far chiamare al server un altro server. Un percorso che, risolto, esce dalla cartella dei
caricamenti non si apre. Il rapporto non scrive niente: né contenuti, né impostazioni, né
registro, né file.

**Come si prova, e come si prova che la prova è vera.** Righe di collaudo da T-121, assegnate
passo per passo. Ogni regola ha una rottura voluta che fa diventare rossa almeno una prova,
elencata in fondo a lavoro finito.

## 1. File toccati

| Passo | File | Perché |
|---|---|---|
| 1 | `src/class-importazione-pagine.php` | Nuovo. Il rapporto: pagine, voce proposta, file, voci senza pagina |
| 1 | `src/class-schermata-importazione.php` | Nuovo. La schermata che chiede la pagina d'ingresso e mostra il rapporto |
| 1 | `trasparenza-pa.php` | Caricamento e agganci |
| 1 | `tests/ImportazionePagineTest.php` | Nuovo. Righe T-121..T-126 |
| tutti | `docs/` | Collaudo, architettura, dati |

## 2. Requisiti serviti

Nessun requisito nuovo. Il passo 1 prepara il passaggio e ne mostra i rischi; i requisiti
protetti sono TRASP-09 e TRASP-19, e TRASP-04 per la corrispondenza con l'albero.

## 2-bis. Norme e fonti

| Regola del rapporto | Norma | Da dove viene, e se è verificata |
|---|---|---|
| Fine della pubblicazione al 31 dicembre dell'anno di decorrenza più la durata | d.lgs. 33/2013, art. 8, c. 3 | Dal file dell'albero in vigore, che porta la durata con la sua fonte (`fonte_durata`), già usata per TRASP-08 nella scheda `fine-pubblicazione.md`. Il testo della norma non è stato riletto per questo passo |
| Gli atti che producono ancora effetti restano oltre i cinque anni | d.lgs. 33/2013, art. 8, c. 3 | Stessa fonte; il caso è già trattato nella scheda `effetti-oltre-cinque-anni.md`. Qui solo dichiarato, non calcolato |
| Un documento scaduto non torna nella sezione | d.lgs. 33/2013, art. 8 (TRASP-09) | `docs/requisiti.md` di questo repository |
| Minimizzazione dei dati personali nel passaggio | Regolamento (UE) 2016/679, art. 5 (TRASP-16) | `docs/requisiti.md` di questo repository. Il principio guida la scelta prudente sulla data, non c'è un obbligo che la prescriva così |
| Ogni cosa portata dentro lascia traccia (passo 2) | d.lgs. 33/2013, art. 6 (TRASP-19) | `docs/requisiti.md` di questo repository |

Il resto delle regole (quali pagine, come si propone la voce, quali file, i tre stati) è una
scelta di costruzione, non viene da una norma.

## 3. Le regole del rapporto (passo 1)

**Quali pagine.** La pagina d'ingresso, che spesso ha testo e file suoi, e le pagine pubblicate
che ne discendono, a qualunque profondità, ordinate come nell'albero delle pagine (ordine del
menu, poi titolo, poi identificativo). Bozze, pagine private e cestino restano
fuori: il rapporto mostra solo ciò che è già pubblico, quindi non rivela nulla a chi ha il
permesso della sezione e non quello delle pagine. La pagina d'ingresso deve essere una pagina
pubblicata; un genitore che, risalendo, riporta alla pagina stessa non fa girare il rapporto
all'infinito.

**Quale voce.** Il titolo della pagina si confronta con il nome di ogni voce dell'albero in
vigore, radice esclusa, dopo averli scritti entrambi in minuscolo, senza accenti e con spazi al
posto di punteggiatura e simboli. Quattro esiti:

| Esito | Quando |
|---|---|
| proposta | il titolo coincide con il nome di una sola voce, e la voce non ha sottovoci |
| con sottovoci | coincide con una sola voce, che ha sottovoci: il contenuto va in una di quelle |
| più voci | coincide con il nome di più voci |
| nessuna | non coincide con nessuna voce |

La voce proposta è una proposta: il passaggio vero la fa confermare.

**Quali file.** Due fonti, unite senza doppioni per percorso relativo:

- gli indirizzi scritti nel testo della pagina che cadono nella cartella dei caricamenti, in un
  attributo o in un codice breve, con o senza schema e con o senza dominio; con un dominio, solo
  se è quello della cartella dei caricamenti del sito. La parte dopo `?` o `#` non conta;
- gli allegati della libreria dei media che hanno la pagina come genitore.

Per ogni file: il percorso relativo alla cartella dei caricamenti, l'allegato della libreria che
lo registra se c'è, la data, e uno di tre stati: **presente** (con dimensione in byte e impronta
SHA-256), **mancante** (il percorso non porta a un file), **fuori** (il percorso, risolti i
`..` e i collegamenti simbolici, esce dalla cartella dei caricamenti: non si apre e non se ne
dice altro).

**La data e la pubblicazione finita di sicuro.** La data di un file è quella di caricamento
dell'allegato che lo registra; senza allegato, l'anno e il mese della cartella `AAAA/MM` in cui
sta; altrimenti nessuna. La pubblicazione di un file è **finita di sicuro** quando l'anno della
data più la durata dell'albero in vigore è minore dell'anno di oggi, nel fuso del sito. Il
ragionamento: l'obbligo di pubblicare nasce prima che il file venga caricato, quindi la sua fine,
il 31 dicembre dell'anno di decorrenza più la durata, non può cadere dopo il 31 dicembre
dell'anno di caricamento più la durata. È un limite prudente nella direzione dei dati personali:
dice "finita" solo quando lo è comunque. Non sa degli atti che producono ancora effetti (art. 8,
c. 3) né delle durate legate alla cessazione: lo dice accanto al conteggio. Senza data, o con un
albero senza durata, il rapporto scrive "non si sa".

**Testo e collegamenti esterni.** Il testo della pagina si misura in caratteri, tolti i tag e
qualunque cosa fra parentesi quadre: dice se c'è testo da rileggere a mano, non lo porta.
I collegamenti con schema `http` o `https` verso un dominio diverso da quello del sito si
elencano una volta ciascuno: possono essere rinvii a banche dati.

**Pagine vuote e voci senza pagina.** Una pagina senza testo, senza file e senza collegamenti
esterni è vuota. Una voce senza sottovoci, radice esclusa, che non è la voce proposta di nessuna
pagina è una voce senza pagina.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Pagine | lette con un'interrogazione: identificativo, genitore, titolo, testo, ordine, solo pagine pubblicate. Una lettura fallita ferma il rapporto |
| Allegati | letti con un'interrogazione: identificativo, genitore, data e percorso registrato. Una lettura fallita ferma il rapporto |
| File | letti dal disco solo dentro la cartella dei caricamenti, per dimensione e impronta |
| Albero | quello in vigore, per nomi, sottovoci e durata |
| Scritto | niente |

## 5. Permessi

La schermata e il rapporto chiedono il permesso della sezione (`Permessi::AMMINISTRAZIONE`) e il
nonce della schermata. Senza l'uno o l'altro nessuna pagina si legge.

## 6. Modi di guasto (passo 1)

| Guasto | Comportamento |
|---|---|
| Lettura delle pagine o degli allegati fallita | "Il rapporto non si può fare: la lettura delle pagine non è riuscita.", mai "nessuna pagina" |
| Pagina d'ingresso non pubblicata o inesistente | il rapporto non parte e lo dice |
| Albero in vigore non caricato | il rapporto non parte e lo dice |
| File illeggibile mentre si calcola l'impronta | stato "mancante" per quel file, il resto prosegue |

## 7. Cosa deliberatamente NON fa (passo 1)

- Non scrive niente e non prepara niente: il passaggio vero è il passo 2.
- Non scarica file da altri siti, nemmeno dal dominio del sito scritto per intero: legge solo dal
  disco.
- Non legge il contenuto dei file: dimensione e impronta bastano a riconoscere un doppione.
- Non interpreta i codici brevi dei costruttori di pagine: cerca solo gli indirizzi. Un
  indirizzo scritto in forma codificata (per esempio in base64) non si trova, e un file collegato
  così non compare: per questo c'è anche l'elenco degli allegati della pagina.

## Dubbi annotati

- La data di caricamento come limite: un file caricato in anticipo rispetto all'obbligo, caso
  raro, verrebbe dato per finito prima del tempo. Si accetta, perché l'errore nell'altro verso
  rimetterebbe in pubblico dati personali, e il passo 2 lascia comunque all'addetto la scelta di
  pubblicare con una decorrenza scritta.

## Rotture volute, a lavoro finito

Da compilare.
