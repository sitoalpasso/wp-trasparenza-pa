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
| 2b | `src/class-importazione-pagine.php` | `copia()`, che copia i file scelti nei contenuti in bozza; nel rapporto, i file che il componente comune protegge già |
| 2b | `src/class-scheda-documenti.php` | Il deposito diviso in due: la parte che prende il blocco e quella che deposita, che `copia()` chiama tenendo già il blocco |
| 2b | `src/class-schermata-importazione.php` | Il modulo "file da copiare nelle bozze", la risposta e l'esito |
| 2b | `tests/ImportazioneFileTest.php` | Nuovo. Righe T-135..T-139 |
| 3 | `src/class-vecchi-indirizzi.php` | Nuovo. La pagina della sezione scelta, gli indirizzi registrati, il rinvio dalla pagina vecchia alla voce |
| 3 | `src/class-schermata-importazione.php` | Il modulo "vecchi indirizzi", la risposta e l'esito |
| 3 | `trasparenza-pa.php` | Caricamento e agganci |
| 3 | `tests/VecchiIndirizziTest.php` | Nuovo. Righe T-143..T-147 |
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

## 7-ter. I file nelle bozze (passo 2b)

**In parole semplici.** Dopo il passo 2a ogni pagina portata ha il suo contenuto in bozza, ancora
senza documenti. Il passo 2b copia i file della pagina vecchia fra i documenti di quel contenuto,
nella cartella protetta del componente comune, la stessa in cui finisce un file caricato a mano
dal riquadro "Documenti". Il file originale resta dov'è: la pagina vecchia continua a funzionare
finché il passaggio non è finito. Un file oltre il termine ordinario di pubblicazione, o di cui
non si conosce la data, non si copia senza una conferma a parte di chi fa il passaggio.

**Il modulo.** Sotto il modulo del passo 2a compare "File da copiare nelle bozze". Ci sono solo
le pagine già portate il cui contenuto è in bozza o in attesa di revisione; per ciascuna, i file
del rapporto:

- un file presente, entro il termine ordinario: casella **già spuntata**;
- un file presente, oltre il termine ordinario o senza data: casella **non spuntata**, in un campo
  diverso (`copia_verificata`), con accanto "copiare solo dopo aver verificato che l'atto produce
  ancora effetti, o che vale una durata degli artt. 14 e 15";
- un file già fra i documenti del contenuto (stessa impronta SHA-256): "già copiato", nessuna
  casella;
- un file mancante, fuori dalla cartella dei caricamenti, o registrato da un allegato che il
  componente comune protegge già: il motivo, nessuna casella.

Il pulsante "Copia i file scelti" manda il modulo per POST, con il suo nonce.

**Le regole di controllo.** La richiesta non si prende per buona: con il blocco della sezione
preso, il rapporto si rifà al momento con le regole del passo 1, e per ogni file chiesto:

1. la pagina deve essere nel rapporto e già portata, altrimenti la riga è scartata;
2. il contenuto che l'ha portata deve essere in bozza o in attesa di revisione, riletto dalla
   banca dati: un contenuto pubblicato, programmato, privato o nel cestino non riceve file da qui,
   perché un documento aggiunto a un contenuto pubblicato è subito pubblico;
3. il percorso deve essere, carattere per carattere, uno dei file di quella pagina nel rapporto
   appena rifatto: nessun percorso arriva dal modulo al disco senza essere passato dalle regole del
   rapporto, quindi un percorso che esce dalla cartella dei caricamenti non si apre;
4. il file deve essere presente e non protetto dal componente comune;
5. un file oltre il termine ordinario o senza data si copia solo se arriva nel campo
   `copia_verificata`; nel campo normale è scartato e la riga dice perché;
6. un file con la stessa impronta di un documento del contenuto, o di un file già copiato nella
   stessa richiesta, è "già copiato": nessun doppione, e ripetere il passaggio dopo
   un'interruzione copia solo quello che manca.

**Come si copia.** Il file si copia in un file temporaneo, e il temporaneo passa al deposito del
componente comune con l'origine "percorso locale": il deposito sposta il file che riceve, e senza
la copia sposterebbe l'originale. Il deposito è lo stesso del riquadro "Documenti": tipo di file
ammesso, protezione della cartella verificata, voce di registro `documento_contenuto` con nome e
impronta, allegato tolto se il registro rifiuta. Per i file portati dalle pagine la voce di registro
porta anche la pagina d'origine e il percorso. Il temporaneo si cancella sempre, anche quando il
deposito rifiuta. Il blocco della sezione resta preso per tutto il passaggio: il deposito ha una
parte che lo prende e una che lavora dentro un blocco già preso, perché prendere due volte lo stesso
blocco non è sicuro su tutte le versioni della banca dati.

**File protetti dal componente comune.** Una pagina può collegare un file che il componente comune
ha già depositato per un altro contenuto, per esempio un atto dell'albo. Copiarlo farebbe nascere
un secondo documento con una scadenza diversa: il rapporto lo segna come "già protetto" e il
passaggio non lo copia. Il confronto si fa sul percorso reale, non su come è scritto nella pagina:
`cartella/./file.pdf` o `cartella//file.pdf` portano allo stesso file, e allo stesso allegato (T-140).
In più, un file il cui percorso reale sta dentro la cartella protetta del componente comune è
"già protetto" anche se nessun allegato lo registra.

**Valori del modulo.** Il percorso di ogni casella si scrive con `Testo::esatto()`, che ricodifica
anche le entità già scritte: un nome di file con `&amp;` letterale torna dal browser identico, e
non viene scartato (T-141).

**Permessi.** Gli stessi del passo 2a, più il permesso di modificare il contenuto che riceve il
file, che il deposito controlla.

**Modi di guasto (passo 2b).**

| Guasto | Comportamento |
|---|---|
| Lettura delle pagine o delle pagine già portate fallita | niente copiato, lo stesso messaggio del rapporto |
| Blocco della sezione occupato | niente copiato, riprovare fra qualche secondo |
| Stato del contenuto o documenti del contenuto non leggibili | file di quella pagina non copiati, le altre proseguono |
| Copia nel temporaneo fallita | quel file non copiato |
| Deposito rifiutato (tipo non ammesso, protezione non verificata) | quel file non copiato, con il motivo del componente comune; temporaneo cancellato |
| Registro che rifiuta | come nel riquadro "Documenti": allegato tolto, quel file non copiato |
| La cartella protetta del componente comune non si può chiedere | niente copiato: senza la cartella non si sa quali file sono già protetti, e nel dubbio si chiude (T-142) |

**Cosa deliberatamente non fa (passo 2b).**

- Non copia a lotti: tutti i file scelti in una richiesta. Se il server interrompe una richiesta
  troppo lunga, la si ripete e si copiano solo i file mancanti.
- Non confronta l'impronta del temporaneo con quella del rapporto: il rapporto si rifà nello stesso
  blocco, un istante prima, e l'impronta del file depositato resta nel registro.
- Non tocca la pagina vecchia né il file originale.

**Norme e fonti (passo 2b).**

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Ogni file copiato ha la sua riga di registro, con pagina d'origine e impronta | d.lgs. 33/2013, art. 6 (TRASP-19) | `docs/requisiti.md` di questo repository |
| Un file oltre il termine ordinario non si copia senza conferma | d.lgs. 33/2013, art. 8, c. 3 (TRASP-08, TRASP-09) | `docs/requisiti.md` e §2-bis di questa scheda; le parole sugli effetti restano citate a memoria, non verificate su Normattiva |
| Un file senza data trattato come oltre il termine | Regolamento (UE) 2016/679, art. 5 (TRASP-16) | `docs/requisiti.md`. Scelta prudente, non prescritta così dalla norma |
| File serviti solo dalla cartella protetta del componente comune | regola di costruzione del progetto (consegna degli allegati) | istruzioni di sviluppo del repository; nessuna norma la prescrive in questa forma |
| Nessun file aggiunto da qui a un contenuto pubblicato | nessuna norma: scelta di costruzione, prudente verso TRASP-09 | `docs/requisiti.md` |

## 7-quater. I vecchi indirizzi (passo 3)

**In parole semplici.** Le pagine vecchie hanno indirizzi già in giro: nei collegamenti di altri
siti, nei motori di ricerca, nei segnalibri. Quando l'ente toglie una pagina vecchia, chi apre il
suo indirizzo trova "pagina non trovata". Con questo passo, chi fa il passaggio registra
l'indirizzo di ogni pagina portata, e da quando la pagina vecchia non risponde più il suo indirizzo
porta alla voce nuova del contenuto, nella pagina della sezione. Finché la pagina vecchia è
pubblicata non cambia niente: il rinvio scatta solo al posto di un "pagina non trovata".

**La pagina della sezione.** È la pagina pubblicata che contiene il codice breve della sezione. Il
modulo la fa scegliere fra le pagine pubblicate che lo contengono, e la scelta si conserva
(opzione `trasparenza_pa_pagina_sezione`). Alla lettura vale solo se è ancora una pagina pubblicata
con il codice breve; altrimenti nessun rinvio, e la schermata lo dice.

**Il modulo.** Sotto quello dei file compare "Vecchi indirizzi":

- la scelta della pagina della sezione;
- per ogni pagina del rapporto già portata, con il contenuto fuori dal cestino, il suo indirizzo e
  la voce di destinazione, con una casella **già spuntata** per registrarlo; la pagina della
  sezione stessa non ha casella, perché un rinvio verso sé stessa girerebbe in tondo;
- gli indirizzi già registrati, anche di pagine non più pubblicate, ciascuno con la casella
  "togli".

Il pulsante "Registra i vecchi indirizzi" manda il modulo per POST, con il suo nonce.

**Le regole di controllo.** Con il blocco della sezione preso:

1. la pagina della sezione scelta deve essere una pagina pubblicata con il codice breve;
2. ogni indirizzo da registrare si ricava dal rapporto rifatto al momento, non dal modulo: il
   modulo dice solo quale pagina. La pagina deve essere nel rapporto, già portata, con il contenuto
   fuori dal cestino, e diversa dalla pagina della sezione;
3. l'indirizzo è il percorso della pagina rispetto all'indirizzo del sito, senza barre all'inizio e
   alla fine, in minuscolo. Si conserva sul contenuto (metadato `_trasparenza_pa_indirizzo_origine`),
   una volta: un contenuto che lo ha già non ne prende un secondo;
4. due contenuti non possono avere lo stesso indirizzo: il secondo è scartato;
5. togliere un indirizzo vale solo per un contenuto della sezione che lo ha.

Ogni registrazione e ogni rimozione ha la sua voce di registro `importazione_indirizzo`, con la
pagina d'origine e l'indirizzo. Se il registro rifiuta, il metadato non si scrive, o non si toglie.

**Il rinvio.** Su `template_redirect`, prima delle ipotesi di WordPress sugli indirizzi sbagliati,
e solo quando la richiesta finisce in "pagina non trovata":

1. il percorso chiesto, senza parametri e senza la parte dell'indirizzo del sito, in minuscolo, si
   cerca fra gli indirizzi registrati, sui contenuti della sezione fuori dal cestino. Il percorso
   resta com'è arrivato, caratteri codificati (`%xx`) compresi, perché così è scritto nel permalink
   registrato; il minuscolo rende uguali anche `%C3%A0` e `%c3%a0`. Non passa da una funzione di
   pulizia del testo, che toglierebbe le sequenze `%xx`: va solo come parametro di
   un'interrogazione preparata, e non si stampa mai (T-145);
2. la destinazione è la voce del contenuto **adesso**, non quella del momento del passaggio: se un
   addetto sposta il contenuto, il rinvio lo segue. Una voce che non c'è più nell'albero porta alla
   pagina della sezione;
3. il rinvio è permanente (301), perché la pagina vecchia non tornerà e i motori di ricerca devono
   aggiornare il loro indice;
4. nessun rinvio se la pagina della sezione non vale, se la lettura fallisce, o se la destinazione
   coincide con l'indirizzo chiesto.

Il rinvio porta alla voce e mai al contenuto o ai suoi file: un contenuto scaduto non torna
raggiungibile da un indirizzo vecchio (TRASP-09).

**Permessi.** Gli stessi del passo 2a. Il rinvio è pubblico e non legge niente che non sia già
pubblico: la voce di un contenuto è visibile nella pagina della sezione.

**Modi di guasto (passo 3).**

| Guasto | Comportamento |
|---|---|
| Lettura delle pagine o degli indirizzi registrati fallita, nel passaggio | niente registrato, il motivo |
| Blocco della sezione occupato | niente registrato, riprovare fra qualche secondo |
| Registro che rifiuta | quell'indirizzo non registrato, o non tolto |
| Lettura degli indirizzi fallita, nel rinvio | nessun rinvio: resta "pagina non trovata" |
| Pagina della sezione tolta o senza codice breve | nessun rinvio; la schermata lo dice |

**Cosa deliberatamente non fa (passo 3).**

- Non toglie, non mette in bozza e non cambia le pagine vecchie: lo decide l'ente, quando il
  contenuto nuovo è pronto.
- Non rinvia gli indirizzi dei file: il file nuovo si consegna dal componente comune con un indirizzo
  suo, e un file vecchio scaduto non deve tornare raggiungibile.
- Non registra indirizzi scritti a mano: solo quelli delle pagine del rapporto.
- Non può richiamare un rinvio già seguito: i browser conservano un rinvio permanente, quindi chi
  lo ha già seguito continua ad andare alla vecchia destinazione anche dopo un "togli" o uno
  spostamento di voce, finché non svuota la memoria del browser. È il prezzo del 301; con un rinvio
  temporaneo (302) non ci sarebbe, ma i motori di ricerca terrebbero l'indirizzo vecchio.

**Norme e fonti (passo 3).**

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| Ogni indirizzo registrato o tolto ha la sua riga di registro | d.lgs. 33/2013, art. 6 (TRASP-19) | `docs/requisiti.md` di questo repository |
| Il rinvio porta alla voce, mai al contenuto o ai file | d.lgs. 33/2013, art. 8 (TRASP-09) | `docs/requisiti.md` |
| Rinvio permanente, perché gli indici dei motori di ricerca seguano la sezione | d.lgs. 33/2013, art. 9 (TRASP-03, indicizzazione della sezione) | `docs/requisiti.md`. Il legame è indiretto: la norma vieta di impedire l'indicizzazione, non prescrive i rinvii. È una scelta di costruzione |
| Il rinvio scatta solo al posto di "pagina non trovata" | nessuna norma: scelta di costruzione, per non nascondere una pagina che l'ente tiene pubblicata | |

## Com'è andata (passo 3)

Costruito come scritto sopra. Tre cose venute dal lavoro:

- il confronto dell'indirizzo nella banca dati di solito non distingue le maiuscole; il minuscolo
  resta nel codice, perché il controllo dei doppioni avviene in PHP e perché non tutte le
  installazioni usano la stessa collazione, e una prova lo verifica direttamente su `percorso()`;
- un indirizzo registrato resta fra i registrati anche quando il contenuto va nel cestino: la
  schermata lo segna "nessun rinvio", e il doppione si controlla contro tutti, così un contenuto
  rimesso dal cestino non trova il suo indirizzo preso da un altro;
- la scelta della pagina della sezione non ha una voce di registro sua: è un'impostazione, non un
  cambio di un contenuto, e ogni registrazione che ne dipende ha la sua voce.

Suite completa in locale: 361 prove verdi, più le due suite separate; PHPCS e T-32 puliti.
GitHub Actions è fermo per la fatturazione, quindi la verifica in CI manca.

**Rotture volute (passo 3)**, una per volta, con le prove di `VecchiIndirizziTest`. Tutte rosse, salvo
l'equivalenza dichiarata sotto. Tre erano verdi alla prima prova e hanno avuto la loro prova:
percorso non in minuscolo, lettura fallita degli indirizzi registrati, motivo della pagina non
portata.

| Rottura | Prova rossa |
|---|---|
| Rinvio anche senza "pagina non trovata" | T-145 |
| Percorso non in minuscolo | T-145 |
| Barra finale non tolta | T-145 |
| Contenuto nel cestino che rinvia | T-145 |
| Voce che non c'è più usata come destinazione | T-145 |
| Destinazione uguale all'indirizzo chiesto | T-145 |
| Pagina della sezione non controllata alla lettura | T-145 |
| Pagina della sezione senza codice breve accettata | T-143, T-145 |
| Pagina della sezione in bozza accettata | T-143 |
| Articolo accettato come pagina della sezione | T-143 |
| Aggancio dopo le ipotesi di WordPress | T-146 |
| Blocco della sezione non preso | T-144 |
| Lettura fallita degli indirizzi registrati non fermata | T-144 |
| Identificativo preso con `(int)` | T-143 |
| Pagina non portata accettata | T-143 |
| Pagina della sezione che registra sé stessa | T-143 |
| Contenuto nel cestino che registra | T-143 |
| Secondo indirizzo sullo stesso contenuto | T-144 |
| Stesso indirizzo su due contenuti | T-144 |
| Registro che rifiuta ignorato nella registrazione | T-144 |
| Registro che rifiuta ignorato nella rimozione | T-144 |
| Rimozione da un contenuto senza indirizzo | T-144 |
| Pagina d'origine non scritta nel registro | T-144 |
| Pagina della sezione non conservata | T-143 |
| Modulo senza permesso della sezione o di creare contenuti | T-143 |
| Nonce di un'altra azione accettato | T-143 |
| Campo "togli" non controllato come elenco | T-143 |
| Pagina della sezione non controllata nella forma | T-143 |
| Pagina già registrata con la sua casella | T-147 |
| Titolo senza escaping nel modulo | T-147 |
| Pagina della sezione non valida senza avviso | T-147 |
| Pagina della sezione non preselezionata | T-147 |
| Pagine senza codice breve fra le scelte | T-147 |
| Modulo a chi non può creare contenuti | T-147 |
| Modulo senza aggancio | T-147 |

Equivalenza: togliere, nel rinvio, il controllo dell'errore di lettura non fa diventare rossa
nessuna prova, perché una lettura fallita restituisce comunque "nessun contenuto" e quindi nessun
rinvio. Il controllo resta come difesa esplicita.

## Com'è andata (passo 2b)

Costruito come scritto sopra. Due aggiunte venute dal lavoro:

- nel rapporto ogni file porta ora anche `protetto`, vero quando l'allegato che lo registra è
  depositato dal componente comune; il modulo e il passaggio lo leggono da lì;
- la copia è possibile anche in un contenuto in attesa di revisione, non solo in bozza: nessuno
  dei due è pubblico.

Due rotture restavano verdi alla prima prova e hanno avuto la loro prova: dettagli aggiunti che
sostituiscono quelli di base nella voce di registro (T-136), e il modulo dei file senza il suo
aggancio (T-139).

Dopo la rilettura si sono aggiunti T-140 e T-141. Il primo: un percorso scritto con `./` o con una
barra doppia non veniva riconosciuto come l'allegato protetto a cui porta; ora il confronto con la
cartella protetta del componente comune si fa sul percorso reale, e copre anche un file di quella
cartella che nessun allegato registra. Il secondo: il valore delle caselle si scrive con
`Testo::esatto()`. Il confronto per percorso con l'allegato resta; non si è aggiunta una tabella dei
percorsi reali di tutti gli allegati del sito, perché ogni file depositato dal componente comune sta
nella sua cartella e la verifica sulla cartella lo copre già, senza una lettura del disco per
allegato.

Poi T-142: se il componente comune non dice dov'è la sua cartella protetta, il passaggio dei file
si ferma invece di trattare la cartella come vuota.

Suite completa in locale: 355 prove verdi, più le due suite separate; PHPCS e T-32 puliti.
GitHub Actions è fermo per la fatturazione, quindi la verifica in CI manca.

**Rotture volute (passo 2b)**, una per volta, con le prove di `ImportazioneFileTest`. Tutte rosse,
salvo l'equivalenza dichiarata sotto.

| Rottura | Prova rossa |
|---|---|
| Blocco della sezione non preso | T-138 |
| Chiave della pagina presa con `(int)` | T-135 |
| Pagina non portata accettata | T-135 |
| Stato del contenuto non controllato | T-135 |
| Contenuto in attesa di revisione escluso | T-135 |
| Percorso non confrontato con i file del rapporto | T-135 |
| File mancante o fuori accettato | T-135 |
| File già protetto copiato | T-135 |
| File senza data copiato senza conferma | T-137 |
| File oltre il termine copiato senza conferma | T-137 |
| Doppioni con i documenti del contenuto | T-138 |
| Doppioni nella stessa richiesta | T-138 |
| Originale spostato invece che copiato | T-136 |
| Temporaneo non cancellato | T-136, T-138 |
| Pagina d'origine non scritta nel registro | T-136 |
| Permesso del contenuto non controllato dentro il blocco | T-135 |
| Dettagli aggiunti che sostituiscono quelli di base | T-136 |
| Modulo senza permesso della sezione o di creare contenuti | T-135 |
| Nonce di un'altra azione accettato | T-135 |
| Nonce non controllato | T-135 |
| Pagina d'ingresso non controllata nella forma | T-135 |
| Campo normale non controllato come elenco | T-135 |
| Campo di conferma non controllato come elenco | T-135 |
| Pagina con contenuto pubblicato nel modulo | T-139 |
| File senza data con la casella già spuntata | T-139 |
| File già copiato con la sua casella | T-139 |
| File mancante con la sua casella | T-139 |
| Percorso senza escaping nel modulo | T-139 |
| Modulo dei file a chi non può creare contenuti | T-139 |
| `protetto` sempre falso nel rapporto | T-135 |
| Modulo dei file senza aggancio | T-139 |
| Cartella protetta non confrontata sul percorso reale | T-140 |
| Cartella protetta non trovata | T-140 |
| Valore della casella con `esc_attr()` | T-141 |
| Cartella protetta non disponibile trattata come vuota | T-142 |
| Passaggio dei file senza il controllo sulla cartella protetta | T-142 |

Equivalenza: chiamare il deposito che prende il blocco, invece di quello che lavora dentro il
blocco già preso, fa diventare rossa T-136 solo perché quel deposito non porta la pagina d'origine
nel registro. Il blocco preso due volte, da solo, non si vede su questa banca dati, che tiene lo
stesso blocco più volte dalla stessa connessione; il pericolo riguarda versioni più vecchie, ed è
il motivo della divisione.

Resta senza prova anche la scelta, in `da_admin_post()`, fra la risposta delle bozze e quella dei
file: la funzione finisce con un reindirizzamento e `exit`, come per il passo 2a.

## Com'è andata (passo 2a)

Costruito come scritto sopra. Il nome vero del metadato è `_trasparenza_pa_pagina_origine`, con il
trattino basso davanti come gli altri metadati della sezione, così WordPress non lo mostra fra i
campi personalizzati. Una chiave del modulo con uno zero davanti (`0123`) non vale come la pagina
123: è scartata, perché due chiavi diverse per la stessa pagina porterebbero due contenuti.

Dopo la rilettura si è aggiunto T-134: il titolo con le barre inverse arrivava senza, e il
rientro delle pagine figlie usava il trattino lungo.

Suite completa in locale: 347 prove verdi, più le due suite separate; PHPCS e T-32 puliti.
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
| Titolo passato senza `wp_slash()` | T-134 |
| Rientro con il trattino lungo | T-134 |

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
