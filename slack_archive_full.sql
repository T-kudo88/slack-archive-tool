--
-- PostgreSQL database dump
--

\restrict BYD9RGGM8hF6sGaJfauJih9AmTgaRoNvgydkdeqOj1ZjzDqRHMkH7tqqKo0pmwq

-- Dumped from database version 15.14
-- Dumped by pg_dump version 15.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    admin_user_id character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    resource_type character varying(255) NOT NULL,
    resource_id bigint NOT NULL,
    accessed_user_id character varying(255),
    ip_address character varying(45),
    user_agent text,
    notes text,
    metadata json,
    created_at timestamp(0) without time zone NOT NULL
);


ALTER TABLE public.audit_logs OWNER TO slack_user;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.audit_logs_id_seq OWNER TO slack_user;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO slack_user;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO slack_user;

--
-- Name: channel_users; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.channel_users (
    id bigint NOT NULL,
    channel_id character varying(255) NOT NULL,
    user_id character varying(255) NOT NULL,
    is_admin boolean DEFAULT false NOT NULL,
    joined_at timestamp(0) without time zone,
    left_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.channel_users OWNER TO slack_user;

--
-- Name: channel_users_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.channel_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.channel_users_id_seq OWNER TO slack_user;

--
-- Name: channel_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.channel_users_id_seq OWNED BY public.channel_users.id;


--
-- Name: channels; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.channels (
    workspace_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    is_private boolean DEFAULT false NOT NULL,
    is_dm boolean DEFAULT false NOT NULL,
    is_mpim boolean DEFAULT false NOT NULL,
    is_archived boolean DEFAULT false NOT NULL,
    member_count integer DEFAULT 0 NOT NULL,
    last_synced_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_im boolean DEFAULT false NOT NULL,
    id character varying(255) NOT NULL,
    slack_channel_id character varying(255)
);


ALTER TABLE public.channels OWNER TO slack_user;

--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO slack_user;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.failed_jobs_id_seq OWNER TO slack_user;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO slack_user;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO slack_user;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.jobs_id_seq OWNER TO slack_user;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: messages; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.messages (
    id bigint NOT NULL,
    workspace_id bigint NOT NULL,
    channel_id character varying(255) NOT NULL,
    user_id character varying(255),
    slack_message_id character varying(255) NOT NULL,
    text text,
    thread_ts character varying(255),
    "timestamp" numeric(16,6) NOT NULL,
    reply_count integer DEFAULT 0 NOT NULL,
    message_type character varying(255) DEFAULT 'message'::character varying NOT NULL,
    has_files boolean DEFAULT false NOT NULL,
    reactions jsonb,
    metadata jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type character varying(255) DEFAULT 'user'::character varying NOT NULL
);


ALTER TABLE public.messages OWNER TO slack_user;

--
-- Name: messages_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.messages_id_seq OWNER TO slack_user;

--
-- Name: messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.messages_id_seq OWNED BY public.messages.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO slack_user;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.migrations_id_seq OWNER TO slack_user;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO slack_user;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.personal_access_tokens_id_seq OWNER TO slack_user;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: slack_files; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.slack_files (
    id bigint NOT NULL,
    slack_file_id character varying(255) NOT NULL,
    name character varying(255),
    title character varying(255),
    mimetype character varying(255),
    file_type character varying(255),
    pretty_type character varying(255),
    user_id character varying(255) NOT NULL,
    channel_id character varying(255) NOT NULL,
    size bigint DEFAULT '0'::bigint NOT NULL,
    url_private text,
    url_private_download text,
    thumb_64 text,
    thumb_80 text,
    thumb_160 text,
    thumb_360 text,
    thumb_480 text,
    thumb_720 text,
    thumb_800 text,
    thumb_960 text,
    thumb_1024 text,
    permalink text,
    permalink_public text,
    is_external boolean DEFAULT false NOT NULL,
    external_type character varying(255),
    is_public boolean DEFAULT false NOT NULL,
    public_url_shared boolean DEFAULT false NOT NULL,
    display_as_bot boolean DEFAULT false NOT NULL,
    username character varying(255),
    "timestamp" timestamp(0) without time zone,
    local_path character varying(255),
    local_thumbnail_path character varying(255),
    download_status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    file_hash character varying(255),
    metadata json,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    message_id character varying(50) NOT NULL,
    CONSTRAINT slack_files_download_status_check CHECK (((download_status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'completed'::character varying, 'failed'::character varying])::text[])))
);


ALTER TABLE public.slack_files OWNER TO slack_user;

--
-- Name: slack_files_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.slack_files_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.slack_files_id_seq OWNER TO slack_user;

--
-- Name: slack_files_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.slack_files_id_seq OWNED BY public.slack_files.id;


--
-- Name: user_workspace; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.user_workspace (
    id bigint NOT NULL,
    user_id character varying(255) NOT NULL,
    workspace_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.user_workspace OWNER TO slack_user;

--
-- Name: user_workspace_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.user_workspace_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.user_workspace_id_seq OWNER TO slack_user;

--
-- Name: user_workspace_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.user_workspace_id_seq OWNED BY public.user_workspace.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.users (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255),
    avatar_url character varying(255),
    is_admin boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    display_name character varying(255),
    api_token character varying(80),
    api_token_created_at timestamp(0) without time zone,
    api_token_last_used_at timestamp(0) without time zone,
    remember_token character varying(100),
    last_login_at timestamp(0) without time zone,
    slack_user_id character varying(255)
);


ALTER TABLE public.users OWNER TO slack_user;

--
-- Name: workspaces; Type: TABLE; Schema: public; Owner: slack_user
--

CREATE TABLE public.workspaces (
    id bigint NOT NULL,
    slack_team_id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    domain character varying(255),
    bot_token text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.workspaces OWNER TO slack_user;

--
-- Name: workspaces_id_seq; Type: SEQUENCE; Schema: public; Owner: slack_user
--

CREATE SEQUENCE public.workspaces_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.workspaces_id_seq OWNER TO slack_user;

--
-- Name: workspaces_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slack_user
--

ALTER SEQUENCE public.workspaces_id_seq OWNED BY public.workspaces.id;


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: channel_users id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channel_users ALTER COLUMN id SET DEFAULT nextval('public.channel_users_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: messages id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.messages ALTER COLUMN id SET DEFAULT nextval('public.messages_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: slack_files id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.slack_files ALTER COLUMN id SET DEFAULT nextval('public.slack_files_id_seq'::regclass);


--
-- Name: user_workspace id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.user_workspace ALTER COLUMN id SET DEFAULT nextval('public.user_workspace_id_seq'::regclass);


--
-- Name: workspaces id; Type: DEFAULT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.workspaces ALTER COLUMN id SET DEFAULT nextval('public.workspaces_id_seq'::regclass);


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.audit_logs (id, admin_user_id, action, resource_type, resource_id, accessed_user_id, ip_address, user_agent, notes, metadata, created_at) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: channel_users; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.channel_users (id, channel_id, user_id, is_admin, joined_at, left_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: channels; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.channels (workspace_id, name, is_private, is_dm, is_mpim, is_archived, member_count, last_synced_at, created_at, updated_at, is_im, id, slack_channel_id) FROM stdin;
1	random	f	f	f	f	8	\N	2025-09-22 06:42:25	2025-09-22 06:42:25	f	C06C0BHPZDM	\N
1	自己紹介	f	f	f	f	7	\N	2025-09-22 06:42:25	2025-09-22 06:42:25	f	C06C0BPFLMD	\N
1	相談-転職について	f	f	f	f	3	\N	2025-09-22 06:42:26	2025-09-22 06:42:26	f	C06C0BRMTL7	\N
1	プログラミングの質問	f	f	f	f	3	\N	2025-09-22 06:42:26	2025-09-22 06:42:26	f	C06CC08U41K	\N
1	全体連絡	f	f	f	f	8	\N	2025-09-22 06:42:27	2025-09-22 06:42:27	f	C06CEQ59B1R	\N
1	雑談	f	f	f	f	7	\N	2025-09-22 06:44:17	2025-09-22 06:44:17	f	C06CETR50US	\N
1	はじめてのitエンジニア転職	f	f	f	f	2	\N	2025-09-22 06:44:17	2025-09-22 06:44:17	f	C06CTGRS4SV	\N
1	音声コンテンツ	f	f	f	f	8	\N	2025-09-22 06:44:18	2025-09-22 06:44:18	f	C08MKETSJUA	\N
1	mpdm-channel_log_bot--q2313009--apple741run62do-1	t	f	t	f	3	\N	2025-09-22 06:44:19	2025-09-22 06:44:19	f	C09F1G1M63F	\N
1	DM-U09EH5W7LFL	f	t	f	f	0	\N	2025-09-22 06:44:20	2025-09-22 06:44:20	f	D09EH5WAZS6	\N
1	DM-U09CXKL4A7N	f	t	f	f	0	\N	2025-09-22 06:44:20	2025-09-22 06:44:20	f	D09CXKL4UP6	\N
1	DM-U09CSFK19V0	f	t	f	f	0	\N	2025-09-22 06:44:21	2025-09-22 06:44:21	f	D09CSFKDJ90	\N
1	DM-U09BD2QTEQ7	f	t	f	f	0	\N	2025-09-22 06:44:21	2025-09-22 06:44:21	f	D09BD2R2RGX	\N
1	DM-U098M82AYM6	f	t	f	f	0	\N	2025-09-22 06:44:21	2025-09-22 06:44:21	f	D098M84J1MW	\N
1	DM-U096S1CEP43	f	t	f	f	0	\N	2025-09-22 06:44:22	2025-09-22 06:44:22	f	D096S1CLDLK	\N
1	DM-U091J8ERQ2E	f	t	f	f	0	\N	2025-09-22 06:44:22	2025-09-22 06:44:22	f	D091J8F0QLS	\N
1	DM-U090Y5NC5TQ	f	t	f	f	0	\N	2025-09-22 06:44:23	2025-09-22 06:44:23	f	D090Y5NEGG6	\N
1	DM-U08UJHFF0CX	f	t	f	f	0	\N	2025-09-22 06:44:23	2025-09-22 06:44:23	f	D08UJHFJTPH	\N
1	DM-U08SSN8DT3R	f	t	f	f	0	\N	2025-09-22 06:44:24	2025-09-22 06:44:24	f	D08SSNA8BCP	\N
1	DM-U08PHPXLT5E	f	t	f	f	0	\N	2025-09-22 06:44:24	2025-09-22 06:44:24	f	D08PHPXR3JL	\N
1	DM-U08JRAYPX8X	f	t	f	f	0	\N	2025-09-22 06:44:24	2025-09-22 06:44:24	f	D08JRAYQY1M	\N
1	DM-U08JQMLJ030	f	t	f	f	0	\N	2025-09-22 06:44:25	2025-09-22 06:44:25	f	D08JQMLMDNJ	\N
1	DM-U08C1QKSVCG	f	t	f	f	0	\N	2025-09-22 06:44:25	2025-09-22 06:44:25	f	D08B6BTLE0N	\N
1	DM-U087ZHL3Q48	f	t	f	f	0	\N	2025-09-22 06:44:26	2025-09-22 06:44:26	f	D087ALDCTA7	\N
1	DM-U087504HAAC	f	t	f	f	0	\N	2025-09-22 06:44:26	2025-09-22 06:44:26	f	D086URWK8N5	\N
1	DM-U086JMG1GD9	f	t	f	f	0	\N	2025-09-22 06:44:26	2025-09-22 06:44:26	f	D086JMG43K5	\N
1	DM-U085Z02KUDC	f	t	f	f	0	\N	2025-09-22 06:44:27	2025-09-22 06:44:27	f	D0865FP03EX	\N
1	DM-U06D3LY2M4Y	f	t	f	f	0	\N	2025-09-22 06:44:27	2025-09-22 06:44:27	f	D07TZCWBYBT	\N
1	DM-U06L2QWC138	f	t	f	f	0	\N	2025-09-22 06:44:39	2025-09-22 06:44:39	f	D07TLN8TSJE	\N
1	DM-U07THSEMVE1	f	t	f	f	0	\N	2025-09-22 06:44:39	2025-09-22 06:44:39	f	D07TLKLK0BV	\N
1	DM-USLACKBOT	f	t	f	f	0	\N	2025-09-22 06:44:40	2025-09-22 06:44:40	f	D07T667T19V	\N
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: messages; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.messages (id, workspace_id, channel_id, user_id, slack_message_id, text, thread_ts, "timestamp", reply_count, message_type, has_files, reactions, metadata, created_at, updated_at, type) FROM stdin;
1	1	C06C0BHPZDM	U09BD2QTEQ7	1756015507.726979	@はっとりさんがチャンネルに参加しました	\N	1756015507.726979	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:21	2025-09-22 08:03:21	user
2	1	C06C0BPFLMD	U09BD2QTEQ7	1756015508.015899	@はっとりさんがチャンネルに参加しました	\N	1756015508.015899	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:22	2025-09-22 08:03:22	user
3	1	C06C0BPFLMD	U07THSEMVE1	1751987420.654809	1.名前\nクドウタツヤです。\nタツヤと呼んでください！\n\n2.今までのプログラミング経験\n2019年10月頃\nプログラミングを知り、独学開始\n↓\n2020年4月〜2021年7月\nSES企業に入社\n金融機関のクレジットカードアプリのプロジェクトや、大手通信会社のネットワークの運用保守として勤務\n↓\n2021年8月〜2024年5月\n別のSES企業へ転職\n全国の自治体に管理されてある戸籍データを、オンプレからクラウドへ移行するためのプロジェクトへアサインされる(テクニカルサポート)\n↓\n2024年6月〜2025年1月\nWEB系自社開発企業へ、アルバイトとして転職。主にGCPを用いて、WordPressサイトを構築。\n↓\n2025年3月〜6月\nWEB系の自社開発と、受託の両方を請け負う会社へ転職。\nPHP、Laravel、HTML・CSSに触れる。\n\n3. 出身地\n高知県\n\n4. 趣味、好きなコト\n・プロ野球観戦\n・ガンダムシリーズ\n\n5. 一言!\n\n・フリーランスエンジニアとして、しっかりと1人で稼いでいけるエンジニアになる！\n・年収1000万円を安定して稼ぐ！\n・1人社長として、起業する！\n\n年齢の割にまだまだ未熟で、ADHD・ASDも持っていますが、これからの努力で全てを克服していきます！\n\n何卒よろしくお願いします！	1751987420.654809	1751987420.654809	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:22	2025-09-22 08:03:22	user
4	1	C06C0BPFLMD	U06D3LY2M4Y	1752024274.551909	よろしくお願いいたします:relaxed:\n\nタツヤさんには、実践のためのチーム開発課題の準備もしてもらってます！	1751987420.654809	1752024274.551909	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:22	2025-09-22 08:03:22	user
5	1	C06C0BRMTL7	U09BD2QTEQ7	1756015507.945009	@はっとりさんがチャンネルに参加しました	\N	1756015507.945009	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:22	2025-09-22 08:03:22	user
6	1	C06CC08U41K	U09BD2QTEQ7	1756015507.802559	@はっとりさんがチャンネルに参加しました	\N	1756015507.802559	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
7	1	C06CEQ59B1R	U06D3LY2M4Y	1756168895.408399	レビュー参考例です↓	\N	1756168895.408399	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
8	1	C06CEQ59B1R	U06D3LY2M4Y	1756168811.167309	<!channel>\n【:rocket:ClaudeCodeレビューを導入:rocket:】\n課題を行いGithubでPRを出すとClaudeCodeで自動レビューがつくようになりました！\n\n【現在課題をやっている方へ】\n• `main` ブランチのマージをお願いします:pray:\n[やり方]\n(開発ブランチに移動した状態)\nコマンド\n``` git merge main```\n↓\n自分の開発しているところに`.github/workflow/~~.yml` のファイルが入っていればOK\n\n【対象リポジトリ】\n• JavaScript\n• Laravel\n• React+JS\nその他順次対応。	\N	1756168811.167309	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
9	1	C06CEQ59B1R	U09BD2QTEQ7	1756015507.870579	@はっとりさんがチャンネルに参加しました	\N	1756015507.870579	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
10	1	C06CEQ59B1R	U06D3LY2M4Y	1754459953.228569	石田さんのクーポン配布頂いているので、\nこちらからもご確認から受け取りお願いします！\n&gt; *【Cursor入門講座】 Cursorを活用した０→1開発入門講座*\n&gt; 以下URLから無料で受け取れるのでぜひ受け取ってみて下さい！\n&gt; <https://www.udemy.com/course/cursor-entry-level/?couponCode=PROGRAMING_SCHOOL_5>	\N	1754459953.228569	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
11	1	C06CEQ59B1R	U06D3LY2M4Y	1754182359.462469	<!channel>\n【告知とUdemy無料プレゼント】\n今日8/3の20時よりAI駆動開発を行なっている、\n石田さんとXにてLIVEをします！:rocket:\n\nテーマは\n【現場でのCursorの使い方】\nです！\n\nもし、Cursor使ったことない、興味はある\nと言う人はぜひ参加してください！\n\nさらに！先着500名に今週リリースの\nCusorのUdemy講座が無料配布されます！\n(受け取ったらレビューはしてね:pray:)\n\n受取方法はリンクをご確認ください！\n<https://x.com/ishida_chatgpt/status/1951784773434872109>	\N	1754182359.462469	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
12	1	C06CEQ59B1R	U06D3LY2M4Y	1753195250.344529	:white_check_mark:マイページの使い方動画作成しました！\n• 学習ログ\n• タスク管理\n• 工数設定\nなど、\nテロップ付きで解説してます！\n\n実務で「その仕事、どんくらいかかる？」って1000回は聞かれるので、なるべく自分の中での"当て勘"磨きましょう:crossed_swords:\n<https://utage-system.com/members/RMSoi176VhSa/course/D7MdtEhjsE09/lesson/zqhsFhNmr3rS>	\N	1753195250.344529	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
13	1	C06CEQ59B1R	U06D3LY2M4Y	1752316590.219999	【会員サイト更新:rocket:】\n・Linux\n・Git\n・課題-Git+Linux\nなど追加しました！\n特にGitには一人でも学べるおすすめアプリを追加してます！\nぜひご確認ください！\n\n<https://utage-system.com/members/RMSoi176VhSa/home>	\N	1752316590.219999	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
14	1	C06CEQ59B1R	U06D3LY2M4Y	1751975782.253799	【お願い】\n「RT」と「いいね」してくれると大変助かります:bow:\n<https://x.com/sima199407/status/1942545700870578555>\n\n完了したらリアクションをお願いします:white_check_mark:	\N	1751975782.253799	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
15	1	C06CEQ59B1R	U06D3LY2M4Y	1751945983.711879	<!channel>\n「今後追加するもの」更新しました:white_check_mark:\nURLが載っているものは先に確認していただけると！\n<https://utage-system.com/members/RMSoi176VhSa/news/jKX9TyXprcHq>	\N	1751945983.711879	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:23	2025-09-22 08:03:23	user
16	1	C06CETR50US	U06D3LY2M4Y	1758416991.306119	こういう案件きた時、受けてもらえる人がいたらいいですねー:relaxed:	\N	1758416991.306119	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
17	1	C06CETR50US	U09BD2QTEQ7	1756015508.087029	@はっとりさんがチャンネルに参加しました	\N	1756015508.087029	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
18	1	C06CETR50US	U06D3LY2M4Y	1754047706.129939	Laravelやってたら誰でも応募できそう↓\n<https://jp.indeed.com/cmp/%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE%E3%81%BF%E3%82%93%E3%81%AA%E3%82%B7%E3%82%B9%E3%83%86%E3%83%A0%E3%82%BA-1/jobs?jk=167ce05c65f2cda9&amp;start=0&amp;clearPrefilter=1|https://jp.indeed.com/cmp/%E6%A0%AA%E5%BC%8F%E4%BC%9A%E7%A4%BE%E3%81%BF%E3%82%93%E3[…]0%E3%82%BA-1/jobs?jk=167ce05c65f2cda9&amp;start=0&amp;clearPrefilter=1>	\N	1754047706.129939	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
19	1	C06CETR50US	U06D3LY2M4Y	1753952516.708149	ADHDじゃなくても使える仕事術\n<https://note.com/igz0/n/nd0a15f2f53d2>	\N	1753952516.708149	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
20	1	C06CETR50US	U06D3LY2M4Y	1752071379.271269	<!channel>\nすいません！今追加しました！	\N	1752071379.271269	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
21	1	C06CETR50US	U07THSEMVE1	1752071344.606589	@工藤辰哉さんがチャンネルに参加しました	\N	1752071344.606589	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
22	1	C06CETR50US	U087ZHL3Q48	1752071307.075009	@Imaiさんがチャンネルに参加しました	\N	1752071307.075009	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
23	1	C06CETR50US	U08JQMLJ030	1752071306.989969	@谷野雄一さんがチャンネルに参加しました	\N	1752071306.989969	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
24	1	C06CETR50US	U08UJHFF0CX	1752071306.904569	@shotaさんがチャンネルに参加しました	\N	1752071306.904569	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
25	1	C06CETR50US	U08C1QKSVCG	1752071306.799259	@Rikuさんがチャンネルに参加しました	\N	1752071306.799259	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
26	1	C06CETR50US	U06D3LY2M4Y	1752067308.480149	<!channel>\n:tada:技術ブログ開設:tada:\nゼロイチエンジニアで技術ブログを立ち上げました！\n\n人に伝える能力を鍛えると、開発現場に入ったときにめちゃくちゃ役立ちます！\n日頃のアウトプットの場としてこちら使っていきます！\n\n【第一弾】@工藤辰哉 さんによるSessionについて！！\nぜひご覧ください！！\n\n【お願い :pray:】\n就活のアピールポイントして、技術ブログやってみたい！\nアウトプットして差別化を行いたい！\nという方、最高です。\nぜひ記事を書いてもいいよ！\nという方いましたら、もんしょーDMまでご連絡お願いします:bow:　\n\n【TOP】\n<https://zenn.dev/01engineer>	\N	1752067308.480149	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:24	2025-09-22 08:03:24	user
27	1	C08MKETSJUA	U06D3LY2M4Y	1758106806.155699	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第24回:studio_microphone:\n悩んだときにやるべきこと・やってはいけないこと\n<https://utage-system.com/audio/ooH28VSfI1x8>	1758106806.155699	1758106806.155699	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
28	1	C08MKETSJUA	U08SSN8DT3R	1758150393.654309	体調管理や生活習慣整えます！	1758106806.155699	1758150393.654309	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
29	1	C08MKETSJUA	\N	1757510570.592659		\N	1757510570.592659	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
30	1	C08MKETSJUA	U06D3LY2M4Y	1757510309.034509	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第23回:studio_microphone:\n今すぐできる言語化がうまくなる方法 (紙とペンを用意)\n<https://utage-system.com/audio/ztYyQVJ8bP0Z>	\N	1757510309.034509	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
31	1	C08MKETSJUA	\N	1757487123.501139		\N	1757487123.501139	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
32	1	C08MKETSJUA	U06D3LY2M4Y	1756898101.017979	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第22回:studio_microphone:\nこの求人を狙ってみればいいんじゃない？\n<https://utage-system.com/audio/B9DtQaU1O0fR>	\N	1756898101.017979	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
33	1	C08MKETSJUA	\N	1756610812.601389		\N	1756610812.601389	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
34	1	C08MKETSJUA	U06D3LY2M4Y	1756387959.648699	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第21回:studio_microphone:\nこれからエンジニアは事業を考える人が強い\n<https://utage-system.com/audio/vX8zEkAbTa8k>	1756387959.648699	1756387959.648699	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
35	1	C08MKETSJUA	U09BD2QTEQ7	1756433378.734399	@もんしょー \n\nお疲れ様です:bow:\n他のも聴かせていただきます！	1756387959.648699	1756433378.734399	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
36	1	C08MKETSJUA	\N	1756384559.844809		\N	1756384559.844809	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
37	1	C08MKETSJUA	U09BD2QTEQ7	1756015508.150049	@はっとりさんがチャンネルに参加しました	\N	1756015508.150049	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_join", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
38	1	C08MKETSJUA	U06D3LY2M4Y	1755693986.188099	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第20回:studio_microphone:\n現場のリアルな話-パチンコ屋前で言われたクビ宣告\n<https://utage-system.com/audio/1pNRdfirJK0Y>	\N	1755693986.188099	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
39	1	C08MKETSJUA	U06D3LY2M4Y	1755090410.733719	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第19回:studio_microphone:\n質問のやり方を変えるだけデキるやつ認定される\n<https://utage-system.com/audio/v0gEWTBLAx3I>	\N	1755090410.733719	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
40	1	C08MKETSJUA	U06D3LY2M4Y	1754574723.641029	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第18回:studio_microphone:\n現場のリアルな話-26時の東京タワー\n<https://utage-system.com/audio/2UUhchU0xOpd>	\N	1754574723.641029	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
41	1	C08MKETSJUA	U06D3LY2M4Y	1753948458.382259	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第17回:studio_microphone:\n現場のリアルな話-マウス投げ事件\n<https://utage-system.com/audio/vaeSOIJFnRj0>	\N	1753948458.382259	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
42	1	C08MKETSJUA	U06D3LY2M4Y	1753279546.405369	<!channel>\n【更新】聞いたらスタンプ押してください:lion_face:\n:studio_microphone:音声コンテンツ第16回:studio_microphone:\n地方にいながらエンジニアになる方法\n<https://utage-system.com/audio/fMwxOUJocR5C>	\N	1753279546.405369	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
43	1	C08MKETSJUA	\N	1752674094.835249		\N	1752674094.835249	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
44	1	C08MKETSJUA	U06D3LY2M4Y	1752673843.574089	<!channel> 【更新】\n:studio_microphone:音声コンテンツ第15回:studio_microphone:\n早めに転職活動をするとメンタルがやられる話\n<https://utage-system.com/audio/h8jCLROSSY57>	\N	1752673843.574089	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
45	1	C08MKETSJUA	U06D3LY2M4Y	1752071436.983059	:studio_microphone:音声コンテンツ第14回:studio_microphone:\nできないことに目を向けるよりもできたことを褒めよ\n<https://utage-system.com/audio/x3bdiQ9kGkks>	\N	1752071436.983059	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
46	1	C08MKETSJUA	\N	1751952733.447819		\N	1751952733.447819	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
47	1	C08MKETSJUA	U06D3LY2M4Y	1751874070.011169	チャンネルのトピックを設定 : [お願い:white_check_mark:]\n聞いたらお好きなスタンプ「 :raised_hands:」をメッセージにつけてほしい！	\N	1751874070.011169	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_topic", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
48	1	C08MKETSJUA	U06D3LY2M4Y	1751874066.614829	がこのチャンネルの説明を「週1回-ラジオ形式で配信します\nリクエストはDMまで。」に設定しました	\N	1751874066.614829	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_purpose", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
49	1	C08MKETSJUA	U06D3LY2M4Y	1751874058.487209	がこのチャンネルの説明を「週1回-ラジオ形式で配信します\nリクエストはDMまで。\n[お願い:white_check_mark:]\n聞いたらお好きなスタンプ「 :raised_hands:」をメッセージにつけてほしい！」に設定しました	\N	1751874058.487209	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "channel_purpose", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
50	1	C08MKETSJUA	U06D3LY2M4Y	1751874011.839849	聞いたらお好きなスタンプ「 :raised_hands:」をメッセージにつけてほしい！	\N	1751874011.839849	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
51	1	C08MKETSJUA	U06D3LY2M4Y	1751465574.644659	:studio_microphone:音声コンテンツ第13回:studio_microphone:\n魔法のように相手にして欲しいこと伝える力\n<https://utage-system.com/audio/SQU5qbLjuIZs>	\N	1751465574.644659	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
52	1	C08MKETSJUA	\N	1751423161.113929		\N	1751423161.113929	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "tabbed_canvas_updated", "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
53	1	C08MKETSJUA	U06D3LY2M4Y	1750999500.496379	:studio_microphone:音声コンテンツ第12回:studio_microphone:\nなぜSESではなく自社開発、受託開発に行くべきか？\n<https://utage-system.com/audio/59g6P1cUQu9e>	\N	1750999500.496379	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:25	2025-09-22 08:03:25	user
54	1	D09BD2R2RGX	U09BD2QTEQ7	1756015512.945439	はっとり さんが Slack に参加しました。さっそく、あいさつしましょう。	\N	1756015512.945439	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "joiner_notification", "username": null}	2025-09-22 08:03:27	2025-09-22 08:03:27	user
55	1	D091J8F0QLS	\N	1757425772.059859	:date: Zoomミーティングのお知らせ\n\n定期MTG-5回目_ たつやさん\n\n日時：2025年09月24日 21:00〜\nミーティングID：83573157813\n参加URL：<https://us06web.zoom.us/j/83573157813>	\N	1757425772.059859	0	message	f	\N	{"app_id": "A091J8CCFFU", "bot_id": "B091J8ER50A", "subtype": null, "username": null}	2025-09-22 08:03:28	2025-09-22 08:03:28	user
56	1	D091J8F0QLS	\N	1756277481.308719	2025年9月9日: Zoomミーティングのお知らせ\n定期MTG4回目_くどうさん\n日時：2025年9月9日 21:00〜\nミーティングID：89817359253\n参加URL：<https://us06web.zoom.us/j/89817359253>	\N	1756277481.308719	0	message	f	\N	{"app_id": "A091J8CCFFU", "bot_id": "B091J8ER50A", "subtype": null, "username": null}	2025-09-22 08:03:28	2025-09-22 08:03:28	user
57	1	D091J8F0QLS	\N	1756208656.116769	8月27日: Zoomミーティングのお知らせ\n定期MTG_3回目+打ち合わせ_たつやさん\n日時：2025年08月27日14:00〜\nミーティングID：897 2180 4709\n参加URL：<https://us06web.zoom.us/j/89721804709>	\N	1756208656.116769	0	message	f	\N	{"app_id": "A091J8CCFFU", "bot_id": "B091J8ER50A", "subtype": null, "username": null}	2025-09-22 08:03:28	2025-09-22 08:03:28	user
58	1	D091J8F0QLS	\N	1751982737.876809	:date: 2025年7月22日: Zoomミーティングのお知らせ\n\n【くどうさん-MTG1回目】\n日時：2025年07月22日 21:00～\nミーティングID：86548517928\n参加URL：<https://us06web.zoom.us/j/86548517928>	\N	1751982737.876809	0	message	f	\N	{"app_id": "A091J8CCFFU", "bot_id": "B091J8ER50A", "subtype": null, "username": null}	2025-09-22 08:03:28	2025-09-22 08:03:28	user
59	1	D091J8F0QLS	\N	1750817591.055349	:date: 6/25 21:30 ミーティングZoomミーティングのお知らせ\n日時：2025年06月25日 21:30〜\nミーティングID：83861121774\n参加URL：<https://us06web.zoom.us/j/83861121774>	\N	1750817591.055349	0	message	f	\N	{"app_id": "A091J8CCFFU", "bot_id": "B091J8ER50A", "subtype": null, "username": null}	2025-09-22 08:03:28	2025-09-22 08:03:28	user
60	1	D07TZCWBYBT	U07THSEMVE1	1757492787.969649	@もんしょー\nもんしょーさん！\n昨日はMTGありがとうございました:bow:\nR2 へのアップロード処理について、少しご相談させてください:pray:\n\n現在 .env では <http://slack-archive.kudoutatsuya.com|slack-archive.kudoutatsuya.com> をエンドポイントに設定しているのですが、\nこちらからアクセスしてみると「サーバが見つかりません」と表示されてしまい、接続できない状況です。\n\n自分なりに調べた限りでは、Cloudflare 側での *カスタムドメインの有効化* や *DNS レコードの設定*、\nあるいは *SSL 証明書の発行* あたりがまだ完了していない可能性があるのかなと思っています。\n\nただ、このあたりの設定に関しては私の理解が十分ではなく、\nどのように進めるべきか判断が難しい状況です。\n大変恐縮ですが、対応方法についてご教示いただけますと助かります:bow:	1757492787.969649	1757492787.969649	5	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
61	1	D07TZCWBYBT	U06D3LY2M4Y	1757497464.443919	ありがとうございます！\n私の方でドメイン作って、そちらをお渡しすれば大丈夫ですかね？\n一旦以下の部分見てみますね:relieved:\n&gt; *カスタムドメインの有効化* や *DNS レコードの設定*\n	1757492787.969649	1757497464.443919	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
62	1	D07TZCWBYBT	U06D3LY2M4Y	1757498290.882359	多分、R2のオブジェクトストレージが作成されてないからだったかも。。\n\n今作りましたので、こちらでかくにんしていただけると！\n\nエンドポイント↓\n`<https://32ccca1e436777177679b0e9294d659a.r2.cloudflarestorage.com/chat-log-dev>`\n\n保存した画像などをアクセスす流際にはこちらを使うみたいです！\n<https://developers.cloudflare.com/r2/buckets/public-buckets/#managed-public-buckets-through-r2dev|パブリック開発>URL↓\n`<https://pub-97ac6203b46749a9bd20d64b9354b424.r2.dev>`	1757492787.969649	1757498290.882359	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
63	1	D07TZCWBYBT	U07THSEMVE1	1757590509.730589	@もんしょー\n\nご対応ありがとうございます！\nこちらの環境では <http://cloudflarestorage.com|cloudflarestorage.com> のエンドポイントに接続しようとすると TLS のハンドシェイクでエラーになってしまい、ファイルのアップロードができない状況です。\n（ca-certificates や OpenSSL 周りを更新しても改善せず、cURL/openssl で直接アクセスしても handshake failure になります）\n\nそのため、現在の .env 設定のままだと接続が難しい可能性が高いです。\n共有いただいた *パブリック用の R2 エンドポイント（*.<http://r2.dev|r2.dev>）* を利用するか、もしくは *カスタムドメインを Cloudflare 側で設定*してそちら経由で接続できるようにするのが解決策になりそうです。\n\n→ こちらで <http://r2.dev|r2.dev> の URL を使って動作確認を進めても大丈夫でしょうか？\nそれともカスタムドメインの設定を優先されますか？	1757492787.969649	1757590509.730589	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
64	1	D07TZCWBYBT	U06D3LY2M4Y	1757649933.887209	r2.devで進めて大丈夫です:ok_hand:	1757492787.969649	1757649933.887209	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
65	1	D07TZCWBYBT	U07THSEMVE1	1757664797.848079	@もんしょー \nありがとうございます！\nr2.devで進めます！	1757492787.969649	1757664797.848079	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
66	1	D07TZCWBYBT	U06D3LY2M4Y	1757487535.028999	:date: Zoomミーティングのお知らせ\n\n定期MTG-5回目_ たつやさん\n\n日時：2025年09月24日 21:00〜\nミーティングID：83573157813\n参加URL：<https://us06web.zoom.us/j/83573157813>	\N	1757487535.028999	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
67	1	D07TZCWBYBT	U06D3LY2M4Y	1757420474.819589	テストケース\n<https://docs.google.com/spreadsheets/d/1JbjHtXh-kXE_ST4Y3NKI_TgsYWyPIQEzOFMwydU-wOQ/edit?usp=sharing>	\N	1757420474.819589	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
87	1	D07TZCWBYBT	U06D3LY2M4Y	1756721932.440279	その解決方法など、ブログで公開してもらえるといいかもです！！ :relaxed: 	1756468400.498309	1756721932.440279	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
88	1	D07TZCWBYBT	U07THSEMVE1	1756723953.605639	@もんしょー\nそうですね！\nその解決プロセスもzennで記事として、共有しようと思います:laughing:\n\n<https://monsho-support.slack.com/marketplace/A09CW608J4U-bot>\n\n・上記リンクの件ですが、Slack 側で *チャットログBot のインストールが完了しています。*\n・ワークスペースのアプリ一覧に log_bot が追加されており、Bot User OAuth Token (xoxb-...) も発行済みです。\n・現状、Bot は正しく連携されているので、次のステップは *Laravel 側の .env にトークンを設定して API 連携の動作確認*に進めます。\n\nこちら共有遅れて申し訳ないです:sweat_drops:	1756468400.498309	1756723953.605639	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
118	1	D07TZCWBYBT	U06D3LY2M4Y	1757428024.644199	*株式会社ファイブニーズ*\n<https://jp.indeed.com/viewjob?jk=52990ce8ee8541a3&amp;from=shareddesktop_copy>\n<https://www.fiveneeds.co.jp/recruitments_career/engineer>	1754765519.909119	1757428024.644199	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
119	1	D07TZCWBYBT	U06D3LY2M4Y	1757428676.174399	*プログラマー/Webマーケティング・ネット広告*\n<https://jp.indeed.com/viewjob?jk=14740b0992beb0c1&amp;from=shareddesktop_copy>\n<https://kokochie.co.jp/recruit/web_appli_engineer/>	1754765519.909119	1757428676.174399	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
68	1	D07TZCWBYBT	U07THSEMVE1	1756981935.208739	@もんしょー\nもんしょーさん\nお世話になります\nSlackログアプリの進捗を共有します :raised_hands:\n\n*今の進み具合*\n• 認証・個人制限：完了\n• メッセージ表示・検索：完了\n• 管理者機能：完了\n• セキュリティ：完了\n• バックエンドAPI：完了\nここまでで *完成度は約95%*、実用レベルまで仕上がっています。\n残りタスクは以下の３つ、\n\n1. *本番環境の構築（最優先）*\n2. *CI/CDの自動化*\n3. *最終テスト（E2E・パフォーマンスなど）*\n *ご相談*\n本番環境移行にあたり、*Cloudflare R2 の契約*が必要になりました。\n• 無料枠（10GB）から始められるので、初期費用はゼロ\n• 転送料も無料\n• 運用コストは月500円程度で、当初の見積もり内です\n *お願い*\nこのタイミングで *Cloudflare R2のアカウント作成*をお願いできますでしょうか:bow:\n無料枠からテストできるのでリスクはありません :+1:	1756981935.208739	1756981935.208739	4	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
69	1	D07TZCWBYBT	U06D3LY2M4Y	1757039504.546889	```アカウント名: kudo-dev\n\n権限: 管理者読み取りと書き込み```\n*User API トークン*\n```Token\n6oWblT7NwjIqDvxCa5uSxlNzBj3U7QeknOYhSBfl\n\nアクセス キー ID\n491a5596fd5e5291efbed39d87458a0b\n\nシークレット アクセス キー\nd99a29d6a739807698433f9b718166944c0cd2d3c9a1c7596fdfd2538e21279c\n\nS3 クライアントには管轄区域固有のエンドポイント\n<https://32ccca1e436777177679b0e9294d659a.r2.cloudflarestorage.com>```	1756981935.208739	1757039504.546889	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
70	1	D07TZCWBYBT	U06D3LY2M4Y	1757039536.856349	↑\nアカウント作成しました！ご確認のほどよろしくお願いいたします！	1756981935.208739	1757039536.856349	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
71	1	D07TZCWBYBT	U07THSEMVE1	1757040530.831849	@もんしょー \nありがとうございます！\n仕事が終わり次第、確認します！	1756981935.208739	1757040530.831849	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
72	1	D07TZCWBYBT	U06D3LY2M4Y	1757042703.928939	よろしくお願いいたします！\n必要な権限等あればまた教えて下さい！	1756981935.208739	1757042703.928939	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:33	user
73	1	D07TZCWBYBT	U07THSEMVE1	1756672369.989299	@もんしょー \nフロントエンドの件で共有です:sparkles:\n\nSlackアーカイブシステムの UIデザイン案（ログイン画面／ダッシュボード／メッセージ詳細／管理者画面） をFigmaで作成し、Notionにまとめましたので共有いたします!\n\n▼デザイン作成（Notion）\n<https://www.notion.so/momsho/230717f1ba7080748107c8a71d326763?source=copy_link|https://www.notion.so/momsho/230717f1ba7080748107c8a71d326763?source=copy_link>\n\n本デザインは要件定義に基づき、以下の4画面を対象としています。\n\t•\tログイン画面\n\t•\tダッシュボード（メッセージ一覧）\n\t•\tメッセージ詳細（スレッド表示含む）\n\t•\t管理者画面\n\nまずはUIの全体像をご確認いただき、必要に応じて修正点や追加のご要望をいただければと思います:blush:\n\nどうぞよろしくお願いいたします:pray:	1756672369.989299	1756672369.989299	2	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:33	2025-09-22 08:03:34	user
74	1	D07TZCWBYBT	U06D3LY2M4Y	1756689058.426949	@工藤辰哉\nありがとうございます！！\n良さげです！\n直すところではないですが、少しだけコメント残しましたのでご確認いただけると:bow:	1756672369.989299	1756689058.426949	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
75	1	D07TZCWBYBT	U07THSEMVE1	1756716433.861399	@もんしょー\nご確認ありがとうございます！:bow:\nコメントも拝見しました。いただいた内容を踏まえて、今後の進め方に反映していきますね。\n引き続きよろしくお願いいたします！	1756672369.989299	1756716433.861399	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
76	1	D07TZCWBYBT	U07THSEMVE1	1756574033.144719	@もんしょー\nすいません:sweat_drops:\n上記とは別の質問になります:bow:\n現在、Slackログイン機能の確認を進めていますが、通常ログイン画面で以下の問題が発生しています。\n\n• /login にアクセスすると、Vite manifest not found というエラーが表示されログイン画面に進めない\n• DBにはユーザーを作成済みで、php artisan tinker 上で Auth::attempt() を実行すると認証は成功している\n• npm run dev で Vite サーバーは正常に起動しており、<http://localhost:5173> でフロントは配信されている\n試したこと：\n• .env の APP_ENV=local VITE_URL=<http://localhost:5173> を設定\n• npm install &amp; npm run dev を実行済み\n質問：\nログイン画面を表示できるようにするためには、他にどの点を確認すればよいでしょうか？	1756468400.498309	1756574033.144719	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "thread_broadcast", "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
89	1	D07TZCWBYBT	U06D3LY2M4Y	1756281395.760569	2025年9月9日: Zoomミーティングのお知らせ\n定期MTG4回目_くどうさん\n日時：2025年9月9日 21:00〜\nミーティングID：89817359253\n参加URL：<https://us06web.zoom.us/j/89817359253>	1756281395.760569	1756281395.760569	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
90	1	D07TZCWBYBT	U06D3LY2M4Y	1757487508.370579	*ミーティング要約*\n簡単な要約です。\n工藤辰哉とshoheiは、データ取り込みツール開発とSlack APIの使用について話し合い、現在の進捗状況を共有した。二人は転職活動について議論し、社内SEポジションや化粧品アパレル企業などの求人案件を検討した後、AI駆動開発とクラウドコードの使用について議論し、顧客レビュー収集サービスを通じたビジネスモデルを検討した。最終的に、事業のスタートダッシュ改善策や営業活動のアプローチについて話し合い、shoheiの新しい仕事の開始と今後のミーティング日程を確認した。\n次のステップです。\n• アクションアイテム：\n• 工藤　辰哉: ローカル環境でSlackのデータを取り込む作業を行う。\n• 工藤　辰哉: Slackの連携状況を確認し、過去分のデータ取り込みの動作確認を行う。\n• 工藤　辰哉: ツールの開発状況について進捗を共有する。\n• 工藤　辰哉: 転職エージェントと明日から相談を始める。\n• shohei: クラウドワークスなどで第三者にツールを使ってもらい、フィードバックを収集する計画を進める。\n• 工藤　辰哉: 見つけた求人に応募する。\n• shohei: 工藤さんの職務経歴書に添付するためのツールのデモ動画を作成する。\n• 工藤　辰哉: ポートフォリオをブラッシュアップして完成させる。\n• 工藤　辰哉: クラウドワークスを利用して、アプリケーションのレビューを集める。\n• 工藤　辰哉: 技術ブログを自分の理解できる範囲の内容で更新する。\n• 工藤　辰哉: マーケティングの勉強をする。\n• 工藤　辰哉: ホームページやXアカウントなどの情報発信の場を整備する。\n• shohei: 工藤さんのポートフォリオをテストして、販売可能な状態にする。\n• 工藤　辰哉: 4月24日21時に進捗共有のミーティングを行う。\n概要\nデータ取り込みと転職活動計画\n工藤辰哉とshoheiは、データ取り込みとツール開発について話し合い、Slack APIを使用したローカル環境でのデータ取り込みを検討することに合意した。工藤辰哉は現在のツール開発の進捗について報告し、shoheiは第三者からのフィードバックを得ることを提案した。二人は転職活動についても議論し、社内SEのポジションや化粧品アパレル企業などの求人案件を検討した。最終的に、工藤辰哉は明日からギープリの転職エージェントと話し合うことを決め、shoheiは求人情報の共有を継続することに同意した。\nAI駆動開発と事業化戦略\nShoheiと工藤辰哉は、AI駆動開発とクラウドコードの使用が従来の手作業開発を代替する傾向について議論し、shoheiは自社開発から事業化への転換を検討していると述べた。二人は顧客レビュー収集サービスを通じて信頼性を高める方法について話し合い、shoheiは10円の一件当たりの料金で100人程度のレビューを収集することを提案した。最終的に、shoheiは月2件の売上目標で年間千万近くを目指すビジネスモデルを説明し、工藤辰哉はエンジニアとしてだけでなく、会社のサービス展開やマーケティングノウハウも学ぶことの重要性を同意した。\n事業スタートダッシュ改善戦略会議\nShoheiと工藤辰哉は、事業のスタートダッシュを改善する方法について話し合い、特に営業活動での自信不足と対処策について議論した。彼らは、まずは無料サービスとして提供し、信頼を構築してから料金設定するアプローチを検討し、まずは地元の業務改善イベントを主催して顧客との接触を増やすことを提案した。最終的に、九州から始めて西日本を展開し、段階的に東日本まで拡大していく戦略についても話し合った。\nShoheiの新しい仕事開始報告\nShoheiは新しい仕事の件について決定したと報告し、来週から開始することを共有した。二人は今後のミーティングを水曜日24日に9時から設定し、shoheiの新しい取り組みの進捗を確認することに合意した。会話の後半では、野球についての雑談が行われ、ソフトバンクの成績や大谷翔平の実績について話し合われた。	1756281395.760569	1757487508.370579	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
120	1	D07TZCWBYBT	U06D3LY2M4Y	1757428734.537669	↑\n追加です！	1754765519.909119	1757428734.537669	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
156	1	D07TZCWBYBT	U06D3LY2M4Y	1753186412.274509	slack-企画説明\n<https://www.notion.so/momsho/230717f1ba7080798601c491ad57b5b4?source=copy_link>	\N	1753186412.274509	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
157	1	D07TZCWBYBT	U06D3LY2M4Y	1753185905.097009	マイページ\n<https://www.notion.so/momsho/238717f1ba708096841cd6d662749f8a?source=copy_link>	\N	1753185905.097009	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
220	1	D07TZCWBYBT	U06D3LY2M4Y	1750814725.680079	@工藤辰哉\nお疲れ様です！\nOKです！早めが良さそうであれば今日の夜とかやりますか？	\N	1750814725.680079	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
77	1	D07TZCWBYBT	U07THSEMVE1	1756468400.498309	@もんしょー\nもんしょーさん！\nお世話になります！\n\nSlackアーカイブの開発ですが、認証とDB基盤までは実装済みで、次に「Slack APIからメッセージを取得」のテストに入ります。そのため Botトークン（xoxb-…）が必要です。\n\nお願いしたいことは以下の3点です：\n\n1.  Slack APIアプリ設定 > OAuth & Permissions に下記スコープを追加  \n  - channels:read, groups:read, im:read, mpim:read\n  - channels:history, groups:history, im:history, mpim:history\n  - users:read, files:read, reactions:read\n\n2.　 **Install to Workspace** を実行してBotを発行し、対象チャンネルに招待\n\n3. 　発行された **Bot User OAuth Token (xoxb-…)** と **テスト用チャンネルID** を共有\n\n共有方法はSlackのDMでも構いませんが、\nセキュリティ的に気になる場合は 1Password Send 等でいただければOKです。\n\nこちらで `.env` に設定して\n``````bash\nphp artisan slack:sync-messages C12345678```\nの形でテスト実行 → Dashboardにメッセージを表示できるようにします。\n\nよろしくお願いします:bow:	1756468400.498309	1756468400.498309	12	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
78	1	D07TZCWBYBT	U06D3LY2M4Y	1756530148.762099	了解です！\n&gt; **Install to Workspace** を実行してBotを発行し、対象チャンネルに招待\n今ためしてみているんですが、アプリの設定か自分の方でインストールまでいかず、、「許可する」ボタンが押せない状態です。\nこちら原因究明中です。\n\n[お願い]\n工藤さんをコラボレーターに追加したので、インストールできるか確認してもらえると助かります:bow:\nインストール先URL\n<https://monsho-support.slack.com/marketplace/A09CW608J4U-bot>	1756468400.498309	1756530148.762099	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
79	1	D07TZCWBYBT	U06D3LY2M4Y	1756531605.781019	もしかしたら、RedirectURLの設定が必要かもしれないので、開発環境に合わせてもらえるとうまくいくかもです！	1756468400.498309	1756531605.781019	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
80	1	D07TZCWBYBT	U07THSEMVE1	1756567503.037489	@もんしょー\nありがとうございます！\nこちらでも環境を整えて試してみました。\n\n• ngrok 経由での外部アクセス設定と Redirect URL の登録は完了しました\n• 「許可する」ボタンまでは押せる状態になり、Slack 側からのリダイレクトも確認できています\n• ただし現在、リダイレクト後に画面が空白になってしまい、Slack API から返ってきたデータの確認を進めています\nログに出ていた 404（apple-touch-icon）はサイトアイコンの参照なので、認証処理には影響なさそうです。\n引き続きレスポンス周りを調査して、また進展があれば共有しますね！	1756468400.498309	1756567503.037489	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
81	1	D07TZCWBYBT	U06D3LY2M4Y	1756610391.079289	ありがとうございます！\n\nnode関連だと思いますが、\n\nまず、バージョンの確認をしたいので\n```$ node -v\n$ npm -v```\nで確認してみてください！\n※nodeが16以上であること\n\n次にここの必要な部分をインストールを試してみるのはどうですかね？？\n<https://arrown-blog.com/laravel-vite-error/#i>	1756468400.498309	1756610391.079289	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
82	1	D07TZCWBYBT	U07THSEMVE1	1756618678.464009	@もんしょー\n\nありがとうございます！\n教えていただいた node / npm のバージョン確認とパッケージの再インストールは実施済みです。\n\n• Node.js: v20.19.4\n• npm: 10.8.2\n一方で、現在は以下の状況でつまずいています：\n\n• Laravel 側は正常に起動しており、ngrok 経由でログイン画面自体は表示できている\n• ただし、ログインリクエストが http://～ で送信されてしまい、ブラウザ側で *Mixed Content (HTTPS ページから HTTP リクエストがブロックされるエラー)* が発生している\n• .env の VITE_APP_URL / APP_URL は <https://xxxx.ngrok-free.app> に設定済み\n• axios の baseURL 設定も https:// に置換するよう修正済み\n• それでもなお http://～/login へのリクエストが飛んでしまう\n→ そのため、Vite や Inertia/axios 周りでの HTTPS リクエストの固定化に問題があるのではと疑っています。\n\nもし解決の方向性についてご存知であれば、ご教示いただけると助かります！	1756468400.498309	1756618678.464009	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
83	1	D07TZCWBYBT	U06D3LY2M4Y	1756650917.042829	> → そのため、Vite や Inertia/axios 周りでの HTTPS リクエストの固定化に問題があるのではと疑っています。\nキャッシュ周りとか確認するのが良いかも、サーバー立ち上げ直してみるのはどうかな？\n\nあと、ここら辺試してみると良いかもです！\n<https://zenn.dev/catatsumuri/articles/79505e2c2a0907#%E8%A7%A3%E6%B1%BA%E6%B3%952%3A-url%E3%82%92%E6%B1%BA%E3%82%81%E6%89%93%E3%81%A1%E3%81%99%E3%82%8B|https://zenn.dev/catatsumuri/articles/79505e2c2a0907#%E8%A7%A3%E6%B1%BA%E6%B3%952%3A-url%E3%82%92%E[…]A%E3%82%81%E6%89%93%E3%81%A1%E3%81%99%E3%82%8B>	1756468400.498309	1756650917.042829	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
84	1	D07TZCWBYBT	U07THSEMVE1	1756672415.824199	@もんしょー \n\nありがとうございます！\n確認いたします:man-bowing:	1756468400.498309	1756672415.824199	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
85	1	D07TZCWBYBT	U07THSEMVE1	1756721819.941389	@もんしょー\n\nログインエラーについてですが、原因は *セッション設定と環境変数の不整合* でした:sweat_drops:\n以下の対応で無事に解決しました:smile:\n\n *対応内容*\n• .env の APP_URL / VITE_APP_URL を *ngrok の https URL* に統一\n• SESSION_DOMAIN=null にしてドメイン制限を解除\n• SESSION_SECURE_COOKIE=false にして開発環境でのHTTPS必須を解除\nその結果、セッションが正しく保持されるようになり、*ログイン成功 &amp; ダッシュボード表示を確認* できました。\n\nまた、同時に発生していた *Mixed Content エラー* も、URLの統一で解消しました。\n\nこれで開発環境でのログイン動作は問題なく進められる状態になっています！\n\nこのまま、Slack認証の機能テストを進めます！	1756468400.498309	1756721819.941389	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
86	1	D07TZCWBYBT	U06D3LY2M4Y	1756721903.062249	お！！良かったです！\n引き続きよろしくお願いします！	1756468400.498309	1756721903.062249	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:34	user
91	1	D07TZCWBYBT	U07THSEMVE1	1754765519.909119	@もんしょー\nもんしょーさん！\nお世話になります！\nSlackのツール作成の件ですが、前回いただいたフィードバックをもとに、\n「基本設計」「テスト項目作成」の２点、ブラッシュアップしました。\n\nこちらお手隙の際にご確認お願いします:bow:\n\n\n基本設計\n<https://www.notion.so/momsho/230717f1ba70800383afca276c1a35ec>\n\nテスト項目作成\n<https://www.notion.so/momsho/230717f1ba708071b65dc470488c0257>	1754765519.909119	1754765519.909119	29	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:34	2025-09-22 08:03:35	user
92	1	D07TZCWBYBT	U06D3LY2M4Y	1754802418.558219	ありがとうございます！確認します！	1754765519.909119	1754802418.558219	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
93	1	D07TZCWBYBT	U06D3LY2M4Y	1754804994.729649	基本OKです！\nあとは、作りながら微調整加えていきましょう！	1754765519.909119	1754804994.729649	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
94	1	D07TZCWBYBT	U07THSEMVE1	1754818283.460879	@もんしょー\nフィードバックありがとうございます！\n基本設計の箇所含め、微修正を加えた後に、詳細設計に移ります！\n\nまたCloudflare R2の契約が必要になった際にも、お声がけさせていただきますね:smile:	1754765519.909119	1754818283.460879	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
95	1	D07TZCWBYBT	U06D3LY2M4Y	1756198471.402159	お疲れ様です！\n上記の件、いかがでしょうか？\n\nZennの記事は９月から週2程度を考えているので、slackの作成に注力してもらえると :pray:	1754765519.909119	1756198471.402159	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
96	1	D07TZCWBYBT	U07THSEMVE1	1756200343.508789	@もんしょー\nお世話になっております！\n\n現在、詳細設計の作成がまだ途中の段階でして:sweat_drops:、Laravelの基礎学習に注力しておりました。そのため、ツールの作成を急ぐ必要がある点を認識し、今後の進め方について一度ご相談させていただければと思っております。\n\nもし可能でしたら、明日以降でお打ち合わせのお時間をいただけませんでしょうか。ご都合の良い日時を教えていただけますと幸いです。\n\nどうぞよろしくお願いいたします:bow:	1754765519.909119	1756200343.508789	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
97	1	D07TZCWBYBT	U07THSEMVE1	1756200397.838309	ちなみにですが、以下が詳細設計(途中)ですが、以下の点でも、不足点などあればご教示いただけると幸いです:bow:\n\n<https://www.notion.so/22c46bdc1f0a8013960bdd92f93eb82c>	1754765519.909119	1756200397.838309	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
98	1	D07TZCWBYBT	U06D3LY2M4Y	1756207723.334889	ありがとうございます！ご状況把握しました\n定期MTGも兼ねて打ち合わせしましょう！\n以下の日程の中でご都合いかがでしょうか？\n• 8/27 14~16\n• 8/28 13~20\n• 8/29 13~20	1754765519.909119	1756207723.334889	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
99	1	D07TZCWBYBT	U07THSEMVE1	1756208409.398859	@もんしょー\nありがとうございます！\n明日(8/27)の14~16でお願いできますでしょうか？	1754765519.909119	1756208409.398859	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
100	1	D07TZCWBYBT	U06D3LY2M4Y	1756208680.116799	@工藤辰哉\n承知です！\nでは明日の14時からお願いします！！\n\n8月27日: Zoomミーティングのお知らせ\n定期MTG_3回目+打ち合わせ_たつやさん\n日時：2025年08月27日14:00〜\nミーティングID：897 2180 4709\n参加URL：<https://us06web.zoom.us/j/89721804709>	1754765519.909119	1756208680.116799	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
101	1	D07TZCWBYBT	U07THSEMVE1	1756210013.212459	@もんしょー \nありがとうございます:sob:\nこちらこそよろしくお願いします:bow:	1754765519.909119	1756210013.212459	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
102	1	D07TZCWBYBT	U06D3LY2M4Y	1756272976.110019	*新聞社の社内SE(システムエンジニア)*\n<https://jp.indeed.com/?vjk=ee7f72f399314102&amp;advn=4226661040843611>	1754765519.909119	1756272976.110019	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
103	1	D07TZCWBYBT	U06D3LY2M4Y	1756273211.747249	*社内SE/自社メディアと広告用LPの更新や管理、コードのバージョン管理など*\n<https://jp.indeed.com/jobs?q=%E7%A4%BE%E5%86%85SE&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;from=searchOnDesktopSerp%2Cwhereautocomplete&amp;cf-turnstile-response=0.0VHLqLxfOmSk28j_LK9bU79UA6zhzu6LQ-kBnuESiIOd1WEwjgJj4n3eT4I_actqJbLNPfpU3MibdLLV158eIc9tp04D1WHWcHRI7eSlxkprmJOng9uhpqAmMWikwdWNCwAgdFIyoaSsh__QVpo_gW549XV0dw34VMMq0vBDcq-gR8tgwtMvLL3xp9quWskCh6yAbd-XwegJU0CWMuIy0xFJ_45pDsN26_nSSxjNozs3nKRB-qijk6eCs6Y3FcZatKJy_HnhGdSKCIrp7edpiY7K_r9KkvtESMM92B9oYEVmr8rdmMGDvRo2O4oms8JyRKUHvaTD-Vs_tnTUBkRUyvud177m1XxPol_i_MZsn9jRwz7UEIwnJR49B7z0m9nWPWRUSSZrWhgQSaxCEhxN186w_Kpw2shGCsPlvfyHND1R9EoDwVYkWPvS_kANAVNQML3hnoJg-0OaI_-Fk1xloqJThYfUngk5dpaS1ED-mkg81ZHYNjaUySOuRagvYVTKNudnD6jA5cApGm1bN_0ImtPOM0RoTYwWldUdPBb0ro_mmnLGM6aZlkpZT5GxEqoJq3rx7WZh1sTIj0hzetsTwNpTCZhmclZkKKyP3gDmZWKa5yMHGlpsobBWZk3tbskODj7hI-AIiFBiZotCJvJGu8jRQ-8IJ0U76jjTCaJC0ppi8aIYVNpabX4K3GZZZlSzOZL-cfhW-16gpXryNGo74gcEbHu6TmrY7s91Baj5XFkhVnzT9bkcnnZDw303dvJps5eMppU5Wa7uADDNbaM2CHekVLNa_lR1-vCs42RLy0PUPlIWzHZ0OUAVtSWX9HdVKoUmWHIsAP4J7DKLPjXHqU6b3KkSiO6SyjY188MlB_tUn0EAmSBNNYSpbC-Agx4Pdn9pk4H5Wj6qFaSSLTJaGvLS3SzXtEHHsZYROJJUaxc.EgGXpLiuwLhojLCofEw3LQ.b696059d84cc2460e618b900397b54c7eee30b5624700449fdd71187fe409f8f&amp;vjk=9180bc00a5210b41&amp;advn=2008716967828110|https://jp.indeed.com/jobs?q=%E7%A4%BE%E5%86%85SE&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%[…]449fdd71187fe409f8f&amp;vjk=9180bc00a5210b41&amp;advn=2008716967828110>	1754765519.909119	1756273211.747249	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
104	1	D07TZCWBYBT	U07THSEMVE1	1756273364.765519	<https://jp.indeed.com/?vjk=d2bb60302e70de81&amp;advn=2008716967828110>	1754765519.909119	1756273364.765519	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
117	1	D07TZCWBYBT	U06D3LY2M4Y	1757422843.457969	*フルリモート【大手車サイト開発】自社勤務/残業10H/フレックス制度 web/オープンSE*\n<https://jp.indeed.com/viewjob?jk=ea572c3946b0f768&from=shareddesktop_copy>\n<https://www.cview.co.jp/recruit/information/>	1754765519.909119	1757422843.457969	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
105	1	D07TZCWBYBT	U06D3LY2M4Y	1756273504.602919	*アプリ開発の社内SE*\n<https://jp.indeed.com/jobs?q=%E7%A4%BE%E5%86%85SE&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp%2Cwhereautocomplete&amp;start=20&amp;vjk=9f3dfbb934b865b4|https://jp.indeed.com/jobs?q=%E7%A4%BE%E5%86%85SE&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%[…]nDesktopSerp%2Cwhereautocomplete&amp;start=20&amp;vjk=9f3dfbb934b865b4>	1754765519.909119	1756273504.602919	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
106	1	D07TZCWBYBT	U06D3LY2M4Y	1756273854.313009	*インハウスのWEBエンジニア*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;from=searchOnDesktopSerp&amp;cf-turnstile-response=0.5FnLVT3u_K0k72GASmbb1_x5TTkCf5dLfX1O-wNlymakB6f1qcSTgmpsITRSj5AMq6pTkZUtc4H1dLS4qut6DXVJDxRJT48koe1Sfuzo3IPYkZYhDkO0WYArRHwUb8b-ve_m32u-aA_6g5k4U_5w2NM0uxuSHCD7magHyjbnVorigRWvSWWmzzxr8q41rq2kMN4qz6xc5gbKJGyzVYjEJqxjw1x4wW6rc3-hOsYKl7OPTVwJp8VcCK7wNBgFgsW8iLGE2YlzM1fQbMO-E5rx-YtZN4U883IBol9ICOnkSd_3hDxD3t_EpTqLycQkAZ4is-1XzDjx_AdTLZGf3l5gwWZjpejBRRQcvktIzZZ2ysueF4L8LRdKZn6bDf1Cl9vbDi9fIMqWrx1UN9IY1_aLpzkEhREZ2wGoRVTMJav3VZC7LUd-i25SDIl8JW0e4ziv79eGroyEnYSqhxgs2W8rIWYAQ6kx1nM-Cxe1_vyfsv5XQ53CndmTmfDXXXgxj6CWekEymk7EbJ_rngS0smXCcBT_GOzYfbQujzRxiBp-hb4hYyvlpgwNshpCnGLVYdFyLrxiUJJg5HazvNLBOO5yvSXK5rFTuMzqut4vgQGynnah0OoEsZ_1vg0hfAdTCzzpAYsZ_G_Yf4kvamxemxpvl3mMbWAdhrRXzHkQuEgB7ahyaebc1_HnzJuLHEtsh4WQKu5PXEh717KeNZc_9IwwE8nltjdtdXjGytVLB_0LOf2ZnCq9bKzHOQqxqlAUEow7vfmNajq7mPRZR3eAHS_zmktPJhQ285O7EhGy_CX5i0Z3sQwfDrlRY1ak_9qRQKMxW4v5VC2xlGyhXzYvmMbqdR6w4s92BxtfBln4PtvbU--OXaHsFGxi_-L23NsdaqieXbcjuWWv3MuXgEtg1BCdWeT9dliJHHODvhH70CT5S6o.Jy2Y1aEeYZ6Ps1S-yhjwjA.808ab907cdd9838b7a180776894f2702aef865be7ca28c3925755988e9e0e6a5&amp;vjk=f26aa5bf4e25c91f&amp;advn=5780637240515974|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]c3925755988e9e0e6a5&amp;vjk=f26aa5bf4e25c91f&amp;advn=5780637240515974>	1754765519.909119	1756273854.313009	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
107	1	D07TZCWBYBT	U06D3LY2M4Y	1756273958.656689	*WEBサイト運用コーダー*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp&amp;start=10&amp;vjk=2d67757530cd1a4b|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]dius=25&amp;from=searchOnDesktopSerp&amp;start=10&amp;vjk=2d67757530cd1a4b>	1754765519.909119	1756273958.656689	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
108	1	D07TZCWBYBT	U06D3LY2M4Y	1756274006.868769	*コーダー/楽天グループ会社*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp&amp;start=20&amp;vjk=020034519d4d1a24&amp;advn=9001991166218079|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]esktopSerp&amp;start=20&amp;vjk=020034519d4d1a24&amp;advn=9001991166218079>	1754765519.909119	1756274006.868769	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
109	1	D07TZCWBYBT	U06D3LY2M4Y	1756274149.707489	*【WEBデザイナー・コーダー】HTML,CSS,PHP/サブマネージャー Webデザイナー*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp&amp;start=30&amp;vjk=c76aeb991441afaf&amp;advn=7561364398809642|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]esktopSerp&amp;start=30&amp;vjk=c76aeb991441afaf&amp;advn=7561364398809642>	1754765519.909119	1756274149.707489	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
110	1	D07TZCWBYBT	U06D3LY2M4Y	1756274185.083029	*化粧品会社のWEBコーダー*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp&amp;start=30&amp;vjk=f3190e0db5800ba1&amp;advn=8218180108931246|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]esktopSerp&amp;start=30&amp;vjk=f3190e0db5800ba1&amp;advn=8218180108931246>	1754765519.909119	1756274185.083029	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
111	1	D07TZCWBYBT	U06D3LY2M4Y	1756274355.060479	*自社運用サイトのコーダー*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp&amp;start=40&amp;vjk=aacf1bc8f1d9f8f8|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]dius=25&amp;from=searchOnDesktopSerp&amp;start=40&amp;vjk=aacf1bc8f1d9f8f8>	1754765519.909119	1756274355.060479	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
112	1	D07TZCWBYBT	U06D3LY2M4Y	1756274488.818469	*【東京都】Web制作・コーダー*\n<https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA%AC%E9%83%BD+23%E5%8C%BA&amp;radius=25&amp;from=searchOnDesktopSerp&amp;start=50&amp;vjk=49fae4b4f42a4bf9&amp;advn=882414166067665|https://jp.indeed.com/jobs?q=%E3%82%B3%E3%83%BC%E3%83%80%E3%83%BC&amp;l=%E6%9D%B1%E4%BA[…]DesktopSerp&amp;start=50&amp;vjk=49fae4b4f42a4bf9&amp;advn=882414166067665>	1754765519.909119	1756274488.818469	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
113	1	D07TZCWBYBT	U06D3LY2M4Y	1757421179.333659	*社内SE/PG/化粧品・アパレル*\n<https://jp.indeed.com/viewjob?jk=b1d3f1a8259ee5c7&amp;from=shareddesktop_copy>	1754765519.909119	1757421179.333659	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
114	1	D07TZCWBYBT	U06D3LY2M4Y	1757421540.416369	*社内SE・プログラマー/Webマーケティング・ネット広告*\n<https://jp.indeed.com/viewjob?jk=d3861ae8c7c88043&amp;from=shareddesktop_copy>\n<https://ienonaka.co.jp/recruit/system/>	1754765519.909119	1757421540.416369	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
115	1	D07TZCWBYBT	U06D3LY2M4Y	1757421923.954249	*リードエンジニア(社内案件100%のインハウス・年間休日最大153日・フルリモート可・副業可)/飲食・旅行・レジャー・アミューズメント*\n<https://jp.indeed.com/viewjob?jk=ff34229480b08b3d&amp;from=shareddesktop_copy>	1754765519.909119	1757421923.954249	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
116	1	D07TZCWBYBT	U06D3LY2M4Y	1757422571.972629	*初級エンジニア/自社が運営する歯科医療ポータルサイトの開発*\n<https://jp.indeed.com/viewjob?jk=fa5edcb1416db954&amp;from=shareddesktop_copy>	1754765519.909119	1757422571.972629	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
121	1	D07TZCWBYBT	U07THSEMVE1	1754475021.710179	@もんしょー \nもんしょーさん！\n今日もお時間いただきありがとうございました :star-struck: \nピスタチオもめっちゃ嬉しいです:sparkles:\n\nまた、ツールの基本設計・テスト項目作成もブラッシュアップできましたら、共有しますね :smile: 	1754475021.710179	1754475021.710179	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
122	1	D07TZCWBYBT	U06D3LY2M4Y	1754480352.802749	こちらこそ、誕生日プレゼントまで頂いてありがとうございます！\n早速飲ませていただきます :bow: \n\nツールの作成の方も引き続きよろしくお願いいたします！	1754475021.710179	1754480352.802749	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
123	1	D07TZCWBYBT	U06D3LY2M4Y	1754463209.671669	中間報告記載用です↓\n<https://www.notion.so/momsho/247717f1ba708046bb52f3a944aaf6f3?source=copy_link>	\N	1754463209.671669	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
124	1	D07TZCWBYBT	U06D3LY2M4Y	1754458468.056499	<https://zenn.dev/yum3/articles/t_laravel_eloquent_performance>	\N	1754458468.056499	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
125	1	D07TZCWBYBT	U06D3LY2M4Y	1754458393.829529	@工藤辰哉\n<https://zenn.dev/yskn_sid25/articles/6bb62cbc02445f>	\N	1754458393.829529	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:35	user
126	1	D07TZCWBYBT	U07THSEMVE1	1754456337.001279	@もんしょー \n到着しました！\n先に部屋に入ってますね！	1754456337.001279	1754456337.001279	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:35	2025-09-22 08:03:36	user
127	1	D07TZCWBYBT	U06D3LY2M4Y	1754456401.721319	承知いたしました！\nもうすぐ着きます！	1754456337.001279	1754456401.721319	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
128	1	D07TZCWBYBT	U06D3LY2M4Y	1754376761.488129	@工藤辰哉\n明日の14~16時で作業場所予約しましたので、こちらにきていただけると:pray:\n\n住所: 〒1600023 東京都 新宿区 西新宿 7-1-7 新宿ダイカンプラザA館\nマップ: <https://maps.google.com/maps?q=35.6944248526,139.6990297914&amp;zoom=16>\n↓\n【入室方法】\n新宿ダイカンプラザA館　719号室です\nドアノブにあるキーボックスを開け、鍵を取り出し入室してください。\n暗証番号は【５５７１】です	1754376761.488129	1754376761.488129	2	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
129	1	D07TZCWBYBT	U07THSEMVE1	1754376941.315929	@もんしょー\n作業場所のご予約ありがとうございます:bow:\n時間になりましたら、直接伺いますね:smile:	1754376761.488129	1754376941.315929	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
130	1	D07TZCWBYBT	U06D3LY2M4Y	1754376960.471929	お願いいたします！	1754376761.488129	1754376960.471929	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
131	1	D07TZCWBYBT	U07THSEMVE1	1754055228.859209	@もんしょー\n別件になりますが、JavaScriptの課題のTodoアプリ作成を完了し、下記のGithubへPRを作成しています！\nお手隙の際にご確認お願いします:pray:\n\n<https://github.com/sho55/javascript-todo-template/pull/2>	1754055228.859209	1754055228.859209	6	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
132	1	D07TZCWBYBT	U06D3LY2M4Y	1754055256.933679	ありがとうございます！\n確認いたします！	1754055228.859209	1754055256.933679	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
133	1	D07TZCWBYBT	U06D3LY2M4Y	1754135929.576829	コメント返しました！\nご確認いただけると:pray:	1754055228.859209	1754135929.576829	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
134	1	D07TZCWBYBT	U07THSEMVE1	1754376682.723609	@もんしょー\nもんしょーさんお世話になります！\nJavaScriptの課題ですが、script.jsの内容を一部修正し、再度テストを実施しました！\nお手隙の際に、再度ご確認お願いします:pray:\n\n<https://github.com/sho55/javascript-todo-template/pull/2/commits/495bd57fa4938a4a32290ed2cec71040027e7565>	1754055228.859209	1754376682.723609	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
135	1	D07TZCWBYBT	U06D3LY2M4Y	1754376702.280909	確認します！	1754055228.859209	1754376702.280909	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
136	1	D07TZCWBYBT	U06D3LY2M4Y	1754626380.367319	お世話になっております！\n返信遅くなりました！\nこちら課題の修正確認いたしました！\nテスト項目概ねOKです！\nカバレッジいい感じですので、次に進んでいただけると:bow:	1754055228.859209	1754626380.367319	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
137	1	D07TZCWBYBT	U07THSEMVE1	1754715230.898379	@もんしょー \nお世話になります！\nご確認ありがとうございます！\n次の課題に進みますね:laughing:	1754055228.859209	1754715230.898379	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
138	1	D07TZCWBYBT	U07THSEMVE1	1753986356.437629	@もんしょー\nもんしょーさん！お世話になります！\nSlackアーカイブシステムの件について、要件定義をもとに「基本設計書」「テスト項目」を作成しました:sparkles:\n\nClaude Codeを活用しながら作成を進めています。\n私自身、基本設計レベルのドキュメント作成は初めてに近いため、内容に至らぬ点や見落としがあるかもしれませんが、その点をご了承いただけますと幸いです:bow:\n\nご意見やご指摘などがあれば、遠慮なくお知らせください:pray:\n\n基本設計書\n<https://www.notion.so/momsho/230717f1ba70800383afca276c1a35ec>\n\nテスト項目\n<https://www.notion.so/momsho/230717f1ba708071b65dc470488c0257>	\N	1753986356.437629	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:36	user
155	1	D07TZCWBYBT	U06D3LY2M4Y	1753188267.114249	ブログの引用の仕方\n<https://blognote.jp/blog-write-quote/>	\N	1753188267.114249	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
139	1	D07TZCWBYBT	U07THSEMVE1	1753793342.905139	@もんしょー\n<https://github.com/T-kudo88>\n\nやる課題: JS-Todo\n\nもんしょーさんお疲れ様です。\nJavaScriptの基礎学習を終えましたので、課題であるJavaScriptでTODOアプリを作成に着手したいと思いますので、Githubのコラボレーターの招待をお願いできますでしょうか:bow:	1753793342.905139	1753793342.905139	2	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:36	2025-09-22 08:03:37	user
140	1	D07TZCWBYBT	U06D3LY2M4Y	1753793871.767129	ありがとうございます！\n追加しました！	1753793342.905139	1753793871.767129	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
141	1	D07TZCWBYBT	U07THSEMVE1	1753796074.272069	@もんしょー\n確認できました！\nありがとうございます！	1753793342.905139	1753796074.272069	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
142	1	D07TZCWBYBT	U07THSEMVE1	1753606632.938869	@もんしょー\nもんしょーさん！お疲れ様です！\nSlackデータの保存・検索ツールに関する要件定義書を作成しましたので、一度共有させていただきます:page_with_curl:\n\n*要件定義の概要：*\n• プロジェクトの背景と目的\n• 機能要件（認証、Slack連携、検索、UIなど）\n• Claude Codeを活用したAI駆動開発戦略\n• データベース・API・フロントエンド設計\n• テスト戦略と開発スケジュール\n• コスト見積もりと運用方針\nご確認いただき、問題なければこの内容をベースに基本設計のフェーズへ進めたいと思います。\nご不明点・ご要望などございましたら、遠慮なく伝えていただけると嬉しいです:smile:\nよろしくお願いします:pray:\n\n<https://www.notion.so/momsho/230717f1ba70807a843dd0c6ca8fc7a2>	1753606632.938869	1753606632.938869	7	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
143	1	D07TZCWBYBT	U06D3LY2M4Y	1753690903.733489	ありがとうございます！\nいい感じだと思います:relaxed:\n\n&gt; 8.2 バックアップ・DR\n&gt; • *データベース*: 日次自動バックアップ\n&gt; • *ファイル*: S3互換ストレージ\n&gt; • *設定*: Infrastructure as Code\n→S3互換ストレージはコスト的にこれを使ってみようかと思いますが、他にも候補があればご教示いただけると幸いです:pray:\n<https://web.arena.ne.jp/wasabi/>	1753606632.938869	1753690903.733489	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
144	1	D07TZCWBYBT	U06D3LY2M4Y	1753691020.608929	VPSはすでに契約しているところがあるので、一旦ローカルでDockerを使っていただけると:pray:	1753606632.938869	1753691020.608929	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
145	1	D07TZCWBYBT	U07THSEMVE1	1753728737.900809	@もんしょー\nありがとうございます！\n一旦基本設計に進みます！\n\nストレージ選択についても、自分の方でもClaude Code等を活用して調べ、そちらも要件定義の方に、いくつか候補として記載しています！\n\n結論から言うと、\nデータ量が1TB未満なら Cloudflare R2、\n1TB以上なら Wasabi が良さげですね:thinking_face:	1753606632.938869	1753728737.900809	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
146	1	D07TZCWBYBT	U06D3LY2M4Y	1753751506.306939	ありがとうございます！\nそれだったら、R2で足りそうですね！\nただ\n・画像\n・動画\nも保存できるならやりたいです！	1753606632.938869	1753751506.306939	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
147	1	D07TZCWBYBT	U07THSEMVE1	1753783313.702269	@もんしょー\nでしたら、尚のことCloudflare R2で画像・動画対応で進めるのがアリかもです！\n\n画像・動画も全部保存できます！\n jpg、png、mp4、mov等の一般的なファイル形式に対応予定です。\n\n調べてみたところ、コスト面も問題なさそうで、画像・動画込みでも月500円程度に収まるかと。 R2なら転送料金も無料なので、ファイルをたくさんダウンロードしても追加料金はかかりません。\n\nClaude Codeで追加で質問したところ、技術的にはSlack APIからファイルを取得してR2に保存、アプリ内で画像プレビューや動画再生もできるようです！！	1753606632.938869	1753783313.702269	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
148	1	D07TZCWBYBT	U06D3LY2M4Y	1753793343.931679	調べていただきありがとうございます！\nそうですね、その方針でいきましょ:relaxed:	1753606632.938869	1753793343.931679	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
149	1	D07TZCWBYBT	U07THSEMVE1	1753884204.047729	@もんしょー\nありがとうございます！\nその点も、要件定義に含めつつ、基本設計を進めています！	1753606632.938869	1753884204.047729	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
150	1	D07TZCWBYBT	U06D3LY2M4Y	1753189056.089419	ありがとうございました！\n8/4~5が出先でして、、もしよかったら、8/6に新宿とかで作業しながらMTGしますか？	1753189056.089419	1753189056.089419	3	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
151	1	D07TZCWBYBT	U07THSEMVE1	1753194057.527819	@もんしょー \nありがとうございます！\nかしこまりました！\nそれでしたらぜひ、8/6(水)に作業しながらでもよろしいでしょうか？	1753189056.089419	1753194057.527819	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
152	1	D07TZCWBYBT	U06D3LY2M4Y	1753194698.198309	それでお願いできればと！\n午前中なら11時くらいから\n午後なら14時くらいから\n2時間くらいやろうかなと思うんですが、どっちがいいですか？	1753189056.089419	1753194698.198309	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
153	1	D07TZCWBYBT	U07THSEMVE1	1753196973.422549	@もんしょー \nそれでしたら、午後の14時からはいかがでしょうか？	1753189056.089419	1753196973.422549	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
154	1	D07TZCWBYBT	U07THSEMVE1	1753188830.482249	@もんしょー\n本日もありがとうございました！\n次のミーティングですが、2週間後の8/5の同じ時間帯でも大丈夫でしょうか？	\N	1753188830.482249	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
158	1	D07TZCWBYBT	U06D3LY2M4Y	1753185635.848179	今日のMTGお願いします！\n\n\n:date: 2025年7月22日: Zoomミーティングのお知らせ\n\n【くどうさん-MTG1回目】\n日時：2025年07月22日 21:00～\nミーティングID：86548517928\n参加URL：<https://us06web.zoom.us/j/86548517928>	\N	1753185635.848179	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
159	1	D07TZCWBYBT	U07THSEMVE1	1753005258.979849	@もんしょー\nもんしょーさん！\nお世話になっております！\n\nレビューいただいていた記事について、一度修正いたしました！\nお手数おかけしますが、再度ご確認お願いします:pray:\n<https://github.com/sho55/tech_blog_apps/pull/9/commits/c0486937f780cdfd1330b04482054b3cca094ff6>	\N	1753005258.979849	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:37	user
160	1	D07TZCWBYBT	U06D3LY2M4Y	1752917614.910409	溜まったPR見ていきますね！\n何かあればコメント残します:bow::skin-tone-2:	1752917614.910409	1752917614.910409	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:37	2025-09-22 08:03:38	user
161	1	D07TZCWBYBT	U07THSEMVE1	1752928329.948219	@もんしょー \nありがとうございます:bow:\n何かありましたら、遠慮なくコメントよろしくお願いします:sparkles:	1752917614.910409	1752928329.948219	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
162	1	D07TZCWBYBT	U07THSEMVE1	1752543190.738189	@もんしょー\nもんしょーさん！\nお世話になります！\n「DirectoryIterator の実践活用（ファイル一覧・容量制御）」について記事を書きましたので、こちらもご確認お願いします:pray:\n\n<https://github.com/sho55/tech_blog_apps/pull/7>	\N	1752543190.738189	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
163	1	D07TZCWBYBT	U07THSEMVE1	1752477995.164609	@もんしょー\n続けざまで失礼します！\n本日分のzennの記事を記述し、PRを作成しています。\nテーマは「最小構成で学ぶ！DockerでPHPを今すぐ動かすための超シンプルDockerfile」になります。\nお手隙の際にご確認お願いします:bow:\n\n<https://github.com/sho55/tech_blog_apps/pull/6>	1752477995.164609	1752477995.164609	2	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
164	1	D07TZCWBYBT	U07THSEMVE1	1752510195.316239	@もんしょー\nお手数おかけしております。\nご指摘いただいた２点ですが、一旦修正しました！\nお手隙の際にご確認お願いします:pray:\n<https://github.com/sho55/tech_blog_apps/pull/6/files>	1752477995.164609	1752510195.316239	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
165	1	D07TZCWBYBT	U06D3LY2M4Y	1752537403.239639	早速の修正ありがとうございます！\n確認いたします！	1752477995.164609	1752537403.239639	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
166	1	D07TZCWBYBT	U06D3LY2M4Y	1752472258.725499	@工藤辰哉\nお疲れ様です！\nぼちぼち、ツール開発の方を進めていきたいなと思ってます！\n詳細の共有は別途MTGの時間を作りますが、ざっくりとしたフェーズをまとめてみました！\nこちら確認できますかね？\n• 以下のページにアクセスできるか\n• 書き込みができるか\n• サブアイテムのページに飛べるか\n• サブアイテムのページに書き込みができるか\n<https://www.notion.so/momsho/230717f1ba708000b4ffddcfc3865446?source=copy_link>	1752472258.725499	1752472258.725499	3	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
167	1	D07TZCWBYBT	U07THSEMVE1	1752476109.778519	@もんしょー \nお疲れ様です！\n先ほどリンクを踏んで、ページのアクセスと書き込みはできましたが、サブアイテムのページには飛べなかったので、アクセスをリクエストしています！\nご確認お願いします:bow:	1752472258.725499	1752476109.778519	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
168	1	D07TZCWBYBT	U06D3LY2M4Y	1752476291.486759	ありがとうございます！\n権限付与いたしました！	1752472258.725499	1752476291.486759	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
169	1	D07TZCWBYBT	U07THSEMVE1	1752478280.830499	@もんしょー \n権限が付与されていることを確認しました！\nメッセージも送れてます！	1752472258.725499	1752478280.830499	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
180	1	D07TZCWBYBT	U07THSEMVE1	1752353119.912629	@もんしょー\n\nお疲れさまです！\n\nREADME.md にてご依頼いただいていた「mainブランチへの直push禁止」と「ブランチ→PR→mainマージ」の運用ルールを追記し、先ほどPull Requestも作成させていただきました。\n\n内容にお目通しいただき、問題なければマージしていただけますと幸いです。\nご確認よろしくお願いいたします！\n\n<https://github.com/sho55/tech_blog_apps/pull/3/commits/70e2e54246b6b52a638c9477a0faad5988373ae1>	1752318726.294859	1752353119.912629	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
170	1	D07TZCWBYBT	U07THSEMVE1	1752422005.770069	@もんしょー\nお疲れ様です！\nSQLの講座について質問がございます！\nお手隙の際にご確認お願いします:bow:\n\n1. mysqldump が使用できない\n• ターミナルから mysqldump -u root -p mysql-basic を実行すると、*「Unknown database ‘mysql-basic’」エラー*が発生\n• Sequel Ace 上では mysql-basic データベースが存在しており、*GUI と CLI で参照している MySQL インスタンスが異なる状態*\n*[今まで試したこと]*\n\n• SHOW DATABASES; をターミナルで実行\n　　→ mysql-basic が表示されず、別インスタンスを参照していると判明\n\n• SHOW VARIABLES LIKE 'datadir'; で確認\n　　→ ターミナルでは /opt/homebrew/var/mysql、Sequel Ace では /var/lib/mysql を使用しており、MySQLが2つ存在していた\n\n• lsof -iTCP -sTCP:LISTEN -P | grep mysql でポート確認\n　　→ 両者ともポート 3306 を使用していたが、*異なるMySQLプロセス*が起動していた\n\n• Homebrew版 MySQL（v9.3.0）をアンインストール\n　　→ /opt/homebrew/var/mysql 側が削除され、Sequel Ace の MySQL に統一完了\n\n• .zshrc に export PATH="/usr/local/mysql/bin:$PATH" を追加して mysql コマンドを使えるように設定\n　　→ /usr/local/mysql/bin が存在せず、which mysql の結果も「not found」で、*CLI が未構成状態*\n\n*[確認・質問したいこと]*\n• Sequel Ace が参照している /var/lib/mysql の MySQL に対し、*ターミナルから mysql や mysqldump を使えるようにするにはどうすれば良いか？*\n• /usr/local/mysql/bin が存在しない現在、*公式インストーラでCLIのみ再構成するのが適切か？* それとも他の方法があるか？\n*現状の進捗（任意）*\n• Sequel Ace 上で mysql-basic を含む全データベースの操作は可能\n• MySQLの重複インストール問題を解消し、Sequel AceのMySQLに環境を統一済み\n• CLIからのアクセスは未整備のため、mysqldump などのバックアップ操作は現時点で不可	\N	1752422005.770069	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:38	user
171	1	D07TZCWBYBT	U07THSEMVE1	1752377142.846639	@もんしょー\n連続で失礼します！\n今日もzennの記事を書きました\nテーマは「なぜPOSTなのか？CSRFバリデーション実装から見えてきたHTTPメソッドのリアル」です！\n\nこちらも併せてご確認お願いします:bow:\n<https://github.com/sho55/tech_blog_apps/pull/4>	1752377142.846639	1752377142.846639	2	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:38	2025-09-22 08:03:39	user
172	1	D07TZCWBYBT	U06D3LY2M4Y	1752385390.022409	確認しました!\napproveしてmergeしてます！	1752377142.846639	1752385390.022409	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
173	1	D07TZCWBYBT	U07THSEMVE1	1752386697.616629	@もんしょー \n確認ありがとうございます:blush:	1752377142.846639	1752386697.616629	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
174	1	D07TZCWBYBT	U07THSEMVE1	1752318726.294859	@もんしょー\nもんしょーさん、お疲れ様です！\n手順書を2つ作成(zennとgithubの連携 &amp; 記事の書き方)と、本日記載した記事(PHPの`intdiv()` を使った整数除算の実用例（おつり計算など)をいつもの場所にプッシュしました。\n\n自分が過去の経験で悩んだ部分をいくつか書き出し、試しに今回のテーマを採用しています。\nご確認の上、気になる点などありましたら、遠慮なく指摘していただけると幸いです！\n\n\n<https://github.com/sho55/tech_blog_apps/tree/main|https://github.com/sho55/tech_blog_apps/tree/main>	1752318726.294859	1752318726.294859	6	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
175	1	D07TZCWBYBT	U06D3LY2M4Y	1752318779.677009	ありがとうございます！\n手順書も助かります:pray:\n確認しますね！	1752318726.294859	1752318779.677009	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
176	1	D07TZCWBYBT	U06D3LY2M4Y	1752321212.248819	確認しました！\n\n> こんにちは、工藤です。\n↓\nこんにちは、工藤*([Xのアカウント])*です。\nURLを入れちゃっていいですよ！	1752318726.294859	1752321212.248819	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
177	1	D07TZCWBYBT	U07THSEMVE1	1752329077.899909	@もんしょー \nご確認ありがとうございます:bow:\n明日から、アカウントのリンクも入れされていただきます :smile: 	1752318726.294859	1752329077.899909	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
178	1	D07TZCWBYBT	U06D3LY2M4Y	1752330941.346939	ありがとうございます！\n2点お伝えすることがあって、\n• READMEに「直接mainはしないで」と追記してもらいたい\n• すでに修正はしたのですが、mainに古いデータままpushされ、記事が全部消えるということが起きておりました、、対処法として「ブランチを作成→PRを出す→mainにpush」という流れをしたいので、それもREADMEに書く\nということをお願いできますでしょうか。。。？\nお手数ですがよろしくお願いいたします:bow:\n\nmainへの直pushは禁止するrulesetはしたので、ご確認いただければと！\n<https://zenn.dev/jin1125/articles/a38c360e3319b7|https://zenn.dev/jin1125/articles/a38c360e3319b7>	1752318726.294859	1752330941.346939	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
179	1	D07TZCWBYBT	U07THSEMVE1	1752351365.996749	@もんしょー\nかしこまりました！\nそのように修正いたします！	1752318726.294859	1752351365.996749	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
195	1	D07TZCWBYBT	U07THSEMVE1	1752058193.216219	@もんしょー\n• Zennの技術ブログの立ち上げ、作成\nもんしょーさんお疲れ様です。\nZennの技術ブログの立ち上げを行い、一つだけで下書きの状態ですが、記事を記述してみました。\n内容は「SESSIONの説明と役割」になります。\n\nお手隙の際にご確認お願いします:bow:\n<https://github.com/sho55/tech_blog_apps>	1752058193.216219	1752058193.216219	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
196	1	D07TZCWBYBT	U06D3LY2M4Y	1751983335.572069	会員サイト\n<https://utage-system.com/members/RMSoi176VhSa/login>	\N	1751983335.572069	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
197	1	D07TZCWBYBT	U06D3LY2M4Y	1751982379.800319	やること2025/07/08\n• Xの日報、広報活動\n• Zennの技術ブログの立ち上げ、作成	\N	1751982379.800319	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
198	1	D07TZCWBYBT	U06D3LY2M4Y	1751982319.485219	Zennの画像貼り付け\n<https://zenn.dev/zenn/articles/deploy-github-images>	\N	1751982319.485219	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
199	1	D07TZCWBYBT	U07THSEMVE1	1751982017.961619	<https://github.com/T-kudo88/sozitoban_portfolio>	\N	1751982017.961619	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
181	1	D07TZCWBYBT	U07THSEMVE1	1752229445.860609	@もんしょー\nもんしょーさんお疲れ様です！\n今日も「CSSの基本的とよく使うプロパティ」について記事を書いて、githubにプッシュ済みです！\nこちらもお手隙の際にご確認お願いします:bow:\n\n<https://github.com/sho55/tech_blog_apps/tree/main|https://github.com/sho55/tech_blog_apps/tree/main>\n\n確認になりますが、zennの記事の投稿頻度ですが、しばらくは毎日記事を上げていく方針であってますでしょうか？\nもし、異なるようでしたら、ご教示いただけると幸いです:sparkles:	1752229445.860609	1752229445.860609	9	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
182	1	D07TZCWBYBT	U06D3LY2M4Y	1752229985.839399	ありがとうございます！\n確認させていただきます！\n\n記事の頻度ですが、最初は初速をつけたくなるべく、毎日が良いですが、最終的には週3くらいのペースで良いかなと考えております！\n\nまた、1点お願いなのですが、他の方にも記事の依頼をしようと思っており、その方が書き方はわからないという時にサポートと簡単なマニュアルの作成をしてもらっても良いですか。。。？\n\n(先ほどご連絡したところ、Githubには招待しましたが、全く書き方がわからないということだったので、ご支援いただけると:sob:)	1752229445.860609	1752229985.839399	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
183	1	D07TZCWBYBT	U07THSEMVE1	1752235008.368269	@もんしょー\n承知いたしました！\nひとまず毎日投稿でいきたいと思います！\n\nマニュアルの件もかしこまりました:smile:\n出来上がり次第、また共有させていただきますね:sparkles:	1752229445.860609	1752235008.368269	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
184	1	D07TZCWBYBT	U06D3LY2M4Y	1752235298.856559	ありがとうございます！！:bow:	1752229445.860609	1752235298.856559	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
185	1	D07TZCWBYBT	U07THSEMVE1	1752239637.754819	@もんしょー\n度々すみません:sweat_drops:\n完成したマニュアルの共有方法についてご相談です！\n\nREADME.md に追記して GitHub に push する形でも問題ないでしょうか？\nもし他にご希望の共有方法があれば、そちらに合わせますので教えていただけると嬉しいです:blush:	1752229445.860609	1752239637.754819	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
186	1	D07TZCWBYBT	U06D3LY2M4Y	1752239934.204769	@工藤辰哉\nありがとうございます！\nその方法で問題ございません！！:ok_hand:	1752229445.860609	1752239934.204769	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
187	1	D07TZCWBYBT	U06D3LY2M4Y	1752243964.508979	あと、記事ついてなんですが、工藤さんの意見が反映されているようなものがいいです！3割くらいでいいので経験や勉強した内容(ニッチでも構わないので入れてもらえると:pray:)	1752229445.860609	1752243964.508979	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
188	1	D07TZCWBYBT	U06D3LY2M4Y	1752243986.342909	こちらからもテーマ準備していきますね！	1752229445.860609	1752243986.342909	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
189	1	D07TZCWBYBT	U06D3LY2M4Y	1752245987.630599	まず会員サイトに出てくる、１テーマごと(PHP、Docker、Webの仕組み)から、めっちゃニッチに深ぼってほしいです！\n• 1テーマで30個くらい出す\n• それ、他の人書かないだろ。。みたいなものを選択(そのキーワードでググってみて確認)\n• そういう記事を作って、ニッチな分野を制覇する\nみたいにやってほしいです！	1752229445.860609	1752245987.630599	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
190	1	D07TZCWBYBT	U07THSEMVE1	1752248240.147669	@もんしょー \nフィードバックありがとうございます！\n改めてPHPやWEBの仕組みで、自分が躓いた部分を書き出して、記事のテーマにしますね。\nまた、明日記事を書いたら、共有いたします！	1752229445.860609	1752248240.147669	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
191	1	D07TZCWBYBT	U07THSEMVE1	1752131652.326639	@もんしょー\nお疲れ様です。\n本日もzennの記事を記述し、下書き状態ですが、githubにaddしています。\nテーマは「*HTMLの実務活用とよく使うタグ一覧*」になります。\n記事の最後尾にも、Youtubeリンクも添付済みです！\n\nお手隙の際にご確認お願いします:bow:\n<https://github.com/sho55/tech_blog_apps/tree/main>	\N	1752131652.326639	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:39	user
192	1	D07TZCWBYBT	U06D3LY2M4Y	1752066866.248059	ありがとうございます！\n良いテーマですね :+1:\n早速公開させていただきました！\n\n最後の部分にYouTubeのリンクだけ追記しましたので、ご了承ください :pray:\n<https://zenn.dev/01engineer/articles/session-intro-and-role>	1752066866.248059	1752066866.248059	1	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:39	2025-09-22 08:03:40	user
193	1	D07TZCWBYBT	U07THSEMVE1	1752086065.498319	@もんしょー \nご確認ありがとうございます！\n明日以降は、Youtubeのリンクも添付して記事を挙げていきますね:sparkles:	1752066866.248059	1752086065.498319	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
194	1	D07TZCWBYBT	U06D3LY2M4Y	1752065348.478159	はやい！\n確認いたします！	1752058193.216219	1752065348.478159	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": "thread_broadcast", "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
218	1	D07TZCWBYBT	U06D3LY2M4Y	1750817392.248299	承知です！\nでは、今日の21時半からやりましょう！\nZoomのリンク送ります！	\N	1750817392.248299	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
219	1	D07TZCWBYBT	U07THSEMVE1	1750817013.601259	@もんしょー \nありがとうございます:bow:\nすごく助かります！\n\n今晩でしたら21時半以降なら、いつでも対応可能です！\nもんしょーさんの都合の良い時間帯があれば、今夜よろしくお願いします:bow:	\N	1750817013.601259	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
200	1	D07TZCWBYBT	U06D3LY2M4Y	1751980017.121919	• slackの90日以降も見れるように\n• 動画を見ながら環境構築(TSを使う)\n<https://www.youtube.com/watch?v=humnThHNjLU>	\N	1751980017.121919	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
201	1	D07TZCWBYBT	U06D3LY2M4Y	1751979671.628079	<https://zenn.dev/zenn/articles/zenn-cli-guide>	\N	1751979671.628079	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
202	1	D07TZCWBYBT	U06D3LY2M4Y	1751979603.305109	Zenn CLIについて\n<https://zenn.dev/zenn/articles/install-zenn-cli>	\N	1751979603.305109	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
203	1	D07TZCWBYBT	U06D3LY2M4Y	1751555476.517179	お疲れ様です！\n今後の方針どんな感じになりました？？	1751555476.517179	1751555476.517179	8	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
204	1	D07TZCWBYBT	U07THSEMVE1	1751611595.420469	@もんしょー \nもんしょーさん、いつもお世話になっております！\nひとまず会社の方は6月末で退職済みです。\n\n今の所はWEBエンジニアとしてキャリアを続けたいと考えおります！\n\n自分の退職が、会社都合として扱われるか次第ですが、失業保険を受けつつ、その合間で給付金を活用するなどして、プログラミングスクールとかで、基礎から学び直した後に、再度転職活動を再開しようかと思ってます。	1751555476.517179	1751611595.420469	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
205	1	D07TZCWBYBT	U06D3LY2M4Y	1751611908.844189	お！エンジニアのキャリアを続けるのですね！:ok_hand:\n\n一旦基礎固めの時期にするのはいいと思います！\nスクールなり私なりを活用できるものをしっかり使った方が良いかなと思います！\n\nまた、金額面で悩んでいるところあればご相談くださいませ！\n現在、お手伝いしてくれる方にはほぼ無料で受講できるプランも作ってます:computer:	1751555476.517179	1751611908.844189	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
206	1	D07TZCWBYBT	U07THSEMVE1	1751822588.155559	@もんしょー \nご提案いただきありがとうございます:sob:\nまだ、離職票が届いてないので、給付金の申請とかはこれからですが、プログラミングスクールを利用するのにしても、金額は正直悩みますね:sweat_drops:\n\nもんしょーさんが言われる、無料で受講できるプランも正直かなり気になります:sparkles:	1751555476.517179	1751822588.155559	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
207	1	D07TZCWBYBT	U06D3LY2M4Y	1751889001.376549	確かにスクールに行くにしても入会金が戻ってくるは半年以上先なので、とりあえず失業手になりますかね、、\n\n\n自分の提案するのは、個別指導受けながら、仕事のお手伝いをしていただくようなものになります！\n内容は確定ではありませんが、\n・Xでの日報、広報活動\n・スクールとしての技術ブログの投稿\n・チーム開発レッスンの立ち上げ\nなどを考えております！\n\n良かったら時間取ってお話しましょうか？	1751555476.517179	1751889001.376549	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
208	1	D07TZCWBYBT	U07THSEMVE1	1751945505.067319	@もんしょー \n入会金って、戻ってくるのがそんなに先なんですね :innocent: \n\n内容の件も詳しくお話聞きたいです！\nお手数おかけしますが、どこかでご都合いかがでしょうか？	1751555476.517179	1751945505.067319	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
209	1	D07TZCWBYBT	U06D3LY2M4Y	1751952654.225989	@工藤辰哉\nありがとうございます！\nちなみに今夜21時以降ではいかがでしょうか？	1751555476.517179	1751952654.225989	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
210	1	D07TZCWBYBT	U07THSEMVE1	1751956372.481169	@もんしょー \nありがとうございます！\n今夜の21時であれば大丈夫です！	1751555476.517179	1751956372.481169	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
211	1	D07TZCWBYBT	U06D3LY2M4Y	1751957730.650839	@工藤辰哉\n承知です！\nでは、お時間なりましたらこちらからお願いします！\n\nshohei momma さんがあなたをスケジュール済みの Zoom ミーティングに招待しています。\n\nトピック: 工藤さん-MTG\n時刻: 2025年7月8日 09:00 PM 大阪、札幌、東京\nZoom ミーティングに参加する\n<https://us06web.zoom.us/j/89241278601>	1751555476.517179	1751957730.650839	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
212	1	D07TZCWBYBT	U07THSEMVE1	1750863545.564549	@もんしょー \n間違いないです！！\nこれから一段一段着実に登ります:woman_climbing:	\N	1750863545.564549	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
213	1	D07TZCWBYBT	U06D3LY2M4Y	1750861943.989279	ありがとうございました！\nこれから発信楽しみにしてます :relaxed:\n\nこれから登るだけですね！	\N	1750861943.989279	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
214	1	D07TZCWBYBT	U07THSEMVE1	1750861473.887509	@もんしょー \nもんしょーさん！\n先ほどお時間いただきありがとうございました:sob:\nおかげさまで前向きになれました！\n\n下記の通りXのアカウントを作成しました♪\nフォローもありがとうございます:blush:\n<https://x.com/tatsuya_restart?s=21|https://x.com/tatsuya_restart?s=21>	\N	1750861473.887509	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
215	1	D07TZCWBYBT	U06D3LY2M4Y	1750859947.511149	<https://www.youtube.com/watch?v=odLzgSZXTs8>	\N	1750859947.511149	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
216	1	D07TZCWBYBT	U06D3LY2M4Y	1750859913.351669	[雇用保険改正]\n<https://www.youtube.com/watch?v=PEHsZMXidp0>\n\n[ジョブピス]\n<https://www.youtube.com/@jobpice>	\N	1750859913.351669	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
217	1	D07TZCWBYBT	U07THSEMVE1	1750820179.916829	@もんしょー \n\nかしこまりました！\n急なお願いで大変恐縮ですが、本日の21時半からよろしくお願いします:bow:	\N	1750820179.916829	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
221	1	D07TZCWBYBT	U07THSEMVE1	1750814457.763049	@もんしょー \n\nもんしょーさん！\nご無沙汰しております:bow:\n\n突然の連絡で申し訳ございません:sweat_drops:\n今後の事は(進路等について)ご相談があるのですが、どこかのタイミングでお時間いただく事は可能でしょうか？	\N	1750814457.763049	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:40	2025-09-22 08:03:40	user
222	1	D07TLKLK0BV	\N	1745044459.538699	:broom: 掃除の時間です！担当者は掃除を始めましょう！	\N	1745044459.538699	0	message	f	\N	{"app_id": null, "bot_id": "B08PHPXJ8NL", "subtype": "bot_message", "username": null}	2025-09-22 08:03:42	2025-09-22 08:03:42	user
223	1	D07TLKLK0BV	U07THSEMVE1	1745042288.176029	added an integration to this channel: <https://monsho-support.slack.com/services/B08PHPXJ8NL|SozitobanNotifier>	\N	1745042288.176029	0	message	f	\N	{"app_id": null, "bot_id": "B08PHPXJ8NL", "subtype": "bot_add", "username": null}	2025-09-22 08:03:42	2025-09-22 08:03:42	user
224	1	D07T667T19V	\N	1756525246.931929		\N	1756525246.931929	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:42	2025-09-22 08:03:42	user
225	1	D07T667T19V	\N	1756516362.852809		\N	1756516362.852809	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:42	2025-09-22 08:03:42	user
226	1	D07T667T19V	\N	1756514954.675579		\N	1756514954.675579	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:42	2025-09-22 08:03:42	user
227	1	D07T667T19V	\N	1733801651.684099	あなたは @もんしょー により #utage-ポートフォリオ添削 から外されました	\N	1733801651.684099	0	message	f	\N	{"app_id": null, "bot_id": null, "subtype": null, "username": null}	2025-09-22 08:03:42	2025-09-22 08:03:42	user
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2025_08_28_102631_create_workspaces_table	1
5	2025_08_28_102640_create_channels_table	1
6	2025_08_28_102647_create_messages_table	1
7	2025_09_02_110945_create_channel_users_table	1
8	2025_09_02_111006_create_audit_logs_table	1
9	2025_09_02_111028_add_personal_data_indexes_to_messages_table	1
10	2025_09_05_112845_add_display_name_to_users_table	1
11	2025_09_06_115456_create_slack_files_table	1
12	2025_09_06_120213_add_api_tokens_to_users_table	1
13	2025_09_07_083633_create_user_workspace_table	1
14	2025_09_10_092520_create_personal_access_tokens_table	1
15	2025_09_14_060014_alter_messages_channel_id_to_string	1
16	2025_09_14_061109_alter_messages_user_id_to_string	1
17	2025_09_14_062213_add_is_im_to_channels_table	1
18	2025_09_14_062424_alter_channels_id_to_string	1
19	2025_09_14_123434_add_message_id_to_slack_files_table	1
20	2025_09_14_132755_add_slack_channel_id_to_channels_table	1
21	2025_09_20_073528_add_remember_token_to_users_table	1
22	2025_09_20_074215_add_last_login_at_to_users_table	1
23	2025_09_20_082219_add_slack_user_id_to_users_table	1
24	2025_09_20_153938_change_message_id_to_string_in_slack_files	1
25	2025_09_22_072027_add_type_to_messages_table	2
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: slack_files; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.slack_files (id, slack_file_id, name, title, mimetype, file_type, pretty_type, user_id, channel_id, size, url_private, url_private_download, thumb_64, thumb_80, thumb_160, thumb_360, thumb_480, thumb_720, thumb_800, thumb_960, thumb_1024, permalink, permalink_public, is_external, external_type, is_public, public_url_shared, display_as_bot, username, "timestamp", local_path, local_thumbnail_path, download_status, file_hash, metadata, deleted_at, created_at, updated_at, message_id) FROM stdin;
1	F09BV9LF7K5	screencapture-github-sho55-react-js-blackjack-pull-3-2025-08-26-09_32_30.png	screencapture-github-sho55-react-js-blackjack-pull-3-2025-08-26-09_32_30.png	image/png	\N	\N	U06D3LY2M4Y	C06CEQ59B1R	0	https://files.slack.com/files-pri/T06CETFH6EN-F09BV9LF7K5/screencapture-github-sho55-react-js-blackjack-pull-3-2025-08-26-09_32_30.png	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:41	2025-09-22 07:29:41	1756168895.408399
2	F09BLS4K9L7	screencapture-github-sho55-javascript-todo-template-pull-4-2025-08-26-09_33_03.png	screencapture-github-sho55-javascript-todo-template-pull-4-2025-08-26-09_33_03.png	image/png	\N	\N	U06D3LY2M4Y	C06CEQ59B1R	0	https://files.slack.com/files-pri/T06CETFH6EN-F09BLS4K9L7/screencapture-github-sho55-javascript-todo-template-pull-4-2025-08-26-09_33_03.png	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:41	2025-09-22 07:29:41	1756168895.408399
3	F095GBA1U13	スクリーンショット 2025-07-12 17.24.56.png	スクリーンショット 2025-07-12 17.24.56.png	image/png	\N	\N	U06D3LY2M4Y	C06CEQ59B1R	0	https://files.slack.com/files-pri/T06CETFH6EN-F095GBA1U13/____________________________2025-07-12_17.24.56.png	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:41	2025-09-22 07:29:41	1752316590.219999
4	F095WSC677B	スクリーンショット 2025-07-12 17.24.45.png	スクリーンショット 2025-07-12 17.24.45.png	image/png	\N	\N	U06D3LY2M4Y	C06CEQ59B1R	0	https://files.slack.com/files-pri/T06CETFH6EN-F095WSC677B/____________________________2025-07-12_17.24.45.png	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:41	2025-09-22 07:29:41	1752316590.219999
5	F09G5JS3151	IMG_3931.jpg	IMG_3931.jpg	image/jpeg	\N	\N	U06D3LY2M4Y	C06CETR50US	0	https://files.slack.com/files-pri/T06CETFH6EN-F09G5JS3151/img_3931.jpg	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:41	2025-09-22 07:29:41	1758416991.306119
6	F09ELE3JDM2	ABA8F453-87AC-40D7-A776-885AB3CE6C29.jpeg	ABA8F453-87AC-40D7-A776-885AB3CE6C29.jpeg	image/jpeg	\N	\N	U07THSEMVE1	D07TZCWBYBT	0	https://files.slack.com/files-pri/T06CETFH6EN-F09ELE3JDM2/aba8f453-87ac-40d7-a776-885ab3ce6c29.jpeg	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:56	2025-09-22 07:29:56	1757492787.969649
7	F09EGF8SZCJ	E871A849-E5B2-4243-8317-E1F673C84B1C.jpeg	E871A849-E5B2-4243-8317-E1F673C84B1C.jpeg	image/jpeg	\N	\N	U07THSEMVE1	D07TZCWBYBT	0	https://files.slack.com/files-pri/T06CETFH6EN-F09EGF8SZCJ/e871a849-e5b2-4243-8317-e1f673c84b1c.jpeg	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:56	2025-09-22 07:29:56	1757492787.969649
8	F09E254MUSK	F09BCFC9-6CA3-48A8-83A9-D71F8CA50BC8.jpeg	F09BCFC9-6CA3-48A8-83A9-D71F8CA50BC8.jpeg	image/jpeg	\N	\N	U07THSEMVE1	D07TZCWBYBT	0	https://files.slack.com/files-pri/T06CETFH6EN-F09E254MUSK/f09bcfc9-6ca3-48a8-83a9-d71f8ca50bc8.jpeg	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:56	2025-09-22 07:29:56	1757492787.969649
9	F09CUSGF8BY	スクリーンショット 2025-08-31 14.37.03.png	スクリーンショット 2025-08-31 14.37.03.png	image/png	\N	\N	U07THSEMVE1	D07TZCWBYBT	0	https://files.slack.com/files-pri/T06CETFH6EN-F09CUSGF8BY/____________________________2025-08-31_14.37.03.png	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:58	2025-09-22 07:29:58	1756618678.464009
10	F09CEBRFWCX	スクリーンショット 2025-08-31 14.36.12.png	スクリーンショット 2025-08-31 14.36.12.png	image/png	\N	\N	U07THSEMVE1	D07TZCWBYBT	0	https://files.slack.com/files-pri/T06CETFH6EN-F09CEBRFWCX/____________________________2025-08-31_14.36.12.png	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:58	2025-09-22 07:29:58	1756618678.464009
11	F099QSXR6J2	画面収録 2025-08-10 14.19.00.mov	画面収録 2025-08-10 14.19.00.mov	video/quicktime	\N	\N	U06D3LY2M4Y	D07TZCWBYBT	0	https://files.slack.com/files-tmb/T06CETFH6EN-F099QSXR6J2-13d29e3cbb/_____________2025-08-10_14.19.00.mp4	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	f	\N	f	f	f	\N	\N	\N	\N	pending	\N	\N	\N	2025-09-22 07:29:59	2025-09-22 07:29:59	1754804994.729649
\.


--
-- Data for Name: user_workspace; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.user_workspace (id, user_id, workspace_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.users (id, name, email, avatar_url, is_admin, is_active, created_at, updated_at, display_name, api_token, api_token_created_at, api_token_last_used_at, remember_token, last_login_at, slack_user_id) FROM stdin;
U06D3LY2M4Y	もんしょー	apple741run62do@gmail.com	https://avatars.slack-edge.com/2024-12-28/8240861697137_1debb02c7f1e1442156e_192.png	f	t	2025-09-22 06:43:13	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U07THSEMVE1	工藤辰哉	q2313009@gmail.com	https://avatars.slack-edge.com/2024-11-08/8000013495875_a7fd4c30f671f1ef6c39_192.png	f	t	2025-09-22 05:11:56	2025-09-22 07:14:35	\N	\N	\N	\N	eT73DASEaTyYkfuencvWvLBU4FyDwKCJlcW4CLLO3fWyoKWMh6SREPCey2dn	\N	\N
U085Z02KUDC	木崎有貴	factory0611@gmail.com	https://secure.gravatar.com/avatar/c694d658d2dc2376a0cef84a4c918f96.jpg?s=192&d=https%3A%2F%2Fa.slack-edge.com%2Fdf10d%2Fimg%2Favatars%2Fava_0021-192.png	f	f	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U086JMG1GD9	Hiroki	hiroki.ne.com@gmail.com	https://secure.gravatar.com/avatar/b8f4409862b3660c25dc41d508a31466.jpg?s=192&d=https%3A%2F%2Fa.slack-edge.com%2Fdf10d%2Fimg%2Favatars%2Fava_0015-192.png	f	f	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U087ZHL3Q48	Imai	penticton.2003@gmail.com	https://avatars.slack-edge.com/2025-01-05/8261662337889_e34e3494578fd9582bd7_192.jpg	f	t	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U08C1QKSVCG	Riku	rikusugawara.1103@gmail.com	https://avatars.slack-edge.com/2025-06-17/9046327736407_603b8add35b8befd3910_192.png	f	t	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U08JQMLJ030	谷野雄一	s1914075un@shujitsu.jp	https://secure.gravatar.com/avatar/cae4a4cba26736ec8c5aa59cdc9ec624.jpg?s=192&d=https%3A%2F%2Fa.slack-edge.com%2Fdf10d%2Fimg%2Favatars%2Fava_0012-192.png	f	t	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U08SSN8DT3R	タカダアツシ	ntt01012014@gmail.com	https://avatars.slack-edge.com/2025-07-01/9129579289989_290594ba8a3dc69b1de5_192.png	f	t	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U08UJHFF0CX	shota	0756235.s@gmail.com	https://avatars.slack-edge.com/2025-06-02/8970594613079_85dfc1fbd0f1f8a59c8b_192.png	f	t	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U098M82AYM6	Hisui Love	hisui.love@gmail.com	https://secure.gravatar.com/avatar/4dde2430132eeeb5c179d53192788cd1.jpg?s=192&d=https%3A%2F%2Fa.slack-edge.com%2Fdf10d%2Fimg%2Favatars%2Fava_0012-192.png	f	f	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
U09BD2QTEQ7	はっとり	yuya.htr0828@gmail.com	https://secure.gravatar.com/avatar/2aeb4746358e4d283d432c2e147a8fe7.jpg?s=192&d=https%3A%2F%2Fa.slack-edge.com%2Fdf10d%2Fimg%2Favatars%2Fava_0000-192.png	f	t	2025-09-22 07:14:35	2025-09-22 07:14:35	\N	\N	\N	\N	\N	\N	\N
\.


--
-- Data for Name: workspaces; Type: TABLE DATA; Schema: public; Owner: slack_user
--

COPY public.workspaces (id, slack_team_id, name, domain, bot_token, is_active, created_at, updated_at) FROM stdin;
1	T06CETFH6EN	Default Workspace	\N	\N	t	2025-09-22 06:40:34	2025-09-22 06:40:34
\.


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 1, false);


--
-- Name: channel_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.channel_users_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.messages_id_seq', 227, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.migrations_id_seq', 25, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: slack_files_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.slack_files_id_seq', 11, true);


--
-- Name: user_workspace_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.user_workspace_id_seq', 1, false);


--
-- Name: workspaces_id_seq; Type: SEQUENCE SET; Schema: public; Owner: slack_user
--

SELECT pg_catalog.setval('public.workspaces_id_seq', 1, true);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: channel_users channel_users_channel_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channel_users
    ADD CONSTRAINT channel_users_channel_id_user_id_unique UNIQUE (channel_id, user_id);


--
-- Name: channel_users channel_users_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channel_users
    ADD CONSTRAINT channel_users_pkey PRIMARY KEY (id);


--
-- Name: channels channels_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channels
    ADD CONSTRAINT channels_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id);


--
-- Name: messages messages_workspace_id_slack_message_id_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_workspace_id_slack_message_id_unique UNIQUE (workspace_id, slack_message_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: slack_files slack_files_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.slack_files
    ADD CONSTRAINT slack_files_pkey PRIMARY KEY (id);


--
-- Name: slack_files slack_files_slack_file_id_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.slack_files
    ADD CONSTRAINT slack_files_slack_file_id_unique UNIQUE (slack_file_id);


--
-- Name: user_workspace user_workspace_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.user_workspace
    ADD CONSTRAINT user_workspace_pkey PRIMARY KEY (id);


--
-- Name: user_workspace user_workspace_user_id_workspace_id_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.user_workspace
    ADD CONSTRAINT user_workspace_user_id_workspace_id_unique UNIQUE (user_id, workspace_id);


--
-- Name: users users_api_token_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_api_token_unique UNIQUE (api_token);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_slack_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_slack_user_id_unique UNIQUE (slack_user_id);


--
-- Name: workspaces workspaces_pkey; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.workspaces
    ADD CONSTRAINT workspaces_pkey PRIMARY KEY (id);


--
-- Name: workspaces workspaces_slack_team_id_unique; Type: CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.workspaces
    ADD CONSTRAINT workspaces_slack_team_id_unique UNIQUE (slack_team_id);


--
-- Name: audit_logs_accessed_user_id_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX audit_logs_accessed_user_id_created_at_index ON public.audit_logs USING btree (accessed_user_id, created_at);


--
-- Name: audit_logs_action_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX audit_logs_action_created_at_index ON public.audit_logs USING btree (action, created_at);


--
-- Name: audit_logs_admin_user_id_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX audit_logs_admin_user_id_created_at_index ON public.audit_logs USING btree (admin_user_id, created_at);


--
-- Name: audit_logs_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX audit_logs_created_at_index ON public.audit_logs USING btree (created_at);


--
-- Name: audit_logs_resource_type_resource_id_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX audit_logs_resource_type_resource_id_index ON public.audit_logs USING btree (resource_type, resource_id);


--
-- Name: channel_users_channel_id_left_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX channel_users_channel_id_left_at_index ON public.channel_users USING btree (channel_id, left_at);


--
-- Name: channel_users_user_id_channel_id_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX channel_users_user_id_channel_id_index ON public.channel_users USING btree (user_id, channel_id);


--
-- Name: channels_slack_channel_id_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX channels_slack_channel_id_index ON public.channels USING btree (slack_channel_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: messages_channel_id_timestamp_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX messages_channel_id_timestamp_index ON public.messages USING btree (channel_id, "timestamp");


--
-- Name: messages_channel_id_user_id_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX messages_channel_id_user_id_index ON public.messages USING btree (channel_id, user_id);


--
-- Name: messages_user_id_channel_id_timestamp_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX messages_user_id_channel_id_timestamp_index ON public.messages USING btree (user_id, channel_id, "timestamp");


--
-- Name: messages_workspace_id_user_id_timestamp_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX messages_workspace_id_user_id_timestamp_index ON public.messages USING btree (workspace_id, user_id, "timestamp");


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: slack_files_download_status_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX slack_files_download_status_created_at_index ON public.slack_files USING btree (download_status, created_at);


--
-- Name: slack_files_file_hash_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX slack_files_file_hash_index ON public.slack_files USING btree (file_hash);


--
-- Name: slack_files_file_type_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX slack_files_file_type_created_at_index ON public.slack_files USING btree (file_type, created_at);


--
-- Name: slack_files_user_id_created_at_index; Type: INDEX; Schema: public; Owner: slack_user
--

CREATE INDEX slack_files_user_id_created_at_index ON public.slack_files USING btree (user_id, created_at);


--
-- Name: audit_logs audit_logs_accessed_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_accessed_user_id_foreign FOREIGN KEY (accessed_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_admin_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_admin_user_id_foreign FOREIGN KEY (admin_user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: channel_users channel_users_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channel_users
    ADD CONSTRAINT channel_users_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: channel_users channel_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channel_users
    ADD CONSTRAINT channel_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: channels channels_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.channels
    ADD CONSTRAINT channels_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: messages messages_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: messages messages_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- Name: slack_files slack_files_channel_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.slack_files
    ADD CONSTRAINT slack_files_channel_id_foreign FOREIGN KEY (channel_id) REFERENCES public.channels(id) ON DELETE CASCADE;


--
-- Name: slack_files slack_files_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.slack_files
    ADD CONSTRAINT slack_files_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_workspace user_workspace_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.user_workspace
    ADD CONSTRAINT user_workspace_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_workspace user_workspace_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: slack_user
--

ALTER TABLE ONLY public.user_workspace
    ADD CONSTRAINT user_workspace_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.workspaces(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict BYD9RGGM8hF6sGaJfauJih9AmTgaRoNvgydkdeqOj1ZjzDqRHMkH7tqqKo0pmwq

