<?php

namespace Nip\Site\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Gate;
use Nip\BackgroundProcess\Models\BackgroundProcess;
use Nip\Composer\Models\ComposerCredential;
use Nip\Database\Models\Database;
use Nip\Database\Models\DatabaseUser;
use Nip\Domain\Enums\DomainRecordType;
use Nip\Domain\Models\Certificate;
use Nip\Domain\Models\DomainRecord;
use Nip\Php\Enums\PhpVersion;
use Nip\Redirect\Models\RedirectRule;
use Nip\Scheduler\Models\ScheduledJob;
use Nip\Security\Models\SecurityRule;
use Nip\Server\Enums\IdentityColor;
use Nip\Server\Models\Server;
use Nip\Site\Data\SitePermissionsData;
use Nip\Site\Data\SiteProvisioningStepData;
use Nip\Site\Database\Factories\SiteFactory;
use Nip\Site\Enums\DeployStatus;
use Nip\Site\Enums\PackageManager;
use Nip\Site\Enums\SiteProvisioningStep;
use Nip\Site\Enums\SiteStatus;
use Nip\Site\Enums\SiteType;
use Nip\Site\Enums\WwwRedirectType;
use Nip\SourceControl\Models\SourceControl;
use Nip\SourceControl\Services\GitHubService;
use Nip\UnixUser\Models\UnixUser;
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
        'source_control_id',
        'database_id',
        'database_user_id',
        'domain',
        'slug',
        'type',
        'status',
        'provisioning_step',
        'batch_id',
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
        'push_to_deploy',
        'auto_source',
        'deploy_hook_token',
        'deployment_retention',
        'zero_downtime',
        'healthcheck_endpoint',
        'environment',
        'avatar_color',
        'packages',
        'notes',
        'detected_packages',
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
            'provisioning_step' => SiteProvisioningStep::class,
            'deploy_status' => DeployStatus::class,
            'www_redirect_type' => WwwRedirectType::class,
            'allow_wildcard' => 'boolean',
            'php_version' => PhpVersion::class,
            'package_manager' => PackageManager::class,
            'avatar_color' => IdentityColor::class,
            'packages' => 'array',
            'push_to_deploy' => 'boolean',
            'auto_source' => 'boolean',
            'zero_downtime' => 'boolean',
            'deployment_retention' => 'integer',
            'detected_packages' => 'array',
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
     * @return BelongsTo<SourceControl, $this>
     */
    public function sourceControl(): BelongsTo
    {
        return $this->belongsTo(SourceControl::class);
    }

    /**
     * @return BelongsTo<UnixUser, $this>
     */
    public function unixUser(): BelongsTo
    {
        return $this->belongsTo(UnixUser::class, 'user', 'username')
            ->where('server_id', $this->server_id);
    }

    /**
     * @return HasMany<DomainRecord, $this>
     */
    public function domainRecords(): HasMany
    {
        return $this->hasMany(DomainRecord::class);
    }

    /**
     * @return HasOne<DomainRecord, $this>
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(DomainRecord::class)
            ->where('type', DomainRecordType::Primary);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return BelongsTo<Database, $this>
     */
    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    /**
     * @return BelongsTo<DatabaseUser, $this>
     */
    public function databaseUser(): BelongsTo
    {
        return $this->belongsTo(DatabaseUser::class);
    }

    /**
     * @return HasMany<ScheduledJob, $this>
     */
    public function scheduledJobs(): HasMany
    {
        return $this->hasMany(ScheduledJob::class);
    }

    /**
     * @return HasMany<BackgroundProcess, $this>
     */
    public function backgroundProcesses(): HasMany
    {
        return $this->hasMany(BackgroundProcess::class);
    }

    /**
     * @return HasMany<SecurityRule, $this>
     */
    public function securityRules(): HasMany
    {
        return $this->hasMany(SecurityRule::class);
    }

    /**
     * @return HasMany<RedirectRule, $this>
     */
    public function redirectRules(): HasMany
    {
        return $this->hasMany(RedirectRule::class);
    }

    /**
     * @return HasMany<ComposerCredential, $this>
     */
    public function composerCredentials(): HasMany
    {
        return $this->hasMany(ComposerCredential::class);
    }

    public function getFullPath(): string
    {
        $directory = $this->root_directory === '/' ? $this->domain : $this->root_directory;

        return "/home/{$this->user}/{$directory}";
    }

    public function getCurrentPath(): string
    {
        return $this->zero_downtime
            ? "{$this->getFullPath()}/current"
            : $this->getFullPath();
    }

    public function getRootPath(): string
    {
        return $this->getCurrentPath().$this->web_directory;
    }

    public function getWebPath(): string
    {
        $webDir = $this->web_directory === '/' ? '' : $this->web_directory;

        return $this->getCurrentPath().$webDir;
    }

    public function getPhpSocketPath(): ?string
    {
        $phpVersion = $this->php_version?->version();

        if (! $phpVersion) {
            return null;
        }

        return "/var/run/php/php{$phpVersion}-fpm-{$this->user}.sock";
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

    public function getDeployHookUrl(): ?string
    {
        if (! $this->deploy_hook_token) {
            return null;
        }

        return url("/deploy/{$this->deploy_hook_token}");
    }

    public function regenerateDeployHookToken(): void
    {
        $this->update([
            'deploy_hook_token' => bin2hex(random_bytes(32)),
        ]);
    }

    public function getAuthenticatedCloneUrl(): ?string
    {
        if (! $this->repository || ! $this->sourceControl) {
            return null;
        }

        $service = new GitHubService($this->sourceControl);

        return $service->getCloneUrl($this->repository);
    }

    public function getCloneUrl(): ?string
    {
        if (! $this->repository) {
            return null;
        }

        if ($this->sourceControl) {
            return $this->getAuthenticatedCloneUrl();
        }

        return "https://github.com/{$this->repository}.git";
    }

    /**
     * @return Attribute<array<SiteProvisioningStepData>, never>
     */
    protected function provisioningSteps(): Attribute
    {
        return Attribute::get(fn () => $this->getProvisioningSteps());
    }

    public function canBeUpdated(?User $user = null): bool
    {
        return Gate::forUser($user ?? request()->user())->allows('update', $this);
    }

    public function canBeDeleted(?User $user = null): bool
    {
        return Gate::forUser($user ?? request()->user())->allows('delete', $this);
    }

    public function canBeDeployed(?User $user = null): bool
    {
        return Gate::forUser($user ?? request()->user())->allows('deploy', $this);
    }

    public function getPermissions(?User $user = null): SitePermissionsData
    {
        return new SitePermissionsData(
            update: $this->canBeUpdated($user),
            delete: $this->canBeDeleted($user),
            deploy: $this->canBeDeployed($user),
        );
    }

    /**
     * @return array<SiteProvisioningStepData>
     */
    public function getProvisioningSteps(): array
    {
        $steps = [
            SiteProvisioningStep::Initializing,
            SiteProvisioningStep::CreatingSiteConfigDirectory,
            SiteProvisioningStep::CreatingNginxServerBlock,
            SiteProvisioningStep::ConfiguringWwwRedirect,
            SiteProvisioningStep::EnablingNginxSite,
            SiteProvisioningStep::CreatingPhpFpmPool,
            SiteProvisioningStep::RestartingServices,
            SiteProvisioningStep::CreatingLogrotateConfig,
            SiteProvisioningStep::CreatingSiteDirectory,
            SiteProvisioningStep::CloningRepository,
            SiteProvisioningStep::ConfiguringEnvironment,
        ];

        if ($this->repository) {
            $steps[] = SiteProvisioningStep::InstallingComposerDependencies;
        }

        if ($this->repository && $this->build_command) {
            $steps[] = SiteProvisioningStep::BuildingFrontendAssets;
        }

        if ($this->repository && $this->type?->hasMigrations()) {
            $steps[] = SiteProvisioningStep::RunningMigrations;
        }

        $steps[] = SiteProvisioningStep::FinalizingSite;

        return array_map(
            fn (SiteProvisioningStep $step) => new SiteProvisioningStepData(
                value: $step->value,
                label: $step->label(),
                description: $step->description(),
            ),
            $steps
        );
    }

    /**
     * Scope to query sites with the same user on the same server, excluding the given site.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForSameUserOnServer(Builder $query, self $site): Builder
    {
        return $query
            ->where('server_id', $site->server_id)
            ->where('user', $site->user)
            ->whereNot('id', $site->id);
    }
}
