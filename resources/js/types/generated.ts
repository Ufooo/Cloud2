export type CertificateData = {
    id: string;
    siteId: string;
    type: string;
    displayableType: string;
    status: string;
    displayableStatus: string;
    statusBadgeVariant: string;
    domains: { [key: number]: string };
    active: boolean;
    path: string | null;
    issuedAt: string | null;
    issuedAtHuman: string | null;
    expiresAt: string | null;
    expiresAtHuman: string | null;
    isExpiringSoon: boolean;
    daysUntilExpiry: number | null;
    createdAt: string | null;
    can: CertificatePermissionsData;
};
export type CertificatePermissionsData = {
    delete: boolean;
    activate: boolean;
    deactivate: boolean;
    renew: boolean;
};
export enum CertificateStatus {
    Pending = 'pending',
    PendingVerification = 'pending_verification',
    Installing = 'installing',
    Installed = 'installed',
    Renewing = 'renewing',
    Removing = 'removing',
    Failed = 'failed',
}
export enum CertificateType {
    LetsEncrypt = 'letsencrypt',
    Existing = 'existing',
    Csr = 'csr',
    Clone = 'clone',
}
export enum ComposerCredentialStatus {
    Pending = 'pending',
    Syncing = 'syncing',
    Synced = 'synced',
    Deleting = 'deleting',
    Failed = 'failed',
}
export enum CronFrequency {
    EveryMinute = 'every_minute',
    Hourly = 'hourly',
    Nightly = 'nightly',
    Weekly = 'weekly',
    Monthly = 'monthly',
    OnReboot = 'on_reboot',
    Custom = 'custom',
}
export enum DatabaseStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Deleting = 'deleting',
    Failed = 'failed',
}
export enum DatabaseType {
    Mysql80 = 'mysql80',
    Mariadb1011 = 'mariadb1011',
    Mariadb114 = 'mariadb114',
    Postgresql16 = 'postgresql16',
    Postgresql17 = 'postgresql17',
    Postgresql18 = 'postgresql18',
    Mysql = 'mysql',
    Mariadb = 'mariadb',
    Postgresql = 'postgresql',
}
export enum DatabaseUserStatus {
    Pending = 'pending',
    Installing = 'installing',
    Syncing = 'syncing',
    Installed = 'installed',
    Deleting = 'deleting',
    Failed = 'failed',
}
export enum DeployStatus {
    NeverDeployed = 'never_deployed',
    Deploying = 'deploying',
    Deployed = 'deployed',
    Failed = 'failed',
}
export enum DeploymentStatus {
    Pending = 'pending',
    Deploying = 'deploying',
    Finished = 'finished',
    Failed = 'failed',
}
export enum DetectedPackage {
    Laravel = 'laravel',
    Horizon = 'horizon',
    Inertia = 'inertia',
    Octane = 'octane',
    Reverb = 'reverb',
    Pulse = 'pulse',
    Telescope = 'telescope',
    Nova = 'nova',
    Cashier = 'cashier',
    Pennant = 'pennant',
    Sanctum = 'sanctum',
    Passport = 'passport',
    Socialite = 'socialite',
    Scout = 'scout',
    Breeze = 'breeze',
    Jetstream = 'jetstream',
    Folio = 'folio',
    Livewire = 'livewire',
}
export type DetectedPackageData = {
    value: string;
    label: string;
    description: string;
    hasEnableAction: boolean;
    enableActionLabel: string | null;
};
export type DomainRecordData = {
    id: string;
    siteId: string;
    certificateId: string | null;
    isSecured: boolean;
    certificateType: string | null;
    name: string;
    type: string;
    displayableType: string;
    status: string;
    displayableStatus: string;
    statusBadgeVariant: string;
    wwwRedirectType: string;
    wwwRedirectTypeLabel: string;
    allowWildcard: boolean;
    isPrimary: boolean;
    url: string;
    createdAt: string | null;
    can: DomainRecordPermissionsData;
};
export type DomainRecordPermissionsData = {
    update: boolean;
    delete: boolean;
    makePrimary: boolean;
};
export enum DomainRecordStatus {
    Pending = 'pending',
    Creating = 'creating',
    Enabled = 'enabled',
    Disabled = 'disabled',
    Updating = 'updating',
    Securing = 'securing',
    Removing = 'removing',
    Disabling = 'disabling',
    Enabling = 'enabling',
    Failed = 'failed',
}
export enum DomainRecordType {
    Primary = 'primary',
    Alias = 'alias',
    Reverb = 'reverb',
}
export type FirewallRuleData = {
    id: number;
    name: string;
    port: string | null;
    ipAddress: string | null;
    type: RuleType;
    status: RuleStatus;
    displayableType: string;
    displayableStatus: string;
};
export enum GracePeriod {
    OneMinute = 1,
    TwoMinutes = 2,
    FiveMinutes = 5,
    TenMinutes = 10,
    ThirtyMinutes = 30,
    OneHour = 60,
}
export enum IdentityColor {
    Blue = 'blue',
    Green = 'green',
    Orange = 'orange',
    Purple = 'purple',
    Red = 'red',
    Yellow = 'yellow',
    Cyan = 'cyan',
    Gray = 'gray',
}
export enum JobStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Paused = 'paused',
    Deleting = 'deleting',
    Failed = 'failed',
}
export enum PackageManager {
    Npm = 'npm',
    Yarn = 'yarn',
    Pnpm = 'pnpm',
    Bun = 'bun',
}
export type PhpSettingData = {
    id: number;
    maxUploadSize: number | null;
    maxExecutionTime: number | null;
    opcacheEnabled: boolean;
};
export enum PhpVersion {
    Php84 = 'php84',
    Php83 = 'php83',
    Php82 = 'php82',
    Php81 = 'php81',
    Php74 = 'php74',
}
export type PhpVersionData = {
    id: number;
    version: string;
    isCliDefault: boolean;
    isSiteDefault: boolean;
    status: PhpVersionStatus;
    createdAt: string | null;
};
export enum PhpVersionStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Uninstalling = 'uninstalling',
    Failed = 'failed',
}
export enum ProcessStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Deleting = 'deleting',
    Failed = 'failed',
}
export enum ProvisionScriptStatus {
    Pending = 'pending',
    Executing = 'executing',
    Completed = 'completed',
    Failed = 'failed',
}
export enum ProvisioningStep {
    WaitingForServer = 0,
    PreparingServer = 1,
    ConfiguringSwap = 2,
    InstallingBaseDependencies = 3,
    InstallingPhp = 4,
    InstallingNginx = 5,
    InstallingDatabase = 6,
    InstallingRedis = 7,
    MakingFinalTouches = 10,
}
export type ProvisioningStepData = {
    value: number;
    label: string;
    description: string;
};
export enum RedirectRuleStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Updating = 'updating',
    Removing = 'removing',
    Failed = 'failed',
}
export enum RedirectType {
    Permanent = 'permanent',
    Temporary = 'temporary',
}
export enum RuleStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Failed = 'failed',
    Deleting = 'deleting',
}
export enum RuleType {
    Allow = 'allow',
    Deny = 'deny',
}
export enum SecurityRuleStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Updating = 'updating',
    Removing = 'removing',
    Failed = 'failed',
}
export type ServerCreateData = {
    name: string;
    provider: ServerProvider;
    type: ServerType;
    ipAddress: string | null;
    privateIpAddress: string | null;
    sshPort: string;
    phpVersion: string;
    databaseType: string | null;
    ubuntuVersion: string | null;
    timezone: string;
    notes: string | null;
    avatarColor: IdentityColor;
    services: Array<any> | null;
};
export type ServerData = {
    id: number;
    name: string;
    slug: string;
    provider: ServerProvider;
    providerServerId: string | null;
    type: ServerType;
    displayableType: string | null;
    status: ServerStatus;
    provisioningCommand: string | null;
    provisionStep: number;
    provisioningSteps: Array<ProvisioningStepData> | null;
    ipAddress: string | null;
    privateIpAddress: string | null;
    sshPort: string;
    phpVersion: string;
    displayablePhpVersion: string | null;
    databaseType: DatabaseType | null;
    dbStatus: string | null;
    ubuntuVersion: string | null;
    timezone: Timezone;
    notes: string | null;
    avatarColor: IdentityColor;
    services: Array<any> | null;
    displayableProvider: string | null;
    displayableDatabaseType: string | null;
    cloudProviderUrl: string | null;
    isReady: boolean;
    gitPublicKey: string | null;
    lastConnectedAt: any | null;
    createdAt: any;
    updatedAt: any;
    can: ServerPermissionsData;
};
export type ServerPermissionsData = {
    view: boolean;
    update: boolean;
    delete: boolean;
};
export enum ServerProvider {
    DigitalOcean = 'digitalocean',
    Vultr = 'vultr',
    Custom = 'custom',
}
export type ServerProviderOptionData = {
    value: ServerProvider;
    label: string;
};
export enum ServerStatus {
    Connecting = 'connecting',
    Connected = 'connected',
    Disconnected = 'disconnected',
    Deleting = 'deleting',
    Provisioning = 'provisioning',
    Locked = 'locked',
    Resizing = 'resizing',
    Stopping = 'stopping',
    Off = 'off',
    Unknown = 'unknown',
}
export enum ServerType {
    App = 'app',
    Web = 'web',
    LoadBalancer = 'loadbalancer',
    Database = 'database',
    Cache = 'cache',
    Worker = 'worker',
    Meilisearch = 'meilisearch',
}
export type ServerTypeOptionData = {
    value: ServerType;
    label: string;
    description: string;
};
export type SiteData = {
    id: string;
    slug: string;
    serverId: number;
    serverName: string | null;
    serverSlug: string | null;
    domain: string;
    type: string | null;
    displayableType: string | null;
    status: string | null;
    displayableStatus: string | null;
    statusBadgeVariant: string | null;
    provisioningStep: number | null;
    provisioningSteps: Array<SiteProvisioningStepData> | null;
    deployStatus: string | null;
    displayableDeployStatus: string | null;
    deployStatusBadgeVariant: string | null;
    user: string;
    rootDirectory: string;
    webDirectory: string;
    fullPath: string;
    webPath: string;
    url: string;
    phpVersion: string | null;
    phpVersionValue: string | null;
    packageManager: string | null;
    buildCommand: string | null;
    repository: string | null;
    branch: string | null;
    displayableRepository: string | null;
    avatarColor: IdentityColor | null;
    notes: string | null;
    lastDeployedAt: string | null;
    lastDeployedAtHuman: string | null;
    createdAt: string | null;
    deployScript: string | null;
    pushToDeploy: boolean;
    autoSource: boolean;
    deployHookUrl: string | null;
    deploymentRetention: number;
    zeroDowntime: boolean;
    healthcheckEndpoint: string | null;
    deployKey: string | null;
    detectedPackages: Array<string> | null;
    packageDetails: Array<DetectedPackageData> | null;
    packages: { [key: string]: boolean } | null;
    can: SitePermissionsData;
};
export enum SitePackage {
    Laravel = 'laravel',
    Horizon = 'horizon',
    Octane = 'octane',
    Pulse = 'pulse',
    Reverb = 'reverb',
    Inertia = 'inertia',
    Nightwatch = 'nightwatch',
    InertiaSsr = 'inertia_ssr',
    Scheduler = 'scheduler',
    Maintenance = 'maintenance',
}
export type SitePermissionsData = {
    update: boolean;
    delete: boolean;
    deploy: boolean;
};
export enum SiteProvisioningStep {
    Initializing = 0,
    CreatingSiteConfigDirectory = 1,
    CreatingNginxServerBlock = 2,
    ConfiguringWwwRedirect = 3,
    EnablingNginxSite = 4,
    CreatingPhpFpmPool = 5,
    RestartingServices = 6,
    CreatingLogrotateConfig = 7,
    CreatingSiteDirectory = 10,
    CloningRepository = 11,
    ConfiguringEnvironment = 12,
    InstallingComposerDependencies = 13,
    BuildingFrontendAssets = 14,
    RunningMigrations = 15,
    FinalizingSite = 99,
}
export type SiteProvisioningStepData = {
    value: number;
    label: string;
    description: string;
};
export enum SiteStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Failed = 'failed',
    Deleting = 'deleting',
}
export enum SiteType {
    Laravel = 'laravel',
    Symfony = 'symfony',
    Statamic = 'statamic',
    WordPress = 'wordpress',
    PhpMyAdmin = 'phpmyadmin',
    Php = 'php',
    NextJs = 'nextjs',
    NuxtJs = 'nuxtjs',
    Html = 'html',
    Other = 'other',
}
export enum SourceControlProvider {
    GitHub = 'github',
    GitLab = 'gitlab',
    Bitbucket = 'bitbucket',
}
export type SshKeyData = {
    id: number;
    name: string;
    fingerprint: string;
    createdAt: string;
    unixUser: UnixUserData | null;
};
export enum SshKeyStatus {
    Pending = 'pending',
    Installed = 'installed',
    Failed = 'failed',
    Deleting = 'deleting',
}
export enum StopSignal {
    TERM = 'TERM',
    HUP = 'HUP',
    INT = 'INT',
    QUIT = 'QUIT',
    KILL = 'KILL',
    USR1 = 'USR1',
    USR2 = 'USR2',
}
export enum SupervisorProcessStatus {
    Running = 'RUNNING',
    Starting = 'STARTING',
    Stopping = 'STOPPING',
    Stopped = 'STOPPED',
    Backoff = 'BACKOFF',
    Exited = 'EXITED',
    Fatal = 'FATAL',
    Unknown = 'UNKNOWN',
}
export enum Timezone {
    UTC = 'UTC',
    EuropeLondon = 'Europe/London',
    EuropeParis = 'Europe/Paris',
    EuropeBerlin = 'Europe/Berlin',
    EuropeBudapest = 'Europe/Budapest',
    EuropeMoscow = 'Europe/Moscow',
    AmericaNewYork = 'America/New_York',
    AmericaChicago = 'America/Chicago',
    AmericaDenver = 'America/Denver',
    AmericaLosAngeles = 'America/Los_Angeles',
    AsiaTokyo = 'Asia/Tokyo',
    AsiaShanghai = 'Asia/Shanghai',
    AsiaSingapore = 'Asia/Singapore',
    AustraliaSydney = 'Australia/Sydney',
}
export enum UbuntuVersion {
    V2404 = '24.04',
    V2204 = '22.04',
    V2004 = '20.04',
}
export type UnixUserData = {
    id: number;
    username: string;
    status: UserStatus;
    displayableStatus: string;
};
export type UserSshKeyData = {
    id: number;
    name: string;
    fingerprint: string;
    userName: string | null;
    createdAt: string | null;
};
export enum UserStatus {
    Pending = 'pending',
    Installing = 'installing',
    Installed = 'installed',
    Deleting = 'deleting',
    Failed = 'failed',
}
export enum WwwRedirectType {
    FromWww = 'from_www',
    ToWww = 'to_www',
    None = 'none',
}
