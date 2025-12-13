<?php

namespace Nip\Site\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Models\Server;
use Nip\Site\Database\Factories\SiteFactory;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, HasSlug;

    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('domain')
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'server_id',
        'domain',
        'slug',
        'type',
        'status',
        'deploy_status',
        'www_redirect_type',
        'allow_wildcard',
        'user',
        'root_directory',
        'web_directory',
        'php_version',
        'package_manager',
        'build_command',
        'repository',
        'branch',
        'deploy_key',
        'deploy_script',
        'environment',
        'is_isolated',
        'avatar_color',
        'notes',
        'last_deployed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => SiteType::class,
            'status' => SiteStatus::class,
            'deploy_status' => DeployStatus::class,
            'www_redirect_type' => WwwRedirectType::class,
            'allow_wildcard' => 'boolean',
            'package_manager' => PackageManager::class,
            'avatar_color' => IdentityColor::class,
            'is_isolated' => 'boolean',
            'last_deployed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<\Nip\Domain\Models\DomainRecord, $this>
     */
    public function domainRecords(): HasMany
    {
        return $this->hasMany(\Nip\Domain\Models\DomainRecord::class);
    }

    /**
     * @return HasOne<\Nip\Domain\Models\DomainRecord, $this>
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(\Nip\Domain\Models\DomainRecord::class)
            ->where('type', \Nip\Domain\Enums\DomainRecordType::Primary);
    }

    /**
     * @return HasMany<\Nip\Domain\Models\Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(\Nip\Domain\Models\Certificate::class);
    }

    /**
     * @return HasMany<\Nip\Scheduler\Models\ScheduledJob, $this>
     */
    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(\Nip\Scheduler\Models\ScheduledJob::class);
    }

    public function getFullPath(): string
    {
        return "/home/{$this->user}/{$this->domain}";
    }

    public function getWebPath(): string
    {
        $webDir = $this->web_directory === '/' ? '' : $this->web_directory;

        return $this->getFullPath().$webDir;
    }

    public function getUrl(): string
    {
        return 'https://'.$this->domain;
    }

    public function getDisplayableRepository(): ?string
    {
        if (! $this->repository) {
            return null;
        }

        $branch = $this->branch ?? 'main';

        return "{$this->repository}:{$branch}";
    }
}
