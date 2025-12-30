@if($wwwRedirectType->value !== 'none' && !($allowWildcard ?? false))
# WWW Redirect
server {
    listen 80;
    listen [::]:80;
    server_tokens off;
@if($wwwRedirectType->value === 'from_www')
    server_name www.{{ $domain }};
    return 301 $scheme://{{ $domain }}$request_uri;
@else
    server_name {{ $domain }};
    return 301 $scheme://www.{{ $domain }}$request_uri;
@endif
}
@endif
