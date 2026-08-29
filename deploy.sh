#!/usr/bin/env bash
# ============================================================================
# Production Deployment Script for Linux VPS (Ubuntu / Debian)
# Usage: ./deploy.sh
# ============================================================================

set -e

echo "🚀 Starting Inventory App Deployment..."

# 1. Pull latest code from git
if [ -d ".git" ]; then
    echo "📥 Pulling latest git changes..."
    git pull origin main || git pull
fi

# 2. Setup directory permissions
echo "🔒 Setting file permissions..."
chmod -R 755 .
chmod -R 775 uploads 2>/dev/null || mkdir -p uploads && chmod -R 775 uploads
chown -R www-data:www-data . 2>/dev/null || true

# 3. Create .env if missing
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    echo "📄 Creating .env from .env.example..."
    cp .env.example .env
fi

# 4. Run automated database setup / migration
if command -v php &> /dev/null; then
    echo "🗄️ Running database auto-migration script..."
    php setup.php || echo "⚠️ Setup script warning (verify DB status)."
else
    echo "⚠️ PHP CLI not found. Please run setup.php manually or via browser."
fi

echo "✅ Deployment completed successfully!"
