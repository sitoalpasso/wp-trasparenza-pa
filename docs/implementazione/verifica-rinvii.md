# Scheda di implementazione: la verifica periodica dei rinvii alle banche dati

Scheda scritta prima del codice e completata a lavoro finito. **Stato: costruita, righe T-163..T-166 verdi in locale.** Chiude la parte di TRASP-25 rimasta
aperta dopo l'unità dei rinvii (`rinvii-banche-dati.md`): la verifica automatica periodica che il
collegamento risponda, con l'esito datato e la voce segnalata.

## In parole semplici

**Cosa succede oggi.** L'ente dichiara per una voce il collegamento alla banca dati nazionale dove
ha già comunicato i dati. Il collegamento si mostra sulla pagina della voce, ma nessuno controlla
che risponda ancora: se la banca dati cambia indirizzo, la voce rinvia a una pagina che non c'è
più, l'obbligo non è più assolto e a occhio non si vede.

**Cosa costruiamo.** Ogni rinvio dichiarato si affida al verificatore dei collegamenti del
componente comune, che lo ricontrolla da solo. La schermata "Rinvii alle banche dati" mostra per
ogni voce l'esito dell'ultimo controllo con la data, e in testa l'elenco delle voci da guardare:
collegamento che non risponde, controllo in ritardo, rinvio non ancora affidato al verificatore,
esito che non si è potuto leggere.

**Come si prova.** Righe T-163..T-166: l'affidamento al salvataggio, il riallineamento
all'apertura della schermata, l'esito mostrato con le voci da guardare, la versione richiesta al
componente comune e la forma della chiave.

## 1. File toccati

- `src/class-verifica-rinvii.php` (nuovo): affidamento, riallineamento, esito da mostrare.
- `src/class-schermata-rinvii.php`: affidamento dopo il salvataggio, riallineamento ed esito nella
  schermata.
- `trasparenza-pa.php`: versione richiesta dell'interfaccia del componente comune `1.13.0`.
- `bin/installa-core.sh`: revisione del componente comune con il verificatore.
- `tests/VerificaRinviiTest.php` (nuovo).
- `docs/`: collaudo, requisiti, architettura, questa scheda.

## 2. Requisito servito

TRASP-25 (art. 9-bis del d.lgs. 33/2013; d.l. 19/2026, art. 8): il rinvio è un dato, con verifica
automatica periodica che il collegamento risponda; un rinvio rotto è un obbligo non assolto.
Criterio di collaudo: esito negativo registrato con data, voce segnalata.

## 3. Le regole

**Chiave.** Il verificatore accetta chiavi in minuscolo; le voci dell'albero ammettono anche le
maiuscole. La chiave è l'identificativo della voce quando è già in minuscolo, altrimenti `v-`
seguito dall'impronta MD5 dell'identificativo: il trattino non compare mai negli identificativi
delle voci, quindi le due forme non si confondono.

**Intervallo: un giorno**, il più breve che il verificatore ammette. È la politica di questo
componente, dichiarata come costante: controllare più spesso non è mai meno conforme, quindi è
un valore che non può essere sbagliato per nessun ente. Con al più cinque controlli all'ora del
verificatore, un giorno basta per decine di rinvii.

**Affidamento al salvataggio (T-163).** Dopo un salvataggio riuscito, il rinvio della voce si
registra presso il verificatore; se il rinvio è stato tolto, si toglie anche lì. Se il
verificatore rifiuta o non riesce a scrivere, il rinvio resta salvato, perché è la dichiarazione
dell'ente e il registro delle modifiche l'ha già tracciata: il messaggio lo dice e la voce compare
fra quelle da guardare.

**Riallineamento all'apertura della schermata (T-164).** Aprendo la schermata, per ogni voce che
ha un rinvio conservato o che l'albero lega a una banca dati: se il rinvio conservato è valido con
l'albero in vigore si registra (la registrazione uguale non cambia niente), altrimenti si toglie.
Così un rinvio salvato quando il verificatore non rispondeva, o diventato non valido con un albero
nuovo, si rimette a posto senza un intervento a mano. Il riallineamento prende lo stesso blocco
dei salvataggi: senza, un salvataggio arrivato fra la lettura dei rinvii e l'affidamento verrebbe
coperto dall'indirizzo letto prima. Se il blocco è preso, il riallineamento si salta e la schermata
lo dice.

**Esito (T-165).** Per ogni voce con un rinvio valido, sotto il modulo: "Mai controllato";
"Risponde", con data e ora dell'ultimo controllo nel fuso del sito; "Non risponde", con data,
ora e motivo; più "Controllo in ritardo" quando il verificatore lo dice. Se il verificatore ha un
indirizzo diverso da quello conservato, o non ha il rinvio, la voce risulta "non ancora affidata
al verificatore". In testa alla schermata, l'elenco delle voci da guardare, con il motivo per
ciascuna.

**Versione (T-166).** Il componente richiede l'interfaccia `1.13.0`, la prima con il controllo
periodico e il ritardo letto sul dato.

## 4. Modi di guasto

| Guasto | Comportamento |
|---|---|
| Registrazione presso il verificatore rifiutata o non scritta, al salvataggio | rinvio salvato, messaggio che lo dice, voce da guardare |
| Rimozione presso il verificatore non riuscita | rinvio tolto, messaggio che lo dice; il riallineamento ci riprova |
| Lettura dei rinvii conservati fallita all'apertura | nessun riallineamento, avviso; la schermata si disegna come oggi |
| Salvataggio in corso all'apertura | riallineamento saltato, avviso; si fa alla prossima apertura |
| Lettura dell'esito fallita | "esito non leggibile", voce da guardare |
| Motivo con codice | escaping |

## 5. Cosa deliberatamente non fa

- Non mostra sulla pagina pubblica che un collegamento non risponde: il rimedio è dell'ente, e la
  segnalazione va a chi può correggere.
- Non ha un pulsante "controlla adesso": il verificatore ricontrolla da solo entro un'ora un
  collegamento nuovo o cambiato.
- Non controlla che dall'altra parte ci sia il dato giusto: il verificatore controlla che la
  pagina risponda.
- Non manda avvisi fuori dalla schermata.
- Non toglie i collegamenti dal verificatore quando il componente si disattiva. Spegnere non
  cancella, come per il resto dei dati della sezione. A componente spento la sezione non è
  registrata presso il componente comune, e il controllo periodico salta i collegamenti delle
  sezioni non registrate (riga C-307 del componente comune): nessuna richiesta parte verso le
  banche dati per conto di un componente spento. Alla riattivazione il riallineamento della
  schermata rimette tutto in linea con i rinvii conservati.

## Com'è andata

Costruito come scritto sopra, nella classe `VerificaRinvii` e in due punti della schermata dei
rinvii. Tre cose venute dalle prove:

- Un guasto del verificatore al salvataggio non rimostra il modulo con i valori inviati: sono già
  salvati, e un modulo con la partenza vecchia farebbe fallire il salvataggio successivo per
  conflitto.
- Il riallineamento guarda sia le voci che hanno un rinvio conservato sia quelle che l'albero lega
  a una banca dati: così si tolgono anche i collegamenti rimasti da una rimozione non riuscita e
  quelli di voci che l'albero in vigore non lega più.
- La prova della compatibilità di versione (T-04) usava valori fissi intorno alla versione
  richiesta di prima: aggiornati intorno alla `1.13.0`.

**Rotture volute**, una per volta, con le prove di `VerificaRinviiTest`. 23 rotture, tutte rosse;
due erano all'inizio verdi e hanno portato la prova dei collegamenti rimasti.

| Rottura | Prova rossa |
|---|---|
| Chiave sempre in minuscolo, o impronta senza prefisso | T-166 |
| Intervallo di sette giorni | T-163, T-166 |
| Rinvio vuoto che non toglie | T-163 |
| Salvataggio che non affida, guasto ignorato, guasto che rimostra il modulo | T-163 |
| Riallineamento con la lettura fallita trattata come vuoto | T-164 |
| Riallineamento senza le voci dell'albero o senza i rinvii conservati | T-164 |
| Rinvio conservato non ricontrollato con l'albero | T-164 |
| Schermata che non riallinea | T-164, T-165 |
| Indirizzo nel verificatore non confrontato | T-165 |
| Lettura fallita, collegamento rotto o ritardo fuori dalle voci da guardare | T-165 |
| Ritardo ignorato, data in UTC, motivo senza escaping, elenco da guardare assente | T-165 |
| Versione richiesta `1.12.0` | T-166 |
| Riallineamento senza blocco, blocco non rilasciato, avviso del blocco assente | T-164 (rilievo della revisione) |

## Norme e fonti

| Regola | Fonte |
|---|---|
| Rinvio con verifica automatica periodica; rinvio rotto come obbligo non assolto | TRASP-25 in `docs/requisiti.md`: d.lgs. 33/2013 art. 9-bis e d.l. 19/2026 art. 8, citati a memoria |
| Contratto del verificatore, chiave, intervallo, `in_ritardo` | scheda del verificatore nel componente comune, letta nel codice |
| Lettura fallita che non vale come vuoto | AGENTS.md, punto 7 |
