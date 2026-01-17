#!/bin/bash

# ==============================================================================
# SERIO API - Pannello di Controllo
# ==============================================================================
#
# Questo script gestisce le operazioni principali del progetto:
#   - Avvio del server di sviluppo (per il generatore)
#   - Esecuzione dei test sull'API generata
#   - Deploy dell'applicazione via FTP
#
# ==============================================================================

# --- Variabili Globali e Colori ---
MAIN_SERVER_PID=""
API_SERVER_PID=""
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# --- Funzioni di Utility ---

# Funzione per fermare tutti i processi server avviati dallo script
cleanup() {
    echo ""
    echo -e "${YELLOW}🔌 Fermando tutti i server...${NC}"
    if [ -n "$MAIN_SERVER_PID" ]; then
        kill $MAIN_SERVER_PID > /dev/null 2>&1
        echo "  - Server di sviluppo (PID: $MAIN_SERVER_PID) fermato."
    fi
    if [ -n "$API_SERVER_PID" ]; then
        kill $API_SERVER_PID > /dev/null 2>&1
        echo "  - Server di test API (PID: $API_SERVER_PID) fermato."
    fi
    echo -e "${GREEN}✅ Pulizia completata.${NC}"
    exit 0
}

# Trappola per l'uscita (CTRL+C)
trap cleanup SIGINT

# --- Funzioni Principali ---

# Funzione per avviare il server di sviluppo principale
start_main_server() {
    if [ -n "$MAIN_SERVER_PID" ]; then
        echo -e "${YELLOW}⚠️  Il server di sviluppo è già in esecuzione (PID: $MAIN_SERVER_PID).${NC}"
        return
    fi
    
    echo -e "${BLUE}🚀 Avvio del server di sviluppo principale...${NC}"
    # Avvia il server e mostra l'output nel terminale
    php -S localhost:8000 index.php &
    MAIN_SERVER_PID=$!
    echo -e "${GREEN}✅ Server avviato su http://localhost:8000 (PID: $MAIN_SERVER_PID)${NC}"
    echo "   Usa questo indirizzo per accedere al generatore di API."
}

# Funzione unificata per eseguire i test sull'API
run_tests() {
    local BASE_URL=$1
    local IS_LOCAL=$2
    
    if [ "$IS_LOCAL" = "true" ]; then
        echo -e "${BLUE}🧪 Esecuzione dei test sull'API locale...${NC}"
        
        # Controlla se la cartella generated-api esiste
        if [ ! -d "generated-api" ]; then
            echo -e "${RED}❌ Errore: cartella 'generated-api' non trovata!${NC}"
            echo "   Genera prima l'API dal pannello: http://localhost:8000/generator/builder"
            return
        fi

        # Trova una porta libera a partire da 8080
        PORT=8080
        while lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null 2>&1 ; do
            PORT=$((PORT + 1))
        done

        # Avvia server PHP di test in background
        php -S localhost:$PORT -t generated-api > /dev/null 2>&1 &
        API_SERVER_PID=$!
        BASE_URL="http://localhost:$PORT"
        echo -e "   ${GREEN}✅ Server di test avviato su $BASE_URL${NC}"
        sleep 2
    else
        echo -e "${BLUE}🧪 Esecuzione dei test sull'API remota...${NC}"
    fi

    echo ""
    echo "========================================="
    if [ "$IS_LOCAL" = "true" ]; then
        echo "🧪 TEST API LOCALE"
    else
        echo "🌐 TEST API REMOTA"
    fi
    echo "========================================="
    echo ""
    echo "URL Base: $BASE_URL"
    echo ""

    # Contatori per i test
    TOTAL_TESTS=0
    PASSED_TESTS=0

    # Funzione per testare endpoint
    test_endpoint() {
        local method=$1; local url=$2; local data=$3; local desc=$4
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        
        echo -e "${YELLOW}TEST:${NC} $desc"
        echo "  → $method $url"
        
        if [ -z "$data" ]; then
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$url")
        else
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$url" -H "Content-Type: application/json" -d "$data")
        fi
        
        http_code=$(echo "$response" | tail -n1); body=$(echo "$response" | head -n-1)
        
        if [ $http_code -ge 200 ] && [ $http_code -lt 300 ]; then
            echo -e "  ${GREEN}✓ OK${NC} (HTTP $http_code)"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        elif [ $http_code -ge 400 ] && [ $http_code -lt 500 ]; then
            echo -e "  ${YELLOW}⚠ Client Error${NC} (HTTP $http_code)"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        fi
        echo "  Response: $(echo $body | jq -c '.' 2>/dev/null || echo $body | head -c 100)"
        echo ""
    }

    # 1. Test GET lista allergens
    test_endpoint "GET" "/api/allergens" "" "Recupera lista allergens"

    # 2. Test GET singolo allergen
    test_endpoint "GET" "/api/allergens/1" "" "Recupera allergen ID 1"

    # 3. Test POST crea allergen (dovrebbe fallire senza auth se configurato)
    test_endpoint "POST" "/api/allergens" '{"name":"Test Allergen","icon":"test.png"}' "Crea nuovo allergen"

    # 4. Test autenticazione LOGIN (dovrebbe fallire con credenziali fake)
    test_endpoint "POST" "/api/auth/login" '{"email":"test@test.com","password":"wrongpass"}' "Login con credenziali errate"

    # 4b. Test login con credenziali corrette
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo -e "${YELLOW}TEST:${NC} Login con credenziali corrette"
    echo "  → POST /api/auth/login"
    login_response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/auth/login" -H "Content-Type: application/json" -d '{"email":"admin@menucrud.com","password":"admin123"}')
    http_code=$(echo "$login_response" | tail -n1); body=$(echo "$login_response" | head -n-1)

    if [ $http_code -eq 200 ]; then
        echo -e "  ${GREEN}✓ OK${NC} (HTTP $http_code)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        TOKEN=$(echo $body | jq -r '.data.token' 2>/dev/null)
        echo "  Token ricevuto: ${TOKEN:0:50}..."
        
        # 4c. Test /api/auth/me con token
        echo ""
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        echo -e "${YELLOW}TEST:${NC} Recupera dati utente autenticato"
        echo "  → GET /api/auth/me"
        me_response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/auth/me" -H "Authorization: Bearer $TOKEN")
        me_code=$(echo "$me_response" | tail -n1); me_body=$(echo "$me_response" | head -n-1)
        
        if [ $me_code -eq 200 ]; then
            echo -e "  ${GREEN}✓ OK${NC} (HTTP $me_code)"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            echo "  User: $(echo $me_body | jq -c '.data' 2>/dev/null || echo $me_body)"
        fi
        
        # 4d. Test POST con autenticazione
        echo ""
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        echo -e "${YELLOW}TEST:${NC} Crea allergen con autenticazione"
        echo "  → POST /api/allergens (con token)"
        create_response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/allergens" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" -d '{"name":"Test Allergen API","icon":"test-icon","created_at":"2025-11-26 16:00:00","updated_at":"2025-11-26 16:00:00"}')
        create_code=$(echo "$create_response" | tail -n1); create_body=$(echo "$create_response" | head -n-1)
        
        if [ $create_code -eq 201 ]; then
            echo -e "  ${GREEN}✓ OK${NC} (HTTP $create_code)"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            echo "  Response: $(echo $create_body | jq -c '.' 2>/dev/null || echo $create_body)"
            
            # Cleanup - elimina il test allergen
            NEW_ID=$(echo $create_body | jq -r '.data.id' 2>/dev/null)
            if [ ! -z "$NEW_ID" ] && [ "$NEW_ID" != "null" ]; then
                curl -s -X DELETE "$BASE_URL/api/allergens/$NEW_ID" -H "Authorization: Bearer $TOKEN" > /dev/null
                echo "  (Test allergen eliminato: ID $NEW_ID)"
            fi
        else
            echo -e "  ${YELLOW}⚠ Warning${NC} (HTTP $create_code)"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            echo "  Response: $(echo $create_body | jq -c '.' 2>/dev/null || echo $create_body)"
        fi
    fi
    echo ""

    # 5. Test rate limiting (solo per locale)
    if [ "$IS_LOCAL" = "true" ]; then
        echo -e "${YELLOW}TEST:${NC} Rate Limiting (5 richieste rapide)"
        for i in {1..5}; do
            response=$(curl -s -w "%{http_code}" -o /dev/null "$BASE_URL/api/allergens")
            echo "  Richiesta $i/5: HTTP $response"
        done
        echo ""
    fi

    # 6. Test endpoint inesistente
    test_endpoint "GET" "/api/nonexistent" "" "Endpoint inesistente (404 atteso)"

    echo "========================================="
    echo "📊 RIEPILOGO"
    echo "========================================="
    echo ""
    
    echo -e "${GREEN}✅ Test superati: $PASSED_TESTS/$TOTAL_TESTS${NC}"
    if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
        echo -e "${GREEN}Tutto OK!${NC}"
    else
        echo -e "${YELLOW}Alcuni test non sono passati.${NC}"
    fi
    echo ""

    # Ferma il server di test se locale
    if [ "$IS_LOCAL" = "true" ] && [ -n "$API_SERVER_PID" ]; then
        kill $API_SERVER_PID > /dev/null 2>&1
        API_SERVER_PID=""
        echo -e "${GREEN}✅ Server di test fermato.${NC}"
    else
        echo -e "${GREEN}✅ Test completati.${NC}"
    fi
}

# Wrapper per test locali
run_api_tests() {
    run_tests "" "true"
}

# Wrapper per test remoti
run_remote_tests() {
    # Leggi l'URL dal file .env se disponibile
    BASE_URL="https://thisisnotmysite.altervista.org/mymenu"
    if [ -f ".env" ]; then
        API_URL=$(grep -E '^API_BASE_URL=' .env | cut -d'=' -f2 | tr -d ' ' | sed 's/#.*//')
        REMOTE_PATH=$(grep -E '^FTP_REMOTE_PATH=' .env | cut -d'=' -f2 | tr -d ' ' | sed 's/#.*//')
        if [ ! -z "$API_URL" ] && [ ! -z "$REMOTE_PATH" ]; then
            BASE_URL="${API_URL}${REMOTE_PATH}"
        fi
    fi
    echo "🌐 Test su: $BASE_URL"
    run_tests "$BASE_URL" "false"
}

# Funzione per eseguire il deploy via FTP
run_deploy() {
    echo -e "${BLUE}🚀 Esecuzione del deploy su Altervista via FTP...${NC}"

    # Verifica se esiste la cartella generated-api
    if [ ! -d "generated-api" ]; then
        echo -e "${RED}❌ Errore: cartella 'generated-api' non trovata!${NC}"
        echo "   Genera prima l'API dal pannello: http://localhost:8000/generator/builder"
        return
    fi

    # Credenziali e configurazione FTP
    FTP_HOST="ftp.thisisnotmysite.altervista.org"
    FTP_USER="thisisnotmysite"
    FTP_PASS="puT59Uqedtjd"
    FTP_PATH="/mymenu"

    # Verifica se lftp è installato
    if ! command -v lftp &> /dev/null; then
        echo -e "${RED}❌ lftp non installato. Installalo con 'sudo apt install lftp'${NC}"
        return
    fi

    echo "   📤 Connessione a $FTP_HOST e caricamento file..."
    lftp -c "
    set ftp:ssl-allow no; set net:timeout 15; set net:max-retries 3;
    open -u $FTP_USER,$FTP_PASS $FTP_HOST;
    cd $FTP_PATH || mkdir -p $FTP_PATH; cd $FTP_PATH;
    echo '🗑️  Pulizia cartella remota...';
    rm -rf *;
    echo '📤 Caricamento file da generated-api...';
    lcd generated-api;
    mirror -R -v --parallel=5 --exclude-glob .git* --exclude-glob .DS_Store . .;
    bye
    "

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ DEPLOY COMPLETATO CON SUCCESSO!${NC}"
        echo "   La tua API è live su: https://thisisnotmysite.altervista.org/mymenu/api/allergens"
    else
        echo -e "${RED}❌ Errore durante il deploy! Controlla l'output e le credenziali.${NC}"
    fi
}

# --- Menu Principale ---
show_menu() {
    echo ""
    echo "========================================="
    echo "SERIO API - PANNELLO DI CONTROLLO"
    echo "========================================="
    echo -e "Comandi disponibili:"
    echo -e "  ${YELLOW}start${NC}   - Avvia il server di sviluppo (porta 8000)"
    echo -e "  ${YELLOW}test${NC}    - Esegui i test sull'API generata (locale)"
    echo -e "  ${YELLOW}remote${NC}  - Esegui i test sull'API remota (Altervista)"
    echo -e "  ${YELLOW}deploy${NC}  - Esegui il deploy su Altervista"
    echo -e "  ${YELLOW}exit${NC}    - Ferma tutti i processi e chiudi"
    echo "-----------------------------------------"
    if [ -n "$MAIN_SERVER_PID" ]; then
        echo -e "Status: ${GREEN}Server di sviluppo ATTIVO (PID: $MAIN_SERVER_PID)${NC}"
    else
        echo -e "Status: ${RED}Server di sviluppo NON ATTIVO${NC}"
    fi
    echo "========================================="
}

# --- Loop Principale ---

show_menu
while true; do
    read -p "> " command
    case "$command" in
        start)
            start_main_server
            echo ""
            ;;
        test)
            run_api_tests
            echo ""
            ;;
        remote)
            run_remote_tests
            echo ""
            ;;
        deploy)
            run_deploy
            echo ""
            ;;
        exit|quit)
            cleanup
            ;;
        help|menu)
            show_menu
            ;;
        *)
            echo -e "${RED}Comando non riconosciuto. Usa 'help' per vedere la lista.${NC}"
            ;;
    esac
done


