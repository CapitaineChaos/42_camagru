#!/bin/sh
set -e

CERT_DIR="$(dirname "$0")/certs"
mkdir -p "$CERT_DIR"

if [ -f "$CERT_DIR/selfsigned.crt" ] && [ -f "$CERT_DIR/selfsigned.key" ]; then
    echo "Certificates already exist in $CERT_DIR - skipping generation."
    exit 0
fi

openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout "$CERT_DIR/selfsigned.key" \
    -out "$CERT_DIR/selfsigned.crt" \
    -subj "/C=FR/ST=IDF/L=Paris/O=42/OU=camagru/CN=localhost"

echo "Self-signed certificate generated in $CERT_DIR"
