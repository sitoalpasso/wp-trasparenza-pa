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
   cui è stato caricato, se è oltre il termine ordinario di pubblicazione, da verificare. È la
   lista da mostrare
   all'ente prima di portare dentro qualunque cosa.
2. **Il passaggio vero.** Da una tabella "pagina vecchia, voce nuova" confermata da chi
   amministra, per ogni pagina un contenuto in bozza nella voce scelta, con i file copiati nella
   cartella protetta del componente comune. Nessun contenuto esce pubblicato da solo: la data
   da cui decorre l'obbligo la scrive un addetto, e un file oltre il termine ordinario non entra
   fra i documenti pubblicabili senza la scelta esplicita di un addetto, che verifica se l'atto
   produce ancora effetti.
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
| 1 | `tests/ImportazionePagineTest.php` | Nuovo. Righe T-121..T-128 |
| 2a | `src/class-importazione-pagine.php` | Le pagine già portate nel rapporto; `porta()`, che crea i contenuti in bozza |
| 2a | `src/class-schermata-importazione.php` | Il modulo "pagina vecchia, voce nuova" sotto il rapporto, la risposta e l'esito |
| 2a | `tests/ImportazioneBozzeTest.php` | Nuovo. Righe T-129..T-133 |
| tutti | `docs/` | Collaudo, architettura, dati |

## 2. Requisiti serviti

Nessun requisito nuovo. Il passo 1 prepara il passaggio e ne mostra i rischi; i requisiti
protetti sono TRASP-09 e TRASP-19, e TRASP-04 per la corrispondenza con l'albero.

## 2-bis. Norme e fonti

| Regola del rapporto | Norma | Da dove viene, e se è verificata |
|---|---|---|
| Fine della pubblicazione al 31 dicembre dell'anno di decorrenza più la durata | d.lgs. 33/2013, art. 8, c. 3 | Dal file dell'albero in vigore, che porta la durata con la sua fonte (`fonte_durata`), già usata per TRASP-08 nella scheda `fine-pubblicazione.md`. Il testo della norma non è stato riletto per questo passo |
| Gli atti che producono ancora effetti restano oltre i cinque anni ("e comunque fino a che gli atti pubblicati producono i loro effetti"), salvi i termini degli artt. 14, c. 2, e 15, c. 4 | d.lgs. 33/2013, art. 8, c. 3 | Il caso è già trattato nelle schede `effetti-oltre-cinque-anni.md` e `cessazione-per-voce.md`. Le parole citate fra virgolette vengono dalla revisione della PR, a memoria, **non verificate su Normattiva** e non rilette per questo passo. Per questo il rapporto non dice mai "finita": dice "oltre il termine ordinario", da verificare |
| Un documento scaduto non torna nella sezione | d.lgs. 33/2013, art. 8 (TRASP-09) | `docs/requisiti.md` di questo repository |
| Minimizzazione dei dati personali nel passaggio | Regolamento (UE) 2016/679, art. 5 (TRASP-16) | `docs/requisiti.md` di questo repository. Il principio guida la scelta prudente sulla data, non c'è un obbligo che la prescriva così |
| Ogni cosa portata dentro lascia traccia (passo 2) | d.lgs. 33/2013, art. 6 (TRASP-19) | `docs/requisiti.md` di questo repository |

Il resto delle regole (quali pagine, come si propone la voce, quali file, i tre stati) è una
scelta di costruzione, non viene da una norma.

## 3. Le regole del rapporto (passo 1)

**Quali pagine.** La pagina d'ingresso, che spesso ha testo e file suoi, e le pagine pubblicate
che ne discendono, a qualunque profondità, ordinate come nell'albero delle pagine (ordine del
menu, poi titolo, poi identificativo). Una pagina non pubblicata (bozza, privata, in attesa,
programmata) o protetta da password non si legge, e sotto di lei non si legge niente, nemmeno le
pagine pubblicate: il rapporto le **conta** ("N pagine non pubblicate o protette da password, o
sotto di loro, non lette"), così chi prepara il passaggio sa che non le copre. Di queste pagine
si leggono solo identificativo e genitore, mai titolo o testo. Il rapporto mostra solo ciò che è
già pubblico, quindi non rivela nulla a chi ha il permesso della sezione e non quello delle
pagine: una pagina protetta da password è pubblicata, ma il suo testo no. La pagina d'ingresso
deve essere una pagina pubblicata senza password; un genitore che, risalendo, riporta alla pagina stessa non fa girare il rapporto
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

- gli indirizzi scritti nel testo della pagina, in un attributo o in un codice breve. Prima si
  prende l'indirizzo intero, che comincia all'inizio del testo o dopo uno spazio, un apice, un
  uguale, una parentesi o un `>`; poi si decide: con un dominio, solo se è quello della cartella
  dei caricamenti del sito; con o senza dominio, solo se il percorso **comincia** con quello della
  cartella. Un indirizzo che contiene quel percorso più avanti, per esempio un altro sito
  WordPress installato in una sottocartella, non è un file. La parte dopo `?` o `#` non conta;
- gli allegati della libreria dei media che hanno la pagina come genitore.

Per ogni file: il percorso relativo alla cartella dei caricamenti, l'allegato della libreria che
lo registra se c'è, la data, e uno di tre stati: **presente** (con dimensione in byte e impronta
SHA-256), **mancante** (il percorso non porta a un file), **fuori** (il percorso, risolti i
`..` e i collegamenti simbolici, esce dalla cartella dei caricamenti: non si apre e non se ne
dice altro).

**La data e il termine ordinario.** La data di un file è quella di caricamento
dell'allegato che lo registra; senza allegato, l'anno e il mese della cartella `AAAA/MM` in cui
sta; altrimenti nessuna. Un file è **oltre il termine ordinario di pubblicazione** quando l'anno della
data più la durata dell'albero in vigore è minore dell'anno di oggi, nel fuso del sito. Il
ragionamento: l'obbligo di pubblicare nasce prima che il file venga caricato, quindi la sua fine,
il 31 dicembre dell'anno di decorrenza più la durata, non può cadere dopo il 31 dicembre
dell'anno di caricamento più la durata. È un limite prudente nella direzione dei dati personali:
non dice "finita": il termine ordinario non vale per gli atti che producono ancora effetti (art. 8,
c. 3) né per le durate degli artt. 14 e 15 legate alla cessazione, quindi accanto a ogni file e
nel riepilogo il rapporto scrive "da verificare". Nel codice la funzione si chiama `finita()`. Senza data, o con un
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
| Pagine | lette con un'interrogazione: identificativo, genitore, titolo, testo, ordine, solo pagine pubblicate senza password. Una lettura fallita ferma il rapporto |
| Pagine non pubblicate o protette da password | lette con un'interrogazione a parte: solo identificativo e genitore, fuori dal cestino. Una lettura fallita ferma il rapporto |
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

## 7-bis. Il passaggio in bozza (passo 2a)

Il passo 2 si divide in due pull request: 2a porta le pagine come contenuti in bozza, 2b ci
copia i file.

**Il modulo.** Sotto il rapporto compare la tabella "pagina vecchia, voce nuova": per ogni pagina
del rapporto un elenco con le voci senza sottovoci, già posizionato sulla voce proposta quando
c'è, e la scelta "non portare", che è quella di partenza per le altre. Una pagina già portata non
ha elenco: mostra il contenuto che l'ha portata, con il collegamento alla sua schermata. Il
pulsante "Porta in bozza le pagine scelte" manda il modulo per POST, con il suo nonce. Il rientro
delle pagine figlie, qui e nel rapporto, è fatto di spazi non separabili e di un trattino
corto (T-134).

**Cosa succede.** Per ogni pagina con una voce scelta nasce un contenuto della sezione:

- in **bozza**, mai pubblicato da questo passo;
- con il **titolo** della pagina e nessun testo: il testo dei costruttori di pagine è mescolato
  alla grafica e va riscritto a mano, come dice il rapporto;
  il titolo passa a `wp_insert_post()` con `wp_slash()`, perché quella funzione toglie le barre
  inverse a quello che riceve e il titolo letto dalla banca dati non ne ha di aggiunte: senza,
  "Documenti A\B" diventerebbe "Documenti AB" (T-134). I due metadati non ne hanno bisogno: la
  pagina d'origine è un numero e la voce è un identificativo dell'albero, che accetta solo
  lettere, cifre e trattino basso;
- con la **voce** scelta e il ricordo della **pagina d'origine** (metadato
  `_trasparenza_pa_pagina_origine`);
- **senza decorrenza**: la data da cui decorre l'obbligo la scrive un addetto nel riquadro
  "Pubblicazione". Senza, il contenuto non compare al pubblico nemmeno se qualcuno lo pubblica;
- con chi ha mandato il modulo come autore.

**Le regole di controllo.** La richiesta non si prende per buona. Il rapporto si rifà al momento,
con le stesse regole del passo 1, e si portano solo pagine che ci sono, con voci fra quelle senza
sottovoci dell'albero in vigore. Una pagina fuori dal rapporto (bozza, protetta da password, fuori
dalla pagina d'ingresso, inesistente) o una voce non ammessa scartano quella riga e lo dicono;
le altre righe proseguono. Una pagina già portata non si porta una seconda volta: conta ogni
contenuto che la ricorda come origine, cestino compreso, perché un doppione nella sezione è
peggio di un passaggio da rifare a mano.

**Registro e ordine delle scritture.** Prima il contenuto in bozza, che non si vede; poi la voce
di registro `importazione_pagina` con la pagina d'origine e la voce; poi i due metadati, riletti.
Se il registro rifiuta, il contenuto appena creato si cancella e la riga dice perché: un
contenuto portato senza traccia nel registro non resta. Tutto il passaggio tiene il blocco della
sezione, così due passaggi insieme non portano due volte la stessa pagina.

**Permessi.** Il permesso della sezione, più quello di creare contenuti della sezione: chi può
amministrare la sezione ma non scrivere contenuti vede il rapporto e non il modulo, e la risposta
lo rifiuta.

**Modi di guasto (passo 2a).**

| Guasto | Comportamento |
|---|---|
| Lettura delle pagine già portate fallita | niente portato: "non si sa quali pagine sono già state portate" |
| Lettura delle pagine fallita | niente portato, lo stesso messaggio del rapporto |
| Blocco della sezione occupato | niente portato, riprovare fra qualche secondo |
| Creazione del contenuto fallita | quella riga non portata, le altre proseguono |
| Registro che rifiuta | contenuto appena creato cancellato, quella riga non portata |
| Metadati non scritti | contenuto cancellato, quella riga non portata |

**Norme e fonti (passo 2a).**

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Ogni contenuto portato ha la sua riga di registro | d.lgs. 33/2013, art. 6 (TRASP-19) | `docs/requisiti.md` di questo repository |
| Nessuna decorrenza scritta dal passaggio: senza, il contenuto non si vede | d.lgs. 33/2013, art. 8, c. 3 (TRASP-08) | Scheda `fine-pubblicazione.md` di questo repository. Il testo della norma non è stato riletto per questo passo |
| Contenuti in bozza, scelta di pubblicare lasciata all'addetto | nessuna norma: scelta di costruzione, prudente verso TRASP-09 e TRASP-16 | `docs/requisiti.md` |

## Com'è andata (passo 2a)

Costruito come scritto sopra. Il nome vero del metadato è `_trasparenza_pa_pagina_origine`, con il
trattino basso davanti come gli altri metadati della sezione, così WordPress non lo mostra fra i
campi personalizzati. Una chiave del modulo con uno zero davanti (`0123`) non vale come la pagina
123: è scartata, perché due chiavi diverse per la stessa pagina porterebbero due contenuti.

Suite completa in locale: 346 prove verdi, più le due suite separate; PHPCS e T-32 puliti.
GitHub Actions è fermo per la fatturazione, quindi la verifica in CI manca.

**Rotture volute (passo 2a)**, una per volta, con le prove di `ImportazioneBozzeTest`. Tutte
rosse, salvo un'equivalenza dichiarata sotto.

| Rottura | Prova rossa |
|---|---|
| Passaggio senza il permesso della sezione | T-129 |
| Passaggio senza il permesso di creare contenuti | T-129 |
| Nonce del rapporto accettato per il passaggio | T-129 |
| Nonce non controllato | T-129 |
| Pagina d'ingresso non controllata nella forma | T-129 |
| Voci non controllate come elenco | T-129 |
| Chiave della pagina presa con `(int)` | T-129 |
| Pagina fuori dal rapporto accettata | T-129 |
| Voce non ammessa accettata | T-129 |
| Blocco della sezione non preso | T-132 |
| Pagina già portata riportata | T-132 |
| Contenuti nel cestino non contati come portati | T-132 |
| Lettura delle pagine già portate fallita non fermata | T-132 |
| Contenuto pubblicato invece che in bozza | T-130 |
| Testo nel contenuto | T-130 |
| Autore fisso | T-130 |
| Registro che rifiuta: contenuto lasciato | T-131 |
| Registro che rifiuta ignorato | T-131 |
| Pagina d'origine non scritta | T-130, T-132 |
| Voce non scritta | T-130 |
| Metadati non riletti | T-131 |
| Modulo mostrato a chi non può creare contenuti | T-133 |
| Voce proposta non preselezionata | T-133 |
| Titolo senza escaping nel modulo | T-133 |
| Esito mostrato più di una volta | T-133 |
| Pagina già portata con la sua scelta | T-133 |

Equivalenza: togliere il controllo "la voce è un testo" e confrontare le voci in modo non stretto
non fa diventare rossa nessuna prova. Un valore che arriva da un modulo è un testo o un elenco, e
un elenco non è mai uguale a una voce nemmeno nel confronto non stretto. Il controllo resta come
difesa esplicita.

## Dubbi annotati

- La data di caricamento come limite: un file caricato in anticipo rispetto all'obbligo, caso
  raro, verrebbe dato per finito prima del tempo. Si accetta, perché l'errore nell'altro verso
  rimetterebbe in pubblico dati personali, e il passo 2 lascia comunque all'addetto la scelta di
  pubblicare con una decorrenza scritta.

## Com'è andata (passo 1)

Costruito come scritto sopra. Due aggiunte venute dal lavoro:

- la data mostrata in "Calcolato al" è lo stesso giorno da cui si contano le fini, preso con
  `RegistroAccessi::oggi()` nel fuso del sito: la prova lo guarda in un fuso lontano;
- le interrogazioni portano il segno `trasparenza-pa-importazione` in un commento SQL, così la
  prova del guasto rompe solo quelle.

Suite completa in locale: 340 prove verdi, più le due suite separate; PHPCS e T-32 puliti.
GitHub Actions è fermo per la fatturazione, quindi la verifica in CI manca.

## Rotture volute, a lavoro finito

Ogni rottura è stata applicata da sola, con le prove di `ImportazionePagineTest`. Tutte rosse.

| Rottura | Prova rossa |
|---|---|
| Schermata senza controllo del permesso | T-121 |
| Schermata senza controllo del nonce | T-121 |
| Pagina d'ingresso controllata senza `\A` e `\z` | T-121 |
| Pagina d'ingresso non pubblicata accettata | T-121 |
| Pagine non pubblicate al posto delle pubblicate | T-122 |
| Figli dopo le pagine già in coda, non subito dopo il genitore | T-122 |
| Confronto con gli accenti | T-122 |
| Confronto con la punteggiatura | T-122 |
| Più voci con lo stesso nome trattate come una | T-122 |
| Voce con sottovoci proposta | T-122 |
| Radice dell'albero fra le voci confrontabili | T-122 |
| Allegati della pagina ignorati | T-123 |
| Lo stesso file due volte | T-123 |
| Indirizzo non decodificato | T-123 |
| Segmento `..` non rifiutato | T-123 |
| Percorso risolto fuori dalla cartella aperto | T-123 |
| Fine all'orlo dell'anno contata come oltre il termine | T-124 |
| Data della cartella preferita a quella dell'allegato | T-124 |
| Data della cartella ignorata | T-124 |
| Durata fissa al posto di quella dell'albero | T-124 |
| Oggi in UTC al posto del fuso del sito | T-124 |
| Collegamenti al sito stesso fra gli esterni | T-125 |
| Collegamenti esterni ripetuti | T-125 |
| Punto finale lasciato nel collegamento | T-125 |
| Testo fra parentesi quadre contato | T-125 |
| Entità contate come più caratteri | T-125 |
| Pagina con soli collegamenti esterni data per vuota | T-125 |
| Voci proposte fra quelle senza pagina | T-125 |
| Lettura delle pagine fallita non fermata | T-126 |
| Lettura delle pagine non pubblicate fallita non fermata | T-126 |
| Pagine non pubblicate non contate | T-122 |
| Pagine pubblicate sotto una non pubblicata non contate | T-122 |
| Etichetta "finita di sicuro" al posto di "oltre il termine ordinario" | T-124 |
| Lettura degli allegati fallita non fermata | T-126 |
| Titolo senza escaping | T-121 |
| Pagine protette da password lette | T-127 |
| Pagine protette da password non contate fra le non lette | T-127 |
| Dominio di un indirizzo intero non controllato | T-123, T-128 |
| Percorso della cartella cercato in un punto qualunque dell'indirizzo | T-128 |
| Indirizzo preso a metà di una parola | T-128 |
| Parte dopo `?` o `#` lasciata nel percorso | T-123 |

Una regola senza rottura: il segno delle pagine già visitate, che impedisce di girare in tondo
fra genitori. Toglierlo non fa diventare rossa la prova: la fa girare per sempre. La prova T-122
che lo copre finisce solo con il segno al suo posto.
