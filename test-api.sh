#!/bin/bash

# Script di test per API generate
echo "🚀 Avvio server PHP per API generate..."
echo ""

# Controlla se la cartella generated-api esiste
if [ ! -d "generated-api" ]; then
    echo "❌ Errore: cartella generated-api non trovata!"
    echo "   Genera prima l'API da http://localhost:8000/generator/builder"
    exit 1
fi

# Trova una porta libera a partire da 8080
PORT=8080
while lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null 2>&1 ; do
    echo "⚠️  Porta $PORT occupata, provo la successiva..."
    PORT=$((PORT + 1))
done

# Avvia server PHP in background
php -S localhost:$PORT -t generated-api > /dev/null 2>&1 &
SERVER_PID=$!

echo "✅ Server avviato su http://localhost:$PORT (PID: $SERVER_PID)"
echo ""
sleep 2

# Colori per output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "🧪 TEST API GENERATE"
echo "========================================="
echo ""

# Funzione per testare endpoint
test_endpoint() {
    local method=$1
    local url=$2
    local data=$3
    local desc=$4
    
    echo -e "${YELLOW}TEST:${NC} $desc"
    echo "  → $method $url"
    
    if [ -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method "http://localhost:$PORT$url")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "http://localhost:$PORT$url" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ $http_code -ge 200 ] && [ $http_code -lt 300 ]; then
        echo -e "  ${GREEN}✓ OK${NC} (HTTP $http_code)"
    elif [ $http_code -ge 400 ] && [ $http_code -lt 500 ]; then
        echo -e "  ${YELLOW}⚠ Client Error${NC} (HTTP $http_code)"
    else
        echo -e "  ${RED}✗ FAIL${NC} (HTTP $http_code)"
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
echo -e "${YELLOW}TEST:${NC} Login con credenziali corrette"
echo "  → POST /api/auth/login"
login_response=$(curl -s -w "\n%{http_code}" -X POST "http://localhost:$PORT/api/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@menucrud.com","password":"admin123"}')

http_code=$(echo "$login_response" | tail -n1)
body=$(echo "$login_response" | head -n-1)

if [ $http_code -eq 200 ]; then
    echo -e "  ${GREEN}✓ OK${NC} (HTTP $http_code)"
    TOKEN=$(echo $body | jq -r '.data.token' 2>/dev/null)
    echo "  Token ricevuto: ${TOKEN:0:50}..."
    
    # 4c. Test /api/auth/me con token
    echo ""
    echo -e "${YELLOW}TEST:${NC} Recupera dati utente autenticato"
    echo "  → GET /api/auth/me"
    me_response=$(curl -s -w "\n%{http_code}" -X GET "http://localhost:$PORT/api/auth/me" \
        -H "Authorization: Bearer $TOKEN")
    
    me_code=$(echo "$me_response" | tail -n1)
    me_body=$(echo "$me_response" | head -n-1)
    
    if [ $me_code -eq 200 ]; then
        echo -e "  ${GREEN}✓ OK${NC} (HTTP $me_code)"
        echo "  User: $(echo $me_body | jq -c '.data' 2>/dev/null || echo $me_body)"
    else
        echo -e "  ${RED}✗ FAIL${NC} (HTTP $me_code)"
        echo "  Response: $(echo $me_body | jq -c '.' 2>/dev/null || echo $me_body)"
    fi
    
    # 4d. Test POST con autenticazione
    echo ""
    echo -e "${YELLOW}TEST:${NC} Crea allergen con autenticazione"
    echo "  → POST /api/allergens (con token)"
    create_response=$(curl -s -w "\n%{http_code}" -X POST "http://localhost:$PORT/api/allergens" \
        -H "Content-Type: application/json" \
        -H "Authorization: Bearer $TOKEN" \
        -d '{"name":"Test Allergen API","icon":"test-icon","created_at":"2025-11-26 16:00:00","updated_at":"2025-11-26 16:00:00"}')
    
    create_code=$(echo "$create_response" | tail -n1)
    create_body=$(echo "$create_response" | head -n-1)
    
    if [ $create_code -eq 201 ]; then
        echo -e "  ${GREEN}✓ OK${NC} (HTTP $create_code)"
        echo "  Response: $(echo $create_body | jq -c '.' 2>/dev/null || echo $create_body)"
        
        # Cleanup - elimina il test allergen
        NEW_ID=$(echo $create_body | jq -r '.data.id' 2>/dev/null)
        if [ ! -z "$NEW_ID" ] && [ "$NEW_ID" != "null" ]; then
            curl -s -X DELETE "http://localhost:$PORT/api/allergens/$NEW_ID" \
                -H "Authorization: Bearer $TOKEN" > /dev/null
            echo "  (Test allergen eliminato: ID $NEW_ID)"
        fi
    else
        echo -e "  ${YELLOW}⚠ Warning${NC} (HTTP $create_code)"
        echo "  Response: $(echo $create_body | jq -c '.' 2>/dev/null || echo $create_body)"
    fi
else
    echo -e "  ${RED}✗ FAIL${NC} (HTTP $http_code)"
    echo "  Response: $(echo $body | jq -c '.' 2>/dev/null || echo $body)"
fi
echo ""

# 5. Test rate limiting (fa 5 richieste rapide)
echo -e "${YELLOW}TEST:${NC} Rate Limiting (5 richieste rapide)"
for i in {1..5}; do
    response=$(curl -s -w "%{http_code}" -o /dev/null "http://localhost:$PORT/api/allergens")
    echo "  Richiesta $i/5: HTTP $response"
done
echo ""

# 6. Test endpoint inesistente
test_endpoint "GET" "/api/nonexistent" "" "Endpoint inesistente (404 atteso)"

echo "========================================="
echo "📊 RIEPILOGO"
echo "========================================="
echo ""
echo "Server API in esecuzione su: http://localhost:$PORT"
echo "PID del server: $SERVER_PID"
echo ""
echo "Endpoint disponibili:"
echo "  • GET  /api/allergens"
echo "  • POST /api/allergens"
echo "  • GET  /api/allergens/{id}"
echo "  • PUT  /api/allergens/{id}"
echo "  • DELETE /api/allergens/{id}"
echo "  • POST /api/auth/login"
echo "  • GET  /api/auth/me"
echo ""
echo "Per fermare il server: kill $SERVER_PID"
echo "O premi CTRL+C per terminare tutto"
echo ""

# Mantieni il server in esecuzione
echo "Premi CTRL+C per fermare il server..."
trap "kill $SERVER_PID 2>/dev/null; echo ''; echo '✅ Server fermato'; exit 0" INT
wait $SERVER_PID
