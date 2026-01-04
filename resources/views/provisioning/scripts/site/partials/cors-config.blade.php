@php
// Get all domains for this site (including www variants)
$allDomains = [];
foreach ($site->domainRecords as $domainRecord) {
    $domain = $domainRecord->domain;
    $allDomains[] = $domain;

    // Add www variant if not already a www domain
    if (!str_starts_with($domain, 'www.')) {
        $allDomains[] = 'www.' . $domain;
    }
}

// Escape dots for regex and build pattern
$escapedDomains = array_map(fn($d) => str_replace('.', '\\.', $d), $allDomains);
$regexPattern = implode('|', $escapedDomains);
@endphp
#
# Regenerate CORS Configuration
#

echo "Regenerating CORS configuration..."
cat > "$SITE_CONF_DIR/cors.conf" << 'CORSEOF'
# Netipar Cloud - CORS Configuration
# Allows cross-origin requests between site domains
# Generated: {{ now()->toIso8601String() }}

set $cors_origin "";
if ($http_origin ~* "^https://({{ $regexPattern }})$") {
    set $cors_origin $http_origin;
}

add_header Access-Control-Allow-Origin $cors_origin always;
add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always;
add_header Access-Control-Allow-Headers "Origin, Content-Type, Accept, Authorization, X-Requested-With, X-XSRF-TOKEN" always;
add_header Access-Control-Allow-Credentials "true" always;
CORSEOF
