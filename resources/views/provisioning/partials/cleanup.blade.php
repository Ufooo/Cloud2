    # Add The Provisioning Cleanup Script Into Root Directory

cat > /root/netipar-cleanup.sh << 'EOF'
#!/usr/bin/env bash

# netipar Provisioning Cleanup Script

UID_MIN=$(awk '/^UID_MIN/ {print $2}' /etc/login.defs)
UID_MAX=$(awk '/^UID_MAX/ {print $2}' /etc/login.defs)
HOME_DIRECTORIES=$(eval getent passwd {0,{${UID_MIN}..${UID_MAX}}} | cut -d: -f6)

for DIRECTORY in $HOME_DIRECTORIES
do
  USER_DIRECTORY="$DIRECTORY/.netipar"

  if [ ! -d $USER_DIRECTORY ]
  then
    continue
  fi

  echo "Cleaning $USER_DIRECTORY..."

  find $USER_DIRECTORY -type f -mtime +30 -print0 | xargs -r0 rm --
done
EOF

chmod +x /root/netipar-cleanup.sh

echo "" | tee -a /etc/crontab
echo "# netipar Provisioning Cleanup" | tee -a /etc/crontab
tee -a /etc/crontab <<"CRONJOB"
0 0 * * * root bash /root/netipar-cleanup.sh 2>&1
CRONJOB
