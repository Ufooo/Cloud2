# Netipar Cloud - Domain Configuration
# Domain: {{ $domain }}
# Site: {{ $site->domain }}
# Generated: {{ now()->toISOString() }}

include /etc/nginx/netipar-conf/{{ $site->domain }}/before/*;
include /etc/nginx/netipar-conf/{{ $site->domain }}/{{ $domain }}/before/*;

@include('provisioning.scripts.partials.nginx-server-block', [
    'site' => $site,
    'domain' => $domain,
    'applicationPath' => $applicationPath,
    'documentRoot' => $documentRoot,
    'phpSocket' => $phpSocket,
    'siteType' => $siteType,
    'allowWildcard' => $allowWildcard,
    'wwwRedirectType' => $wwwRedirectType,
])

include /etc/nginx/netipar-conf/{{ $site->domain }}/after/*;
include /etc/nginx/netipar-conf/{{ $site->domain }}/{{ $domain }}/after/*;
