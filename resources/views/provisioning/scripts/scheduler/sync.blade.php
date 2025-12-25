#!/bin/bash
set -e

# Netipar Cloud - Sync Scheduled Job
# Job ID: {{ $jobId }}
# User: {{ $user }}

CRON_USER="{{ $user }}"
JOB_ID="{{ $jobId }}"
CRON_EXPRESSION="{{ $cronExpression }}"
COMMAND="{{ $command }}"

# Create the cron line with a marker comment
CRON_LINE="${CRON_EXPRESSION} ${COMMAND} # netipar-job:${JOB_ID}"

# Get current crontab (ignore error if empty)
CURRENT_CRONTAB=$(crontab -u "${CRON_USER}" -l 2>/dev/null || echo "")

# Remove any existing entry for this job ID
NEW_CRONTAB=$(echo "${CURRENT_CRONTAB}" | grep -v "# netipar-job:${JOB_ID}$" || true)

# Add the new cron line
if [ -z "${NEW_CRONTAB}" ]; then
    NEW_CRONTAB="${CRON_LINE}"
else
    NEW_CRONTAB="${NEW_CRONTAB}
${CRON_LINE}"
fi

# Install the new crontab
echo "${NEW_CRONTAB}" | crontab -u "${CRON_USER}" -

echo "Scheduled job ${JOB_ID} synced successfully for user ${CRON_USER}"
