# Collaudo: come si dimostra che funziona

Come si prova automaticamente che il plugin fa ciò che `requisiti.md` promette. Vale la
stessa impostazione dell'intero progetto: PHPUnit su un WordPress vero avviato da
`wp-env`, CI a ogni push, e un requisito non è chiuso finché il suo test non è verde.

Una particolarità di questo plugin: molte prove usano come riferimento i **file
ufficiali ANAC** (JSON Schema, esempi compilati, template CSV), versionati nel
repository con la fonte citata. Si valida sempre contro quei file, mai contro una nostra
reimplementazione: se l'esempio ufficiale non passa dal nostro modulo, il difetto è
nostro per definizione.

## I test più importanti

- **TRASP-05 con le fixture ufficiali**: gli esempi compilati pubblicati da ANAC,
  caricati nel plugin, devono essere accettati; gli stessi file corrotti apposta devono
  essere respinti con un errore che indica il campo sbagliato.
- **TRASP-06, lo schema nuovo senza toccare codice**: si aggiunge uno schema non
  obbligatorio come solo file di definizione e la sezione nuova deve funzionare senza
  modifiche al PHP. È la prova che l'architettura dichiarativa è vera e non uno slogan.
- **TRASP-09 a cron fermo**: un dato scaduto, con il compito pianificato mai eseguito,
  deve essere fuori dalla sezione ma **non cancellato**, su tutti i percorsi di lettura
  dell'elenco chiuso di core (C-10..C-21).

## Requisito per requisito

La colonna **Stato** ha gli stessi tre valori di `requisiti.md`, applicati qui al test e
non al codice: **da fare** (il test non è scritto), **in corso** (scritto e rosso, cioè
il comportamento non c'è ancora), **fatto** (scritto e verde in CI). Un requisito è
"fatto" in `requisiti.md` solo se tutte le sue righe qui sono "fatto".

| Req | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| TRASP-01 | da fare | Si controllano denominazione e collegamento in home | dicitura esatta, link presente |
| TRASP-02 | da fare | Si visita ogni pagina della sezione da utente anonimo | tutto accessibile senza autenticazione |
| TRASP-03 | da fare | Si ispezionano meta robots, robots.txt e sitemap della sezione | nessun noindex, nessun blocco, URL in sitemap |
| TRASP-04 | fatto | Si confronta l'alberatura generata con l'Allegato A, voce per voce (righe T-20..T-25) | ogni nodo previsto esiste, denominazione conforme; esiste "Controlli sulle attività economiche" e non esiste "Controlli sulle imprese" |
| TRASP-05 | da fare | Si compilano i tre obblighi; si caricano le fixture ufficiali ANAC, integre e corrotte | solo campi strutturati; il dato valida; le fixture integre passano, le corrotte no con errore sul campo |
| TRASP-06 | da fare | Si aggiunge uno schema come solo file di definizione | funziona senza modifiche al PHP |
| TRASP-06 | da fare | [attacco, statico] si cercano i nomi dei campi degli schemi nei sorgenti PHP | assenti |
| TRASP-07/20 | da fare | Si esporta uno schema popolato | formato aperto, condizioni di riutilizzo come parametro, nessun PDF da scansione |
| TRASP-08 | da fare | Dato con obbligo decorrente nell'anno X | scadenza al 31 dicembre di X+5; casi a cavallo d'anno inclusi |
| TRASP-09 | da fare | Dato scaduto, cron fermo, tutti i percorsi C-10..C-21 | fuori dalla sezione, non cancellato, in archivio controllato |
| TRASP-10 | da fare | Ogni sottosezione e ogni voce | data di ultimo aggiornamento esposta |
| TRASP-11 | da fare | Voce assolta per collegamento | il link porta al dato reale, non a una pagina vuota |
| TRASP-12 | da fare | Si invia una richiesta di accesso civico di prova | modulo raggiungibile, destinatari indicati, richiesta nel registro |
| TRASP-13/14 | da fare | Campi RPCT e PIAO | configurabili e mostrati dove previsto |
| TRASP-15 | da fare | Si carica un PDF che sembra una scansione | avviso al redattore, dichiarato come indizio |
| TRASP-16 | da fare | Flusso di pubblicazione senza la conferma sui dati personali | non si conclude |
| TRASP-17 | da fare | Collaudo congiunto con l'albo (vedi sotto) | politiche invertite entrambe corrette |
| TRASP-18 | da fare | Sottosezione "Altri contenuti - Accessibilità" | presente con dichiarazione e obiettivi |
| TRASP-19 | da fare | Pubblicazione e rimozione di una voce | voci nel registro di core |
| TRASP-21 | da fare | Nome del file generato per una sottosezione | contiene anno, due date, codice sottosezione e versione, confrontato con i template ufficiali |
| TRASP-22 | da fare | Si genera l'at_map | valida contro lo schema ufficiale; ogni URL dichiarato risponde. Il nodo dell'art. 23-bis non compare finché lo schema ufficiale non lo prevede, perché lo schema non ammette chiavi in più |
| TRASP-23 | da fare | Riga ATmap nel robots | prodotta solo se attivata, e la documentazione la chiama raccomandazione |
| TRASP-24 | da fare | Richiesta anonima esterna su ogni URL dell'at_map | HTTPS, risposta piena, nessun filtro |
| TRASP-25 | da fare | Voce-rinvio con collegamento rotto | esito negativo registrato con data, voce segnalata |
| TRASP-26 | da fare | Dato pubblicato con schema v1.0, arrivo di una v1.1 | la v1.1 si affianca, il dato v1.0 resta valido e leggibile |
| TRASP-27 | fatto | Sottosezione dichiarata non applicabile (righe T-13..T-15, T-28) | stampa carattere per carattere la dicitura obbligata; lo stato sospeso esiste; la sottosezione vuota senza stato è segnalata |
| TRASP-28 | da fare | Passaggi fra i quattro stati editoriali | tracciati; nei due stati di difformità la segnalazione al RPCT è registrata |
| TRASP-29 | da fare | Voce-rinvio verso una banca dati nazionale | espone amministrazione titolare e destinatario dell'accesso civico; il caso senza banca dati non è configurabile come rinvio |
| art. 31 | da fare | Ente senza organismo di valutazione; nessun rilievo degli organi di controllo | il dato valida comunque: il campo facoltativo si omette, l'elenco vuoto si emette vuoto |

## Avvio del componente (righe T-01..T-10)

Non chiudono nessun TRASP-xx: costruiscono il piano su cui poggiano TRASP-03 e TRASP-17,
perche le due politiche della sezione esistono solo se il componente le dichiara al
meccanismo comune. Le righe T-03 e T-05 girano in avvii separati della suite (`composer
test:senza-core`, `composer test:core-incompatibile`): il loro caso non esiste in un
processo dove il meccanismo comune e caricato e compatibile.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-01 | fatto | La dipendenza e dichiarata in due posti: intestazione `Requires Plugins` e versione di interfaccia controllata all'avvio | coerenti; WordPress rifiuta una dipendenza inesistente con il codice dedicato |
| T-02 | fatto | Il file principale si carica senza il meccanismo comune | nessuna chiamata al caricamento, avvio agganciato a `plugins_loaded` |
| T-03 | fatto | Avvio senza il meccanismo comune, con il componente fra quelli attivi | resta attivo e inerte; l'avviso nomina componente, meccanismo da installare e la sola versione richiesta; riservato a chi attiva i componenti |
| T-04 | fatto | Criterio di compatibilita sui tre casi | inferiore con stesso maggiore: no; maggiore diverso: no; superiore: si |
| T-05 | fatto | Versione di interfaccia incompatibile, in un processo separato | componente disattivato, nessuna sezione, avviso con versione richiesta e trovata |
| T-06 | fatto | Avvio con versione compatibile | attivo, sezione registrata, nessun avviso |
| T-07 | fatto | Politiche rilette dal meccanismo comune | indicizzazione consentita, scadenza archivio |
| T-08 | fatto | Avvio invocato due volte nella stessa richiesta | nessuna seconda registrazione, nessun avviso |
| T-09 | fatto | Sezione gia registrata da altri, con politiche diverse e identiche | conflitto in entrambi i casi, componente inerte, politiche altrui intatte, avviso riservato |
| T-10 | fatto | Filtro di scadenza del meccanismo comune non avviato | errore dedicato restituito, nessuna sezione, avviso presente |

## Motore dell'alberatura (righe T-11..T-19)

Provano il motore con un albero finto scritto per le prove, che non e l'albero
normativo: il motore conosce la forma del file e non il suo contenuto. L'albero vero
arriva come file di dati versionato e ha prove sue.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-11 | fatto | File dell'albero valido | caricato; la radice si chiama esattamente "Amministrazione trasparente"; i figli escono nell'ordine del file |
| T-12 | fatto | File dell'albero difettoso, un caso per difetto: file assente, JSON non valido, formato sconosciuto, dati incompleti, dicitura mancante, identificativo con caratteri non ammessi, identificativo duplicato, voce senza nome, radice assente o doppia, radice con nome diverso, padre inesistente, ciclo | rifiutato con un codice d'errore diverso per ogni difetto; nessun albero parziale |
| T-13 | fatto | Voce senza stato dichiarato | lo stato e "non dichiarato", non "applicabile"; la voce compare nell'elenco delle non dichiarate (TRASP-27) |
| T-14 | fatto | Stato impostato su una voce inesistente o con un valore fuori dai tre | rifiutato, niente scritto |
| T-15 | fatto | Voce "non applicabile" | la pagina stampa carattere per carattere "L'obbligo di pubblicazione non è applicabile all'amministrazione", con il testo atteso scritto nella prova e non letto dal file (TRASP-27) |
| T-16 | fatto | Pagina con il codice breve, visitatore anonimo | 200; la voce chiesta mostra nome, percorso e figli con i loro indirizzi stabili; senza parametro mostra la radice (TRASP-02) |
| T-17 | fatto | Parametro con una voce che non esiste | risposta 404, non la radice |
| T-18 | fatto | Pagina della sezione | nessun `noindex` nel metatag e nell'intestazione (TRASP-03); il componente non aggancia `wp_robots` e non aggiunge intestazioni; con il sito impostato a non farsi indicizzare compare un avviso per chi amministra. La politica del meccanismo comune non c'entra: la pagina e una pagina ordinaria |
| T-19 | fatto | Albero non disponibile all'avvio | avviso riservato a chi attiva i componenti; il codice breve non disegna un albero vuoto o parziale |

## Albero normativo (righe T-20..T-25)

Provano il file `dati/alberatura/corrente.json` contro le fonti: Allegato A del d.lgs.
33/2013, Allegato 1 della delibera ANAC 1310/2016, art. 23-bis. I nomi attesi sono scritti
nella prova e non letti dal file, altrimenti la prova confronterebbe il file con se stesso.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-20 | fatto | Il file distribuito con il componente | supera tutti i controlli del motore; la radice e `amministrazione_trasparente` |
| T-21 | fatto | Voci di primo livello | 23, con nomi e ordine dell'Allegato A, carattere per carattere |
| T-22 | fatto | Voce dell'art. 23-bis | esiste "Controlli sulle attività economiche" con riferimento all'art. 23-bis; "Controlli sulle imprese" non esiste in nessun livello |
| T-23 | fatto | Voci di secondo livello | per ogni voce di primo livello, i figli con nomi e ordine dell'Allegato 1 (65 in tutto); le voci sui pagamenti sotto "Pagamenti dell'amministrazione"; nessun terzo livello |
| T-24 | fatto | Identificativi | i 68 identificativi del modello `at_map` che hanno una voce nelle fonti coincidono con quelli dell'albero |
| T-25 | fatto | Dicitura e fonti | dicitura del non applicabile carattere per carattere; fonti con titolo, indirizzo o estremi e data di consultazione; fonte dichiarata per ogni dicitura |

## Schermata di gestione degli stati (righe T-26..T-33)

Provano la schermata in cui l'amministrazione dichiara lo stato di ogni voce, il permesso
che la apre e la traccia che ogni cambio lascia nel registro delle modifiche del meccanismo
comune. Scheda: `docs/implementazione/gestione-stati.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-26 | fatto | Prima installazione; permesso poi tolto dall'amministratore; ruolo di amministratore assente | il permesso va al solo amministratore, una volta; tolto, non torna alla richiesta dopo; senza il ruolo niente si assegna e la prima installazione resta da fare |
| T-27 | fatto | Nessun ruolo ha il permesso della sezione | avviso a chi gestisce i componenti, con il nome del permesso; nessun avviso a chi non li gestisce, ne quando un ruolo lo ha |
| T-28 | fatto | Menu e schermata, con e senza permesso | la voce di menu chiede il permesso della sezione; amministratore del sito senza quel permesso: schermata non agganciata e rifiutata se aperta; con il permesso: tutte le voci tranne la radice, "Da scegliere" solo per le non dichiarate, stato attuale selezionato, effetti spiegati con le frasi dell'albero, conteggio delle voci senza stato (TRASP-27) |
| T-29 | fatto | [attacco] Salvataggio senza permesso, senza nonce, con il nonce di un'altra azione, con un nonce non testuale, con il nonce di un altro utente | rifiutato; niente scritto, niente nel registro |
| T-30 | fatto | Salvataggio valido; campo vuoto; [attacco] richiesta con una voce buona e un difetto: voce inesistente, valore fuori dai tre o con uno spazio, radice, valore non testuale, identificativo numerico, campo che non e un elenco | i cambi validi si salvano, il campo vuoto non cambia niente e uno stato dichiarato non torna non dichiarato; con un difetto non si scrive niente, nemmeno la voce buona |
| T-31 | fatto | Cambi di stato, stato rimandato uguale, registro che rifiuta la seconda scrittura | una voce di registro per cambio, con voce, stato prima e dopo, utente, senza contenuto; lo stato uguale non si registra; il rifiuto del registro ferma quel cambio e i successivi, l'esito dice quanti sono passati |
| T-32 | fatto | Albero non disponibile; sezione non registrata | salvataggio rifiutato e schermata senza modulo, con il motivo; senza sezione nessuna voce di menu e nessun permesso assegnato |
| T-33 | fatto | [attacco] Un altro salvataggio tiene il blocco; lo stato cambia nella banca dati dopo che la richiesta lo ha letto | rifiutato dopo l'attesa, niente scritto, poi di nuovo possibile; lo stato di partenza si rilegge dalla banca dati, quindi il "prima" del registro e quello vero e il cambio dell'altra richiesta resta |

## Collaudo congiunto con l'albo pretorio

Con entrambi i plugin attivi: le pagine della trasparenza sono indicizzabili e in
sitemap, quelle dell'albo hanno noindex e sono fuori. Un test, due asserzioni, eseguito
su un'installazione con tutti e due: è il controllo dell'errore invisibile a occhio.

## Note di costruzione

- I file si pubblicano per sottosezione, non per articolo (lo provano i template
  ufficiali: `art.31-oiv`, `-or`, `-cc`; `art.13-op`, `-oa`, `-as`, `-org`, `-rif`).
- Il separatore dei CSV ufficiali è il punto e virgola.
- L'intestazione obbligatoria dei JSON segue la forma degli esempi ufficiali.

## La regola di crescita

Ogni bug scoperto diventa un test che prima riproduce il bug e fallisce, poi passa con
la correzione, e resta per sempre. La batteria può solo crescere.
