# Scheda di implementazione: convivenza con l'albo pretorio

Scheda scritta prima delle prove e completata a lavoro finito. Serve TRASP-17 per la parte che
riguarda le politiche opposte e i cicli di vita indipendenti; la parte sullo stesso documento
pubblicato in tutte e due le sezioni resta fuori (vedi sezione 7).

## In parole semplici

**Cosa dice la norma.** La sezione "Amministrazione trasparente" deve essere raggiungibile dai
motori di ricerca: l'art. 9 del d.lgs. 33/2013 vieta di impedirne l'indicizzazione. L'albo
pretorio ha la regola opposta, perché pubblica atti con dati personali per un tempo limitato e i
provvedimenti del Garante chiedono che non finiscano negli indici dei motori. Sullo stesso sito
le due regole devono valere insieme, ciascuna sulle sue pagine.

**Cosa costruiamo.** Nessuna funzione nuova: un modo di provare i due plugin accesi insieme, con
il componente comune, come saranno sul sito vero. Oggi ogni plugin si prova da solo, e un errore
che nasce solo dalla convivenza (un plugin che toglie dai motori anche le pagine dell'altro, o
che cambia il comportamento dell'altro) non lo vede nessuno.

**Come si prova.** Una suite separata carica componente comune, albo e trasparenza. Fa due cose:

1. rilancia tutte le prove della trasparenza con l'albo acceso, così ogni comportamento già
   provato (scadenze, file, pagina pubblica, permessi, importazione) si riprova in convivenza;
2. aggiunge le prove proprie della convivenza: avvio congiunto, indicizzazione invertita sulle
   pagine, mappa del sito.

## 1. File toccati

- `bin/installa-albo.sh` (nuovo): procura l'albo pretorio a una revisione fissata del suo
  repository pubblico, come `bin/installa-core.sh` fa con il componente comune.
- `tests/avvio-comune.php`: un parametro in più per caricare l'albo fra il componente comune e
  la trasparenza.
- `tests/bootstrap-con-albo.php`, `phpunit-con-albo.xml.dist`, `phpunit-con-albo-tutte.xml.dist`
  (nuovi): la suite della convivenza, in due avvii.
- `tests/ConAlboTest.php` (nuovo): le prove proprie.
- `phpunit.xml.dist`: esclude `ConAlboTest.php` dalla suite normale, dove l'albo non c'è.
- `composer.json`: comando `test:con-albo`.
- `.github/workflows/ci.yml`: un passo in più.
- `docs/collaudo.md`, `docs/requisiti.md`, questa scheda.

Nessun file in `src/`.

## 2. Requisiti serviti

| Requisito | Cosa copre questa unità |
|---|---|
| TRASP-17 | politiche di indicizzazione opposte, ciascuna sulle sue pagine; cicli di vita indipendenti |
| TRASP-03 | solo il lato della convivenza: l'albo non toglie dai motori le pagine della sezione |

## 3. La revisione dell'albo

L'albo si prende dal repository pubblico, alla revisione `74e9b8f` di `main` (versione
0.8.0-alpha, interfaccia del componente comune richiesta 1.11.0, la stessa che la trasparenza
prova con `bin/installa-core.sh`). Una revisione fissa, e non un ramo, per la stessa ragione del
componente comune: un cambiamento dell'albo entra in queste prove con una modifica leggibile di
`bin/installa-albo.sh`, non da solo fra due esecuzioni.

## 4. Cosa provano le righe

- **T-148, avvio congiunto.** Le tre sezioni (albo, storico pubblico dell'albo, trasparenza)
  sono registrate, ciascuna con la sua politica: albo e storico con indicizzazione vietata e
  scaduti irraggiungibili, trasparenza con indicizzazione consentita e scaduti in archivio. Ogni
  tipo di contenuto sta nella sua sezione. Né la trasparenza né il componente comune lasciano
  avvisi in bacheca (quelli dell'albo sulla sua configurazione sono suoi).
- **T-149, indicizzazione invertita.** La pagina con il codice breve dell'albo porta il divieto
  ai motori, nel sorgente (`noindex`) e nell'intestazione (`X-Robots-Tag`). La pagina della
  sezione, anche aperta su una voce, non porta nessun divieto. Una pagina che contiene tutti e
  due i codici brevi porta il divieto: vince l'albo (sezione 6).
- **T-150, mappa del sito.** La pagina della sezione è nella mappa del sito delle pagine, quella
  dell'albo no.
- **T-151, cicli di vita indipendenti.** Con l'albo registrato accanto, un contenuto della
  trasparenza con la fine della pubblicazione nel futuro compare negli elenchi; con la fine a
  ieri risulta scaduto e sparisce, e la sua politica resta quella della trasparenza. Il
  componente comune governa la scadenza per tipo di contenuto, quindi un atto dell'albo e un
  contenuto della trasparenza scadono ciascuno con la sua data e la sua regola.

**In più, un controllo largo ma più debole.** Tutte le prove della suite normale girano anche con
il codice dell'albo caricato (`phpunit-con-albo-tutte.xml.dist`), tranne quelle dell'avvio
(`AvvioTest`), che controllano che la bacheca resti vuota e troverebbero gli avvisi di
configurazione dell'albo. Qui l'albo è presente con i suoi agganci a WordPress (interrogazioni,
contenuti, motori, mappe, salvataggi), che restano attaccati per tutto l'avvio, ma **non sempre
con le sue registrazioni nel componente comune**: molte prove azzerano le sezioni e i tipi
registrati e riavviano solo la trasparenza, e da lì in poi l'albo non risulta più registrato.
Riaccenderlo dopo ogni azzeramento vorrebbe dire ripetere nelle prove della trasparenza la
procedura interna d'avvio dell'albo, che non è un'interfaccia pubblica. Per questo la convivenza
con le registrazioni di tutti e due la provano solo T-148..T-151, nel loro avvio.

## 5. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Albo non procurato | la suite della convivenza si ferma subito con il messaggio "eseguire bin/installa-albo.sh" |
| Albo a una revisione che chiede un'altra interfaccia del componente comune | l'albo non si avvia e T-148 è rossa |

## 6. Dubbi annotati

**Una pagina con tutti e due i codici brevi.** Il divieto dell'albo vale per tutta la pagina,
quindi anche la sezione della trasparenza esce dagli indici. Le due regole qui si scontrano; la
lettura scelta è la più prudente, perché il divieto dell'albo protegge dati personali e non si
indebolisce per far posto all'altra regola. La soluzione è tenere le due sezioni su pagine
diverse. Un avviso nella schermata della trasparenza che lo segnali è possibile, ma è una
funzione nuova e la decide l'utente.

## 7. Cosa deliberatamente non fa

- **Lo stesso documento in tutte e due le sezioni senza duplicare il file** (seconda parte di
  TRASP-17). Oggi il componente comune lega ogni file depositato a un solo contenuto e lo sposta
  nella cartella protetta: per condividerlo serve un meccanismo nuovo nel componente comune.
  TRASP-17 resta quindi "in corso".
- Non prova l'albo con la trasparenza accesa: è il lavoro del repository dell'albo.
- Non cambia nessun comportamento dei due plugin.

## Com'è andata

Le prove della convivenza sono verdi alla prima esecuzione: oggi i due plugin convivono come
devono, e questa unità non cambia niente in `src/`. Due cose venute dal lavoro:

- la convivenza gira in **due avvii** e non in uno: molte prove della suite normale azzerano le
  sezioni del componente comune e riavviano solo la trasparenza, quindi dopo di loro l'albo non
  risulta più registrato. Le righe T-148..T-151 girano in un avvio loro, prima di qualunque
  azzeramento; il controllo largo è l'altro avvio, con il limite dichiarato in sezione 4 (rilievo
  della rilettura: in una prima versione T-151 era il controllo largo, e prometteva più di quanto
  provasse);
- il divieto ai motori del componente comune vale per tipo di contenuto, e le pagine con i codici
  brevi sono pagine di WordPress: per questo T-149 non vede una trasparenza dichiarata "vietata",
  che resta compito di T-148. Il divieto sulla pagina dell'albo lo mette l'albo stesso.

Suite in locale: 366 prove verdi nella suite normale, più le due separate; convivenza 4 prove
(T-148..T-151) più 353 del controllo largo (le 366 senza le 13 dell'avvio). PHPCS e T-32 puliti. GitHub
Actions è fermo per la fatturazione, quindi la verifica in CI manca, compreso il nuovo passo.

**Rotture volute**, una per volta, in tutti e tre i componenti (quelle sull'albo e sul componente
comune nella copia dell'ambiente di prova, mai nei loro repository). Tutte rosse.

| Rottura | Dove | Prova rossa |
|---|---|---|
| Trasparenza dichiarata con indicizzazione vietata | trasparenza | T-148 |
| Un avviso in bacheca all'avvio della trasparenza | trasparenza | T-148 |
| Albo senza divieto nel sorgente della sua pagina | albo | T-149 |
| Albo che mette il divieto su ogni pagina | albo | T-149 |
| Pagina dell'albo nella mappa del sito | albo | T-150 |
| Albo che toglie dalla mappa tutte le pagine | albo | T-150 |
| Componente comune che vieta ogni richiesta | componente comune | T-149 |
| Albo che nasconde i contenuti della trasparenza dagli elenchi | albo | controllo largo |
| Contenuto scaduto della trasparenza ancora negli elenchi | componente comune | T-151 |
| Albo che nasconde i contenuti della trasparenza solo quando è registrato | albo | T-151 |
| Suite della convivenza senza l'albo caricato | prove | T-148..T-150 (errore) |

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| La sezione si lascia indicizzare | d.lgs. 33/2013, art. 9 (TRASP-03) | `docs/requisiti.md` di questo repository |
| Albo e trasparenza convivono con politiche opposte | d.lgs. 33/2013 (TRASP-17) | `docs/requisiti.md` |
| L'albo vieta l'indicizzazione | indicazioni del Garante, che raccomanda i metatag (ALBO-06 dell'albo) | `docs/requisiti.md` del repository dell'albo, riga ALBO-06, riletta per questa unità; il provvedimento del Garante non è riletto |
