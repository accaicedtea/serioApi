#!/bin/bash

echo "🚀 Deploy automatico su Altervista via FTP"
echo "=========================================="
echo ""

# Leggi credenziali da .env
FTP_HOST="ftp.accaicedtea.altervista.org"
FTP_USER="accaicedtea"
FTP_PASS="uthc8An48aA7"
FTP_PATH="/mymenu"

# Verifica se lftp è installato
if ! command -v lftp &> /dev/null; then
    echo "❌ lftp non installato. Installalo con:"
    echo "   sudo apt install lftp"
    exit 1
fi

# Verifica se esiste la cartella deploy
if [ ! -d "deploy-altervista" ]; then
    echo "📦 Creo il pacchetto di deploy..."
    ./prepare-deploy.sh
    echo ""
fi

echo "📤 Connessione a $FTP_HOST..."
echo "📁 Cartella remota: $FTP_PATH"
echo ""

# Deploy via FTP
lftp -c "
set ftp:ssl-allow no
set net:timeout 10
set net:max-retries 2
open -u $FTP_USER,$FTP_PASS $FTP_HOST
cd $FTP_PATH || mkdir -p $FTP_PATH; cd $FTP_PATH

echo '🗑️  Pulizia cartella remota...'
rm -rf *

echo '📤 Caricamento file...'
lcd deploy-altervista

mirror -R -v \
    --parallel=4 \
    --exclude-glob .git* \
    --exclude-glob .DS_Store \
    . .

echo '✅ Upload completato!'
bye
"

if [ $? -eq 0 ]; then
    echo ""
    echo "=========================================="
    echo "✅ DEPLOY COMPLETATO CON SUCCESSO!"
    echo "=========================================="
    echo ""
    echo "🌐 La tua API è disponibile su:"
    echo "   https://accaicedtea.altervista.org/mymenu/api/allergens"
    echo "   https://accaicedtea.altervista.org/mymenu/api/auth/login"
    echo ""
    echo "🧪 Testa con:"
    echo "   curl https://accaicedtea.altervista.org/mymenu/api/allergens"
    echo ""
else
    echo ""
    echo "❌ Errore durante il deploy!"
    echo "Controlla le credenziali FTP e riprova."
fi
