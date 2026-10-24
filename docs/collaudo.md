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
| TRASP-04 | da fare | Si confronta l'alberatura generata con l'Allegato A, voce per voce | ogni nodo previsto esiste, denominazione conforme; esiste "Controlli sulle attività economiche" e non esiste "Controlli sulle imprese" |
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
| TRASP-27 | da fare | Sottosezione dichiarata non applicabile | stampa carattere per carattere la dicitura obbligata; lo stato sospeso esiste; la sottosezione vuota senza stato è segnalata |
| TRASP-28 | da fare | Passaggi fra i quattro stati editoriali | tracciati; nei due stati di difformità la segnalazione al RPCT è registrata |
| TRASP-29 | da fare | Voce-rinvio verso una banca dati nazionale | espone amministrazione titolare e destinatario dell'accesso civico; il caso senza banca dati non è configurabile come rinvio |
| art. 31 | da fare | Ente senza organismo di valutazione; nessun rilievo degli organi di controllo | il dato valida comunque: il campo facoltativo si omette, l'elenco vuoto si emette vuoto |

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
