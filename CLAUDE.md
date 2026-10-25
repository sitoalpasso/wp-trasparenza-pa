# Trasparenza PA - istruzioni di sviluppo

Plugin WordPress per gli obblighi di pubblicazione della sezione "Amministrazione
trasparente". Implementa i requisiti derivati dalla normativa applicabile ai soggetti
dell'art. 2-bis del d.lgs. 33/2013: l'elenco completo, con fonte e criterio di verifica
per ciascuno, e in `docs/requisiti.md`.

Licenza: GPL-3.0-or-later. Versioni minime: WordPress 6.5, PHP 8.1. Multisite non
supportato.

## Comandi

- Ambiente: `npx wp-env start` (configurazione in `.wp-env.json`)
- Test: `composer test` (PHPUnit sulla suite di test di WordPress, con il componente comune
  procurato da `bin/installa-core.sh`), piu `composer test:senza-core` e
  `composer test:core-incompatibile`, che girano in avvii separati
- Standard: `composer lint` (PHPCS, WordPress Coding Standards)

## Regole di lavoro

- Ogni requisito di `docs/requisiti.md` ha almeno un test che lo verifica: una modifica
  che tocca un requisito aggiorna prima il test. I test sono la specifica eseguibile.
- La scadenza dei contenuti e una proprieta del dato letto, non un evento pianificato:
  nessuna funzione puo assumere che il compito pianificato sia stato eseguito.
- Ogni tipo di contenuto dichiara `show_in_rest` esplicitamente, in un senso o
  nell'altro.
- Nessun valore dipendente dal singolo ente cablato nel codice: durate, denominazioni e
  soglie sono configurazione con validazione. I default esistono solo dove un default
  sbagliato e impossibile.
- Fuso orario: sempre `wp_timezone()`, mai un fuso cablato.
- Sicurezza: nonce e `permission_callback` su ogni superficie registrata, sanitizzazione
  in ingresso, escaping in uscita, capability dedicate.
- Documentazione e messaggi di commit in italiano, descrittivi e senza riferimenti a
  installazioni specifiche.
- Gli schemi ANAC sono file di dati in `schemas/`; i nomi dei loro campi non compaiono
  mai nei sorgenti PHP (c'e un test che lo verifica). Le modifiche agli schemi si fanno
  aggiungendo versioni, mai sovrascrivendo.
- Richiede il plugin `conformita-core`. L'intestazione `Requires Plugins` impedisce
  l'attivazione quando quel plugin manca, ma accetta slug e non vincoli di versione: la
  compatibilita di versione si verifica quindi **all'avvio**, su `plugins_loaded`, e non
  all'attivazione.
- La politica di indicizzazione di questa sezione e l'opposto di quella della pubblicita
  legale: qui l'indicizzazione e obbligatoria e va dichiarata esplicitamente a core, che
  non ha un valore predefinito.
