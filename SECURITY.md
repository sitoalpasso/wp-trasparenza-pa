# Sicurezza

## Come segnalare una vulnerabilità

**Non aprire una issue pubblica.** Le vulnerabilità si segnalano in privato, in uno di questi
due modi:

1. con la segnalazione privata di GitHub: scheda **Security** del repository, pulsante
   **Report a vulnerability**;
2. per posta elettronica a **andrea@andreaceravolo.it**.

Indica nella segnalazione:

- versione del plugin, di `conformita-core`, di WordPress e di PHP;
- il tipo di problema e il suo impatto (che cosa può vedere o fare chi lo sfrutta);
- i passi minimi per riprodurlo, con dati inventati;
- il requisito coinvolto, se lo conosci, citato per identificativo (per esempio TRASP-04).

Non inserire dati personali, contenuti di atti reali o indirizzi di installazioni.

## Tempi di risposta

| Passo | Entro |
|---|---|
| Conferma di ricezione | 3 giorni lavorativi |
| Prima valutazione (vulnerabilità sì o no, gravità) | 10 giorni lavorativi |
| Correzione per i casi gravi (atto scaduto o non pubblico raggiungibile, documento scaricabile quando non dovrebbe, dati personali esposti, scrittura senza permesso) | 30 giorni dalla conferma |

Chi segnala viene tenuto informato fino alla correzione. L'avviso si pubblica quando la
versione corretta è disponibile, con il credito a chi ha segnalato, se lo desidera. Chiediamo
di non divulgare il problema prima di allora.

## Versioni supportate

Il plugin è in sviluppo (versione `0.x`) e non è ancora destinato alla produzione. Le
correzioni di sicurezza si fanno solo sul ramo `main` e sull'ultima versione pubblicata.

| Versione | Supportata |
|---|---|
| ultima versione pubblicata e `main` | sì |
| versioni precedenti | no |

## Che cosa è in ambito

- Il codice di questo repository: alberatura della sezione, stato degli obblighi, pagine
  pubbliche, schermate di amministrazione, lettura dei file di dati.
- In particolare: un dato che resta pubblico oltre la sua durata, o che viene cancellato
  invece di uscire dalla sezione; scritture senza il permesso dedicato; mancato controllo di
  nonce o capability; un file di dati letto da un percorso scelto dall'esterno; iniezione
  SQL, XSS, CSRF; dati personali esposti dove non servono.

## Che cosa è fuori ambito

- WordPress, PHP, il server e la loro configurazione.
- Il componente comune `conformita-core`: si segnala nel suo repository, con la stessa
  procedura.
- Temi e altri plugin, salvo che il difetto stia nel modo in cui questo plugin li usa.
- I limiti già dichiarati in `docs/sicurezza.md` e nelle schede di `docs/implementazione/`.
- L'indicizzazione delle pagine della sezione: è voluta, perché la norma la impone.
- Problemi che richiedono un account che abbia già il permesso di fare la stessa cosa
  dall'interfaccia.

## I principi di sicurezza del plugin

Il dettaglio, controllo per controllo, è in [`docs/sicurezza.md`](docs/sicurezza.md). I
principi in breve:

- **Nel dubbio il sistema si chiude.** Se un controllo non riesce a stabilire se un dato è
  pubblicabile, non lo mostra; se un file di dati o una lettura della banca dati non
  risponde, l'operazione si ferma. Un errore produce un rifiuto, mai un permesso silenzioso.
- **La durata è una proprietà del dato letto.** Un dato scaduto esce dalla sezione su ogni
  strada anche se il compito pianificato non è mai stato eseguito, e resta in archivio.
- **Dati, non codice.** Alberatura e schemi sono file di dati; il codice non contiene i loro
  campi.
- **Permessi dedicati** controllati con `current_user_can()`, nonce insieme al permesso e mai
  al suo posto, `permission_callback` su ogni rotta.
- **Il server decide** stato, date e autore; un campo inviato dal browser che non gli spetta
  si ignora o fa rifiutare la richiesta.
