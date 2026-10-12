@php
    // The helpers sit above the shebang on purpose: a "#!" line at the very start of the
    // compiled view is dropped when PHP includes it (measured under the CLI worker), so
    // with the shebang first it would never reach the rendered script.

    $confDir = '/etc/nginx/netipar-conf/'.$site->domain;
    $isPhpSite = (bool) $site->type?->isPhpBased();
    $phpSocket = $site->getPhpSocketPath();

    // This script is built from quoted heredocs whose terminator is a line of its
    // own, so a value that carries a line break could close one early and run the
    // rest as shell. Nothing a user typed is allowed to span lines.
    $oneLine = fn (string $value): string => (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);

    // The realm is an nginx string that may contain variables: "$" would start one
    // and fail `nginx -t`, so it is dropped; the quote and backslash are escaped.
    // nginx also reads auth_basic "off" as the switch that turns authentication off
    // (the quotes are gone by the time it compares), which would leave the site open
    // while the rule says installed, so a realm that reads "off" is replaced.
    $realmOf = function (string $name) use ($oneLine): string {
        $realm = str_replace('$', '', $oneLine($name));

        if ($realm === '' || strcasecmp($realm, 'off') === 0) {
            $realm = 'Restricted';
        }

        return addcslashes($realm, '"\\');
    };

    // Nothing is written unless every path is in the canonical shape: a path the form request
    // would have refused (a legacy row, a seeder, a console edit) gives a location that never
    // matches, or one that answers before the site's dotfile deny. Only the rule ids are named
    // in the message, never the offending value.
    $invalidPathRuleIds = $rules
        ->filter(fn ($rule): bool => $rule->path !== null && ! preg_match(\Nip\Security\Models\SecurityRule::PATH_PATTERN, $rule->path))
        ->pluck('id');

    // A path rule on a PHP site has to repeat the site's fastcgi handling, and that needs the
    // PHP-FPM socket. Without it the only way to write the rule would be to leave the handling
    // out, which serves the .php files below the path as plain source. So nothing is written at
    // all. The fastcgi handling lives only in path blocks: a site-wide rule never refers to the
    // socket and taking the last rule away writes nothing, so both keep working on a site that
    // has no PHP version (typically an imported one). The test is the same "has a path" split
    // the rule loop below makes.
    $abortReason = null;

    if ($invalidPathRuleIds->isNotEmpty()) {
        $abortReason = '[ERROR] A rule has a path that is not a plain URL path (rule '.$invalidPathRuleIds->implode(', ').'), so it cannot be written safely. Edit the rule and save it again.';
    } elseif ($isPhpSite && blank($phpSocket) && $rules->contains(fn ($rule): bool => $rule->path !== null)) {
        $abortReason = '[ERROR] A rule with a path needs the PHP socket of '.e($site->domain).', but the site has no PHP version set, so it cannot be written safely. Set the PHP version of the site, or remove the path from the rule (a site-wide rule works without it), then save again.';
    }
@endphp
#!/bin/bash
set -eu
trap '' PIPE HUP

# Netipar Cloud - Sync Security Rules (HTTP basic auth)
# Site: {{ $site->domain }}
#
# server/auth.conf is pulled in by the `include netipar-conf/<site>/server/*;`
# hook inside the server block, and that block is shared by EVERY domain of the
# site: a rule written here protects all of the site's domains, not only the
# primary one.
#
# The password files live in htpasswd/, never inside server/: nginx parses every
# file in server/ as configuration, so `nginx -t` fails on a password file. The
# same goes for the working copies auth.conf.new and auth.conf.prev below.

echo "Syncing security rules for {{ $site->domain }}..."

@if($abortReason !== null)
echo "{!! $abortReason !!}"
exit 1
@else
SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"
AUTH_CONF="$SITE_CONF_DIR/server/auth.conf"
AUTH_CONF_NEW="$SITE_CONF_DIR/auth.conf.new"
AUTH_CONF_PREV="$SITE_CONF_DIR/auth.conf.prev"
HTPASSWD_DIR="$SITE_CONF_DIR/htpasswd"
SYNC_DONE=0

mkdir -p "$SITE_CONF_DIR/server" "$HTPASSWD_DIR"

# Keep the configuration that works today, so a sync that does not make it through can put
# it back instead of leaving the site without its protection. Without a copy there was no
# previous configuration (an old copy of an interrupted run must not be mistaken for one).
if [ -f "$AUTH_CONF" ]; then
    cp -p "$AUTH_CONF" "$AUTH_CONF_PREV"
else
    rm -f "$AUTH_CONF_PREV"
fi
rm -f "$AUTH_CONF_NEW"

# From here on there is something to put back, and this is the only place that does it: it
# runs however the script ends (a failed nginx test, a failing command, a signal) unless the
# reload went through. TERM and INT are turned into a plain exit so that it also runs for
# them, whatever the bash version; HUP and a closed output pipe are ignored above, so a
# dropped connection cannot stop a sync halfway.
on_exit() {
    rc=$?
    set +e
    rm -f "$AUTH_CONF_NEW"
    if [ "$SYNC_DONE" != 1 ]; then
        if [ -f "$AUTH_CONF_PREV" ]; then
            mv -f "$AUTH_CONF_PREV" "$AUTH_CONF"
            echo "Previous security config restored - the protection that was active before is unchanged"
        else
            rm -f "$AUTH_CONF"
            echo "There was no previous security config - the new one was removed"
        fi
        nginx -t
    fi
    exit "$rc"
}
trap on_exit EXIT
trap 'exit 143' TERM
trap 'exit 130' INT

@if($rules->isEmpty())
# No security rules - remove the configuration if it exists
echo "Removing HTTP authentication (no rules)..."
rm -f "$AUTH_CONF"
@else
echo "Writing {{ $rules->count() }} security rule(s)..."

# Password hashes must not be readable by other users, not even for the moment
# between creating a password file and the chmod below.
OLD_UMASK=$(umask)
umask 077

@foreach($rules as $rule)
cat > "$HTPASSWD_DIR/{{ $rule->id }}" << 'HTPASSWDEOF{{ $rule->id }}'
@foreach($rule->credentials as $credential)
{!! $oneLine($credential->username) !!}:{!! $oneLine($credential->password) !!}
@endforeach
HTPASSWDEOF{{ $rule->id }}
chown root:netipar "$HTPASSWD_DIR/{{ $rule->id }}"
chmod 640 "$HTPASSWD_DIR/{{ $rule->id }}"

@endforeach
umask "$OLD_UMASK"

# Written next to server/ and moved into place, so nginx never reads a half-written file.
cat > "$AUTH_CONF_NEW" << 'AUTHEOF'
# Netipar Cloud - HTTP Basic Auth for {{ $site->domain }}
# Auto-generated - do not edit manually
#
# Included in the server block of EVERY domain of this site.

@foreach($rules as $rule)
@php
    // Path rules: a "^~" prefix location ends nginx's regex matching, so none of the site's own
    // regex locations (the "~ \.php$" handler, the dotfile deny) run below it any more. A .php file
    // would be sent out as plain source and dotfiles would be readable. The nested locations
    // repeat both, the dotfile deny first because the first matching regex location wins.
    // The prefix "/admin" would also match "/administrator", hence an exact "= /admin" block
    // plus a "^~ /admin/" block instead of "^~ /admin". nginx forbids a location inside an exact
    // one, so a path that is itself a PHP file (/wp-login.php) gets its fastcgi handling right in
    // the exact block and has no "^~" pair: a file has nothing below it.
    $realm = $realmOf($rule->name);
    $userFile = $confDir.'/htpasswd/'.$rule->id;
    $isPhpFile = $isPhpSite && str_ends_with((string) $rule->path, '.php');
@endphp
@if($rule->path === null)
# Rule {{ $rule->id }}: the whole site
auth_basic "{!! $realm !!}";
auth_basic_user_file {{ $userFile }};
@elseif($isPhpFile)
# Rule {{ $rule->id }}: {{ $rule->path }}
location = {{ $rule->path }} {
    auth_basic "{!! $realm !!}";
    auth_basic_user_file {{ $userFile }};
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:{{ $phpSocket }};
    fastcgi_index index.php;
    include fastcgi_params;
    include netipar_fastcgi_defaults;
}
@else
# Rule {{ $rule->id }}: {{ $rule->path }}
location = {{ $rule->path }} {
    auth_basic "{!! $realm !!}";
    auth_basic_user_file {{ $userFile }};
@if($isPhpSite)
    try_files $uri $uri/ /index.php?$query_string;
@else
    try_files $uri $uri/ =404;
@endif
}

location ^~ {{ $rule->path }}/ {
    auth_basic "{!! $realm !!}";
    auth_basic_user_file {{ $userFile }};
@if($isPhpSite)
    try_files $uri $uri/ /index.php?$query_string;
@else
    try_files $uri $uri/ =404;
@endif

    location ~ /\.(?!well-known).* {
        deny all;
    }
@if($isPhpSite)

    location ~ \.php$ {
        auth_basic "{!! $realm !!}";
        auth_basic_user_file {{ $userFile }};
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:{{ $phpSocket }};
        fastcgi_index index.php;
        include fastcgi_params;
        include netipar_fastcgi_defaults;
    }
@endif
}
@endif

@endforeach
AUTHEOF
mv -f "$AUTH_CONF_NEW" "$AUTH_CONF"
@endif

# The password files above are already rewritten when nginx tests the configuration, and a
# rollback puts back only auth.conf. After a rejected sync the previous configuration can
# therefore be paired with the new password hashes: the site stays protected (a rejected
# change never opens it), but the old password stops working until the next successful sync
# writes everything again. That is intended, not a bug.
echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "[ERROR] Nginx rejected the security configuration (see its output above), putting the previous one back..."
    exit 1
fi

echo "Reloading Nginx..."
service nginx reload
SYNC_DONE=1

rm -f "$AUTH_CONF_PREV"

@php
    $keepArgs = $rules->map(fn ($rule): string => "! -name '{$rule->id}'")->implode(' ');
@endphp
# Drop the password files of rules that are gone. This happens after the reload so
# the running configuration never points at a file that was just deleted.
find "$HTPASSWD_DIR" -mindepth 1 -maxdepth 1 -type f {!! $keepArgs !!} -print -delete

echo "Security rules synced successfully!"
@endif
