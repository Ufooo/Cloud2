#!/bin/bash
set -e

# Netipar Cloud - Sync Background Process
# Process ID: {{ $processId }}
# Name: {{ $name }}

PROGRAM_NAME="netipar-{{ $processId }}"
CONFIG_FILE="/etc/supervisor/conf.d/${PROGRAM_NAME}.conf"
LOG_DIR="/home/{{ $user }}/.netipar"

# Ensure log directory exists
mkdir -p "${LOG_DIR}"
chown {{ $user }}:{{ $user }} "${LOG_DIR}"

# Create supervisor configuration
cat > "${CONFIG_FILE}" <<'SUPERVISOR_CONFIG'
[program:{{ 'netipar-'.$processId }}]
command={{ $command }}
directory={{ $directory }}
process_name=%(program_name)s_%(process_num)02d
user={{ $user }}
numprocs={{ $processes }}
autostart=true
autorestart=true
startsecs={{ $startsecs }}
stopwaitsecs={{ $stopwaitsecs }}
stopsignal=SIG{{ $stopsignal }}
redirect_stderr=true
stdout_logfile=/home/{{ $user }}/.netipar/{{ 'netipar-'.$processId }}.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
stopasgroup=true
killasgroup=true
SUPERVISOR_CONFIG

# Reload supervisor configuration
supervisorctl reread
supervisorctl update

# Start the program
supervisorctl start "${PROGRAM_NAME}:*"

echo "Background process ${PROGRAM_NAME} synced successfully"
