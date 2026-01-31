<?php

use Nip\Domain\Models\Certificate;

it('detects wildcard certificates need dns-01 for renewal', function () {
    // Wildcard cert with null verification_method should use dns
    $cert = new Certificate;
    $cert->domains = ['*.example.com', 'example.com'];
    $cert->verification_method = null;

    $usesDns = $cert->verification_method === 'dns' || $cert->isWildcard();
    expect($usesDns)->toBeTrue();
});

it('uses http for non-wildcard certs with null verification_method', function () {
    $cert = new Certificate;
    $cert->domains = ['example.com', 'www.example.com'];
    $cert->verification_method = null;

    $usesDns = $cert->verification_method === 'dns' || $cert->isWildcard();
    expect($usesDns)->toBeFalse();
});

it('uses dns-01 when verification_method is dns', function () {
    $cert = new Certificate;
    $cert->domains = ['example.com'];
    $cert->verification_method = 'dns';

    $usesDns = $cert->verification_method === 'dns' || $cert->isWildcard();
    expect($usesDns)->toBeTrue();
});
