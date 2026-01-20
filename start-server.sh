#!/bin/bash

# Start PHP Development Server
# This script starts the PHP built-in web server for testing

set -e

# Configuration
PORT="${1:-8080}"
HOST="${2:-0.0.0.0}"
DOCROOT="$(cd "$(dirname "$0")" && pwd)"

echo "=========================================="
echo "PHP Development Server"
echo "=========================================="
echo ""
echo "Starting server..."
echo "  Host: $HOST"
echo "  Port: $PORT"
echo "  Document Root: $DOCROOT"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP is not installed or not in PATH"
    echo "   Please install PHP to use this development server"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "  PHP Version: $PHP_VERSION"

# Check if .env file exists
if [ ! -f "$DOCROOT/.env" ]; then
    echo ""
    echo "⚠️  Warning: .env file not found!"
    echo "   Please copy .env.example to .env and configure it:"
    echo "   cp .env.example .env"
    echo ""
    echo "   Press Ctrl+C to exit, or Enter to continue anyway..."
    read
fi

# Check if port is already in use
if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    echo ""
    echo "❌ Error: Port $PORT is already in use"
    echo "   Try a different port: ./start-server.sh 8081"
    exit 1
fi

echo ""
echo "=========================================="
echo "Server is running!"
echo "=========================================="
echo ""
echo "  🌐 Access the site at:"
echo "     http://localhost:$PORT"
echo ""

# If on a local network, show LAN IP
LOCAL_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
if [ -n "$LOCAL_IP" ]; then
    echo "  📱 Access from other devices:"
    echo "     http://$LOCAL_IP:$PORT"
    echo ""
fi

echo "  📋 Streaming features:"
echo "     - FFmpeg-powered HLS transcoding"
echo "     - Hardware acceleration support"
echo "     - Intelligent caching with resume"
echo ""
echo "  ⚙️  Configuration:"
echo "     Edit .env to enable/disable streaming"
echo "     Run ./setup-ffmpeg.sh to setup hardware acceleration"
echo ""
echo "  Press Ctrl+C to stop the server"
echo "=========================================="
echo ""

# Start the PHP built-in server
php -S "$HOST:$PORT" -t "$DOCROOT"

# This will only execute if the server is stopped
echo ""
echo "Server stopped."
