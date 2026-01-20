<?php

use Nip\SecurityMonitor\Enums\GitChangeType;
use Nip\SecurityMonitor\Services\GitStatusParser;

beforeEach(function () {
    $this->parser = new GitStatusParser;
});

it('parses valid JSON with various change types', function () {
    $jsonOutput = json_encode([
        'sites' => [
            [
                'path' => '/var/www/example.com',
                'changes' => [
                    ['status' => ' M', 'type' => 'modified', 'file' => 'app/Models/User.php'],
                    ['status' => '??', 'type' => 'untracked', 'file' => 'public/test.txt'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'old-file.php'],
                ],
            ],
        ],
    ]);

    $result = $this->parser->parse($jsonOutput);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('sites')
        ->and($result['sites'])->toHaveCount(1)
        ->and($result['sites'][0]['path'])->toBe('/var/www/example.com')
        ->and($result['sites'][0]['changes'])->toHaveCount(3);
});

it('parses empty results', function () {
    $jsonOutput = json_encode(['sites' => []]);

    $result = $this->parser->parse($jsonOutput);

    expect($result)->toBeArray()
        ->and($result['sites'])->toBeEmpty();
});

it('throws exception for invalid JSON', function () {
    $invalidJson = 'not valid json {';

    expect(fn () => $this->parser->parse($invalidJson))
        ->toThrow(RuntimeException::class, 'Invalid JSON output from git scan');
});

it('extracts changes for a specific site path', function () {
    $parsedData = [
        'sites' => [
            [
                'path' => '/var/www/site1.com',
                'changes' => [
                    ['status' => ' M', 'type' => 'modified', 'file' => 'file1.php'],
                ],
            ],
            [
                'path' => '/var/www/site2.com',
                'changes' => [
                    ['status' => '??', 'type' => 'untracked', 'file' => 'file2.php'],
                    ['status' => ' D', 'type' => 'deleted', 'file' => 'file3.php'],
                ],
            ],
        ],
    ];

    $changes = $this->parser->getChangesForSite($parsedData, '/var/www/site2.com');

    expect($changes)->toHaveCount(2)
        ->and($changes[0]['file'])->toBe('file2.php')
        ->and($changes[1]['file'])->toBe('file3.php');
});

it('returns empty array when site path not found', function () {
    $parsedData = [
        'sites' => [
            ['path' => '/var/www/site1.com', 'changes' => []],
        ],
    ];

    $changes = $this->parser->getChangesForSite($parsedData, '/var/www/nonexistent.com');

    expect($changes)->toBeEmpty();
});

it('returns empty array when site has no changes', function () {
    $parsedData = [
        'sites' => [
            ['path' => '/var/www/site1.com'],
        ],
    ];

    $changes = $this->parser->getChangesForSite($parsedData, '/var/www/site1.com');

    expect($changes)->toBeEmpty();
});

it('retrieves site error message', function () {
    $parsedData = [
        'sites' => [
            [
                'path' => '/var/www/site1.com',
                'error' => 'Not a git repository',
            ],
        ],
    ];

    $error = $this->parser->getSiteError($parsedData, '/var/www/site1.com');

    expect($error)->toBe('Not a git repository');
});

it('returns null when site has no error', function () {
    $parsedData = [
        'sites' => [
            ['path' => '/var/www/site1.com', 'changes' => []],
        ],
    ];

    $error = $this->parser->getSiteError($parsedData, '/var/www/site1.com');

    expect($error)->toBeNull();
});

it('maps git status codes to change types correctly', function () {
    expect($this->parser->mapStatusToChangeType('??'))->toBe(GitChangeType::Untracked)
        ->and($this->parser->mapStatusToChangeType('MM'))->toBe(GitChangeType::Modified)
        ->and($this->parser->mapStatusToChangeType('DD'))->toBe(GitChangeType::Deleted)
        ->and($this->parser->mapStatusToChangeType('AA'))->toBe(GitChangeType::Added);
});

it('maps git status codes with spaces after trimming', function () {
    expect($this->parser->mapStatusToChangeType(' M'))->toBe(GitChangeType::Unknown)
        ->and($this->parser->mapStatusToChangeType('M '))->toBe(GitChangeType::Unknown)
        ->and($this->parser->mapStatusToChangeType(' D'))->toBe(GitChangeType::Unknown)
        ->and($this->parser->mapStatusToChangeType('D '))->toBe(GitChangeType::Unknown);
});

it('maps unknown status codes to Unknown change type', function () {
    expect($this->parser->mapStatusToChangeType('XX'))->toBe(GitChangeType::Unknown)
        ->and($this->parser->mapStatusToChangeType(''))->toBe(GitChangeType::Unknown)
        ->and($this->parser->mapStatusToChangeType('ZZ'))->toBe(GitChangeType::Unknown);
});
