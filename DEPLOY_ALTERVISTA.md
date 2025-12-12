# 🚀 Deploy su Altervista

## Preparazione

1. **Accedi al tuo account Altervista**
   - Vai su https://it.altervista.org/
   - Accedi con le tue credenziali

2. **Accedi al FileManager o usa FTP**
   - Dal pannello di controllo, vai in "FileManager"
   - Oppure usa un client FTP (FileZilla, WinSCP, ecc.)

## Caricamento File

### Opzione 1: FileManager Web
1. Apri il FileManager
2. Naviga nella cartella principale (solitamente `/`)
3. Carica tutti i file del progetto mantenendo la struttura

### Opzione 2: FTP (Consigliato)
1. **Credenziali FTP:**
   - Host: `ftp.tuousername.altervista.org`
   - Username: Il tuo username Altervista
   - Password: La tua password Altervista
   - Porta: 21 (o 990 per FTPS)

2. **Carica i file:**
   ```
   - Tutti i file PHP nella root
   - Cartelle: app/, core/, config/, public/, generated-api/
   - File: .htaccess, .env, composer.json
   ```

### Opzione 3: Usa la funzione Deploy integrata
1. Vai su `/deploy` nel tuo progetto locale
2. Compila i dati FTP:
   - Host: `ftp.tuousername.altervista.org`
   - Username: il tuo username
   - Password: la tua password
   - Porta: 21
   - SSL/TLS: ✓ (consigliato)
3. Clicca "Testa Connessione"
4. Se ok, clicca "Carica sul Server"

## Configurazione Database

1. **Crea un database MySQL su Altervista:**
   - Pannello di controllo → Database → Crea nuovo database
   - Annota: nome database, username, password, host

2. **Aggiorna il file `.env` sul server:**
   ```env
   DB_TYPE=mysql
   DB_HOST=localhost (o il server fornito da Altervista)
   DB_NAME=my_tuousername_nomedb
   DB_USER=tuousername
   DB_PASSWORD=tuapassword
   DB_PORT=3306
   
   # JWT Configuration
   JWT_SECRET=cambia-questa-chiave-segreta-in-produzione
   JWT_ALGO=HS256
   JWT_EXPIRES_IN=3600
   
   # FTP per deploy
   FTP_HOST=ftp.tuousername.altervista.org
   FTP_PORT=21
   FTP_USERNAME=tuousername
   FTP_PASSWORD=tuapassword
   FTP_REMOTE_PATH=/
   FTP_SSL=true
   ```

3. **Importa le tabelle:**
   - Vai su phpMyAdmin (Pannello → Database → phpMyAdmin)
   - Seleziona il tuo database
   - Importa il file SQL se ce l'hai, oppure
   - Usa l'interfaccia del progetto per creare le tabelle

## Verifica Funzionamento

1. **Visita il tuo sito:**
   ```
   https://tuousername.altervista.org/
   ```

2. **Testa le rotte principali:**
   - `/` - Homepage
   - `/database` - Gestione database
   - `/generator` - Configurazione API
   - `/builder` - Generatore API

3. **Verifica file .htaccess:**
   - Deve essere nella root del progetto
   - Altervista supporta mod_rewrite di default

## Permessi File

Altervista gestisce automaticamente i permessi, ma se hai problemi:

```bash
# Le cartelle devono essere 755
chmod 755 app/ core/ config/ public/ generated-api/

# I file devono essere 644
chmod 644 *.php .htaccess .env
```

## Troubleshooting

### Errore 500
- Controlla i log PHP nel pannello Altervista
- Verifica che `.htaccess` sia presente
- Controlla sintassi in `.env`

### Database non si connette
- Verifica le credenziali in `.env`
- Il DB_HOST su Altervista è solitamente `localhost`
- Il nome database include il prefisso `my_username_`

### Le rotte non funzionano (404)
- Assicurati che `.htaccess` sia presente
- Verifica che mod_rewrite sia abilitato (lo è di default su Altervista)

### File non caricati
- Controlla che tutti i file siano stati caricati
- Verifica la struttura delle cartelle
- Usa FTP invece del FileManager web per upload grandi

## Sicurezza

⚠️ **IMPORTANTE prima di andare online:**

1. Cambia `JWT_SECRET` in `.env` con una chiave casuale e complessa
2. Usa HTTPS (Altervista lo fornisce gratis)
3. Non lasciare credenziali di test nel database
4. Imposta password forti per gli utenti API

## Note Altervista

- PHP 8.x disponibile (verifica versione nel pannello)
- MySQL 5.7+ disponibile
- FTP/FTPS supportato
- Certificato SSL gratuito
- Limite upload: ~100MB per file
- Limite esecuzione PHP: 30 secondi (di default)

## Struttura Finale su Altervista

```
/
├── .htaccess           ← IMPORTANTE per routing
├── .env                ← Configurazione (da aggiornare)
├── index.php           ← Entry point
├── composer.json
├── app/
│   ├── Controllers/
│   ├── Views/
│   └── Builder/
├── core/
│   ├── App.php
│   ├── Database.php
│   └── ...
├── config/
│   └── routes.php
├── public/
│   ├── css/
│   ├── js/
│   └── assets/
└── generated-api/      ← Le tue API generate
    ├── config/
    ├── endpoints/
    └── index.php
```

## Link Utili

- Pannello Altervista: https://it.altervista.org/
- Documentazione: https://it.altervista.org/supporto
- Forum supporto: https://forum.it.altervista.org/

---

✅ Dopo il deploy, testa tutto e goditi le tue API REST! 🎉
