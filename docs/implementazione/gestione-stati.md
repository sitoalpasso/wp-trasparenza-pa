# Scheda di implementazione: schermata di gestione degli stati

Prima unità della fase di gestione. Scheda compilata a lavoro finito. Serve TRASP-27 per la
parte che mancava, la scelta dello stato da parte di una persona, e TRASP-19 per la traccia
di ogni scelta.

## In parole semplici

**Cosa costruiamo.** Una schermata del pannello di amministrazione, "Amministrazione
trasparente > Stato delle voci", dove il responsabile della trasparenza dichiara per ogni
voce della sezione se l'obbligo riguarda l'ente: applicabile, non applicabile o sospeso.
Nessuna voce parte con uno stato già scelto. La schermata dice cosa comporta ogni scelta e
tiene in cima l'elenco delle voci ancora senza stato, che sono quelle non conformi.

**Come la costruiamo.** La schermata la apre solo chi ha un permesso dedicato. Alla prima
installazione il permesso va all'amministratore; poi l'ente può darlo al responsabile della
trasparenza e toglierlo all'amministratore, e un aggiornamento non lo ridà. Quando qualcuno
salva, prima si controllano tutte le voci inviate: con un solo errore non si salva niente.
Poi, una voce alla volta, si scrive nel registro delle modifiche chi ha cambiato cosa, e solo
dopo si cambia lo stato. Due salvataggi nello stesso momento non si pestano i piedi: il
secondo aspetta che il primo finisca.

**Come si prova, e come si prova che la prova è vera.** Ventisette prove nuove (righe
T-26..T-33), con l'albero finto delle prove. Quindici rotture volute, una per volta e poi
tolte, le fanno diventare rosse: permesso riassegnato a ogni richiesta, controllo del
permesso tolto, controllo del nonce tolto, radice accettata, stato scritto prima del
registro, "Da scegliere" tolto, avviso mostrato a tutti, menu aperto con il permesso
generico delle impostazioni, permessi assegnati anche a componente inerte, stato uguale
registrato come cambio, salvataggio a metà davanti a un valore sbagliato, rilettura dalla
banca dati tolta, blocco tolto, rilettura dello stato scritto tolta, esito dello stato
non scritto ignorato.

## 1. File toccati

| File | Perché |
|---|---|
| `src/class-permessi.php` | Nuovo. Il permesso `trasparenza_pa_amministrazione`, la prima assegnazione, l'avviso quando nessuno lo ha |
| `src/class-schermata-stati.php` | Nuovo. Menu, schermata, salvataggio con blocco e registro |
| `trasparenza-pa.php` | Caricamento delle due classi e agganci |
| `tests/GestioneStatiTest.php` | Nuovo. Righe T-26..T-33 |
| `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md` | Righe di collaudo, permesso e voce di registro, parti del plugin |

## 2. Requisito servito

TRASP-27: lo stato a tre valori e la dicitura c'erano già (T-13..T-15); ora c'è il modo di
sceglierlo e la sottosezione senza stato è segnalata a chi deve sceglierlo. TRASP-19: ogni
cambio di stato è una voce del registro di core.

## 3. Flusso per chi usa il sito

Il responsabile apre "Amministrazione trasparente" nel menu del pannello. Vede la spiegazione
dei tre stati, con le frasi che la pagina pubblica stamperà, l'elenco delle voci senza stato
e la tabella di tutte le voci con un menu a tendina per ciascuna. Sceglie, salva, e torna
sulla stessa schermata con l'esito: quanti cambi sono stati salvati, oppure perché non si è
salvato niente. Il visitatore della pagina pubblica vede la frase prevista sulle voci non
applicabili o sospese, come già prima.

## 4. Dati letti e scritti

| Dato | Lettura e scrittura |
|---|---|
| Opzione `trasparenza_pa_stati_obbligo` | già esistente; si rilegge dalla banca dati prima di ogni salvataggio, si scrive una voce alla volta |
| Opzione `trasparenza_pa_permessi_assegnati` | nuova; la versione che ha fatto la prima assegnazione. Finché manca, l'assegnazione si ritenta |
| Ruolo `administrator` | riceve `trasparenza_pa_amministrazione` una volta |
| Registro di core | una voce per cambio: sezione `amministrazione_trasparente`, azione `stato_obbligo`, dettagli `voce`, `prima` (nullo se non dichiarato), `dopo`. Utente e istante li mette core |
| Dato temporaneo `trasparenza_pa_esito_stati_<utente>` | l'esito del salvataggio, per un minuto, letto e cancellato dalla schermata |
| Blocco della banca dati | nome ricavato da banca dati e prefisso del sito, preso per la durata del salvataggio |

## 5. Permessi

| Azione | Chi |
|---|---|
| Vedere la voce di menu e aprire la schermata | `trasparenza_pa_amministrazione`; senza, WordPress non aggancia la schermata e la funzione che la disegna rifiuta comunque |
| Salvare | `trasparenza_pa_amministrazione` più il nonce dell'azione, legato all'utente; senza uno dei due, rifiuto con codice 403 |
| Vedere l'avviso "nessun ruolo può dichiarare gli stati" | `activate_plugins` |

Il permesso non è `manage_options`: l'ente può darlo al responsabile della trasparenza senza
dargli le impostazioni del sito. Il visitatore anonimo non tocca niente di questa unità.

## 6. Modi di guasto

| Caso | Esito |
|---|---|
| Voce inesistente, radice, valore fuori dai tre, valore non testuale, campo non elenco | niente scritto, niente nel registro, messaggio |
| Registro che rifiuta un cambio | quel cambio e i successivi non si fanno; i precedenti restano con la loro traccia; il messaggio dice quanti sono passati e su quale voce si è fermato |
| Stato non scritto dopo la voce di registro | quel cambio non c'è e i successivi non si fanno; il messaggio dice quanti sono passati, su quale voce si è fermato e che il registro ha per quella voce un cambio non avvenuto |
| Altro salvataggio in corso | attesa fino a cinque secondi, poi rinuncia senza scrivere |
| Albero non disponibile | schermata senza modulo, con il motivo; salvataggio rifiutato |
| Sezione non registrata (componente inerte) | nessuna voce di menu, nessun permesso assegnato |
| Ruolo di amministratore assente | permesso non assegnato, assegnazione da ritentare; avviso "nessun ruolo" a chi gestisce i componenti |

**Limite dichiarato.** L'ordine è "prima il registro, poi lo stato" perché un cambio senza
traccia non deve esistere. Il caso opposto resta possibile in un solo modo: la banca dati
accetta la voce di registro e subito dopo non scrive lo stato. Dopo ogni scrittura lo stato si
rilegge dalla banca dati, quindi il caso si vede subito nell'esito: il salvataggio si ferma su
quella voce, non risulta "salvato", dice quanti cambi sono passati e che per quella voce il
registro contiene un cambio non avvenuto. È il verso meno dannoso dei due.

## 7. Test

Righe T-26..T-33 di `docs/collaudo.md`, in `tests/GestioneStatiTest.php`. La prova del blocco
apre una seconda connessione alla banca dati e tiene il blocco da lì, come farebbe una
richiesta contemporanea: dura cinque secondi, il tempo dell'attesa.

## 8. Cosa deliberatamente NON fa

Non offre una schermata per assegnare il permesso ad altri ruoli: lo si fa con gli strumenti
dei ruoli già in uso sul sito. Non permette di tornare a "non dichiarato". Non chiede un
motivo per il cambio di stato, che la norma non prevede. Non mostra i contenuti delle voci né
il responsabile della trasparenza, che arrivano con le unità successive.

## 9. Come si prova sul sito vero

1. Da amministratore, nel pannello compare "Amministrazione trasparente". La schermata
   elenca le 23 voci di primo livello e le loro sottovoci, tutte su "Da scegliere", e
   l'avviso in cima dice quante sono. Se compare già uno stato scelto su un sito appena
   installato, il rilascio si annulla.
2. Scelgo "Non applicabile" per una voce e salvo: torna l'esito "Salvato 1 cambio di stato".
   Apro da un browser senza accesso la pagina pubblica di quella voce: deve comparire la frase
   "L'obbligo di pubblicazione non è applicabile all'amministrazione". Se non compare, il
   rilascio si annulla.
3. Nel registro delle modifiche c'è una voce `stato_obbligo` con il mio utente, la voce, prima
   vuoto e dopo `non_applicabile`. Se manca, il rilascio si annulla.
4. Da un utente redattore, senza il permesso, la voce di menu non c'è e l'indirizzo
   `wp-admin/admin.php?page=trasparenza-pa-stati` risponde che l'accesso non è consentito.
5. Ripristino: si rimette lo stato della voce com'era. La voce di registro resta, ed è giusto
   così.

## Dubbi annotati

- La prima assegnazione va all'amministratore, che non è un default sbagliato possibile: ha
  già tutti i poteri sul sito. L'alternativa, nessuno, avrebbe lasciato la schermata
  invisibile a tutti fino a un intervento manuale sui ruoli.
- Il blocco usa `GET_LOCK` della banca dati, presente in MySQL e MariaDB. Un servizio di
  banca dati che lo neghi fa rinunciare ogni salvataggio con il messaggio "un altro
  salvataggio è in corso": il sistema si chiude, non si apre.
