<?php

use Nip\Network\Data\BannedIpData;
use Nip\Network\Services\Fail2banService;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

beforeEach(function () {
    // Position has no constructor in stevebauman/location v7 — passing an array
    // is silently discarded, so the properties must be assigned explicitly.
    Location::fake([
        '8.8.8.8' => tap(new Position, function (Position $position) {
            $position->countryName = 'United States';
            $position->countryCode = 'US';
        }),
        '1.1.1.1' => tap(new Position, function (Position $position) {
            $position->countryName = 'Australia';
            $position->countryCode = 'AU';
        }),
    ]);
});

it('parses banned IPs from the fail2ban database', function () {
    $ssh = Mockery::mock(SSHService::class);
    $server = Server::factory()->create();

    $now = now()->timestamp;
    $ssh->shouldReceive('connect')->with($server)->once();

    // Two calls: the bans query, then the sshd attempted-users enrichment.
    $ssh->shouldReceive('exec')
        ->twice()
        ->andReturn(
            new ExecutionResult(
                output: "sshd|8.8.8.8|{$now}|3600|5\nsshd|1.1.1.1|{$now}|7200|2",
                exitCode: 0,
                duration: 0.1
            ),
            new ExecutionResult(output: '', exitCode: 0, duration: 0.1)
        );

    $service = new Fail2banService($ssh);
    $bans = $service->getBannedIps($server);

    expect($bans)->toHaveCount(2)
        ->and($bans[0])->toBeInstanceOf(BannedIpData::class)
        ->and($bans[0]->ip)->toBe('8.8.8.8')
        ->and($bans[1]->ip)->toBe('1.1.1.1');
});

it('resolves GeoIP data for IP addresses', function () {
    $service = new Fail2banService(Mockery::mock(SSHService::class));

    $result = $service->resolveGeoIp(['8.8.8.8', '1.1.1.1']);

    expect($result['8.8.8.8'])->toBe(['country' => 'United States', 'countryCode' => 'US'])
        ->and($result['1.1.1.1'])->toBe(['country' => 'Australia', 'countryCode' => 'AU']);
});

it('handles GeoIP lookup failures gracefully', function () {
    $service = new Fail2banService(Mockery::mock(SSHService::class));

    $result = $service->resolveGeoIp(['192.168.1.1']);

    expect($result['192.168.1.1'])->toBe(['country' => null, 'countryCode' => null]);
});

it('returns empty array when SSH command fails', function () {
    $ssh = Mockery::mock(SSHService::class);
    $server = Server::factory()->create();

    $ssh->shouldReceive('connect')->with($server)->once();
    $ssh->shouldReceive('exec')
        ->once()
        ->andReturn(new ExecutionResult(
            output: '',
            exitCode: 1,
            duration: 0.1
        ));

    $service = new Fail2banService($ssh);
    $bans = $service->getBannedIps($server);

    expect($bans)->toBeEmpty();
});
