    apt-get install -y --force-yes unattended-upgrades

# Configure Fail2ban custom filter for nginx probe/scanner detection
cat > /etc/fail2ban/filter.d/nginx-probe-scanner.conf << 'EOF'
# Fail2Ban filter for nginx probe/scanner detection
# Catches PHP backdoor probes, .env access attempts, WordPress exploits

[Definition]

failregex = ^<HOST> \- \S+ \[\] \"(GET|POST|HEAD) /\.env\b
            ^<HOST> \- \S+ \[\] \"(GET|POST|HEAD) /[^\"]*\.env\b
            ^<HOST> \- \S+ \[\] \"(GET|POST|HEAD) /wp-(admin|login|content|includes)/[^\"]*\.php
            ^<HOST> \- \S+ \[\] \"(GET|POST|HEAD) /[a-z0-9]{1,8}\.php\b[^\"]*\" (301|404|403|444|400)
            ^<HOST> \- \S+ \[\] \"(GET|POST|HEAD) /[^\"]*(?:phpinfo|setup-config|filemanager|wp_filemanager)[^\"]*\"

ignoreregex =

datepattern = {^LN-BEG}%%ExY(?P<_sep>[-/.])%%m(?P=_sep)%%d[T ]%%H:%%M:%%S(?:[.,]%%f)?(?:\s*%%z)?
              ^[^\[]*\[({DATE})
              {^LN-BEG}

journalmatch = _SYSTEMD_UNIT=nginx.service + _COMM=nginx
EOF

# Configure Fail2ban with hardened settings
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
# Ban time: 24 hours (increased from default 10 minutes)
bantime = 24h

# Ban after 3 failed attempts
maxretry = 3

# Detection window: 10 minutes
findtime = 10m

# Whitelist localhost, server's own IP, and the Cloud platform IP
ignoreip = 127.0.0.1/8 ::1 {{ $server->ip_address }}@if($server->private_ip_address) {{ ' ' . $server->private_ip_address }}@endif @if(config('services.platform.ip')){{ ' ' . config('services.platform.ip') }}@endif

[sshd]
enabled = true
port = ssh
maxretry = 3
bantime = 24h

# Recidive jail - ban repeat offenders for 1 week
[recidive]
enabled = true
logpath = /var/log/fail2ban.log
banaction = %(banaction_allports)s
bantime = 1w
findtime = 1d
maxretry = 3

# Nginx: malformed/bad requests (400 errors)
[nginx-bad-request]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
backend = auto
maxretry = 3
bantime = 24h
findtime = 5m

# Nginx: bot scanner 404 probes
[nginx-botsearch]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
backend = auto
maxretry = 3
bantime = 24h
findtime = 10m

# Nginx: rate limit violations
[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
backend = auto
maxretry = 5
bantime = 1h
findtime = 1m

# Nginx: PHP/env probe scanner - strictest
[nginx-probe-scanner]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
backend = auto
maxretry = 2
bantime = 1w
findtime = 1m
EOF

systemctl restart fail2ban

cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
};
Unattended-Upgrade::Package-Blacklist {
    //
};
EOF

cat > /etc/apt/apt.conf.d/10periodic << EOF
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
APT::Periodic::Unattended-Upgrade "1";
EOF

# Disable protected_regular

sudo sed -i "s/fs.protected_regular = .*/fs.protected_regular = 0/" /usr/lib/sysctl.d/99-protect-links.conf

sysctl --system
# Configure Additional Log Rotation

if [[ $(grep --count "maxsize" /etc/logrotate.d/fail2ban) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/fail2ban
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/fail2ban
fi
if [[ $(grep --count "maxsize" /etc/logrotate.d/rsyslog) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/rsyslog
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/rsyslog
fi
if [[ $(grep --count "maxsize" /etc/logrotate.d/ufw) == 0 ]]; then
    sed -i -r "s/^(\s*)(daily|weekly|monthly|yearly)$/\1\2\n\1maxsize 100M/" /etc/logrotate.d/ufw
else
    sed -i -r "s/^(\s*)maxsize.*$/\1maxsize 100M/" /etc/logrotate.d/ufw
fi

cat > /etc/systemd/system/timers.target.wants/logrotate.timer << EOF
[Unit]
Description=Rotation of log files
Documentation=man:logrotate(8) man:logrotate.conf(5)

[Timer]
OnCalendar=*:0/1

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl restart logrotate.timer

# Fix incorrect logrotate default configuration
sed -i -r "s/^create 0640 www-data adm/create 0640 netipar adm/" /etc/logrotate.d/nginx
