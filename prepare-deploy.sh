#!/bin/bash

echo "📦 Preparazione pacchetto per deploy su Altervista..."
echo ""

# Crea cartella di deploy
DEPLOY_DIR="deploy-altervista"
rm -rf $DEPLOY_DIR
mkdir -p $DEPLOY_DIR

# Copia tutti i file necessari
echo "📋 Copia file da generated-api..."
cp -r generated-api/* $DEPLOY_DIR/
cp generated-api/.htaccess $DEPLOY_DIR/ 2>/dev/null

# Lista file copiati
echo ""
echo "✅ File pronti per il deploy in: $DEPLOY_DIR/"
echo ""
echo "📁 Struttura:"
tree -L 2 $DEPLOY_DIR/ 2>/dev/null || find $DEPLOY_DIR -maxdepth 2 -print

echo ""
echo "🔍 Verifica file critici CORS:"
if [ -f "$DEPLOY_DIR/cors.php" ]; then
    echo "  ✓ cors.php presente"
else
    echo "  ✗ cors.php MANCANTE!"
fi

if [ -f "$DEPLOY_DIR/.htaccess" ]; then
    echo "  ✓ .htaccess presente"
    echo "    Content:"
    head -5 "$DEPLOY_DIR/.htaccess" | sed 's/^/      /'
else
    echo "  ✗ .htaccess MANCANTE!"
fi

if [ -f "$DEPLOY_DIR/index.php" ]; then
    echo "  ✓ index.php presente"
else
    echo "  ✗ index.php MANCANTE!"
fi

echo ""
echo "📤 PROSSIMI PASSI:"
echo "1. Comprimi la cartella: zip -r mymenu.zip $DEPLOY_DIR/*"
echo "2. Carica su Altervista via FTP o File Manager"
echo "3. Estrai nella cartella /mymenu/"
echo ""
echo "🌐 URL finale: https://accaicedtea.altervista.com/mymenu/api/allergens"
echo ""

# Crea anche lo zip automaticamente
echo "📦 Creazione archivio ZIP..."
cd $DEPLOY_DIR
zip -r ../mymenu-deploy.zip . > /dev/null 2>&1
cd ..

if [ -f "mymenu-deploy.zip" ]; then
    echo "✅ Archivio creato: mymenu-deploy.zip"
    echo "   Dimensione: $(du -h mymenu-deploy.zip | cut -f1)"
else
    echo "❌ Errore nella creazione dello ZIP"
fi

echo ""
echo "🚀 Pronto per il deploy!"
