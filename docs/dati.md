# Dati: cosa esiste, dove sta, chi può toccarlo

La mappa dei dati del plugin, con le risposte alle domande di controllo. I nomi tecnici
precisi vengono fissati dalle schede in `docs/implementazione/`; questo file tiene la
visione d'insieme e si aggiorna nello stesso commit che introduce un dato nuovo.

## Le tre famiglie di dati

**1. Le definizioni** (file di dati nel repository, non nel database):

| Cosa | Contenuto | Chi la cambia |
|---|---|---|
| Alberatura | l'albero delle sezioni conforme all'Allegato A: identificativo del nodo, denominazione esatta, articolo di riferimento, piu le diciture degli stati che ne richiedono una e la voce in cui si mostrano i dati che non sono voci, come il responsabile della trasparenza, la durata della pubblicazione, le voci coperte da un modulo ANAC e le voci la cui pubblicazione si conta dalla cessazione, con gli anni. Un file per versione in `dati/alberatura/`, con la versione nel nome: la data, seguita da un numero per una seconda versione dello stesso giorno; le versioni vecchie restano accanto a quella in vigore, formato descritto in `docs/implementazione/alberatura.md` | solo un aggiornamento del plugin, mai l'utente |
| Schemi | una definizione per ogni schema ANAC e per ogni sua versione, con la data di approvazione ANAC e la fonte; accanto, i JSON Schema ufficiali usati per validare. Catalogo `schemas/catalogo.json`, formato descritto in `docs/implementazione/motore-schemi.md`; i file ufficiali sono identici a quelli scaricati e il catalogo ne porta l'impronta SHA-256 (`docs/implementazione/schemi-ufficiali.md`) | idem: una versione nuova si affianca, non sovrascrive |

**2. I contenuti** (nel database del sito):

| Dato | Dove sta | Chi lo scrive |
|---|---|---|
| Voce pubblicata (il dato compilato di uno schema) | contenuto di tipo dedicato più campi strutturati; porta con sé l'identificativo della versione di schema con cui è stato raccolto | redattore, tramite i moduli generati dallo schema |
| Nodo dell'albero: stato dell'obbligo (applicabile, non applicabile, sospeso) | opzione `trasparenza_pa_stati_obbligo`, una coppia identificativo-stato per nodo; un nodo assente e "non dichiarato", che non e uno dei tre e non ha valore predefinito. Ogni cambio lascia una voce `stato_obbligo` nel registro di core, con voce, stato prima e dopo | chi ha il permesso `trasparenza_pa_amministrazione`, dalla schermata "Stato delle voci" |
| Responsabile della prevenzione della corruzione e della trasparenza | opzione `trasparenza_pa_responsabile`: nome, email, telefono, indirizzo dell'atto di nomina. Nessun valore predefinito: assente vuol dire non indicato. Ogni salvataggio che cambia qualcosa lascia una voce `responsabile_trasparenza` nel registro di core con i soli nomi dei campi cambiati, mai i valori, che sono dati personali | chi ha il permesso `trasparenza_pa_amministrazione`, dalla schermata "Responsabile" |
| Contenuto libero di una voce | contenuto di tipo `trasparenza_testo`: titolo, testo, autore, revisioni; creazione, pubblicazione, modifica e cestino nel registro di core | chi ha i permessi del tipo |
| Documenti di un contenuto | gli allegati con il contenuto come padre depositati dal componente comune, che ne tiene file, impronta e consegna; nessun elenco separato. Ogni aggiunta e ogni rimozione lasciano una voce `documento_contenuto` nel registro di core, con operazione, allegato, nome del file e impronta | chi puo modificare il contenuto, dal riquadro "Documenti"; togliere solo mentre il contenuto non e pubblicato |
| Data da cui decorre l'obbligo di un contenuto | metadato `_trasparenza_pa_decorrenza`, AAAA-MM-GG; vale una riga sola, come la voce. Da lì la fine della pubblicazione, il 31 dicembre di tanti anni dopo quanti ne dice l'albero in vigore, scritta con `conformita_core_imposta_fine_pubblicazione()`. Ogni cambio lascia una voce `pubblicazione_contenuto` nel registro di core, con data e fine di prima e di dopo | chi puo modificare il contenuto, dal riquadro "Pubblicazione" |
| Ultimo giorno degli effetti di un contenuto | metadato `_trasparenza_pa_effetti`, AAAA-MM-GG, facoltativo; una riga sola. La fine diventa la più lontana fra quella di legge e questo giorno. Ogni cambio, rimozione compresa, è nella stessa voce di registro | chi puo modificare il contenuto, dal riquadro "Pubblicazione" |
| Cessazione del mandato o dell'incarico | metadato `_trasparenza_pa_cessazione`, AAAA-MM-GG, facoltativo e ammesso solo nelle voci che l'albero lega alla cessazione; una riga sola. La fine diventa tanti anni dopo la cessazione quanti ne dice l'albero, lo stesso giorno. Ogni cambio, rimozione compresa, è nella stessa voce di registro | chi puo modificare il contenuto, dal riquadro "Pubblicazione" |
| Contenuto con i dati patrimoniali | metadato `_trasparenza_pa_patrimoniale`, `1` o assente, ammesso solo nelle voci la cui regola ha un termine per i dati patrimoniali e solo con la cessazione. La fine diventa quel termine dopo la cessazione: per l'art. 14, il giorno stesso. Ogni cambio è nella stessa voce di registro | chi puo modificare il contenuto, dal riquadro "Pubblicazione" |
| Voce in cui sta un contenuto | metadato `_trasparenza_pa_collocazione`, l'identificativo di una voce senza sottovoci; vale una riga sola, e più righe si riordinano dal riquadro registrando quelle trovate. Ogni cambio lascia una voce `collocazione_contenuto` nel registro di core, con la voce di prima e quella nuova | chi puo modificare il contenuto, dal riquadro "Voce della sezione" |
| Versione installata del componente | opzione `trasparenza_pa_versione_installata`; quando cambia si riassegnano i permessi della voce e del contenuto | solo il sistema |
| Obbligo e versione dello schema della voce | metadati `_trasparenza_pa_obbligo` e `_trasparenza_pa_versione` (codice numero@data); scritti alla scelta dell'obbligo, poi mai piu cambiati dal modulo | il sistema, alla scelta del redattore |
| Dato compilato della voce | metadato `_trasparenza_pa_dato`, in JSON, salvato anche quando non e conforme | il sistema, dai valori del modulo |
| Esito dell'ultimo controllo | metadato `_trasparenza_pa_esito`: conforme oppure no, messaggio, difetti con percorso, regola e messaggio | il sistema |
| Data di ultimo aggiornamento | per nodo e per voce, scritta dal sistema a ogni pubblicazione | solo il sistema |
| Stato editoriale (bozza, validato, pubblicabile provvisoriamente, non pubblicabile) | stato della voce, con esito della validazione | il flusso di validazione, mai a mano |
| Segnalazioni al RPCT | registro proprio: voce, difformità, data, destinatario | solo il sistema, nei due stati di difformità |
| Voce-rinvio | tipo dedicato: etichetta, URL, data, amministrazione titolare, destinatario dell'accesso civico, esito dell'ultima verifica | redattore la crea; l'esito lo scrive il verificatore di core |
| Richieste di accesso civico | registro proprio: data, oggetto, stato della risposta | il modulo pubblico la crea; il responsabile la aggiorna |
| Decorrenza dell'obbligo e scadenza quinquennale | metadato della voce; la scadenza si calcola dalla decorrenza, non dalla data di caricamento | il sistema, dalla decorrenza indicata dal redattore |

**3. I file generati**: gli export in formato aperto e l'`at_map`, prodotti dal sistema
con nomi che contengono date, versione e codice della sottosezione. Si rigenerano dai
dati: non sono mai l'unica copia di niente.

## Le risposte alle domande di controllo

**Dove sta la data di pubblicazione?** La decorrenza dell'obbligo è un metadato della
voce, indicato in compilazione; la data di ultimo aggiornamento la scrive il sistema. La
scadenza non si salva come dato indipendente che qualcuno potrebbe sbagliare: si calcola
dalla decorrenza (cinque anni dal 1° gennaio dell'anno successivo), e il calcolo ha i
suoi test, compresi i casi a cavallo d'anno.

**Cosa succede se cambio un dato già pubblicato?** L'aggiornamento è una nuova versione
della voce con la nuova data di aggiornamento: la storia resta nel registro di core, e i
file esportati nuovi hanno un nome con la data nuova. Nessuna sovrascrittura muta.

**Cosa succede ai documenti allegati?** Come nell'albo: cartella protetta, consegna solo
tramite endpoint. Finché la voce è pubblicata il download è pubblico; scaduta la voce, il
file resta ma si ottiene solo dall'archivio ad accesso controllato, per l'accesso civico.
Mai cancellato.

**Chi può cambiare lo stato di un obbligo o di una voce?** Solo chi ha la capability
dedicata, e lo stato editoriale segue solo le transizioni del flusso di validazione. La
dicitura del "non applicabile" la stampa il sistema, carattere per carattere: non è un
testo libero che un redattore può riscrivere.

## I permessi

Le capability dei tipi le **deriva core** dall'identificativo del tipo, ma core non le
assegna a nessun ruolo: **è questo plugin che le assegna**, e la corrispondenza è
configurabile dall'ente. Prima dell'assegnazione le voci non sono visibili a nessuno,
nemmeno all'amministratore: è voluto, perché l'errore che produce si vede subito invece di
lasciare tutto aperto in silenzio. Va detto nel README, altrimenti sembra un difetto.
Attenzione: riguarda l'amministrazione, non la consultazione pubblica, che resta senza
autenticazione perché è un requisito di legge.

| Azione | Capability dedicata | Note |
|---|---|---|
| Compilare bozze delle voci | gestione trasparenza | |
| Validare e pubblicare | pubblicazione trasparenza | passa dal controllo dati personali |
| Dichiarare lo stato di un obbligo e indicare il responsabile della trasparenza | `trasparenza_pa_amministrazione` | del componente, non derivato da un tipo; va all'amministratore una volta sola, alla prima installazione (opzione `trasparenza_pa_permessi_assegnati`), e se l'ente lo toglie non torna con gli aggiornamenti. Il non applicabile stampa la dicitura obbligata |
| Creare e modificare voci-rinvio | gestione trasparenza | il caso senza banca dati (art. 19) non è configurabile come rinvio |
| Consultare l'archivio delle voci scadute | archivio trasparenza | il pubblico accede solo per accesso civico |
| Gestire le richieste di accesso civico | accesso civico | |
| Consultare il registro delle modifiche | lettura registro (di core) | |
| Vedere la sezione pubblicata | nessuna: pubblico, senza autenticazione | è un requisito, non una dimenticanza |

## Cosa NON viene salvato

Nessun tracciamento dei visitatori: l'accesso alla sezione è anonimo per legge. Nessuna
copia locale modificata dei JSON Schema ANAC: si valida contro i file ufficiali
versionati nel repository con la fonte citata. Nessun campo degli schemi dentro il
codice PHP: se un nome di campo compare nei sorgenti, un test statico lo blocca.
