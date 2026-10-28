# Scheda di implementazione: contenuti delle voci, primo passo

Terza unità della fase di gestione. Scheda scritta prima del codice e completata a lavoro finito. L'unità mette dentro
ogni voce della sezione testo e documenti, con la data di ultimo aggiornamento e la traccia
di ogni modifica. Si fa in quattro richieste di unione corte, ciascuna con le sue prove:

1. **questa**: il contenuto come tipo a sé e la scelta della voce in cui sta;
2. la data da cui decorre l'obbligo e la fine della pubblicazione calcolata (art. 8, c. 3),
   senza la quale il componente comune non mostra il contenuto al pubblico;
3. i documenti allegati, depositati attraverso il componente comune;
4. la pagina pubblica: i contenuti dentro la loro voce e la data di ultimo aggiornamento
   (TRASP-10).

Questa prima richiesta serve TRASP-19 per i contenuti: chi ha creato, pubblicato, cambiato o
tolto cosa, e quando.

## In parole semplici

**Cosa costruiamo.** Nel menu "Trasparenza" compare "Contenuti": una pagina di testo, con
titolo, che il personale scrive come un articolo e colloca in una voce della sezione, per
esempio "Bandi di concorso". La voce si sceglie da un elenco preso dall'albero ufficiale, e
si possono scegliere solo le voci in fondo all'albero, quelle che non hanno sottovoci. Ogni
scelta e ogni cambio di voce finiscono nel registro delle modifiche, insieme a creazione,
pubblicazione, modifica e cestino, che il componente comune registra da sé.

**Come la costruiamo.** Il contenuto è un tipo a sé, separato dalla "voce di trasparenza"
già esistente, che serve ai dati compilati sugli schemi ANAC e resta chiusa alla
pubblicazione finché non arrivano gli stati editoriali. Un contenuto libero non ha uno
schema da rispettare: per lui gli stati editoriali di TRASP-28, che le istruzioni ANAC
legano alla conformità allo schema, non si applicano, e la pubblicazione è quella normale di
WordPress. Pubblicare però non basta per comparire: il contenuto compare nella sezione solo
quando ha una voce valida e, dal secondo passo, una fine della pubblicazione. Il salvataggio
della voce segue le regole delle altre schermate: nonce, permesso, valore controllato,
lettura dalla banca dati che distingue "assente" da "lettura fallita", stato di partenza nel
modulo per non cancellare il cambio di un altro, un salvataggio alla volta, prima il registro
e poi il dato, e il dato riletto.

**Come si prova, e come si prova che la prova è vera.** Sedici prove nuove (righe
T-59..T-62). Trenta rotture volute, una per volta e poi tolte, le fanno diventare rosse:
radice ammessa, voci con sottovoci ammesse, controllo della voce tolto, nonce non
controllato, nonce non legato al contenuto, permesso non controllato, tipo del contenuto non
controllato, conflitto non controllato, lettura con `get_post_meta()`, più voci memorizzate
rifiutate come una lettura fallita, partenza non controllata nel riordino, voci trovate non
registrate, righe vecchie cancellate prima del registro, righe vecchie lasciate, rilettura del
riordino tolta, avviso delle più voci tolto, esito legato al solo utente, esito mostrato su
ogni schermata, registro scritto dopo il dato, rilettura tolta, blocco tolto, stessa voce trattata come
cambio, partenza tolta dal riquadro, esito mostrato a ogni richiesta, tipo aperto al
pubblico, tipo esposto a REST, permessi assegnati solo alla voce, tipo registrato senza
sezione, nomi delle voci senza escaping, voce memorizzata non controllata contro l'albero.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-tipo-contenuto.php` | Nuovo. Registrazione del tipo attraverso il componente comune, chiuso al pubblico, sotto il menu "Trasparenza" |
| `src/class-scheda-contenuto.php` | Nuovo. Il riquadro "Voce della sezione", il salvataggio, l'esito mostrato una volta |
| `src/class-metadato.php` | Nuovo. Lettura di un metadato che distingue il dato assente dalla lettura fallita, come `Opzione` |
| `src/class-permessi.php` | I permessi generici valgono per i due tipi |
| `trasparenza-pa.php` | Costante del tipo, caricamento e agganci |
| `tests/ContenutoTest.php` | Righe T-59..T-62 |
| `docs/` | Collaudo, requisiti, architettura, dati |

## 2. Requisito servito

TRASP-19 (art. 6 del d.lgs. 33/2013): ogni creazione, pubblicazione, modifica e rimozione di
un contenuto è nel registro di core, e ogni scelta della voce è una voce di registro del
componente. TRASP-10 e TRASP-08 arrivano con i passi successivi; questa richiesta prepara
il dato su cui si appoggiano.

## 3. Flusso per chi usa il sito

Chi ha i permessi del contenuto apre "Trasparenza > Contenuti > Aggiungi", scrive titolo e
testo, sceglie la voce nel riquadro "Voce della sezione" e salva o pubblica. Torna sulla
stessa schermata con l'esito della scelta della voce: salvata, nessun cambio, oppure il
motivo per cui non è stata salvata. Finché il contenuto non ha una voce, il riquadro dice che
non comparirà nella sezione.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Contenuto | tipo `trasparenza_testo` (il componente comune ammette al massimo venti caratteri); titolo, testo, autore, revisioni |
| Metadato `_trasparenza_pa_collocazione` | nuovo; l'identificativo della voce dell'albero. Letto dalla banca dati prima e dopo ogni scrittura. Vale una riga sola: con più righe, che il riquadro non crea, il contenuto non ha una voce valida finché una scelta dal riquadro non le sostituisce con una |
| Registro di core | le righe automatiche del componente comune su ogni cambio del contenuto; una riga del componente per ogni cambio di voce: sezione `amministrazione_trasparente`, azione `collocazione_contenuto`, dettagli con la voce di prima e quella nuova. Utente e istante li mette core |
| Dato temporaneo `trasparenza_pa_esito_collocazione_<utente>_<contenuto>` | l'esito della scelta della voce, per un minuto; letto e cancellato solo dalla schermata di quel contenuto, così due salvataggi vicini di contenuti diversi non si confondono |
| Blocco della banca dati | lo stesso delle altre schermate, per la durata della scrittura |

## 5. Permessi

| Azione | Chi |
|---|---|
| Creare, modificare, pubblicare, cestinare un contenuto | i permessi del tipo, che il componente comune ricava dall'identificativo; li riceve l'amministratore a ogni versione, come quelli della voce |
| Scegliere la voce | chi può modificare quel contenuto, più il nonce legato al contenuto |
| Vedere il contenuto | nessun indirizzo pubblico; la pagina della sezione lo mostrerà dal quarto passo |

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Voce inesistente, radice, voce con sottovoci, valore non testuale | niente scritto, niente nel registro, messaggio |
| Nonce mancante o di un altro contenuto, utente senza permesso | niente scritto, niente nel registro |
| Albero non disponibile | niente scritto, messaggio; il riquadro lo dice |
| Lettura del metadato fallita | niente scritto, niente nel registro, messaggio |
| Più di una voce memorizzata | il riquadro lo dice e mostra "Da scegliere"; una voce scelta da lì sostituisce tutte le righe con una, dopo una voce di registro che conserva quelle trovate. Se il modulo mostrava una voce è un conflitto; se la voce nuova non risulta scritta, il messaggio dice che il contenuto non ha una voce e che il registro ha un cambio non avvenuto |
| La voce è stata cambiata da un altro dopo l'apertura del modulo | conflitto: niente scritto, messaggio che chiede di ricaricare |
| Registro che rifiuta | niente scritto, messaggio con il motivo |
| Dato non scritto dopo la voce di registro | messaggio che dice del cambio non avvenuto |
| Altro salvataggio in corso | attesa fino a cinque secondi, poi rinuncia senza scrivere |
| Contenuto pubblicato senza voce, o con una voce che una versione nuova dell'albero non ha più | non compare nella sezione; il riquadro lo dice a chi lo apre |

## 7. Test

Righe T-59..T-62 di `docs/collaudo.md`, in `tests/ContenutoTest.php`.

## 8. Cosa deliberatamente NON fa

Non calcola la fine della pubblicazione (secondo passo): finché non c'è, il componente comune
tratta il contenuto come privo di data e non lo mostra al pubblico. Non carica documenti
(terzo passo). Non mostra niente sulla pagina pubblica (quarto passo). Non sposta la versione
del componente: i permessi del tipo nuovo arrivano con la riassegnazione a ogni versione, e
il componente non è ancora stato rilasciato, quindi non esistono installazioni da
aggiornare.

## 9. Come si prova sul sito vero

1. Da amministratore, nel menu "Trasparenza" c'è "Contenuti". Da un utente con ruolo
   Redattore il menu non c'è.
2. Creo un contenuto, scelgo "Bandi di concorso" e salvo: l'esito dice che la voce è
   salvata. Nel registro delle modifiche c'è la riga della creazione e quella della voce.
3. Apro lo stesso contenuto in due schede, cambio la voce nella prima e salvo, poi la cambio
   nella seconda e salvo: la seconda dice che c'è un conflitto e la voce resta quella della
   prima.
4. Ripristino: si cestina il contenuto di prova. La riga di registro resta, ed è giusto così.

## Dubbi annotati

- La lettura di TRASP-28 qui sopra (stati editoriali solo per i dati su schema) è la più
  stretta fra quelle possibili. Se l'ente o una fonte successiva li volesse anche per i
  contenuti liberi, si aggiungono sullo stesso tipo senza cambiare il dato.
- **Voci coperte dagli schemi ANAC (art. 13, 4-bis, 31): chiuso il 28 ottobre 2026.** Le
  voci restano aperte ai contenuti liberi finché la compilazione sui moduli non è
  obbligatoria. Dove il modulo su schema della voce è già usabile per pubblicare, il
  riquadro indirizza l'addetto al modulo, proposto come prima strada, e il testo libero resta
  un ripiego dichiarato. La data da cui il modulo diventa obbligatorio è un dato con fonte,
  nell'albero o nel catalogo degli schemi, mai scritta nel codice: da quella data il
  contenuto libero in quelle voci si chiude. Finché la voce su schema resta in bozza, perché
  gli stati editoriali non ci sono ancora, l'avviso dice chiaramente all'addetto che cosa
  compare al pubblico e che cosa no. La regola si costruisce con la pagina pubblica (passo 4)
  o in una richiesta a sé.
