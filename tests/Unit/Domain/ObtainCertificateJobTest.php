<?php

use Nip\Domain\Jobs\ObtainCertificateJob;
use Nip\Domain\Models\Certificate;

it('verifies certificate deployment from output', function () {
    $certificate = Mockery::mock(Certificate::class)->makePartial();
    $job = new ObtainCertificateJob($certificate);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('verifyCertificateDeployed');
    $method->setAccessible(true);

    // Null output should fail verification
    expect($method->invoke($job, null))->toBeFalse();

    // Empty output should fail verification
    expect($method->invoke($job, ''))->toBeFalse();

    // Output without success indicators should fail
    expect($method->invoke($job, 'Some random output'))->toBeFalse();

    // Output with "Certificate deployed to" should pass
    expect($method->invoke($job, 'Some output... Certificate deployed to /etc/nginx/ssl'))->toBeTrue();

    // Output with "obtained successfully!" should pass
    expect($method->invoke($job, 'Some output... obtained successfully!'))->toBeTrue();

    // Output with "CERT_EXPIRES:" should pass
    expect($method->invoke($job, 'CERT_EXPIRES:2025-03-30'))->toBeTrue();

    // Output that only shows propagation waiting should fail
    $incompleteOutput = <<<'OUTPUT'
Obtaining Let's Encrypt certificate via DNS-01 challenge for salesboard.hu...
Creating directories...
TXT record created successfully (ID: abc123)
Waiting for DNS propagation...
Waiting for propagation... (1/24)
Waiting for propagation... (2/24)
Waiting for propagation... (3/24)
OUTPUT;
    expect($method->invoke($job, $incompleteOutput))->toBeFalse();

    // Complete successful output should pass
    $completeOutput = <<<'OUTPUT'
Obtaining Let's Encrypt certificate via DNS-01 challenge for salesboard.hu...
Creating directories...
TXT record created successfully (ID: abc123)
Waiting for DNS propagation...
DNS record propagated successfully!
Certificate deployed to /etc/nginx/ssl/11/8
Let's Encrypt certificate (DNS-01) obtained successfully!
CERT_EXPIRES:2025-03-30
OUTPUT;
    expect($method->invoke($job, $completeOutput))->toBeTrue();
});
