#!/bin/bash

# update.sh
# Run this script inside your project folder to update only core/ and zero

# Variables
URL="https://github.com/0php/Zero/archive/refs/heads/main.zip"
ZIP_FILE="main.zip"
DEFAULT_DIR="Zero-main"

echo "🔄 Updating Zero PHP Framework..."

# Create a temporary folder
TMP_DIR=$(mktemp -d)

# Download and extract the latest source
curl -L -o "$TMP_DIR/$ZIP_FILE" "$URL"
unzip -q "$TMP_DIR/$ZIP_FILE" -d "$TMP_DIR"

# Copy only core/ and zero file into current folder
rsync -a --delete "$TMP_DIR/$DEFAULT_DIR/core/" ./core/
cp -f "$TMP_DIR/$DEFAULT_DIR/zero" ./zero

# Cleanup
rm -rf "$TMP_DIR"

echo "✅ Update Zero PHP Framework completed"
