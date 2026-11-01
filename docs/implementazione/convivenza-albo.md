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
- `tests/bootstrap-con-albo.php`, `phpunit-con-albo.xml.dist` (nuovi): la suite della
  convivenza.
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
- **T-151, la trasparenza non cambia con l'albo acceso.** Tutte le prove della suite normale
  girano anche nella suite della convivenza, tranne quelle dell'avvio (`AvvioTest`), che
  controllano che la bacheca resti vuota e troverebbero gli avvisi di configurazione dell'albo;
  per la convivenza le sostituisce T-148.

I cicli di vita indipendenti sono provati da T-151: le prove della fine della pubblicazione,
dei cinque anni, della cessazione e dei documenti scaduti girano con l'albo acceso. Il componente
comune governa la scadenza per tipo di contenuto, quindi un atto dell'albo e un contenuto della
trasparenza scadono ciascuno con la sua data e la sua regola.

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

## Norme e fonti

| Regola | Norma | Fonte, e se è verificata |
|---|---|---|
| La sezione si lascia indicizzare | d.lgs. 33/2013, art. 9 (TRASP-03) | `docs/requisiti.md` di questo repository |
| Albo e trasparenza convivono con politiche opposte | d.lgs. 33/2013 (TRASP-17) | `docs/requisiti.md` |
| L'albo vieta l'indicizzazione | indicazioni del Garante, che raccomanda i metatag (ALBO-06 dell'albo) | `docs/requisiti.md` del repository dell'albo, riga ALBO-06, riletta per questa unità; il provvedimento del Garante non è riletto |
