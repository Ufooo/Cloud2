#!/bin/bash
set -e

# Netipar Cloud - Restart Background Process

PROGRAM_NAME="{{ $programName }}"

supervisorctl restart "${PROGRAM_NAME}"

echo "Background process ${PROGRAM_NAME} restarted successfully"
