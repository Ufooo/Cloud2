#!/bin/bash
set -e

# Netipar Cloud - Start Background Process

PROGRAM_NAME="{{ $programName }}"

supervisorctl start "${PROGRAM_NAME}"

echo "Background process ${PROGRAM_NAME} started successfully"
