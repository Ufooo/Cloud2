# Redirect to Primary Domain
# {{ $domain }} -> {{ $primaryDomain }}
server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain }} www.{{ $domain }};
    server_tokens off;

    # NETIPAR SSL (DO NOT REMOVE!)
    # ssl_certificate;
    # ssl_certificate_key;

    return 301 $scheme://{{ $primaryDomain }}$request_uri;
}
