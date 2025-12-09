// Re-export generated types and enums
export {
    IdentityColor,
    ServerProvider,
    ServerStatus,
    ServerType,
    type ServerData as Server,
    type ServerCreateData,
    type ServerPermissionsData,
    type ServerProviderOptionData,
    type ServerTypeOptionData,
} from './generated';

export interface ServerListResponse {
    data: import('./generated').ServerData[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}
