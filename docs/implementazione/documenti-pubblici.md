# Scheda di implementazione: documenti scaricabili dalla pagina pubblica

Seconda parte del quarto passo descritto in `pagina-pubblica-contenuti.md`. Scheda scritta
prima del codice e completata a lavoro finito. Porta sulla pagina pubblica i documenti
allegati ai contenuti, scaricabili da chiunque, secondo la scelta del 29 ottobre 2026: una
regola dichiarata nel componente comune, "i file di questo tipo seguono la pagina della
sezione", con una condizione che decide questo componente.

## In parole semplici

**Cosa costruiamo.** Sotto ogni contenuto della pagina pubblica compare l'elenco dei suoi
documenti, ciascuno con il collegamento per scaricarlo. Il file lo consegna il componente
comune dal suo indirizzo controllato, mai dalla cartella dei caricamenti.

**Come la costruiamo.** I contenuti della sezione non hanno una pagina propria: si vedono solo
dentro la pagina della sezione. Il componente comune, da solo, consegnerebbe i loro file
soltanto se il contenuto avesse una pagina aperta al pubblico, e quindi non li consegnerebbe
mai. Dalla versione 1.10.0 della sua interfaccia, un tipo può dichiarare che i suoi file
seguono la sezione, con una funzione che il componente comune chiama come ultimo controllo,
dopo pubblicazione, password, scadenza e appartenenza del file al contenuto. La funzione di
questo componente risponde sì solo se il contenuto ha una voce, una sola, che l'albero in
vigore ammette: cioè solo se il contenuto compare davvero nella pagina. Un contenuto
pubblicato ma senza voce non si vede, e nemmeno i suoi documenti si scaricano, anche da chi
conosce i numeri dell'indirizzo. Se la voce non si riesce a leggere, la risposta è no.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-78..T-80, con file veri depositati dal componente comune e scaricati dal suo punto pubblico; ogni
regola ha una rottura voluta che fa diventare rossa almeno una prova, elencata in fondo a
lavoro finito.

## 1. File toccati

| File | Perché |
|---|---|
| `trasparenza-pa.php` | Versione dell'interfaccia del componente comune richiesta: 1.10.0 |
| `bin/installa-core.sh` | Revisione fissata del componente comune che la offre |
| `src/class-tipo-contenuto.php` | Dichiara che i file del tipo seguono la sezione, con la funzione |
| `src/class-contenuti-pubblici.php` | La funzione che decide; l'elenco dei documenti sotto ogni contenuto |
| `tests/` | Nuove prove |
| `docs/` | Collaudo, requisiti, architettura |

## 2. Requisiti serviti

- TRASP-02 (art. 9): i documenti della sezione si scaricano senza autenticazione.
- TRASP-09 (art. 8) per i documenti: un documento di un contenuto scaduto non si scarica; lo
  decide il componente comune, che controlla la scadenza a ogni richiesta.

## 3. Flusso per chi usa il sito

Chi visita la sezione apre una voce, trova i contenuti e sotto ciascuno i suoi documenti; un
clic scarica il file con il suo nome.

## 4. Dati letti e scritti

Solo lettura: gli allegati del contenuto depositati dal componente comune
(`SchedaDocumenti::documenti()`, che distingue "nessuno" da "lettura fallita"), la voce del
contenuto letta dalla banca dati. Niente si scrive.

## 5. Permessi

Lettura pubblica. Lo scaricamento passa dal punto pubblico del componente comune, con tutti i
suoi controlli più la condizione di questo componente.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Contenuto pubblicato senza voce, con più voci, con una voce che l'albero non ammette | documenti non scaricabili, contenuto non mostrato |
| Contenuto in bozza, con password, scaduto; allegato di un altro contenuto o cestinato | non scaricabile, per i controlli del componente comune |
| Lettura fallita della voce | non scaricabile |
| Lettura fallita dei documenti mentre la pagina si disegna | avviso di indisponibilità al posto di contenuti e date, come per le altre letture |
| Componente comune con interfaccia più vecchia di 1.10.0 | il componente non si avvia, come oggi per ogni versione incompatibile |

## 7. Test

Righe T-78..T-80 di `docs/collaudo.md`, in `tests/DocumentiPubbliciTest.php`; la prova T-04 della compatibilità guarda ora le versioni attorno alla 1.10.0.

## 8. Cosa deliberatamente NON fa

- Non mostra la dimensione o il formato del file accanto al nome.
- Non rinomina i file secondo le specifiche ANAC (TRASP-21): il nome è quello del deposito.
- Non controlla i formati aperti (TRASP-07, TRASP-20): resta un'unità futura.

## 9. Come si prova sul sito vero

1. Apro un contenuto con una voce, carico un PDF e pubblico.
2. Da una finestra anonima apro la voce: sotto il contenuto c'è il PDF, e il collegamento lo
   scarica.
3. Tolgo la voce al contenuto e salvo: il contenuto sparisce dalla pagina, e il collegamento
   copiato prima non scarica più niente.
4. Ripristino: si cestina il contenuto di prova.

## Rotture volute, a lavoro finito

Quattordici rotture, una per volta e poi tolte. Dodici fanno diventare rossa almeno una
prova: condizione non dichiarata (i file seguirebbero la sezione per ogni contenuto
pubblicato), condizione sempre vera, albero assente ignorato, numero del contenuto non
controllato, tipo non controllato, lettura fallita della voce ignorata, più voci accettate,
voce ammessa non controllata, documenti non elencati, cestinati elencati, lettura fallita dei
documenti ignorata, interfaccia vecchia richiesta.

Due restano verdi, e sono volute:

- prendere l'indirizzo dell'allegato da WordPress invece di chiederlo al componente comune
  non cambia niente, perché il componente comune riscrive quell'indirizzo con il suo punto di
  consegna; la chiamata diretta resta, perché dice che cosa si vuole;
- togliere l'escaping al nome del file non cambia niente oggi, perché il deposito ripulisce il
  nome da ogni carattere che l'escaping cambierebbe; l'escaping resta, perché il nome è un
  dato memorizzato.
