export interface User {
    id: number;
    name: string;
    email: string | null;
    email_verified_at?: string;
    slack_user_id: string | null;
    avatar_url?: string;
    is_admin: boolean;
    is_active: boolean;
    access_token?: string;
    refresh_token?: string;
    token_expires_at?: string;
    last_login_at?: string;

    display_name?: string;
    message_count?: number;
    accessible_channel_count?: number;
    last_sync_at?: string | null;
}

export interface Workspace {
    id: number;
    name: string;
    // 必要なら display_name や description も追加
  }

  export interface Channel {
    id: number;
    name: string;
    is_private: boolean;
    is_dm: boolean;
  }

  export interface SlackFile {
    id: number;
    name: string;
    mime_type: string;
    size: number;
    url: string;
    created_at: string;
  }

  export interface PaginationData<T> {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    data: T[];

    // 👇 追加
    prev_page_url?: string | null;
    next_page_url?: string | null;
    links?: { url: string | null; label: string; active: boolean }[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
