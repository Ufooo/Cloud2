<?php

use Illuminate\Support\Facades\Cache;
use Nip\Network\Data\BannedIpData;
use Nip\Network\Services\Fail2banService;
use Nip\Server\Models\Server;
use Nip\Server\Services\SSH\ExecutionResult;
use Nip\Server\Services\SSH\SSHService;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

beforeEach(function () {
    Location::fake([
        '8.8.8.8' => new Position([
            'countryName' => 'United States',
            'countryCode' => 'US',
        ]),
        '1.1.1.1' => new Position([
            'countryName' => 'Australia',
            'countryCode' => 'AU',
        ]),
    ]);
});

it('enriches banned IPs with GeoIP data', function () {
    Cache::shouldReceive('remember')
        ->with('geoip:8.8.8.8', \Mockery::any(), \Mockery::any())
        ->andReturn(['country' => 'United States', 'countryCode' => 'US']);

    Cache::shouldReceive('remember')
        ->with('geoip:1.1.1.1', \Mockery::any(), \Mockery::any())
        ->andReturn(['country' => 'Australia', 'countryCode' => 'AU']);

    $ssh = Mockery::mock(SSHService::class);
    $server = Server::factory()->create();

    $now = now()->timestamp;
    $ssh->shouldReceive('connect')->with($server)->once();
    $ssh->shouldReceive('exec')
        ->once()
        ->andReturn(new ExecutionResult(
            output: "sshd|8.8.8.8|{$now}|3600|5\nsshd|1.1.1.1|{$now}|7200|2",
            exitCode: 0,
            duration: 0.1
        ));

    $service = new Fail2banService($ssh);
    $bans = $service->getBannedIps($server);

    expect($bans)->toHaveCount(2)
        ->and($bans[0])->toBeInstanceOf(BannedIpData::class)
        ->and($bans[0]->ip)->toBe('8.8.8.8')
        ->and($bans[0]->country)->toBe('United States')
        ->and($bans[0]->countryCode)->toBe('US')
        ->and($bans[1]->ip)->toBe('1.1.1.1')
        ->and($bans[1]->country)->toBe('Australia')
        ->and($bans[1]->countryCode)->toBe('AU');
});

it('handles GeoIP lookup failures gracefully', function () {
    Cache::shouldReceive('remember')
        ->with('geoip:192.168.1.1', \Mockery::any(), \Mockery::any())
        ->andReturn(['country' => null, 'countryCode' => null]);

    $ssh = Mockery::mock(SSHService::class);
    $server = Server::factory()->create();

    $now = now()->timestamp;
    $ssh->shouldReceive('connect')->with($server)->once();
    $ssh->shouldReceive('exec')
        ->once()
        ->andReturn(new ExecutionResult(
            output: "sshd|192.168.1.1|{$now}|3600|1",
            exitCode: 0,
            duration: 0.1
        ));

    $service = new Fail2banService($ssh);
    $bans = $service->getBannedIps($server);

    expect($bans)->toHaveCount(1)
        ->and($bans[0]->ip)->toBe('192.168.1.1')
        ->and($bans[0]->country)->toBeNull()
        ->and($bans[0]->countryCode)->toBeNull();
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
