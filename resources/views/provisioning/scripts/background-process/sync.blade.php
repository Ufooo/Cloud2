#!/bin/bash
set -e

# Netipar Cloud - Sync Background Process
# Process ID: {{ $processId }}
# Name: {{ $name }}

PROGRAM_NAME="netipar-{{ $processId }}"
CONFIG_FILE="/etc/supervisor/conf.d/${PROGRAM_NAME}.conf"
LOG_DIR="/home/{{ $user }}/.netipar"
@if ($isInitialInstall)

# A program is named after this process's database id, so a config already
# sitting under that name belongs to something else: a daemon installed by
# hand, or one from another Cloud instance whose id space overlaps ours.
# Overwriting it replaces a running daemon without a trace, so a first install
# refuses and leaves the machine untouched. The check comes before anything
# else is written for exactly that reason.
if [ -f "${CONFIG_FILE}" ]; then
    echo "${CONFIG_FILE} already exists and was not written for this process."
    echo "Remove or import the daemon running as ${PROGRAM_NAME}, then retry this installation."
    # The bracketed form is what ProvisionScript::errorMessage() extracts for
    # the failed-script alert, so this line is what the user actually reads.
    echo "[ERROR] Refusing to overwrite supervisor program ${PROGRAM_NAME} - it belongs to another daemon."
    exit 1
fi
@endif

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
