# Scheda di implementazione: voci coperte da un modulo ANAC

Terza parte del quarto passo descritto in `contenuti-voci.md` e in
`pagina-pubblica-contenuti.md`. Scheda scritta prima del codice e completata a lavoro finito.
Costruisce la scelta del 28 ottobre 2026 annotata in `contenuti-voci.md`: nelle voci per cui
esiste un modulo ANAC (artt. 13, 4-bis e 31 del d.lgs. 33/2013) il testo libero resta ammesso
finché la compilazione sul modulo non è obbligatoria; dove il modulo è già usabile, si indirizza
l'addetto al modulo, perché si abitui allo standard.

## In parole semplici

**Cosa costruiamo.** Quando un addetto colloca un contenuto in una voce coperta da un modulo
ANAC, per esempio "Articolazione degli uffici" per l'art. 13, la schermata del contenuto gli
dice che per quella voce esiste il modulo, quale versione vale oggi, e che cosa arriva al
pubblico. Oggi i dati compilati sul modulo restano in bozza, perché la pubblicazione dal
modulo non è ancora aperta: al pubblico arriva il testo libero, e la schermata lo dice senza
giri di parole. Un collegamento porta al modulo, per chi ha il permesso di compilarlo.

**Come la costruiamo.** Quali voci sono coperte da quale modulo è un dato, non codice: sta nel
file dell'albero, in una versione nuova (2026-10-29) che aggiunge solo l'elenco e la sua
fonte. L'elenco si ricava dai riferimenti normativi delle voci, trascritti dall'elenco degli
obblighi ANAC: una voce è coperta dal modulo di un articolo quando il suo riferimento cita
quell'articolo e lo schema ANAC di quell'articolo ne copre i dati. La versione del modulo in
vigore oggi la dice il catalogo degli schemi, con le sue date. Il codice non conosce né gli
articoli né le voci.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-75..T-77;
ogni regola ha una rottura voluta che fa diventare rossa almeno una prova, elencata in fondo a
lavoro finito.

## 1. File toccati

| File | Perché |
|---|---|
| `dati/alberatura/2026-10-29.json` | Nuovo. L'albero 2026-10-28-2 più l'elenco delle voci coperte da un modulo e la sua fonte |
| `trasparenza-pa.php` | L'albero in vigore diventa la versione 2026-10-29 |
| `src/class-alberatura.php` | Legge e controlla l'elenco: dice di una voce quale modulo la copre |
| `src/class-scheda-contenuto.php` | L'avviso nel riquadro "Voce della sezione" |
| `tests/` | Nuove prove; la prova della versione 2026-10-28-2 guarda il suo file per nome |
| `docs/` | Collaudo, architettura, dati |

## 2. Requisiti serviti

- TRASP-05 (delibera ANAC 495/2024): i dati delle voci con modulo si raccolgono in forma
  strutturata quando il modulo è usabile; fino ad allora l'addetto sa che il modulo esiste.
- TRASP-26: la versione del modulo detta all'addetto è quella in vigore oggi secondo il
  catalogo.

## 3. Flusso per chi usa il sito

L'addetto apre un contenuto, sceglie la voce e salva. Se la voce è coperta da un modulo,
riaprendo il contenuto il riquadro mostra l'avviso: il modulo che copre la voce, la versione in
vigore, che i dati del modulo oggi restano in bozza e che al pubblico arriva il testo libero, e
il collegamento per compilare il modulo.

## 4. Dati letti e scritti

Solo lettura: l'elenco `moduli` dell'albero in vigore, il catalogo degli schemi. Niente si
scrive.

## 5. Permessi

L'avviso si vede da chi può modificare il contenuto. Il collegamento al modulo compare solo a
chi può creare voci di trasparenza.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Elenco malformato nel file dell'albero: non un oggetto, modulo senza nome o senza voci, voce che non esiste o che ha sottovoci, voce in due moduli | l'albero non si carica, come per gli altri errori del file |
| Catalogo degli schemi non disponibile | l'avviso dice che il modulo esiste e che il catalogo non si è potuto leggere |
| Nessuna versione del modulo ancora in vigore | l'avviso dice che il modulo non vale ancora e che per ora si usa il testo libero |
| Voce non coperta da un modulo, o nessuna voce | nessun avviso |

## 7. Test

Righe T-75..T-77 di `docs/collaudo.md`, in `tests/AlberoNormativoTest.php` e `tests/VociConModuloTest.php`.

## 8. Cosa deliberatamente NON fa

- **Non chiude il testo libero quando il modulo diventa obbligatorio.** La data dell'obbligo
  non è ancora stabilita da una fonte: quando lo sarà, entra nel catalogo come dato con fonte e
  la chiusura si costruisce allora.
- **Non cambia l'avviso quando la pubblicazione dal modulo si apre.** Oggi ogni voce di
  trasparenza resta in bozza; l'avviso che chiede di compilare sul modulo al posto del testo
  arriva con l'unità che apre quella pubblicazione.
- Non avvisa al momento della scelta, prima di salvare.
- Non cambia la pagina pubblica: al pubblico arriva il testo libero, come oggi.

## 9. Come si prova sul sito vero

1. Apro un contenuto in bozza, scelgo la voce "Organizzazione > Articolazione degli uffici" e
   salvo: il riquadro mostra l'avviso con il modulo dell'art. 13 e la sua versione, e il
   collegamento per compilarlo.
2. Scelgo "Bandi di concorso" e salvo: l'avviso sparisce.
3. Ripristino: si cestina il contenuto di prova.

## Dubbi annotati

- La voce dei titolari di incarichi politici cita l'art. 13, c. 1, lett. a), ma contiene anche
  i dati dell'art. 14, che il modulo non copre: l'avviso dice che il modulo copre i dati del
  suo articolo, non tutta la voce.

## Rotture volute, a lavoro finito

Ventidue rotture, una per volta e poi tolte. Venti fanno diventare rossa almeno una
prova: elenco non oggetto accettato, identificativo non controllato, identificativo senza
ancore, chiavi non controllate, nome vuoto accettato, nome lungo accettato, nome non
ripulito, voci vuote accettate, voci con nomi accettate, voce inesistente accettata, voce con
sottovoci accettata, voce ripetuta accettata, modulo mai trovato, avviso non mostrato,
catalogo assente ignorato, versione assente ignorata, permesso non controllato, versione non
detta, bozza non detta, versione nuova dell'albero non in vigore.

Due restano verdi, e sono volute:

- togliere il controllo che la voce non sia la radice non cambia niente, perché la radice ha
  sempre sottovoci in un albero con più di una voce, e una voce con sottovoci si rifiuta già;
- togliere il controllo che la voce sia un testo non cambia niente, perché le voci dell'albero
  hanno identificativi testuali e un numero non ne trova nessuna.
