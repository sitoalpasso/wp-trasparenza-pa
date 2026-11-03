# Scheda di implementazione: la verifica periodica dei rinvii alle banche dati

Scheda scritta prima del codice e completata a lavoro finito. Chiude la parte di TRASP-25 rimasta
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
nuovo, si rimette a posto senza un intervento a mano.

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

## Norme e fonti

| Regola | Fonte |
|---|---|
| Rinvio con verifica automatica periodica; rinvio rotto come obbligo non assolto | TRASP-25 in `docs/requisiti.md`: d.lgs. 33/2013 art. 9-bis e d.l. 19/2026 art. 8, citati a memoria |
| Contratto del verificatore, chiave, intervallo, `in_ritardo` | scheda del verificatore nel componente comune, letta nel codice |
| Lettura fallita che non vale come vuoto | AGENTS.md, punto 7 |
