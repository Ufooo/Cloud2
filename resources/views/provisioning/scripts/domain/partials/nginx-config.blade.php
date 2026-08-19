# Netipar Cloud - Domain Configuration
# Domain: {{ $domain }}
# Site: {{ $site->domain }}
# Generated: {{ now()->toISOString() }}

@if($wwwRedirectType->value === 'to_primary')
@include('provisioning.scripts.partials.nginx-redirect-to-primary', [
    'domain' => $domain,
    'primaryDomain' => $primaryDomain,
])
@else
include /etc/nginx/netipar-conf/{{ $site->domain }}/before/*;
include /etc/nginx/netipar-conf/{{ $site->domain }}/{{ $domain }}/before/*;

@php
    $behavior = $wildcardBehavior ?? \Nip\Domain\Enums\WildcardBehavior::Serve;

    // Evaluated once and handed to both blocks below. If the two could compute
    // it separately they could disagree, and either the wildcard is claimed
    // twice (conflicting server name) or by nobody at all (every subdomain
    // falls through to the catch-all).
    $wildcardRedirects = ($allowWildcard ?? false)
        && $behavior->redirectsUnconfiguredSubdomains()
        && isset($certificate)
        && $certificate
        && $certificate->coversDomain('*.'.$domain);
@endphp
@include('provisioning.scripts.partials.nginx-server-block', [
    'site' => $site,
    'domain' => $domain,
    'applicationPath' => $applicationPath,
    'documentRoot' => $documentRoot,
    'phpSocket' => $phpSocket,
    'siteType' => $siteType,
    'allowWildcard' => $allowWildcard,
    'wwwRedirectType' => $wwwRedirectType,
    'wildcardRedirects' => $wildcardRedirects,
])
@include('provisioning.scripts.partials.nginx-wildcard-redirect', [
    'domain' => $domain,
    'certificate' => $certificate ?? null,
    'wildcardRedirects' => $wildcardRedirects,
])

include /etc/nginx/netipar-conf/{{ $site->domain }}/after/*;
include /etc/nginx/netipar-conf/{{ $site->domain }}/{{ $domain }}/after/*;
@endif
