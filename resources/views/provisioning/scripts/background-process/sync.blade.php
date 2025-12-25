#!/bin/bash
set -e

# Netipar Cloud - Sync Background Process
# Process ID: {{ $processId }}
# Name: {{ $name }}

PROGRAM_NAME="netipar-{{ $processId }}"
CONFIG_FILE="/etc/supervisor/conf.d/${PROGRAM_NAME}.conf"

# Create supervisor configuration
cat > "${CONFIG_FILE}" <<'SUPERVISOR_CONFIG'
[program:{{ 'netipar-'.$processId }}]
command={{ $command }}
directory={{ $directory }}
user={{ $user }}
numprocs={{ $processes }}
autostart=true
autorestart=true
startsecs={{ $startsecs }}
stopwaitsecs={{ $stopwaitsecs }}
stopsignal={{ $stopsignal }}
stdout_logfile=/var/log/supervisor/{{ 'netipar-'.$processId }}.log
stderr_logfile=/var/log/supervisor/{{ 'netipar-'.$processId }}.error.log
SUPERVISOR_CONFIG

# Reload supervisor configuration
supervisorctl reread
supervisorctl update

# Start or restart the program
if supervisorctl status "${PROGRAM_NAME}" 2>/dev/null | grep -q "RUNNING"; then
    supervisorctl restart "${PROGRAM_NAME}"
else
    supervisorctl start "${PROGRAM_NAME}" || true
fi

echo "Background process ${PROGRAM_NAME} synced successfully"
