#!/bin/bash
set -e

# Netipar Cloud - Stop Background Process

PROGRAM_NAME="{{ $programName }}"

supervisorctl stop "${PROGRAM_NAME}"

echo "Background process ${PROGRAM_NAME} stopped successfully"
