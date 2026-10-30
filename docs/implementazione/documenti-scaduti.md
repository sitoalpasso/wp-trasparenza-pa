# Scheda di implementazione: documenti dei contenuti scaduti

Scheda scritta prima del codice e completata a lavoro finito. Adegua il componente alla
versione 1.11.0 dell'interfaccia del componente comune, che sul punto amministrativo distingue
chi gestisce i contenuti da chi consulta quelli scaduti.

## In parole semplici

**Cosa cambia nel componente comune.** Fino alla versione 1.10.0 chi gestiva i contenuti apriva
dal riquadro "Documenti" anche i file di un contenuto scaduto. Dalla 1.11.0 il file di un
contenuto scaduto lo apre solo chi ha anche il permesso di consultare i contenuti scaduti. In
questo componente quel permesso non lo aveva nessuno: finita la pubblicazione, chi lavora sulla
sezione avrebbe visto il nome del documento con un collegamento che risponde con un rifiuto.

**Cosa costruiamo.** L'amministratore riceve il permesso di consultare i contenuti scaduti,
insieme agli altri permessi dei contenuti, e da lì l'ente può darlo anche ad altri ruoli. Così
il documento di un contenuto scaduto resta ritrovabile, per esempio per rispondere a una
richiesta di accesso civico, che dopo la scadenza è la sola strada per ottenerlo (art. 8, c. 3,
del d.lgs. 33/2013). Il pubblico non vede niente di più: il permesso vale solo sul punto
amministrativo. Nel riquadro il collegamento compare solo a chi riesce davvero ad aprire il
file; a chi non può, il riquadro mostra il nome e dice perché.

**Come si prova, e come si prova che la prova è vera.** Prove nuove nelle righe T-90 e T-91,
con il punto amministrativo vero del componente comune e l'emissione raccolta dalla prova;
ogni regola ha una rottura voluta che fa diventare rossa almeno una prova, elencata in fondo a
lavoro finito.

## 1. File toccati

| File | Perché |
|---|---|
| `trasparenza-pa.php` | Versione dell'interfaccia richiesta: 1.11.0 |
| `bin/installa-core.sh` | Revisione del componente comune usata dalle prove: quella con l'interfaccia 1.11.0 |
| `src/class-permessi.php` | Il permesso di consultare gli scaduti dei contenuti all'amministratore |
| `src/class-scheda-documenti.php` | Il collegamento solo a chi apre il file |
| `tests/DocumentiScadutiTest.php` | Nuovo. Righe T-90 e T-91 |
| `tests/AvvioTest.php` | La prova T-04 guarda le versioni attorno alla 1.11.0 |
| `docs/` | Collaudo, architettura |

## 2. Requisito servito

TRASP-09 (art. 8, c. 3, d.lgs. 33/2013): alla scadenza i dati escono dalla sezione ma non si
cancellano e restano ottenibili con l'accesso civico. Per rispondere, l'ente deve poterli
ritrovare.

## 3. Flusso per chi usa il sito

L'amministratore apre un contenuto scaduto: il riquadro "Documenti" elenca i file con il
collegamento, e il file si apre. Un ruolo che gestisce i contenuti ma non consulta gli scaduti
vede il nome del file senza collegamento, con la frase che spiega perché. Un contenuto non
ancora pubblicato, o pubblicato e non scaduto, si comporta come oggi.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Ruolo `administrator` | riceve il permesso di consultare gli scaduti del tipo dei contenuti, con il nome chiesto al componente comune; si rilegge dai ruoli conservati come gli altri permessi |
| Fine della pubblicazione | letta dal componente comune, per decidere se mostrare il collegamento |

## 5. Permessi

Il permesso di consultare gli scaduti segue la regola degli altri permessi dei contenuti: si
assegna a ogni cambio della versione installata, così una versione nuova porta con sé i
permessi nuovi. Non segue la regola del permesso di amministrare la sezione, che si assegna una
volta sola: quello è una dichiarazione dell'ente, questo è un permesso di lavoro sui contenuti.
Un ente che lo toglie all'amministratore lo ritrova al prossimo aggiornamento del componente,
come gli altri permessi dei contenuti. Il tipo delle voci non riceve il permesso: le voci non hanno documenti e non scadono.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Componente comune senza il permesso di consultazione per il tipo | i permessi non si assegnano, la versione non si memorizza, si ritenta alla richiesta successiva |
| Permesso non scritto nei ruoli conservati | come sopra, con il messaggio dei permessi non scritti |
| Fine della pubblicazione illeggibile | il componente comune la tratta come scaduto: senza permesso di consultazione nessun collegamento |
| Componente comune con interfaccia più vecchia della 1.11.0 | il componente non si avvia, come per ogni versione incompatibile |

## 7. Test

Righe T-90 e T-91 di `docs/collaudo.md`, in `tests/DocumentiScadutiTest.php`; la prova T-04
della compatibilità guarda ora le versioni attorno alla 1.11.0.

## 8. Cosa deliberatamente NON fa

- Non crea un archivio pubblico degli scaduti: il permesso vale solo sul punto amministrativo.
- Non dà il permesso ad altri ruoli: lo decide l'ente dal suo gestore dei ruoli.
- Non tiene un registro delle aperture dei file: è un'unità del componente comune, se servirà.

## 9. Come si prova sul sito vero

1. Da amministratore apro un contenuto con la fine della pubblicazione passata: il riquadro
   "Documenti" mostra il collegamento e il file si apre.
2. Da una finestra anonima il contenuto non compare e l'indirizzo pubblico del file risponde
   con un rifiuto.
3. Tolgo il permesso di consultare gli scaduti all'amministratore con un gestore dei ruoli:
   il riquadro mostra il nome senza collegamento, con la frase.
4. Ripristino: si ridà il permesso e si cestina il contenuto di prova.

## Rotture volute, a lavoro finito

Dodici rotture, una per volta e poi tolte, e tutte fanno diventare rossa almeno una prova:
permesso di consultazione non assegnato, assegnato anche alle voci, errore del componente
comune sul nome del permesso ignorato, rilettura dei ruoli tolta, rilettura fatta sui ruoli in
memoria invece che su quelli conservati, collegamento mostrato sempre, frase tolta, contenuto in
bozza trattato come esposto, scadenza ignorata nel riquadro, permesso di consultazione ignorato,
permesso di consultazione non controllato sull'utente, versione richiesta riportata alla 1.10.0.

L'errore del componente comune sul nome del permesso fa diventare rossa la prova perché senza
nome l'amministratore resta senza permesso: non c'è una prova che faccia rispondere con un
errore il componente comune vero, che per un tipo registrato il nome lo dà sempre.
