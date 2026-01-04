#!/bin/bash
set -e

# Netipar Cloud - Remove Background Process
# Process ID: {{ $processId }}

PROGRAM_NAME="{{ $programName }}"
CONFIG_FILE="/etc/supervisor/conf.d/${PROGRAM_NAME}.conf"

# Stop the program group if running
if supervisorctl status "${PROGRAM_NAME}:*" 2>/dev/null; then
    supervisorctl stop "${PROGRAM_NAME}:*" || true
fi

# Remove the configuration file
if [ -f "${CONFIG_FILE}" ]; then
    rm -f "${CONFIG_FILE}"
fi

# Reload supervisor configuration
supervisorctl reread
supervisorctl update

echo "Background process ${PROGRAM_NAME} removed successfully"
