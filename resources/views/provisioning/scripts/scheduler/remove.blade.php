#!/bin/bash
set -e

# Netipar Cloud - Remove Scheduled Job
# Job ID: {{ $jobId }}
# User: {{ $user }}

CRON_USER="{{ $user }}"
JOB_ID="{{ $jobId }}"

# Get current crontab (ignore error if empty)
CURRENT_CRONTAB=$(crontab -u "${CRON_USER}" -l 2>/dev/null || echo "")

# Remove the entry for this job ID
NEW_CRONTAB=$(echo "${CURRENT_CRONTAB}" | grep -v "# netipar-job:${JOB_ID}$" || true)

# Install the updated crontab
if [ -z "${NEW_CRONTAB}" ]; then
    # If crontab is now empty, remove it entirely
    crontab -u "${CRON_USER}" -r 2>/dev/null || true
else
    echo "${NEW_CRONTAB}" | crontab -u "${CRON_USER}" -
fi

echo "Scheduled job ${JOB_ID} removed successfully for user ${CRON_USER}"
