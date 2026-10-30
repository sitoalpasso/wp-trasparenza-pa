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
| TRASP-02 | in corso | Si visita ogni pagina della sezione da utente anonimo (righe T-71, T-79 per i contenuti delle voci e i loro documenti) | tutto accessibile senza autenticazione |
| TRASP-03 | da fare | Si ispezionano meta robots, robots.txt e sitemap della sezione | nessun noindex, nessun blocco, URL in sitemap |
| TRASP-04 | fatto | Si confronta l'alberatura generata con l'Allegato A, voce per voce (righe T-20..T-25) | ogni nodo previsto esiste, denominazione conforme; esiste "Controlli sulle attività economiche" e non esiste "Controlli sulle imprese" |
| TRASP-05 | da fare | Si compilano i tre obblighi; si caricano le fixture ufficiali ANAC, integre e corrotte | solo campi strutturati; il dato valida; le fixture integre passano, le corrotte no con errore sul campo |
| TRASP-06 | da fare | Si aggiunge uno schema come solo file di definizione | funziona senza modifiche al PHP |
| TRASP-06 | da fare | [attacco, statico] si cercano i nomi dei campi degli schemi nei sorgenti PHP | assenti |
| TRASP-07/20 | da fare | Si esporta uno schema popolato | formato aperto, condizioni di riutilizzo come parametro, nessun PDF da scansione |
| TRASP-08 | in corso | Dato con obbligo decorrente nell'anno X (righe T-63..T-66 per i contenuti delle voci); atto che produce effetti oltre (righe T-81..T-83); dati di titolari e consulenti (righe T-84..T-89) | scadenza al 31 dicembre di X+5; casi a cavallo d'anno inclusi; oltre, fino all'ultimo giorno degli effetti, mai prima di quella di legge; per titolari e consulenti, tre anni dopo la cessazione; per i dati patrimoniali, il giorno della cessazione |
| TRASP-09 | da fare | Dato scaduto, cron fermo, tutti i percorsi C-10..C-21 | fuori dalla sezione, non cancellato, in archivio controllato |
| TRASP-10 | in corso | Ogni sottosezione e ogni voce (righe T-71..T-74 per i contenuti delle voci) | data di ultimo aggiornamento esposta |
| TRASP-11 | da fare | Voce assolta per collegamento | il link porta al dato reale, non a una pagina vuota |
| TRASP-12 | da fare | Si invia una richiesta di accesso civico di prova | modulo raggiungibile, destinatari indicati, richiesta nel registro |
| TRASP-13 | fatto | Responsabile della trasparenza (righe T-54..T-58) | indicato da chi ha il permesso della sezione, con nome e recapiti controllati; mostrato nella voce prevista dall'Allegato 1 della delibera ANAC 1310/2016; se manca, chi può rimediare lo vede |
| TRASP-14 | da fare | Campo PIAO | configurabile e mostrato dove previsto |
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
| TRASP-27 | fatto | Sottosezione dichiarata non applicabile (righe T-13..T-15, T-48) | stampa carattere per carattere la dicitura obbligata; lo stato sospeso esiste; la sottosezione vuota senza stato è segnalata |
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

Provano il file dell'albero in vigore, `dati/alberatura/2026-10-29.json`, contro le fonti: Allegato A del d.lgs.
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

## Motore degli schemi (righe T-26..T-33)

Provano il motore con schemi finti scritti per le prove, nella stessa forma dei file ANAC
(draft-07, riferimenti esterni a indirizzi assoluti). Gli schemi ufficiali entrano come file
di dati e hanno prove loro. La libreria di validazione e opis/json-schema (Apache-2.0),
distribuita con i nomi prefissati.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-26 | fatto | Libreria nel pacchetto distribuito | presente solo con i nomi prefissati; con una seconda copia della stessa libreria gia caricata da un altro componente la validazione funziona e usa la propria |
| T-27 | fatto | Catalogo degli schemi valido | caricato; per ogni versione: obbligo, versione, data di approvazione ANAC, data di entrata in vigore, fonte, file; versioni dello stesso obbligo in ordine di entrata in vigore; un file che comincia con il segno BOM, come quelli ufficiali, si legge |
| T-28 | fatto | Catalogo difettoso, un caso per difetto: file assente, JSON non valido, formato sconosciuto, versione doppia per lo stesso obbligo (stesso numero e stessa data di approvazione), due file con lo stesso identificativo di schema nella stessa versione, data non valida, fonte mancante, file dello schema assente, schema non JSON, riferimento esterno senza file locale, file comune elencato da una versione ma assente dal catalogo, rimando a una definizione che non esiste nel file indicato (in un altro file o nello stesso) | rifiutato con un codice d'errore diverso per ogni difetto; nessun catalogo parziale |
| T-29 | fatto | Dato contro lo schema finto | il dato conforme passa; un dato difforme e respinto e l'errore indica il percorso del campo e la regola violata |
| T-30 | fatto | Validazione senza rete | i riferimenti a indirizzi esterni si risolvono solo sui file locali del catalogo; nessuna richiesta di rete parte, anche quando la rete e disponibile |
| T-31 | fatto | Due versioni dello stesso obbligo (TRASP-26) | il dato nuovo prende la versione in vigore alla data di oggi nel fuso del sito; il dato raccolto con la versione precedente resta valido e si valida con la sua; una versione non ancora in vigore non si sceglie da sola |
| T-32 | fatto | [statico] nomi dei campi di tutti gli schemi, quelli di `schemas/` e quelli finti delle prove, nei sorgenti PHP (TRASP-06) | assenti. Non vacuita: un nome di campo scritto in un commento di `src/` rende rossa la prova; ogni file di schema ufficiale deve dare almeno un nome, e la raccolta deve contenerne uno degli schemi ufficiali, cosi la prova non resta verde se smette di leggerli |
| T-33 | fatto | Schema in piu aggiunto come solo file (TRASP-06) | un terzo schema finto, aggiunto al catalogo delle prove senza toccare il PHP, si carica e valida |

## Schemi ufficiali ANAC (righe T-34..T-36)

I file dell'Autorita come dati, scaricati dalla Guida Online del Portale Servizi ANAC, e gli
esempi compilati che la stessa guida pubblica. Si valida sempre contro quei file: se un
esempio ufficiale non passa, il difetto e nostro.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-34 | fatto | Catalogo ufficiale (TRASP-05, TRASP-26) | `schemas/catalogo.json` si carica; ha gli obblighi degli artt. 4-bis, 13 e 31, ciascuno con una versione in vigore oggi, date e fonte all'indirizzo ANAC; ogni file e identico byte per byte a quello scaricato (impronta SHA-256 nel catalogo) e il suo nome coincide con la fine del suo `$id`. Non vacuita: un byte cambiato in uno schema rende rossa la prova |
| T-35 | fatto | Esempi ufficiali ANAC (TRASP-05) | i sette esempi compilati della guida (art. 13: pubbliche amministrazioni, ordini e collegi, societa ed enti; art. 31: organismo indipendente di valutazione, organi di revisione, Corte dei conti; art. 4-bis), cosi come scaricati e quindi con il segno BOM, passano senza nessuna richiesta di rete |
| T-36 | fatto | Esempi ufficiali rotti apposta (TRASP-05), un caso per rottura: campo obbligatorio tolto, data non GG/MM/AAAA, campo non previsto, valore fuori elenco, recapito senza posta elettronica, importo senza decimali, trimestre fuori intervallo, indirizzo del documento senza protocollo | ciascuno respinto, con il percorso del campo sbagliato e la regola violata |
| T-37 | fatto | Stesso numero di versione, stesso identificativo, contenuto diverso (TRASP-26) | due versioni "1.0" dello stesso obbligo con date di approvazione diverse, file con lo stesso `$id` e un file comune ripubblicato anch'esso con lo stesso `$id`, si caricano insieme; la versione si indica con numero e data (`1.0@AAAA-MM-GG`, il solo numero non basta); ogni versione si controlla solo con i suoi file, quindi un dato passa con una e non con l'altra; ogni versione si dice a parole con la sua data, per esempio "versione 1.0 del 10 giugno 2026" |

## Voce e modulo di compilazione (righe T-38..T-45)

Il tipo della voce, i permessi, la pubblicazione chiusa (T2c-1), poi il modulo generato dallo schema (T2c-2). La pubblicazione si apre con gli stati editoriali (T5).

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-38 | fatto | Tipo della voce di trasparenza (TRASP-05) | registrato attraverso il componente comune nella sezione della trasparenza, con `show_in_rest` dichiarato falso; una richiesta anonima all'indirizzo di una voce non la mostra; il menu compare in amministrazione |
| T-39 | fatto | Permessi della voce | chiesti al componente comune e assegnati all'amministratore a ogni cambio della versione installata; un utente Autore o Redattore di WordPress non vede e non modifica le voci. [statico] i nomi dei permessi derivati non compaiono nei sorgenti |
| T-40 | fatto | Pubblicazione chiusa fino agli stati editoriali (TRASP-28) | ogni tentativo di pubblicare, dalla schermata, da codice o per programmazione, lascia la voce in bozza con il motivo; dopo ogni strada lo stato riletto e l'ultimo cambio di stato nel registro del componente comune dicono la stessa cosa; un aggancio al cambio di stato con priorita normale legge gia la bozza nella banca dati. Limite dichiarato fino a T5: gli agganci specifici del passaggio a pubblicata partono ancora |
| T-41 | fatto | Modulo generato dallo schema (TRASP-05, TRASP-06) | per l'art. 13 il modulo ha un campo per ogni proprieta raggiunta dallo schema, con gruppi per gli oggetti, serie per gli elenchi, scelta per gli elenchi chiusi di valori ed etichetta presa dallo schema; un quarto schema finto, aggiunto come solo file, produce il suo modulo senza toccare il PHP |
| T-42 | fatto | Giro completo con l'esempio ufficiale (TRASP-05, TRASP-26) | l'esempio dell'art. 13 inserito nel modulo e salvato da un dato identico all'esempio, conforme; la voce conserva obbligo e codice di versione; il riquadro dice con quale versione, per esempio "versione 1.0 del 10/06/2026". Il giro resta identico anche con un indirizzo che contiene %20 e un carattere UTF-8 codificato, e con un organo senza uffici (elenco obbligatorio vuoto), dopo due salvataggi |
| T-43 | fatto | Dato difforme dal modulo | salvato in bozza con i difetti, ciascuno mostrato in italiano accanto al campo del suo percorso; un campo vuoto non entra nel dato come testo vuoto; l'esito vale solo per il dato da cui e nato: se il dato salvato cambia per un'altra strada, la voce risulta non controllata; un invio troncato dal limite di campi del server (manca il marcatore dopo l'ultimo campo) non tocca dato ed esito e lascia un avviso con il limite, e vicino al limite il riquadro avvisa prima dell'invio |
| T-44 | fatto | Versione fissata alla raccolta (TRASP-26) | una voce raccolta con una versione si ricontrolla con quella anche quando ne entra in vigore una nuova; il modulo non cambia obbligo ne versione di una voce gia legata; il primo legame non si scrive se un'altra richiesta l'ha gia scritto nel frattempo |
| T-45 | fatto | Sicurezza del salvataggio | senza nonce valido o senza il permesso sulla voce non si scrive niente; il nonce vale per una sola voce, e quello di una voce non scrive su un'altra; un valore con marcatori HTML esce come testo, non come marcatori |

## Schermata di gestione degli stati (righe T-46..T-53)

Provano la schermata in cui l'amministrazione dichiara lo stato di ogni voce, il permesso
che la apre e la traccia che ogni cambio lascia nel registro delle modifiche del meccanismo
comune. Scheda: `docs/implementazione/gestione-stati.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-46 | fatto | Prima installazione; permesso poi tolto dall'amministratore; tolto e con la lettura dell'assegnazione fallita; ruolo di amministratore assente | il permesso va al solo amministratore, una volta; tolto, non torna alla richiesta dopo; con la lettura fallita i ruoli non si toccano e chi gestisce i componenti vede l'errore; senza il ruolo niente si assegna e la prima installazione resta da fare |
| T-47 | fatto | Nessun ruolo ha il permesso della sezione | avviso a chi gestisce i componenti, con il nome del permesso; nessun avviso a chi non li gestisce, ne quando un ruolo lo ha |
| T-48 | fatto | Menu e schermata, con e senza permesso | la voce di menu chiede il permesso della sezione; amministratore del sito senza quel permesso: schermata non agganciata e rifiutata se aperta; con il permesso: tutte le voci tranne la radice, "Da scegliere" solo per le non dichiarate, stato attuale selezionato, effetti spiegati con le frasi dell'albero, conteggio delle voci senza stato (TRASP-27) |
| T-49 | fatto | [attacco] Salvataggio senza permesso, senza nonce, con il nonce di un'altra azione, con un nonce non testuale, con il nonce di un altro utente | rifiutato; niente scritto, niente nel registro |
| T-50 | fatto | Salvataggio valido; campo vuoto; [attacco] richiesta con una voce buona e un difetto: voce inesistente, valore fuori dai tre o con uno spazio, radice, valore non testuale, identificativo numerico, campo che non e un elenco; [attacco] stato di partenza mancante, fuori dai tre, non testuale | i cambi validi si salvano, il campo vuoto non cambia niente e uno stato dichiarato non torna non dichiarato; con un difetto non si scrive niente, nemmeno la voce buona |
| T-51 | fatto | Cambi di stato, stato rimandato uguale, registro che rifiuta la seconda scrittura, stato non scritto alla seconda scrittura; lettura degli stati fallita con altre voci dichiarate; valore conservato che non e un elenco | una voce di registro per cambio, con voce, stato prima e dopo, utente, senza contenuto; lo stato uguale non si registra; il rifiuto del registro ferma quel cambio e i successivi, l'esito dice quanti sono passati; lo stato non scritto ferma il salvataggio, l'esito non è "salvato" e dice che il registro ha un cambio non avvenuto; con la lettura fallita o il valore che non e un elenco non si scrive niente, né stato né registro, e le altre voci restano dichiarate |
| T-52 | fatto | Albero non disponibile; sezione non registrata | salvataggio rifiutato e schermata senza modulo, con il motivo; senza sezione nessuna voce di menu e nessun permesso assegnato |
| T-53 | fatto | [attacco] Un altro salvataggio tiene il blocco; lo stato cambia nella banca dati dopo che la richiesta lo ha letto; due moduli completi aperti sullo stesso stato, poi un terzo che cambia in modo diverso una voce già cambiata | rifiutato dopo l'attesa, niente scritto, poi di nuovo possibile; lo stato di partenza si rilegge dalla banca dati, quindi il "prima" del registro e quello vero e il cambio dell'altra richiesta resta; il modulo porta lo stato che mostrava e si applicano solo le voci cambiate, quindi il secondo modulo non riporta indietro le voci del primo; il terzo e un conflitto, non salva niente e nomina la voce |

## Responsabile della trasparenza (righe T-54..T-58)

Provano dove il file dell'albero dice di mostrare i dati che non sono voci, la schermata
in cui l'amministrazione indica il responsabile della prevenzione della corruzione e della
trasparenza, e il riquadro che la pagina pubblica ne mostra. Scheda:
`docs/implementazione/responsabile.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-54 | fatto | File dell'albero con collocazioni, senza, e con collocazioni sbagliate: non elenco, voce inesistente, radice, nome del dato non valido o numerico, voce non testuale | lette quelle valide; senza, nessuna collocazione inventata; ogni sbaglio rifiuta il file con `trasparenza_albero_collocazione` |
| T-55 | fatto | Collocazione del responsabile nell'albero distribuito; versione dell'albero che la porta | voce "Prevenzione della Corruzione" sotto "Altri contenuti", con il testo atteso scritto nella prova; fonte dichiarata e presente fra le fonti del file; la collocazione arriva con la versione 2026-10-28, in un file suo, uguale alla 2026-10-24 salvo le collocazioni; la 2026-10-24 resta accanto, con l'impronta attesa scritta nella prova |
| T-56 | fatto | Dati validi, minimi, [attacco] sbagliati: nome vuoto, con codice, su due righe, oltre 200 caratteri, non testuale; email vuota, non valida, con codice; telefono con lettere o codice; atto di nomina con schema `javascript:`, senza schema, relativo, senza protocollo, con virgolette o spazi; dati conservati estranei | i validi passano ripuliti dagli spazi ai bordi, telefono e nomina facoltativi; ogni sbaglio si rifiuta invece di salvarlo cambiato; i dati conservati estranei valgono come non indicati |
| T-57 | fatto | Menu e schermata con e senza permesso; [attacco] salvataggio senza permesso, senza nonce, con il nonce di un'altra azione, non testuale o di un altro utente; salvataggio valido, uguale, sbagliato; [attacco] valori di partenza mancanti o non testuali; due moduli completi aperti insieme, su campi diversi e sullo stesso campo; [attacco] due moduli insieme con un rifiuto rimostrato e reinviato; lettura dei dati attuali fallita; registro che rifiuta; dati non scritti; [attacco] un altro salvataggio tiene il blocco; avviso | sottovoce con il permesso della sezione, schermata rifiutata senza; rifiuti senza scrivere niente; una voce di registro per salvataggio con i soli nomi dei campi cambiati e nessun valore personale; dati uguali non registrati; con uno sbaglio niente scritto e il modulo rimostra quanto inviato; il modulo porta i valori della banca dati all'apertura, anche quando rimostra quanto inviato; partenza mancante: niente scritto; due moduli su campi diversi: si applicano solo i campi cambiati, e il registro dice solo quelli; sullo stesso campo: conflitto, niente scritto e niente nel registro; il modulo rimostrato dopo un rifiuto porta le partenze della richiesta rifiutata, e il reinvio non riporta indietro i campi cambiati da altri; lettura fallita: niente scritto e niente nel registro, i dati di prima restano; registro che rifiuta: niente scritto; dati non scritti: esito di errore che dice del cambio non avvenuto; blocco tenuto: rinuncia senza scrivere; avviso solo a chi ha il permesso, sulla bacheca e sulle schermate della sezione, finché il responsabile manca |
| T-58 | fatto | Pagina pubblica con il responsabile indicato, senza campi facoltativi, non indicato, albero senza collocazione | riquadro solo nella voce indicata, dati in uscita sicura, recapito email come collegamento; righe facoltative assenti se vuote; nessun riquadro senza dati o senza collocazione |

## Contenuti delle voci (righe T-59..T-62)

Provano il contenuto libero della sezione, testo con titolo, e la scelta della voce in cui
sta. Scheda: `docs/implementazione/contenuti-voci.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-59 | fatto | Tipo registrato; [attacco] richiesta anonima per identificativo e per ricerca di un contenuto pubblicato con fine lontana; sezione non registrata | il tipo passa dal componente comune nella sezione, fuori da REST, non pubblico, sottovoce del menu "Trasparenza", con testo e revisioni; l'anonimo non lo trova; senza sezione il tipo non si registra e i permessi non si assegnano |
| T-60 | fatto | Permessi del tipo tolti e riassegnati dall'installazione; ruoli Autore, Redattore, Collaboratore | permessi propri del tipo, diversi da quelli della voce, all'amministratore; gli altri ruoli non vedono e non modificano |
| T-61 | fatto | Riquadro con e senza voce, con una voce che l'albero non ammette piu, albero di una sola voce; salvataggio valido, cambio, stessa voce; [attacco] radice, voce con sottovoci, inesistente, con codice, non testuale; partenza mancante o non testuale; nonce mancante o di un altro contenuto; contenuto di un altro tipo; utente senza permesso; due moduli aperti insieme; lettura fallita; più voci memorizzate, scelta dal riquadro con e senza partenza, registro che rifiuta, voce nuova non scritta; albero non disponibile; registro che rifiuta; dato non scritto; blocco tenuto; salvataggio di due contenuti uno dopo l'altro, con e senza riquadro | solo le voci senza sottovoci, con il percorso, mai la radice; "Da scegliere" e l'avviso finché manca una voce valida; ogni cambio lascia una voce di registro con prima e dopo, scritta prima del dato; la stessa voce non si registra; ogni rifiuto e ogni guasto: niente scritto e niente nel registro, con il motivo; il secondo modulo che cambia la voce e un conflitto, quello che sceglie la voce gia scritta no; dato non scritto: esito che dice del cambio non avvenuto; più voci: il riquadro lo dice, la scelta con partenza vuota lascia una voce sola dopo una voce di registro con quelle trovate, con partenza è un conflitto, con il registro che rifiuta le righe restano, con la voce non scritta l'esito lo dice; l'esito si mostra una volta, solo a chi ha salvato e solo sulla schermata di quel contenuto, e due salvataggi vicini di contenuti diversi non si confondono; senza riquadro la voce non si tocca |
| T-62 | fatto | Creazione, pubblicazione, modifica e cestino di un contenuto | righe del registro del componente comune con sezione, tipo, contenuto e utente, compresi pubblicazione e rimozione |

## Fine della pubblicazione dei contenuti (righe T-63..T-66)

Provano la data da cui decorre l'obbligo e la fine della pubblicazione calcolata secondo
l'art. 8, c. 3, del d.lgs. 33/2013 (TRASP-08). Scheda: `docs/implementazione/fine-pubblicazione.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-63 | fatto | Durata nell'albero in vigore; versione che la porta; [attacco] durate non valide nel file | cinque anni, numero scritto nella prova, con la fonte fra le fonti del file; la durata arriva con la versione 2026-10-28-2, in un file suo, uguale alla 2026-10-28 salvo durata e fonte; la 2026-10-28 resta accanto, con l'impronta attesa scritta nella prova, e si legge senza durata; zero, negativi, testo, decimali, vero, nullo, chiavi in più o diverse: il file si rifiuta; senza durata il file si carica e la durata non c'è |
| T-64 | fatto | Fine per legge da decorrenze a cavallo d'anno, 29 febbraio, durate diverse, limite del 9999; [attacco] date non vere o scritte in altro modo; albero con un'altra durata | 31 dicembre di tanti anni dopo la decorrenza quanti ne dice l'albero; oltre il 9999 nessuna fine; solo AAAA-MM-GG vere del calendario, senza spazi, a capo o altro intorno; la durata si legge dall'albero in vigore, non dal codice |
| T-65 | fatto | Riquadro con e senza data, con un valore con codice; salvataggio, stessa data, cambio; [attacco] date non valide, data non testuale, partenza mancante o non testuale, nonce mancante o di un altro contenuto, contenuto di un altro tipo, utente senza permesso, data cancellata; due moduli aperti insieme; lettura fallita della data o della fine; più date memorizzate; albero non disponibile o senza durata; registro che rifiuta; data non scritta; fine rifiutata da core; versione dell'albero con un'altra durata; blocco tenuto; salvataggio di due contenuti uno dopo l'altro, con e senza riquadro | avviso finché il contenuto non ha una fine valida; fine mostrata; valori non validi mai mostrati come data e sempre con escaping; ogni cambio lascia una voce di registro con data, ultimo giorno degli effetti, cessazione e fine di prima e di dopo, scritta prima dei dati; la stessa data con la fine giusta non si registra; ogni rifiuto e ogni guasto: niente scritto e niente nel registro, con il motivo; la data scritta non si toglie; il secondo modulo che cambia la data è un conflitto; più date: il riquadro lo dice, con partenza è un conflitto, con partenza vuota resta una riga dopo una voce di registro con quelle trovate; data non scritta: la fine non si tocca; fine rifiutata: l'esito lo dice, il riquadro mostra la differenza, e un nuovo salvataggio la riallinea, anche dopo un cambio di durata; l'esito si mostra una volta, solo a chi ha salvato, solo sulla schermata di quel contenuto; senza riquadro niente si tocca e nessun esito |
| T-66 | fatto | Contenuto pubblicato senza data, con la data di oggi, con decorrenza di cinque e di sei anni fa | per il componente comune: scaduto senza data; visibile con la data di oggi; visibile fino al 31 dicembre di quest'anno con decorrenza di cinque anni fa; scaduto con decorrenza di sei anni fa |

## Documenti dei contenuti (righe T-67..T-70)

Provano i documenti allegati a un contenuto, depositati dal componente comune con file veri
e la verifica della protezione fatta rispondere da un server finto. Scheda:
`docs/implementazione/documenti-contenuti.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-67 | fatto | Deposito su un contenuto in bozza e su uno pubblicato, due di seguito; [attacco] tipo di file non ammesso, file non arrivato da un caricamento HTTP, protezione non verificata; nonce mancante o di un altro contenuto, contenuto di un altro tipo, utente senza permesso; caricamento non riuscito; valori di rimozione non validi, non in elenco, con spazi o a capo, troppo lunghi; registro che rifiuta, anche con l'allegato che non si toglie o con il file che resta sul disco; blocco tenuto | il file sta nella cartella protetta con la marca del componente comune, compare fra i documenti in ordine di deposito, e lascia una voce di registro con operazione, allegato, nome e impronta del componente comune; ogni rifiuto: niente depositato, niente sul disco, niente nel registro, con il motivo; il file indicato come caricamento resta dov'era; registro che rifiuta: l'allegato appena depositato si toglie con il file, e se l'allegato o il file restano l'esito lo dice, con il nome del file e mai il percorso; nessun file e niente da togliere: nessun esito |
| T-68 | fatto | Rimozione su un contenuto in bozza, chiesta due volte nella stessa richiesta, ripetuta; contenuto pubblicato e poi rimesso in bozza; [attacco] contenuto pubblicato, allegato di un altro contenuto, allegato non depositato dal componente comune, numero con caratteri in più, altro tipo, utente senza permesso; lettura fallita degli allegati o dello stato; registro che rifiuta; allegato non cancellato; riga cancellata e file rimasto sul disco; lettura fallita dopo la cancellazione | l'allegato e il suo file spariscono dopo una voce di registro con nome e impronta; una sola rimozione per allegato; rimesso in bozza si toglie; ogni rifiuto e ogni guasto: niente tolto, niente nel registro salvo la rimozione non avvenuta, che l'esito dichiara; file rimasto: esito proprio con il nome del file e mai il percorso, e una voce di registro `file_rimasto` che richiama quella della rimozione; senza poter controllare la cancellazione l'esito chiede di controllare |
| T-69 | fatto | Riquadro senza documenti, con un documento, su un contenuto pubblicato; modulo della schermata; lettura fallita | nonce, campo del file, "Nessun allegato."; nome del file con il collegamento amministrativo del componente comune e la casella per togliere; pubblicato: nessuna casella e l'avviso su come togliere; il modulo porta i file solo sulla schermata dei contenuti; con la lettura fallita il riquadro lo dice |
| T-70 | fatto | Salvataggio del contenuto con una rimozione e un file non caricato via HTTP; secondo contenuto salvato subito dopo; salvataggio senza riquadro | la rimozione avviene, il file non si deposita e resta dov'era; gli esiti si mostrano una volta, solo a chi ha salvato, solo sulla schermata di quel contenuto, e non si confondono fra contenuti; senza riquadro niente si tocca e nessun esito |

## Contenuti sulla pagina pubblica (righe T-71..T-74)

Provano quali contenuti compaiono nella pagina pubblica della sezione, con quali date e con
quale testo. Scheda: `docs/implementazione/pagina-pubblica-contenuti.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-71 | fatto | Da anonimo, un contenuto pubblicato con voce valida e fine futura; [attacco] contenuti in bozza, in revisione, privati, pianificati, cestinati, con password, senza fine, con la fine passata, con più fini, senza voce, con più voci, con una voce che ha sottovoci o che l'albero non ha, di un altro tipo; un altro componente che allarga l'interrogazione; un contenuto con la fine oggi e l'orologio portato a domani; quattro contenuti di date diverse, due con la stessa | il primo compare solo nella sua voce, con titolo e testo, e la voce sopra non lo ripete; nessuno degli altri compare, nemmeno con l'interrogazione allargata; quello che scade sparisce senza che nessuno lo sposti, e resta pubblicato; i più recenti per primi, a parità di data l'ultimo creato |
| T-72 | fatto | Contenuti in tre voci con date di ultima modifica diverse, una alle 23:30 in UTC; voce vuota; contenuti nascosti più recenti di quelli visibili | ogni contenuto ha la sua data, la voce la più recente dei suoi, ogni voce sopra e la pagina della sezione la più recente fra quelle sotto, tutte nel fuso del sito (le 23:30 in UTC a Roma sono il giorno dopo); voce senza niente di visibile: nessuna data; i nascosti non spostano la data |
| T-73 | fatto | [attacco] titolo e testo con codice, gestori di eventi, collegamenti `javascript:` e un codice breve, scritti nella banca dati senza il filtro del salvataggio; un amministratore apre la pagina | il codice si toglie, il testo ammesso resta, il codice breve resta testo e non si esegue; l'amministratore vede esattamente la pagina del pubblico |

| T-74 | fatto | [guasto] lettura fallita dell'elenco dei contenuti, del caricamento di un contenuto, della sua voce o della sua fine; voce davvero vuota | la voce, quelle sopra e la pagina della sezione mostrano l'avviso di indisponibilità al posto di contenuti e date, senza dettagli del guasto; tolto il guasto il contenuto torna; una voce vuota non mostra l'avviso |
## Voci coperte da un modulo ANAC (righe T-75..T-77)

Provano l'elenco delle voci coperte da un modulo ANAC nel file dell'albero e l'avviso
all'addetto nel riquadro del contenuto. Scheda: `docs/implementazione/voci-con-modulo.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-75 | fatto | Elenco nell'albero in vigore; versione che lo porta | sette voci, scritte nella prova: tre per l'art. 13, tre per l'art. 31, una per l'art. 4-bis, ciascuna con un riferimento che cita l'articolo del modulo, e ogni modulo ha versioni nel catalogo degli schemi; fonte dichiarata e presente fra le fonti del file; l'elenco arriva con la versione 2026-10-29, ora in vigore, uguale alla 2026-10-28-2 salvo elenco e fonte; la 2026-10-28-2 resta accanto, con l'impronta attesa scritta nella prova, e si legge senza moduli |
| T-76 | fatto | [attacco] elenchi non validi nel file: non un oggetto, senza nomi, identificativo strano o troppo lungo, modulo non oggetto, senza nome o senza voci, chiave in più, nome vuoto, non testo o troppo lungo, voci vuote o con nomi, voce non testo, inesistente, radice, con sottovoci, ripetuta, in due moduli; nome con spazi ai bordi; elenco vuoto; senza elenco | ogni sbaglio rifiuta il file con `trasparenza_albero_moduli`; il nome si ripulisce; elenco vuoto e file senza elenco si caricano, e nessuna voce ha un modulo |
| T-77 | fatto | Riquadro di un contenuto collocato in una voce degli artt. 13, 31 e 4-bis; in una voce senza modulo, in una con sottovoci, in nessuna; utente che non può creare voci; catalogo senza versioni per quei moduli; catalogo non leggibile | avviso con il nome del modulo e la versione in vigore detta con la sua data, che i dati del modulo restano in bozza e che al pubblico arriva il testo, e collegamento per compilare il modulo; nessun avviso senza modulo; senza permesso l'avviso c'è e il collegamento no; senza versione in vigore e senza catalogo l'avviso lo dice, senza collegamento |

## Documenti scaricabili dalla pagina pubblica (righe T-78..T-80)

Provano i documenti dei contenuti sulla pagina pubblica e il loro scaricamento dal punto
pubblico del componente comune, con file veri depositati e risposte raccolte al posto
dell'emissione. Scheda: `docs/implementazione/documenti-pubblici.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-78 | fatto | Contenuto con due documenti; contenuto senza documenti; documento cestinato; [guasto] riga del file registrato mancante, file sparito dal disco; [guasto] lettura fallita dei documenti | sotto il contenuto l'elenco dei documenti, in ordine di deposito, con l'indirizzo pubblico del componente comune e il nome del file, mai l'indirizzo della cartella dei caricamenti; senza documenti nessun elenco; il cestinato non si elenca e non si scarica; senza la riga del file o senza il file sul disco il nome resta (il titolo dell'allegato se manca la riga), senza collegamento e con "(file non disponibile)"; con la lettura fallita l'avviso di indisponibilità al posto di contenuti e date |
| T-79 | fatto | Da anonimo, documento di un contenuto mostrato sulla pagina; [attacco] contenuto pubblicato senza voce, con una voce che ha sottovoci, con una voce che l'albero non ha, con più voci; in bozza, con password, con la fine passata; [guasto] lettura fallita della voce | il primo si scarica; tutti gli altri no, anche con l'indirizzo, e non compaiono sulla pagina |
| T-80 | fatto | La funzione dichiarata al componente comune chiamata con il contenuto come testo, nullo, zero, di un altro tipo, e senza albero in vigore | sempre no; senza albero anche il punto pubblico rifiuta |

## Pubblicazione finché l'atto produce effetti (righe T-81..T-83)

Provano la seconda data del riquadro "Pubblicazione", l'ultimo giorno in cui l'atto produce
effetti, secondo l'art. 8, c. 3, del d.lgs. 33/2013 (TRASP-08). Scheda:
`docs/implementazione/effetti-oltre-cinque-anni.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-81 | fatto | Fine con effetti assenti, lo stesso giorno della decorrenza, prima, uguale e dopo la fine di legge, al 9999, con un'altra durata; decorrenza oltre il 9999; [attacco] effetti non veri o scritti in altro modo, non testuali, partenza non testuale, campo o partenza mancanti, effetti senza decorrenza, effetti prima della decorrenza | la più lontana fra la fine di legge e gli effetti, mai prima di quella di legge; senza fine di legge nessuna fine; ogni attacco: niente scritto, niente nel registro, con il motivo; lo stesso giorno della decorrenza si accetta |
| T-82 | fatto | Riquadro con e senza effetti, con effetti che prolungano e che non prolungano; salvataggio, stessa data, cambio, rimozione; [attacco] due moduli aperti insieme, rimozione di una data non vista, valore con codice, lettura fallita degli effetti, più date degli effetti memorizzate; [guasto] effetti non scritti o non tolti | campo e partenza nel riquadro; il riquadro dice se la fine viene dagli effetti o se gli effetti non la prolungano; ogni cambio, rimozione compresa, lascia una voce di registro con effetti e fine di prima e di dopo; la stessa data non si registra; il secondo modulo e la rimozione non vista sono conflitti; valori non validi mai mostrati come data e sempre con escaping; lettura fallita: niente scritto e niente nel registro; più date: il riquadro lo dice, con partenza è un conflitto, con partenza vuota resta quella scritta o nessuna, dopo una voce di registro con quelle trovate; effetti non scritti o non tolti: l'esito lo dice e la fine non si tocca |
| T-83 | fatto | Contenuto pubblicato con decorrenza di sei anni fa, senza effetti, con effetti fino a oggi, fino a ieri, fino al prossimo anno, con gli effetti tolti | per il componente comune: scaduto senza effetti; visibile con effetti fino a oggi compreso; scaduto con effetti fino a ieri; visibile con effetti fino al prossimo anno; scaduto di nuovo con gli effetti tolti |

## Tre anni dalla cessazione per titolari e consulenti (righe T-84..T-89)

Provano le voci che l'albero lega alla cessazione e la data di cessazione del riquadro
"Pubblicazione", secondo gli artt. 14, c. 2, e 15, c. 4, del d.lgs. 33/2013, fatti salvi
dall'art. 8, c. 3 (TRASP-08). Scheda: `docs/implementazione/cessazione-per-voce.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-84 | fatto | Elenco nell'albero in vigore; versione che lo porta | sei voci, scritte nella prova: cinque dell'art. 14, c. 2, e una dell'art. 15, c. 4, ciascuna con un riferimento che cita il suo articolo, tre anni, il nome della regola e la fonte fra le fonti del file, su Normattiva; zero anni per i dati patrimoniali nella regola dell'art. 14, nessun termine in quella dell'art. 15; l'elenco arriva con la versione 2026-10-29-2, uguale alla 2026-10-29 salvo elenco e fonti; la 2026-10-29 resta accanto, con l'impronta attesa scritta nella prova, e si legge senza elenco |
| T-85 | fatto | [attacco] elenchi non validi nel file: non un oggetto, senza nomi, identificativo strano o lungo, regola non oggetto, chiavi mancanti o in più, nome vuoto o lungo, anni zero, testo o decimali, fonte non testo o non fra le fonti, anni dei dati patrimoniali negativi, testo, decimali o oltre gli anni della regola, voci vuote o con nomi, voce non testo, inesistente, radice, con sottovoci, ripetuta, in due regole | il file si rifiuta; il nome si ripulisce dagli spazi; gli anni dei dati patrimoniali fino a quelli della regola si accettano; un elenco vuoto, o nessun elenco, si accetta e nessuna voce segue la cessazione |
| T-86 | fatto | Fine da cessazioni a cavallo d'anno, dal 29 febbraio, con anni diversi, al limite del 9999 | lo stesso giorno, tanti anni dopo; dal 29 febbraio il 1° marzo se il giorno manca; oltre il 9999 nessuna fine |
| T-87 | fatto | Riquadro in una voce della cessazione e fuori; salvataggio, stessa data, proroga, rimozione; voce cambiata dopo la cessazione; voce e cessazione nello stesso salvataggio; [attacco] date non valide o non testuali, campo o partenza mancanti, cessazione prima della decorrenza, con la fine degli effetti, senza decorrenza, oltre il 9999, in una voce senza regola, senza voce, con più voci; due moduli aperti insieme, rimozione non vista, valore con codice, lettura fallita della cessazione o della voce, più date memorizzate; [guasto] cessazione non scritta o non tolta | campo visibile solo nelle voci della cessazione, o se una data c'è già; la fine viene dalla cessazione e il riquadro lo dice; senza cessazione torna la regola generale e il riquadro chiede la data; ogni cambio lascia una voce di registro con cessazione e fine di prima e di dopo; ogni attacco: niente scritto, niente nel registro, con il motivo; voce cambiata: il riquadro chiede di togliere la data e la differenza, il salvataggio con la data si rifiuta, senza la data riallinea; nello stesso salvataggio la voce si scrive prima; più date: con partenza conflitto, senza resta quella scritta, con quelle trovate nel registro; non scritta o non tolta: l'esito lo dice e la fine non si tocca |
| T-88 | fatto | Contenuto pubblicato con decorrenza di dieci anni fa, senza cessazione, con cessazione di tre anni fa oggi, di tre anni e un giorno fa, con fine prevista fra un anno | per il componente comune: scaduto senza cessazione; visibile fino a tre anni dopo la cessazione compresi; scaduto il giorno dopo; visibile con l'incarico in corso |
| T-89 | fatto | Riquadro in una voce dell'art. 14 e in una dell'art. 15; casella spuntata con e senza cessazione, stessa casella, tolta; [attacco] valori della casella diversi da spuntata, partenza mancante o non testuale, casella cambiata da un altro, casella nella voce dell'art. 15 o in una voce senza regola, valori strani o più righe memorizzati; [guasto] lettura fallita, casella non tolta | casella solo dove la regola ha un termine per i dati patrimoniali; spuntata: la fine è il giorno della cessazione, l'esito lo dice e la cessazione è obbligatoria; tolta: tornano i tre anni; ogni cambio è nel registro con la casella di prima e di dopo; ogni attacco: niente scritto, niente nel registro, con il motivo; valori strani mai mostrati come spuntata e sostituiti da una riga, con quelli trovati nel registro; lettura fallita o casella non tolta: l'esito lo dice e la fine non si tocca |

## Rinvii alle banche dati nazionali (righe da T-92)

Provano l'adempimento per collegamento a una banca dati nazionale: l'elenco nel file
dell'albero, la dichiarazione dell'ente e la pagina pubblica, secondo l'art. 9-bis e
l'Allegato B del d.lgs. 33/2013 e il d.l. 19/2026, art. 8, cc. 2 e 3 (TRASP-11, TRASP-25,
TRASP-29). Scheda: `docs/implementazione/rinvii-banche-dati.md`.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| T-92 | fatto | Elenco nell'albero in vigore; versione che lo porta; voce dei bandi di concorso; voce senza rinvii | undici banche dati, scritte nella prova con nome, voci, fonte fra quelle del file e, per le dieci dell'Allegato B, il titolare; Soldi pubblici senza titolare; SIQuEL con il limite agli enti locali, REMS con quello agli immobili di proprietà statale; una voce può avere più banche dati, nell'ordine del file; la voce dei bandi di concorso nessuna; l'elenco arriva con la versione 2026-10-30, uguale alla 2026-10-29-2 salvo elenco e fonti; la 2026-10-29-2 resta accanto, con l'impronta attesa scritta nella prova |
| T-93 | fatto | [attacco] elenchi non validi nel file: non un oggetto, senza nomi, identificativo strano o lungo, banca dati non oggetto, chiavi mancanti o in più, nome vuoto, lungo o non testo, fonte non testo o non fra le fonti, voci vuote o con nomi, voce non testo, inesistente, radice, con sottovoci, ripetuta nella stessa banca dati, titolare o limite vuoti, lunghi o non testo; voce numero al posto del testo in un albero con una voce di sole cifre; radice in un albero della sola radice | il file si rifiuta; nome, titolare e limite si ripuliscono dagli spazi; un elenco vuoto, o nessun elenco, si accetta e nessuna voce ha rinvii |

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
