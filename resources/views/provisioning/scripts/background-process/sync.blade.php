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

# A deploy restarts this program as {{ $user }}, which needs sudo rights for
# supervisorctl. Those were only granted while creating a unix user, so older
# and imported users never got them and their deploys skipped the restart in
# silence. Granting it here covers every user that actually runs a daemon.
#
# The rule goes through a temporary file: an invalid sudoers file locks every
# user out of sudo, so nothing is installed before visudo accepts it.
SUDOERS_FILE="/etc/sudoers.d/supervisor"

if ! grep -q "^{{ $user }} " "${SUDOERS_FILE}" 2>/dev/null; then
    TMP_SUDOERS=$(mktemp)
    [ -f "${SUDOERS_FILE}" ] && cat "${SUDOERS_FILE}" > "${TMP_SUDOERS}"
    echo "{{ $user }} ALL=NOPASSWD: /usr/bin/supervisorctl *" >> "${TMP_SUDOERS}"

    if visudo -c -f "${TMP_SUDOERS}" > /dev/null; then
        install -o root -g root -m 0440 "${TMP_SUDOERS}" "${SUDOERS_FILE}"
        echo "Granted supervisorctl permission to {{ $user }}"
    else
        echo "WARNING: refused to install an invalid sudoers file for {{ $user }}"
    fi

    rm -f "${TMP_SUDOERS}"
fi

# Create supervisor configuration
cat > "${CONFIG_FILE}" <<'SUPERVISOR_CONFIG'
[program:{{ 'netipar-'.$processId }}]
command={!! $command !!}
directory={!! $directory !!}
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
