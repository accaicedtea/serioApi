# 🚀 Generatore Automatico di API REST per Database Relazionali

Genera automaticamente API REST complete in PHP con autenticazione JWT, routing, sicurezza, rate limiting e documentazione per qualsiasi database MySQL/MariaDB.

---

## 📋 Indice

1. [Caratteristiche](#-caratteristiche)
2. [Prerequisiti](#-prerequisiti)
3. [Installazione](#-installazione)
4. [Configurazione](#️-configurazione)
5. [Utilizzo](#-utilizzo)
6. [Struttura del Progetto](#-struttura-del-progetto)
7. [File Principali](#-file-principali)
8. [Script di Gestione](#-script-di-gestione)
9. [API Generata](#-api-generata)
10. [Deploy](#-deploy)
11. [Testing](#-testing)

---

## ✨ Caratteristiche

- ✅ **Generazione automatica** di endpoint CRUD per ogni tabella del database
- ✅ **Autenticazione JWT** con login, token validation e refresh
- ✅ **Controlli di sicurezza** avanzati (SQL Injection, XSS, CSRF)
- ✅ **Rate Limiting** configurabile per endpoint
- ✅ **CORS** configurato per chiamate cross-origin
- ✅ **Routing** automatico con `.htaccess` (Apache) e `index.php` (PHP built-in server)
- ✅ **Permessi granulari** per operazioni (SELECT, INSERT, UPDATE, DELETE)
- ✅ **Documentazione** README generata automaticamente
- ✅ **Testing** integrato con test suite locale e remota
- ✅ **Deploy FTP** automatizzato su Altervista (o altri hosting)
- ✅ **File-based caching** con TTL configurabile

---

## 🔧 Prerequisiti

- **PHP** 7.4 o superiore
- **MySQL** o **MariaDB** 5.7+
- **Apache** con `mod_rewrite` (per produzione) o PHP built-in server (per sviluppo)


### Installazione su Windows

Scarica e installa [XAMPP](https://www.apachefriends.org/) o [WAMP](https://www.wampserver.com/).

---

## 📥 Installazione

1. **Clona il repository**

```bash
git clone https://github.com/accaicedtea/serioApi.git
cd serioApi
```

2. **Configura il database**

Crea un database MySQL e importa i tuoi dati, oppure usa un database esistente.

3. **Configura le variabili d'ambiente**

Copia il file `.env.example` in `.env` e modifica i valori:

```bash
cp config/.env.example config/.env
nano config/.env
```

4. **Configura il database**

Modifica il file `config/database.php` con le credenziali del tuo database:

```php
$databases = [
        'development' => [
            'dbType' => 'MySQL',
            'host' => 'localhost',
            'dbname' => 'my_menu',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
        'production' => [
            'dbType' => 'MySQL',
            'host' => 'localhost',
            'dbname' =>  'my_remote_db',
            'user' =>  'user_masterino',
            'pass' =>  '',
            'charset' => 'utf8mb4',
        ],
];
```


---

## ⚙️ Configurazione

### File `.env`

Il file `config/.env` contiene tutte le variabili d'ambiente del progetto:

```env
# Database
DB_HOST=127.0.0.1
DB_NAME=db_tezt
DB_USER=root
DB_PASS=password

# JWT Configuration
JWT_SECRET=chiave_segreta_lunga_e_casuale
JWT_ALGO=HS256
JWT_EXPIRES_IN=86400 //in secondi

# Cache Configuration
CACHE_ENABLED=true
CACHE_TTL=300 //in secondi
CACHE_DIR=storage/cache

# Environment
ENVIRONMENT=development # puoi cambiarlo in qualsiasi momento con quello che hai messo tu in config/database.php
```

### File API (`config/api_config.json`)

Il file JSON contiene la configurazione di tutte le tabelle e i permessi e si modifica e genera in maniera automatica:

```json
{
  "db_test": {
    "allergens": {
      "enabled": true,
      "select": "all",
      "insert": "auth",
      "update": "auth",
      "delete": "admin",
      "rate_limit": 100,
      "rate_limit_window": 60
    }
  }
}
```

**Permessi disponibili:**
- `all` - Nessuna autenticazione richiesta
- `auth` - Richiede autenticazione (utente autenticato)
- `admin` - Richiede ruolo admin
- `owner` - Solo il proprietario della risorsa

---

## 🎯 Utilizzo

### Metodo 1: Script di gestione integrato (Consigliato)

Avvia lo script interattivo:

```bash
./server.sh
```

Comandi disponibili:
- `start` - Avvia il server di sviluppo (porta 8000)
- `test` - Esegui i test sull'API locale
- `remote` - Esegui i test sull'API remota (Altervista)
- `deploy` - Deploy automatico su Altervista via FTP
- `exit` - Ferma tutti i processi e chiudi

### Metodo 2: Manuale

```bash
# Avvia il server di sviluppo
php -S localhost:8000 index.php

# Apri il browser su
http://localhost:8000/
```

---

## 📁 Struttura del Progetto

```
ProgettoSerio/
├── app/
│   ├── Builder/              # Template per file generati
│   │   ├── JWT.php           # Template JWT handler
│   │   ├── Cache.php         # Template cache handler
│   │   ├── User.php          # Template modello User
│   │   ├── auth.php          # Template middleware auth
│   │   ├── ep_auth.php       # Template endpoint auth
│   │   ├── me.php            # Template endpoint /auth/me
│   │   ├── helpers.php       # Template funzioni helper
│   │   ├── cors.php          # Template gestione CORS
│   │   └── security.php      # Template middleware security
│   │
│   ├── Controllers/
│   │   └── ApiBuilderController.php  # Controller principale generazione API
│   │
│   └── Views/
│       └── generator/
│           └── builder.php   # Interfaccia web per configurare e generare API
│
├── config/
│   ├── .env                  # Variabili d'ambiente
│   ├── database.php          # Configurazione database
│   ├── api_config.json       # Configurazione tabelle e permessi
│   └── routes.php            # Routing applicazione principale
│
├── core/
│   ├── Controller.php        # Classe base controller
│   ├── Database.php          # Classe connessione database
│   ├── Security.php          # Funzioni di sicurezza
│   └── helpers.php           # Funzioni helper globali
│
├── generated-api/            # API generata (output)
│   ├── config/
│   │   ├── database.php      # Configurazione DB API generata
│   │   ├── jwt.php           # Handler JWT
│   │   ├── cache.php         # Handler cache
│   │   └── helpers.php       # Helper API generata
│   │
│   ├── middleware/
│   │   ├── auth.php          # Verifica autenticazione
│   │   ├── security.php      # Controlli di sicurezza
│   │   └── security_helper.php
│   │
│   ├── models/
│   │   ├── User.php          # Modello utente
│   │   └── [Tabella].php     # Un modello per ogni tabella
│   │
│   ├── endpoints/
│   │   ├── auth.php          # Endpoint login
│   │   └── [tabella].php     # Endpoint CRUD per ogni tabella
│   │
│   ├── auth/
│   │   └── me.php            # Endpoint info utente corrente
│   │
│   ├── storage/
│   │   └── cache/            # Directory cache file-based
│   │
│   ├── .htaccess             # Routing Apache
│   ├── index.php             # Router PHP built-in server
│   └── README.md             # Documentazione API generata
│
├── public/
│   └── index.php             # Entry point applicazione
│
├── storage/
│   └── cache/                # Cache applicazione principale
│
├── index.php                 # Entry point principale
├── server.sh                 # Script di gestione integrato
├── .gitignore
└── README.md                 # Info guida
```

---

## 📄 File Principali

### 1. `app/Controllers/ApiBuilderController.php`

**Ruolo:** Controller principale che genera le API.

**Funzioni principali:**
- `index()` - Mostra interfaccia web di configurazione
- `generate()` - Genera l'intera API basandosi sulla configurazione
- `createDirectoryStructure()` - Crea cartelle necessarie

**Quando modificarlo:**
- Per aggiungere nuovi tipi di file da generare
- Per modificare la logica di generazione endpoint
- Per aggiungere nuove funzionalità all'API generata

---

### 2. `app/Builder/*.php`

**Ruolo:** Template utilizzati come base per i file generati.

**File principali:**
- **`JWT.php`** - Classe per generare e validare token JWT
- **`Cache.php`** - Sistema di caching file-based con TTL
- **`security.php`** - Middleware rate limiting e sicurezza

**Come funzionano:**
I template contengono placeholder come `__JWT_SECRET__`, `__CACHE_TTL__` che vengono sostituiti durante la generazione con i valori dal file `.env` o direttamente nel Controller.

---

### 3. `config/api_config.json`

**Ruolo:** Configurazione centralizzata delle tabelle e permessi.

**Struttura:**
```json
{
  "nome_database": {
    "nome_tabella": {
      "enabled": true,           // Abilita endpoint per questa tabella
      "select": "all",           // Permessi GET (all/auth/admin/owner)
      "insert": "auth",          // Permessi POST
      "update": "auth",          // Permessi PUT
      "delete": "admin",         // Permessi DELETE
      "rate_limit": 100,         // Numero massimo richieste
      "rate_limit_window": 60    // Finestra temporale (secondi)
    }
  }
}
```

**Quando modificarlo:**
- Per abilitare/disabilitare tabelle
- Per modificare i permessi di accesso
- Per configurare rate limiting per tabella

---

### 4. `config/.env`

**Ruolo:** Variabili d'ambiente per configurazione sensibile.

**Sezioni:**
- **Database:** credenziali connessione DB
- **JWT:** chiave segreta, algoritmo, durata token
- **Cache:** abilitazione, TTL, directory
- **Environment:** development/production

**Quando modificarlo:**
- Durante setup iniziale
- Prima del deploy in produzione (cambia credenziali!)
- Per modificare durata token JWT
- Per abilitare/disabilitare cache

---

### 5. `server.sh`

**Ruolo:** Script bash che unifica gestione server, test e deploy.

**Test eseguiti:**
1. GET lista elementi
2. GET singolo elemento
3. POST senza autenticazione (deve fallire)
4. POST `/api/auth/login` (credenziali errate)
5. POST `/api/auth/login` (credenziali corrette)
6. GET `/api/auth/me` (con token)
7. POST creazione elemento (con token)
8. Rate limiting (5 richieste rapide)
9. Endpoint inesistente (404)
---

## 🛠️ Script di Gestione

### Avvio Rapido

```bash
./server.sh
```

### Comandi Disponibili

#### `start` - Avvia Server di Sviluppo

```bash
> start
```

Avvia il server PHP sulla porta 8000. Accedi al generatore su `http://localhost:8000/`.

#### `test` - Test API Locale

```bash
> test
```

Esegue la suite di test completa sull'API generata in locale. Avvia automaticamente un server di test su una porta libera (default 8080).

**Output esempio:**
```
✅ Test superati: 8/8
Tutto OK!
```

#### `remote` - Test API Remota

```bash
> remote
```

Esegue gli stessi test ma sull'API deployata su Altervista (o comunque su internet).

#### `deploy` - Deploy Automatico

```bash
> deploy
```

Carica l'intera cartella `generated-api` sul server remoto via FTP. Configurabile modificando le variabili in `run_deploy()`:

```bash
FTP_HOST="ftp.exempiohost.com"
FTP_USER="usrname"
FTP_PASS="passw"
FTP_PATH="/cartella/remota"
```

#### `exit` - Termina

```bash
> exit
```

Ferma tutti i server in background e esce.

---

## 🌐 API Generata

### Endpoint Autenticazione

#### POST `/api/auth/login`

Login e generazione token JWT.

**Request:**
```json
{
  "email": "admin@menucrud.com",
  "password": "admin123"
}
```

**Response (200):**
```json
{
  "status": 200,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user": {
      "id": 1,
      "email": "admin@menucrud.com",
      "name": "Admin",
      "role": "admin"
    }
  },
  "message": "Login effettuato con successo"
}
```

#### GET `/api/auth/me`

Recupera informazioni utente autenticato.

**Headers:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200):**
```json
{
  "status": 200,
  "data": {
    "id": 1,
    "email": "admin@menucrud.com",
    "name": "Admin",
    "role": "admin"
  }
}
```

---

### Endpoint CRUD (esempio tabella `allergens`)

#### GET `/api/allergens`

Lista tutti gli elementi.

**Response (200):**
```json
{
  "status": 200,
  "data": [
    {"id": 1, "name": "Glutine", "icon": "gluten.png"},
    {"id": 2, "name": "Lattosio", "icon": "milk.png"}
  ]
}
```

#### GET `/api/allergens/{id}`

Recupera singolo elemento.

**Response (200):**
```json
{
  "status": 200,
  "data": {
    "id": 1,
    "name": "Glutine",
    "icon": "gluten.png"
  }
}
```

#### POST `/api/allergens`

Crea nuovo elemento (richiede autenticazione se configurato).

**Headers:**
```
Authorization: Bearer [token]
Content-Type: application/json
```

**Request:**
```json
{
  "name": "Arachidi",
  "icon": "peanuts.png"
}
```

**Response (201):**
```json
{
  "status": 201,
  "data": {
    "id": 3
  },
  "message": "Created successfully"
}
```

#### PUT `/api/allergens/{id}`

Aggiorna elemento esistente.

**Headers:**
```
Authorization: Bearer [token]
Content-Type: application/json
```

**Request:**
```json
{
  "name": "Arachidi aggiornato",
  "icon": "peanuts_new.png"
}
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Updated successfully"
}
```

#### DELETE `/api/allergens/{id}`

Elimina elemento (richiede permessi admin se configurato).

**Headers:**
```
Authorization: Bearer [token]
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Deleted successfully"
}
```

---

## 🚀 Deploy

### Deploy Manuale

1. **Genera l'API**

Vai su `http://localhost:8000/generator/builder` e clicca "Genera API".

2. **Configura credenziali database remoto**

Modifica `generated-api/config/database.php` con le credenziali del server di produzione:

```php
define('DB_HOST', 'loacalhost');
define('DB_NAME', 'my_dbname');
define('DB_USER', 'username');
define('DB_PASS', 'password');
```

3. **Carica via FTP**

Carica il contenuto di `generated-api/` nella directory pubblica del tuo hosting.

4. **Verifica `.htaccess`**

Assicurati che il server supporti `.htaccess` e che `mod_rewrite` sia attivo.

### Deploy Automatico (con `server.sh`)

```bash
./server.sh
> deploy
```

Lo script:
1. Verifica esistenza `generated-api`
2. Si connette al server FTP
3. Pulisce la directory remota
4. Carica tutti i file
5. Mostra URL API live

**Configurazione FTP:**

Modifica `server.sh` alla funzione `run_deploy()`:

```bash
FTP_HOST="ftp.tuohost.com"
FTP_USER="tuo_utente"
FTP_PASS="tua_password"
FTP_PATH="/percorso/destinazione"
```

---

## 🧪 Testing

### Test Locali

```bash
./server.sh
> test
```

Esegue 9 test automatici:
- ✅ Recupero lista
- ✅ Recupero elemento singolo
- ✅ Creazione senza auth (fallimento atteso)
- ✅ Login con credenziali errate (fallimento atteso)
- ✅ Login con credenziali corrette
- ✅ Recupero dati utente autenticato
- ✅ Creazione con autenticazione
- ✅ Rate limiting
- ✅ Endpoint inesistente (404)

**Output:**
```
========================================
🧪 TEST API LOCALE
========================================
URL Base: http://localhost:8080

TEST: Recupera lista allergens
  → GET /api/allergens
  ✓ OK (HTTP 200)
  Response: {"status":200,"data":[...]}

...

========================================
📊 RIEPILOGO
========================================

✅ Test superati: 8/8
Tutto OK!
```

### Test Remoti

```bash
./server.sh
> remote
```

Stessi test ma su API remota deployata.


## 📚 Esempi di Utilizzo Client-Side

### JavaScript / Fetch API

```javascript
// Login
async function login(email, password) {
  const response = await fetch('http://localhost:8080/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  const { data } = await response.json();
  localStorage.setItem('token', data.token);
  return data;
}

// GET con autenticazione
async function getAllergens() {
  const token = localStorage.getItem('token');
  const response = await fetch('http://localhost:8080/api/allergens', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return await response.json();
}

// POST con autenticazione
async function createAllergen(name, icon) {
  const token = localStorage.getItem('token');
  const response = await fetch('http://localhost:8080/api/allergens', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({ name, icon })
  });
  return await response.json();
}
```



## 🔒 Sicurezza

### Protezioni Implementate

- ✅ **SQL Injection** - Con PDO 
- ✅ **XSS** - Con `htmlspecialchars()` su output
- ✅ **CSRF** - Token validation
- ✅ **Rate Limiting** - Limitazione richieste per IP
- ✅ **JWT** - Token con scadenza configurabile
- ✅ **Password Hashing** - Bcrypt per password utenti

### Suggerimenti

1. **Cambia JWT_SECRET** prima del deploy in produzione
2. **Usa HTTPS** in produzione

## 🐛 Problemi Comuni

### Errore: "Connection refused" durante i test

**Causa:** Il server di test non è partito correttamente.

**Soluzione:** Verifica che la porta 8080 (o successive) sia libera:
```bash
lsof -i :8080
```

### Errore: "JWT token invalid"

**Causa:** Token scaduto o chiave JWT_SECRET diversa.

**Soluzione:** Esegui nuovamente il login per ottenere un nuovo token.

### Deploy FTP fallisce

**Causa:** Credenziali errate o `lftp` non installato.

**Soluzione:**
```bash
sudo apt install lftp
# Verifica credenziali in server.sh
```

### API restituisce sempre 404

**Causa:** `.htaccess` non funziona o `mod_rewrite` non attivo.

**Soluzione (Apache):**
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Soluzione (PHP built-in server):**
Usa sempre `index.php` come router:
```bash
php -S localhost:8080 -t generated-api index.php
```

---
