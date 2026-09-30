#
# Nginx http2 syntax
#
# The standalone "http2 on;" directive exists from nginx 1.25.1. Older releases stop at "unknown
# directive" and only know http2 as a parameter of listen. The templates that render a 443 block
# (nginx-server-block, nginx-wildcard-redirect, nginx-www-redirect, nginx-config-redirect) write
# the standalone form, because a template cannot know which nginx its output lands on. The server
# can, so a script passes every file it has just written through nginx_http2_syntax: on an nginx
# that is too old the standalone directive is folded back into the listen lines, otherwise the
# file is left alone. Files that do not exist are skipped, and running it twice changes nothing.
#
# Same probe and threshold as certificate/enable-ssl, and it writes the same two forms. An nginx
# from 1.25.1 up to 1.26.0 gets the listen parameter, which is deprecated there but still valid.
#

NGINX_VERSION=$(nginx -v 2>&1 | grep -o '[0-9.]\+')
VERSION_NUM=$(echo "$NGINX_VERSION" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')
MIN_VERSION=$(echo "1.26.0" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')

nginx_http2_syntax() {
    local config_file config

    if [ "$VERSION_NUM" -ge "$MIN_VERSION" ]; then
        return 0
    fi

    for config_file in "$@"; do
        if [ -f "$config_file" ]; then
            config=$(sed \
                -e '/^    http2 on;$/d' \
                -e 's/^    listen 443 ssl;$/    listen 443 ssl http2;/' \
                -e 's/^    listen \[::\]:443 ssl;$/    listen [::]:443 ssl http2;/' \
                "$config_file")
            printf '%s\n' "$config" > "$config_file"
        fi
    done
}
