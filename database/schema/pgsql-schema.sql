--
-- PostgreSQL database dump
--

\restrict DLFH2j3RU1gfXyStUo73gDSFiduDBznxbwi9XbJx2ckG74EI1QrRexY2Sle8r77

-- Dumped from database version 16.14
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON SCHEMA public IS '';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: ai_analysis_dispatch_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_analysis_dispatch_states (
    id bigint NOT NULL,
    analyzable_type character varying(255) NOT NULL,
    analyzable_id bigint NOT NULL,
    project_id bigint,
    prompt_template_id bigint,
    provider_context_hash character varying(64) NOT NULL,
    dispatch_key character varying(191) NOT NULL,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    attempts smallint DEFAULT '0'::smallint NOT NULL,
    last_error_code character varying(255),
    error_message text,
    last_attempt_at timestamp(0) without time zone,
    next_retry_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    meta_json json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    failure_category character varying(64),
    last_failed_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: ai_analysis_dispatch_states_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_analysis_dispatch_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_analysis_dispatch_states_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_analysis_dispatch_states_id_seq OWNED BY public.ai_analysis_dispatch_states.id;


--
-- Name: ai_analysis_results; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_analysis_results (
    id bigint NOT NULL,
    article_id bigint,
    social_media_item_id bigint,
    summary text NOT NULL,
    sentiment character varying(255) NOT NULL,
    sentiment_score double precision NOT NULL,
    main_issue character varying(255) NOT NULL,
    entities text,
    risk_level character varying(255) NOT NULL,
    risk_reason text,
    reach_estimate integer DEFAULT 0 NOT NULL,
    reach_score_10 integer DEFAULT 1 NOT NULL,
    reach_score_max integer DEFAULT 10 NOT NULL,
    reach_level character varying(255) NOT NULL,
    reach_trend character varying(255) NOT NULL,
    reach_source character varying(255) NOT NULL,
    reach_confidence character varying(255) NOT NULL,
    reach_reason text NOT NULL,
    recommendation text NOT NULL,
    raw_response text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    local_relevance_score integer,
    estimated_reach_band character varying(255),
    confidence_score integer,
    confidence_level character varying(255),
    signals_used text,
    reasoning_summary text,
    limitations text,
    is_exact_reach boolean DEFAULT false NOT NULL,
    reach_method character varying(255),
    potential_reach_score integer,
    potential_reach_level character varying(255),
    potential_reach_band character varying(255),
    project_reach_score integer,
    project_reach_level character varying(255),
    project_reach_band character varying(255),
    analysis_status character varying(255) DEFAULT 'success'::character varying NOT NULL,
    validation_errors text,
    potential_estimated_readers integer,
    project_estimated_readers integer
);


--
-- Name: ai_analysis_results_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_analysis_results_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_analysis_results_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_analysis_results_id_seq OWNED BY public.ai_analysis_results.id;


--
-- Name: ai_prompt_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_prompt_templates (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    source_type character varying(255) NOT NULL,
    system_prompt text NOT NULL,
    user_prompt_template text NOT NULL,
    output_schema text,
    is_active boolean DEFAULT true NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: ai_prompt_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_prompt_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_prompt_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_prompt_templates_id_seq OWNED BY public.ai_prompt_templates.id;


--
-- Name: ai_providers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ai_providers (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    provider_type character varying(255) NOT NULL,
    base_url character varying(255),
    api_key text,
    model_name character varying(255) NOT NULL,
    temperature numeric(3,2) DEFAULT 0.7 NOT NULL,
    max_tokens integer DEFAULT 2048 NOT NULL,
    custom_headers text,
    custom_body_template text,
    is_active boolean DEFAULT true NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    last_tested_at timestamp(0) without time zone,
    last_test_status character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_error text,
    requests_per_minute integer DEFAULT 60 NOT NULL,
    priority integer DEFAULT 10 NOT NULL,
    cooldown_until timestamp(0) without time zone,
    last_failure_code character varying(255),
    capabilities json
);


--
-- Name: ai_providers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ai_providers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ai_providers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ai_providers_id_seq OWNED BY public.ai_providers.id;


--
-- Name: apify_actors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.apify_actors (
    id bigint NOT NULL,
    platform character varying(255) NOT NULL,
    actor_name character varying(255) NOT NULL,
    actor_slug character varying(255) NOT NULL,
    function_type character varying(255) NOT NULL,
    default_keyword character varying(255),
    default_limit integer DEFAULT 20 NOT NULL,
    date_from date,
    date_to date,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    last_run_at timestamp(0) without time zone,
    last_run_status character varying(255),
    last_run_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    keyword_field_mapping character varying(255) DEFAULT 'search'::character varying NOT NULL,
    output_mapping text,
    interval_minutes integer DEFAULT 240 NOT NULL,
    memory_limit integer DEFAULT 1024 NOT NULL,
    range_mode character varying(255) DEFAULT '7d'::character varying NOT NULL,
    priority integer DEFAULT 1 NOT NULL,
    maximum_cost_per_run_usd numeric(8,4) DEFAULT '0'::numeric NOT NULL,
    build character varying(255) DEFAULT 'latest'::character varying NOT NULL,
    timeout_seconds integer DEFAULT 10000 NOT NULL,
    no_timeout boolean DEFAULT false NOT NULL
);


--
-- Name: apify_actors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.apify_actors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: apify_actors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.apify_actors_id_seq OWNED BY public.apify_actors.id;


--
-- Name: apify_dispatch_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.apify_dispatch_states (
    id bigint NOT NULL,
    dispatch_key character varying(255) NOT NULL,
    project_id bigint NOT NULL,
    actor_id bigint NOT NULL,
    platform character varying(255) NOT NULL,
    keyword character varying(255) NOT NULL,
    normalized_keyword character varying(255) NOT NULL,
    window_start timestamp(0) without time zone,
    window_end timestamp(0) without time zone,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    run_id character varying(255),
    attempts integer DEFAULT 0 NOT NULL,
    queued_at timestamp(0) without time zone,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    next_retry_at timestamp(0) without time zone,
    last_error_code character varying(255),
    last_error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    actual_cost_usd numeric(10,6),
    items_collected integer,
    run_duration_secs integer,
    CONSTRAINT apify_dispatch_states_status_check CHECK (((status)::text = ANY (ARRAY[('queued'::character varying)::text, ('processing'::character varying)::text, ('success'::character varying)::text, ('failed'::character varying)::text, ('retry_wait'::character varying)::text, ('cancelled'::character varying)::text])))
);


--
-- Name: COLUMN apify_dispatch_states.actual_cost_usd; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.apify_dispatch_states.actual_cost_usd IS 'Biaya aktual run dari Apify API (usageTotalCostUsd)';


--
-- Name: COLUMN apify_dispatch_states.items_collected; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.apify_dispatch_states.items_collected IS 'Jumlah item yang berhasil dikumpulkan dari dataset';


--
-- Name: COLUMN apify_dispatch_states.run_duration_secs; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.apify_dispatch_states.run_duration_secs IS 'Durasi run dalam detik dari Apify API stats';


--
-- Name: apify_dispatch_states_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.apify_dispatch_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: apify_dispatch_states_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.apify_dispatch_states_id_seq OWNED BY public.apify_dispatch_states.id;


--
-- Name: apify_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.apify_settings (
    id bigint NOT NULL,
    api_token text,
    connection_status character varying(255) DEFAULT 'belum_dicek'::character varying NOT NULL,
    last_test_status character varying(255),
    last_test_dataset_id character varying(255),
    last_test_message text,
    last_test_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    api_token_backup_1 text,
    api_token_backup_2 text,
    api_token_backup_3 text,
    active_token_index integer DEFAULT 0 NOT NULL,
    connection_status_backup_1 character varying(255) DEFAULT 'belum_dicek'::character varying NOT NULL,
    connection_status_backup_2 character varying(255) DEFAULT 'belum_dicek'::character varying NOT NULL,
    connection_status_backup_3 character varying(255) DEFAULT 'belum_dicek'::character varying NOT NULL
);


--
-- Name: apify_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.apify_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: apify_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.apify_settings_id_seq OWNED BY public.apify_settings.id;


--
-- Name: articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.articles (
    id bigint NOT NULL,
    project_id bigint,
    title character varying(255) NOT NULL,
    content text NOT NULL,
    url text,
    source_name character varying(255) NOT NULL,
    sentiment character varying(255) DEFAULT 'neutral'::character varying NOT NULL,
    sentiment_score double precision DEFAULT '0'::double precision NOT NULL,
    category character varying(255) DEFAULT 'General'::character varying NOT NULL,
    published_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    canonical_url text,
    author character varying(255),
    excerpt text,
    summary text
);


--
-- Name: articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.articles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.articles_id_seq OWNED BY public.articles.id;


--
-- Name: branding_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branding_settings (
    id bigint NOT NULL,
    app_name character varying(255) DEFAULT 'ARUSBAWAH'::character varying NOT NULL,
    app_logo_path character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: branding_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.branding_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: branding_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.branding_settings_id_seq OWNED BY public.branding_settings.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: candidate_links; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.candidate_links (
    id bigint NOT NULL,
    url text NOT NULL,
    canonical_url text NOT NULL,
    source_type character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'candidate'::character varying NOT NULL,
    project_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: candidate_links_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.candidate_links_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: candidate_links_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.candidate_links_id_seq OWNED BY public.candidate_links.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: global_keywords; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.global_keywords (
    id bigint NOT NULL,
    keyword character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: global_keywords_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.global_keywords_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: global_keywords_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.global_keywords_id_seq OWNED BY public.global_keywords.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
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


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: news_source_suggestions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.news_source_suggestions (
    id bigint NOT NULL,
    news_source_id bigint,
    suggested_by character varying(255) DEFAULT 'ai'::character varying NOT NULL,
    source_name character varying(255),
    domain character varying(255),
    base_url character varying(255),
    search_url character varying(255),
    feed_url character varying(255),
    sitemap_url character varying(255),
    search_result_selector character varying(255),
    article_link_selector character varying(255),
    article_content_selector character varying(255),
    confidence double precision,
    ai_reason text,
    status character varying(255) DEFAULT 'draft_ai'::character varying NOT NULL,
    test_result_json json,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    article_author_selector character varying(255),
    article_date_selector character varying(255),
    article_noise_selector character varying(255),
    crawling_type character varying(255)
);


--
-- Name: news_source_suggestions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.news_source_suggestions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: news_source_suggestions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.news_source_suggestions_id_seq OWNED BY public.news_source_suggestions.id;


--
-- Name: news_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.news_sources (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    domain character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    crawling_type character varying(255) DEFAULT 'html'::character varying NOT NULL,
    selector character varying(255),
    timeout_seconds integer,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    base_url character varying(255),
    feed_url character varying(255),
    search_url character varying(255),
    sitemap_url character varying(255),
    search_result_selector character varying(255),
    article_link_selector character varying(255),
    article_content_selector character varying(255),
    is_search_enabled boolean DEFAULT false NOT NULL,
    is_feed_enabled boolean DEFAULT false NOT NULL,
    is_sitemap_enabled boolean DEFAULT false NOT NULL,
    article_author_selector character varying(255),
    article_date_selector character varying(255),
    article_noise_selector character varying(255),
    source_type character varying(255),
    media_scope character varying(255),
    dewan_pers_status character varying(255),
    local_reach_weight numeric(4,1),
    scrape_priority integer,
    reach_notes text,
    deleted_at timestamp(0) without time zone,
    icon_url character varying(512),
    path_blocklist text,
    selector_blocklist text
);


--
-- Name: news_sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.news_sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: news_sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.news_sources_id_seq OWNED BY public.news_sources.id;


--
-- Name: package_actors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.package_actors (
    id bigint NOT NULL,
    package_id bigint NOT NULL,
    apify_actor_id bigint NOT NULL,
    is_enabled boolean DEFAULT true NOT NULL,
    cost_per_run_usd numeric(8,4),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    default_limit integer,
    memory_limit integer
);


--
-- Name: COLUMN package_actors.cost_per_run_usd; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.package_actors.cost_per_run_usd IS 'Override biaya per run; null = pakai nilai global actor';


--
-- Name: COLUMN package_actors.default_limit; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.package_actors.default_limit IS 'Override limit default; null = pakai default actor';


--
-- Name: COLUMN package_actors.memory_limit; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.package_actors.memory_limit IS 'Override memory limit (MB); null = pakai default actor';


--
-- Name: package_actors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.package_actors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: package_actors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.package_actors_id_seq OWNED BY public.package_actors.id;


--
-- Name: packages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.packages (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    use_portal boolean DEFAULT true NOT NULL,
    price numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    social_media_features text,
    news_portal_features text,
    advantages text,
    is_popular boolean DEFAULT false NOT NULL,
    news_interval_minutes integer DEFAULT 5 NOT NULL,
    social_interval_minutes integer DEFAULT 10 NOT NULL
);


--
-- Name: packages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.packages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: packages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.packages_id_seq OWNED BY public.packages.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: project_articles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project_articles (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    article_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    rescrape_status character varying(255),
    rescrape_reason text,
    rescrape_requested_at timestamp(0) without time zone,
    rescrape_source character varying(255),
    rescrape_meta json
);


--
-- Name: project_articles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.project_articles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: project_articles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.project_articles_id_seq OWNED BY public.project_articles.id;


--
-- Name: project_social_media_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project_social_media_items (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    social_media_item_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: project_social_media_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.project_social_media_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: project_social_media_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.project_social_media_items_id_seq OWNED BY public.project_social_media_items.id;


--
-- Name: project_telegram_recipients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project_telegram_recipients (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    chat_id character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: project_telegram_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.project_telegram_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: project_telegram_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.project_telegram_recipients_id_seq OWNED BY public.project_telegram_recipients.id;


--
-- Name: project_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.project_user (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: project_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.project_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: project_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.project_user_id_seq OWNED BY public.project_user.id;


--
-- Name: projects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.projects (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    topics json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    first_news_scrape_attempt_at timestamp(0) without time zone,
    ai_insight_summary text,
    ai_insight_recommendations json,
    ai_insight_updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    context_keywords json,
    exclude_keywords json,
    sources json,
    ai_insight_viral_summary text,
    package_id bigint,
    news_last_scraped_at timestamp(0) without time zone
);


--
-- Name: projects_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.projects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: projects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.projects_id_seq OWNED BY public.projects.id;


--
-- Name: reach_assessments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reach_assessments (
    id bigint NOT NULL,
    project_id bigint NOT NULL,
    assessable_type character varying(255) NOT NULL,
    assessable_id bigint NOT NULL,
    method character varying(255) NOT NULL,
    score_version character varying(20) NOT NULL,
    audience_capacity_score numeric(5,2) NOT NULL,
    observed_consumption_score numeric(5,2),
    interaction_score numeric(5,2) NOT NULL,
    diffusion_score numeric(5,2) NOT NULL,
    media_context_score numeric(5,2) NOT NULL,
    potential_hybrid_score numeric(5,2) NOT NULL,
    potential_reach_score integer NOT NULL,
    potential_reach_level character varying(255) NOT NULL,
    local_relevance_score numeric(5,2) NOT NULL,
    relevance_status character varying(255) NOT NULL,
    adjusted_local_hybrid_score numeric(5,2) NOT NULL,
    adjusted_local_reach_score integer NOT NULL,
    adjusted_local_reach_level character varying(255) NOT NULL,
    confidence_score integer NOT NULL,
    confidence_level character varying(255) NOT NULL,
    is_exact_reach boolean DEFAULT false NOT NULL,
    signals_json json,
    explanation text,
    calculated_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: reach_assessments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reach_assessments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reach_assessments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reach_assessments_id_seq OWNED BY public.reach_assessments.id;


--
-- Name: risk_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.risk_notifications (
    id bigint NOT NULL,
    ai_analysis_result_id bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: risk_notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.risk_notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: risk_notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.risk_notifications_id_seq OWNED BY public.risk_notifications.id;


--
-- Name: scraping_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scraping_items (
    id bigint NOT NULL,
    candidate_link_id bigint NOT NULL,
    url text NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    retry_count integer DEFAULT 0 NOT NULL,
    last_attempt_at timestamp(0) without time zone,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: scraping_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scraping_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scraping_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scraping_items_id_seq OWNED BY public.scraping_items.id;


--
-- Name: scraping_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scraping_settings (
    id bigint NOT NULL,
    google_news_interval integer DEFAULT 60 NOT NULL,
    portal_crawling_interval integer DEFAULT 120 NOT NULL,
    limit_per_run integer DEFAULT 50 NOT NULL,
    date_range character varying(255) DEFAULT '7d'::character varying NOT NULL,
    timeout_seconds integer DEFAULT 30 NOT NULL,
    retry_limit integer DEFAULT 3 NOT NULL,
    retry_delay_minutes integer DEFAULT 10 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    enable_realtime boolean DEFAULT false NOT NULL
);


--
-- Name: scraping_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scraping_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scraping_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scraping_settings_id_seq OWNED BY public.scraping_settings.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: social_media_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.social_media_comments (
    id bigint NOT NULL,
    social_media_item_id bigint NOT NULL,
    platform character varying(50) NOT NULL,
    comment_id character varying(255) NOT NULL,
    parent_comment_id character varying(255),
    author_name character varying(255),
    author_url text,
    avatar_url text,
    content text,
    like_count integer DEFAULT 0 NOT NULL,
    posted_at timestamp(0) without time zone,
    raw_json text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: social_media_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.social_media_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: social_media_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.social_media_comments_id_seq OWNED BY public.social_media_comments.id;


--
-- Name: social_media_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.social_media_items (
    id bigint NOT NULL,
    project_id bigint,
    platform character varying(255) NOT NULL,
    post_url text NOT NULL,
    author_name character varying(255) NOT NULL,
    author_url text,
    content text NOT NULL,
    posted_at timestamp(0) without time zone,
    like_count integer DEFAULT 0 NOT NULL,
    comment_count integer DEFAULT 0 NOT NULL,
    share_count integer DEFAULT 0 NOT NULL,
    view_count integer DEFAULT 0 NOT NULL,
    follower_count integer DEFAULT 0 NOT NULL,
    raw_json text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    comments_checked boolean DEFAULT false NOT NULL
);


--
-- Name: social_media_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.social_media_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: social_media_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.social_media_items_id_seq OWNED BY public.social_media_items.id;


--
-- Name: telegram_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telegram_settings (
    id bigint NOT NULL,
    bot_token text,
    default_chat_id character varying(255),
    is_active boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: telegram_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.telegram_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: telegram_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.telegram_settings_id_seq OWNED BY public.telegram_settings.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    role character varying(255) DEFAULT 'user'::character varying NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: ai_analysis_dispatch_states id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_dispatch_states ALTER COLUMN id SET DEFAULT nextval('public.ai_analysis_dispatch_states_id_seq'::regclass);


--
-- Name: ai_analysis_results id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_results ALTER COLUMN id SET DEFAULT nextval('public.ai_analysis_results_id_seq'::regclass);


--
-- Name: ai_prompt_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_prompt_templates ALTER COLUMN id SET DEFAULT nextval('public.ai_prompt_templates_id_seq'::regclass);


--
-- Name: ai_providers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_providers ALTER COLUMN id SET DEFAULT nextval('public.ai_providers_id_seq'::regclass);


--
-- Name: apify_actors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_actors ALTER COLUMN id SET DEFAULT nextval('public.apify_actors_id_seq'::regclass);


--
-- Name: apify_dispatch_states id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_dispatch_states ALTER COLUMN id SET DEFAULT nextval('public.apify_dispatch_states_id_seq'::regclass);


--
-- Name: apify_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_settings ALTER COLUMN id SET DEFAULT nextval('public.apify_settings_id_seq'::regclass);


--
-- Name: articles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles ALTER COLUMN id SET DEFAULT nextval('public.articles_id_seq'::regclass);


--
-- Name: branding_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branding_settings ALTER COLUMN id SET DEFAULT nextval('public.branding_settings_id_seq'::regclass);


--
-- Name: candidate_links id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_links ALTER COLUMN id SET DEFAULT nextval('public.candidate_links_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: global_keywords id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_keywords ALTER COLUMN id SET DEFAULT nextval('public.global_keywords_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: news_source_suggestions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_source_suggestions ALTER COLUMN id SET DEFAULT nextval('public.news_source_suggestions_id_seq'::regclass);


--
-- Name: news_sources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_sources ALTER COLUMN id SET DEFAULT nextval('public.news_sources_id_seq'::regclass);


--
-- Name: package_actors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.package_actors ALTER COLUMN id SET DEFAULT nextval('public.package_actors_id_seq'::regclass);


--
-- Name: packages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.packages ALTER COLUMN id SET DEFAULT nextval('public.packages_id_seq'::regclass);


--
-- Name: project_articles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_articles ALTER COLUMN id SET DEFAULT nextval('public.project_articles_id_seq'::regclass);


--
-- Name: project_social_media_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_social_media_items ALTER COLUMN id SET DEFAULT nextval('public.project_social_media_items_id_seq'::regclass);


--
-- Name: project_telegram_recipients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_telegram_recipients ALTER COLUMN id SET DEFAULT nextval('public.project_telegram_recipients_id_seq'::regclass);


--
-- Name: project_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_user ALTER COLUMN id SET DEFAULT nextval('public.project_user_id_seq'::regclass);


--
-- Name: projects id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects ALTER COLUMN id SET DEFAULT nextval('public.projects_id_seq'::regclass);


--
-- Name: reach_assessments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reach_assessments ALTER COLUMN id SET DEFAULT nextval('public.reach_assessments_id_seq'::regclass);


--
-- Name: risk_notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.risk_notifications ALTER COLUMN id SET DEFAULT nextval('public.risk_notifications_id_seq'::regclass);


--
-- Name: scraping_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraping_items ALTER COLUMN id SET DEFAULT nextval('public.scraping_items_id_seq'::regclass);


--
-- Name: scraping_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraping_settings ALTER COLUMN id SET DEFAULT nextval('public.scraping_settings_id_seq'::regclass);


--
-- Name: social_media_comments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_comments ALTER COLUMN id SET DEFAULT nextval('public.social_media_comments_id_seq'::regclass);


--
-- Name: social_media_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_items ALTER COLUMN id SET DEFAULT nextval('public.social_media_items_id_seq'::regclass);


--
-- Name: telegram_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_settings ALTER COLUMN id SET DEFAULT nextval('public.telegram_settings_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: ai_analysis_dispatch_states ai_analysis_dispatch_states_dispatch_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_dispatch_states
    ADD CONSTRAINT ai_analysis_dispatch_states_dispatch_key_unique UNIQUE (dispatch_key);


--
-- Name: ai_analysis_dispatch_states ai_analysis_dispatch_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_dispatch_states
    ADD CONSTRAINT ai_analysis_dispatch_states_pkey PRIMARY KEY (id);


--
-- Name: ai_analysis_results ai_analysis_results_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_results
    ADD CONSTRAINT ai_analysis_results_pkey PRIMARY KEY (id);


--
-- Name: ai_prompt_templates ai_prompt_templates_name_source_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_prompt_templates
    ADD CONSTRAINT ai_prompt_templates_name_source_type_unique UNIQUE (name, source_type);


--
-- Name: ai_prompt_templates ai_prompt_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_prompt_templates
    ADD CONSTRAINT ai_prompt_templates_pkey PRIMARY KEY (id);


--
-- Name: ai_providers ai_providers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_providers
    ADD CONSTRAINT ai_providers_pkey PRIMARY KEY (id);


--
-- Name: apify_actors apify_actors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_actors
    ADD CONSTRAINT apify_actors_pkey PRIMARY KEY (id);


--
-- Name: apify_dispatch_states apify_dispatch_states_dispatch_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_dispatch_states
    ADD CONSTRAINT apify_dispatch_states_dispatch_key_unique UNIQUE (dispatch_key);


--
-- Name: apify_dispatch_states apify_dispatch_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_dispatch_states
    ADD CONSTRAINT apify_dispatch_states_pkey PRIMARY KEY (id);


--
-- Name: apify_settings apify_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.apify_settings
    ADD CONSTRAINT apify_settings_pkey PRIMARY KEY (id);


--
-- Name: articles articles_canonical_url_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_canonical_url_unique UNIQUE (canonical_url);


--
-- Name: articles articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.articles
    ADD CONSTRAINT articles_pkey PRIMARY KEY (id);


--
-- Name: branding_settings branding_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branding_settings
    ADD CONSTRAINT branding_settings_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: candidate_links candidate_links_canonical_url_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_links
    ADD CONSTRAINT candidate_links_canonical_url_unique UNIQUE (canonical_url);


--
-- Name: candidate_links candidate_links_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_links
    ADD CONSTRAINT candidate_links_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: global_keywords global_keywords_keyword_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_keywords
    ADD CONSTRAINT global_keywords_keyword_unique UNIQUE (keyword);


--
-- Name: global_keywords global_keywords_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.global_keywords
    ADD CONSTRAINT global_keywords_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: news_source_suggestions news_source_suggestions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_source_suggestions
    ADD CONSTRAINT news_source_suggestions_pkey PRIMARY KEY (id);


--
-- Name: news_sources news_sources_domain_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_sources
    ADD CONSTRAINT news_sources_domain_unique UNIQUE (domain);


--
-- Name: news_sources news_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_sources
    ADD CONSTRAINT news_sources_pkey PRIMARY KEY (id);


--
-- Name: package_actors package_actors_package_id_apify_actor_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.package_actors
    ADD CONSTRAINT package_actors_package_id_apify_actor_id_unique UNIQUE (package_id, apify_actor_id);


--
-- Name: package_actors package_actors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.package_actors
    ADD CONSTRAINT package_actors_pkey PRIMARY KEY (id);


--
-- Name: packages packages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.packages
    ADD CONSTRAINT packages_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: project_articles project_articles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_articles
    ADD CONSTRAINT project_articles_pkey PRIMARY KEY (id);


--
-- Name: project_articles project_articles_project_id_article_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_articles
    ADD CONSTRAINT project_articles_project_id_article_id_unique UNIQUE (project_id, article_id);


--
-- Name: project_social_media_items project_social_media_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_social_media_items
    ADD CONSTRAINT project_social_media_items_pkey PRIMARY KEY (id);


--
-- Name: project_social_media_items project_social_media_items_project_id_social_media_item_id_uniq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_social_media_items
    ADD CONSTRAINT project_social_media_items_project_id_social_media_item_id_uniq UNIQUE (project_id, social_media_item_id);


--
-- Name: project_telegram_recipients project_telegram_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_telegram_recipients
    ADD CONSTRAINT project_telegram_recipients_pkey PRIMARY KEY (id);


--
-- Name: project_telegram_recipients project_telegram_recipients_project_id_chat_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_telegram_recipients
    ADD CONSTRAINT project_telegram_recipients_project_id_chat_id_unique UNIQUE (project_id, chat_id);


--
-- Name: project_user project_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_user
    ADD CONSTRAINT project_user_pkey PRIMARY KEY (id);


--
-- Name: project_user project_user_project_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_user
    ADD CONSTRAINT project_user_project_id_user_id_unique UNIQUE (project_id, user_id);


--
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (id);


--
-- Name: reach_assessments reach_assessments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reach_assessments
    ADD CONSTRAINT reach_assessments_pkey PRIMARY KEY (id);


--
-- Name: reach_assessments reach_assessments_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reach_assessments
    ADD CONSTRAINT reach_assessments_unique UNIQUE (project_id, assessable_type, assessable_id, method, score_version);


--
-- Name: risk_notifications risk_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.risk_notifications
    ADD CONSTRAINT risk_notifications_pkey PRIMARY KEY (id);


--
-- Name: scraping_items scraping_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraping_items
    ADD CONSTRAINT scraping_items_pkey PRIMARY KEY (id);


--
-- Name: scraping_settings scraping_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraping_settings
    ADD CONSTRAINT scraping_settings_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: social_media_comments social_media_comments_item_comment_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_comments
    ADD CONSTRAINT social_media_comments_item_comment_unique UNIQUE (social_media_item_id, comment_id);


--
-- Name: social_media_comments social_media_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_comments
    ADD CONSTRAINT social_media_comments_pkey PRIMARY KEY (id);


--
-- Name: social_media_items social_media_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_items
    ADD CONSTRAINT social_media_items_pkey PRIMARY KEY (id);


--
-- Name: social_media_items social_media_items_post_url_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_items
    ADD CONSTRAINT social_media_items_post_url_unique UNIQUE (post_url);


--
-- Name: telegram_settings telegram_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telegram_settings
    ADD CONSTRAINT telegram_settings_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: ai_analysis_results_article_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_analysis_results_article_id_index ON public.ai_analysis_results USING btree (article_id);


--
-- Name: ai_analysis_results_social_media_item_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_analysis_results_social_media_item_id_index ON public.ai_analysis_results USING btree (social_media_item_id);


--
-- Name: ai_dispatch_state_analyzable_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_dispatch_state_analyzable_idx ON public.ai_analysis_dispatch_states USING btree (project_id, analyzable_type, analyzable_id);


--
-- Name: ai_dispatch_state_failure_category_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_dispatch_state_failure_category_idx ON public.ai_analysis_dispatch_states USING btree (failure_category, status);


--
-- Name: ai_dispatch_state_last_failed_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_dispatch_state_last_failed_idx ON public.ai_analysis_dispatch_states USING btree (status, last_failed_at);


--
-- Name: ai_dispatch_state_project_template_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_dispatch_state_project_template_idx ON public.ai_analysis_dispatch_states USING btree (project_id, prompt_template_id);


--
-- Name: ai_dispatch_state_retry_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ai_dispatch_state_retry_idx ON public.ai_analysis_dispatch_states USING btree (status, next_retry_at);


--
-- Name: apify_dispatch_states_actor_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_actor_id_index ON public.apify_dispatch_states USING btree (actor_id);


--
-- Name: apify_dispatch_states_keyword_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_keyword_index ON public.apify_dispatch_states USING btree (keyword);


--
-- Name: apify_dispatch_states_normalized_keyword_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_normalized_keyword_index ON public.apify_dispatch_states USING btree (normalized_keyword);


--
-- Name: apify_dispatch_states_platform_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_platform_index ON public.apify_dispatch_states USING btree (platform);


--
-- Name: apify_dispatch_states_project_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_project_id_index ON public.apify_dispatch_states USING btree (project_id);


--
-- Name: apify_dispatch_states_run_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_run_id_index ON public.apify_dispatch_states USING btree (run_id);


--
-- Name: apify_dispatch_states_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_status_index ON public.apify_dispatch_states USING btree (status);


--
-- Name: apify_dispatch_states_window_end_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_window_end_index ON public.apify_dispatch_states USING btree (window_end);


--
-- Name: apify_dispatch_states_window_start_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX apify_dispatch_states_window_start_index ON public.apify_dispatch_states USING btree (window_start);


--
-- Name: articles_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX articles_published_at_index ON public.articles USING btree (published_at);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: project_articles_article_rescrape_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_articles_article_rescrape_idx ON public.project_articles USING btree (article_id, rescrape_status);


--
-- Name: project_articles_rescrape_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX project_articles_rescrape_status_idx ON public.project_articles USING btree (project_id, rescrape_status);


--
-- Name: projects_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX projects_is_active_index ON public.projects USING btree (is_active);


--
-- Name: reach_assessments_assessable_type_assessable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reach_assessments_assessable_type_assessable_id_index ON public.reach_assessments USING btree (assessable_type, assessable_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: social_media_comments_platform_posted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX social_media_comments_platform_posted_at_index ON public.social_media_comments USING btree (platform, posted_at);


--
-- Name: social_media_items_posted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX social_media_items_posted_at_index ON public.social_media_items USING btree (posted_at);


--
-- Name: ai_analysis_dispatch_states ai_analysis_dispatch_states_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_dispatch_states
    ADD CONSTRAINT ai_analysis_dispatch_states_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE SET NULL;


--
-- Name: ai_analysis_dispatch_states ai_analysis_dispatch_states_prompt_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_dispatch_states
    ADD CONSTRAINT ai_analysis_dispatch_states_prompt_template_id_foreign FOREIGN KEY (prompt_template_id) REFERENCES public.ai_prompt_templates(id) ON DELETE SET NULL;


--
-- Name: ai_analysis_results ai_analysis_results_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_results
    ADD CONSTRAINT ai_analysis_results_article_id_foreign FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: ai_analysis_results ai_analysis_results_social_media_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ai_analysis_results
    ADD CONSTRAINT ai_analysis_results_social_media_item_id_foreign FOREIGN KEY (social_media_item_id) REFERENCES public.social_media_items(id) ON DELETE CASCADE;


--
-- Name: candidate_links candidate_links_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.candidate_links
    ADD CONSTRAINT candidate_links_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: news_source_suggestions news_source_suggestions_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_source_suggestions
    ADD CONSTRAINT news_source_suggestions_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: news_source_suggestions news_source_suggestions_news_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.news_source_suggestions
    ADD CONSTRAINT news_source_suggestions_news_source_id_foreign FOREIGN KEY (news_source_id) REFERENCES public.news_sources(id) ON DELETE SET NULL;


--
-- Name: package_actors package_actors_apify_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.package_actors
    ADD CONSTRAINT package_actors_apify_actor_id_foreign FOREIGN KEY (apify_actor_id) REFERENCES public.apify_actors(id) ON DELETE CASCADE;


--
-- Name: package_actors package_actors_package_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.package_actors
    ADD CONSTRAINT package_actors_package_id_foreign FOREIGN KEY (package_id) REFERENCES public.packages(id) ON DELETE CASCADE;


--
-- Name: project_articles project_articles_article_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_articles
    ADD CONSTRAINT project_articles_article_id_foreign FOREIGN KEY (article_id) REFERENCES public.articles(id) ON DELETE CASCADE;


--
-- Name: project_articles project_articles_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_articles
    ADD CONSTRAINT project_articles_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: project_social_media_items project_social_media_items_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_social_media_items
    ADD CONSTRAINT project_social_media_items_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: project_social_media_items project_social_media_items_social_media_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_social_media_items
    ADD CONSTRAINT project_social_media_items_social_media_item_id_foreign FOREIGN KEY (social_media_item_id) REFERENCES public.social_media_items(id) ON DELETE CASCADE;


--
-- Name: project_telegram_recipients project_telegram_recipients_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_telegram_recipients
    ADD CONSTRAINT project_telegram_recipients_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: project_user project_user_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_user
    ADD CONSTRAINT project_user_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: project_user project_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.project_user
    ADD CONSTRAINT project_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: projects projects_package_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_package_id_foreign FOREIGN KEY (package_id) REFERENCES public.packages(id) ON DELETE SET NULL;


--
-- Name: reach_assessments reach_assessments_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reach_assessments
    ADD CONSTRAINT reach_assessments_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: risk_notifications risk_notifications_ai_analysis_result_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.risk_notifications
    ADD CONSTRAINT risk_notifications_ai_analysis_result_id_foreign FOREIGN KEY (ai_analysis_result_id) REFERENCES public.ai_analysis_results(id) ON DELETE CASCADE;


--
-- Name: scraping_items scraping_items_candidate_link_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scraping_items
    ADD CONSTRAINT scraping_items_candidate_link_id_foreign FOREIGN KEY (candidate_link_id) REFERENCES public.candidate_links(id) ON DELETE CASCADE;


--
-- Name: social_media_comments social_media_comments_social_media_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_media_comments
    ADD CONSTRAINT social_media_comments_social_media_item_id_foreign FOREIGN KEY (social_media_item_id) REFERENCES public.social_media_items(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict DLFH2j3RU1gfXyStUo73gDSFiduDBznxbwi9XbJx2ckG74EI1QrRexY2Sle8r77

--
-- PostgreSQL database dump
--

\restrict YUe7ukg0sXyJ68G7PAyxwMU3taRe26fxIuGIlsdVp4n4j4SgiyoDuR2Ts6NCc2k

-- Dumped from database version 16.14
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_06_30_040247_create_projects_table	1
5	2026_06_30_040300_create_articles_table	1
6	2026_06_30_061350_add_role_to_users_and_project_user_table	1
7	2026_06_30_061413_create_ai_providers_table	1
8	2026_06_30_160000_add_status_to_users_table	1
9	2026_06_30_170000_create_apify_settings_table	1
10	2026_06_30_170100_create_apify_actors_table	1
11	2026_06_30_183000_create_global_keywords_table	1
12	2026_06_30_183100_create_candidate_links_table	1
13	2026_06_30_183200_create_scraping_items_table	1
14	2026_06_30_183300_create_project_articles_table	1
15	2026_06_30_183500_create_social_media_items_table	1
16	2026_06_30_183600_create_ai_analysis_results_table	1
17	2026_06_30_183700_create_telegram_settings_table	1
18	2026_06_30_183800_create_risk_notifications_table	1
19	2026_06_30_183900_create_scraping_settings_table	1
20	2026_06_30_184000_create_news_sources_table	1
21	2026_06_30_184100_create_ai_prompt_templates_table	1
22	2026_06_30_184200_update_apify_actors_table	1
23	2026_06_30_184300_create_project_telegram_recipients_table	1
24	2026_06_30_184400_add_last_error_to_ai_providers_table	1
25	2026_07_01_103207_add_enable_realtime_to_scraping_settings_table	1
26	2026_07_01_144427_add_is_active_to_projects_table	1
27	2026_07_01_144428_rebuild_articles_table_without_project_foreign_key	1
28	2026_07_01_144429_rebuild_social_media_items_table_without_project_foreign_key	1
29	2026_07_01_144430_create_project_social_media_items_table	1
30	2026_07_01_144431_backfill_project_social_media_items_from_legacy_project_id	1
31	2026_07_01_163226_add_canonical_url_to_articles	1
32	2026_07_02_010000_alter_articles_url_columns_to_text	1
33	2026_07_02_030000_add_portal_metadata_to_news_sources_table	1
34	2026_07_02_040000_create_news_source_suggestions_table	1
35	2026_07_02_041236_add_author_and_date_selectors_to_crawler_tables	1
36	2026_07_02_041236_add_author_to_articles_table	1
37	2026_07_02_044033_add_noise_selector_to_crawling_tables	1
38	2026_07_02_131500_add_reach_metadata_to_news_sources_table	1
39	2026_07_02_144500_add_ai_reach_fields_to_ai_analysis_results_table	1
40	2026_07_02_150000_create_reach_assessments_table	1
41	2026_07_02_160000_add_potential_and_project_reach_fields_to_ai_analysis_results_table	1
42	2026_07_02_170000_add_ai_reach_validation_fields_to_ai_analysis_results_table	1
43	2026_07_02_192634_add_estimated_readers_fields_to_ai_analysis_results_table	1
44	2026_07_03_000000_create_ai_analysis_dispatch_states_table	1
45	2026_07_03_100000_add_first_news_scrape_attempt_at_to_projects_table	1
46	2026_07_04_030000_add_requests_per_minute_to_ai_providers_table	1
47	2026_07_07_155452_add_ai_insight_columns_to_projects_table	1
48	2026_07_07_184157_create_apify_dispatch_states_table	1
49	2026_07_08_000000_add_failure_category_to_ai_analysis_dispatch_states_table	1
50	2026_07_08_113341_add_failover_columns_to_ai_providers_table	1
51	2026_07_09_064731_add_soft_deletes_to_news_sources_table	1
52	2026_07_09_095106_add_soft_deletes_to_projects_table	1
53	2026_07_09_210000_add_deleted_at_to_ai_analysis_dispatch_states_table	1
54	2026_07_09_223000_add_rescrape_fields_to_project_articles_table	1
55	2026_07_10_000001_make_social_media_items_posted_at_nullable	2
56	2026_07_11_000001_add_icon_url_to_news_sources_table	3
57	2026_07_13_213000_add_maximum_cost_per_run_to_apify_actors_table	4
58	2026_07_15_021458_create_branding_settings_table	5
59	2026_07_17_110000_add_run_options_to_apify_actors_table	6
60	2026_07_17_120000_cleanup_unused_instagram_actor_keyword	6
61	2026_07_17_130000_update_tiktok_actor_slug_to_clockworks	6
62	2026_07_17_141500_normalize_tiktok_canonical_actor_fields	6
63	2026_07_17_160000_drop_unused_post_filter_and_cost_reference_from_apify_actors_table	6
64	2026_07_18_000000_remove_instagram_keyword_search_from_apify_actors	6
65	2026_07_18_000100_strip_tiktok_payload_options_from_apify_actors	6
66	2026_07_18_000200_delete_legacy_tiktok_actors_from_apify_actors	6
67	2026_07_18_000300_purge_legacy_tiktok_runtime_data	6
68	2026_07_18_000350_purge_tiktok_project_social_media_pivot	6
69	2026_07_18_010000_ensure_build_column_exists_on_apify_actors_table	6
70	2026_07_21_170000_add_context_and_exclude_keywords_to_projects_table	7
71	2026_07_21_180000_drop_dead_apify_actor_columns	8
72	2026_07_21_190000_add_excerpt_and_summary_to_articles_table	9
73	2026_07_24_000000_enforce_unique_ai_prompt_templates_name_source_type	10
74	2026_07_24_010000_add_crawling_type_to_news_source_suggestions_table	10
75	2026_07_24_000001_add_soft_deletes_to_ai_prompt_templates_table	11
76	2026_07_24_160500_add_blocklist_fields_to_news_sources_table	11
77	2026_07_24_231413_add_missing_indexes_for_performance_optimization	12
78	2026_07_27_023432_add_sources_to_projects_table	13
79	2026_07_29_000001_seed_report_ai_prompt_template	14
80	2026_07_28_000002_add_ai_insight_viral_summary_to_projects_table	15
81	2026_07_29_100000_create_packages_table	16
82	2026_07_29_100001_create_package_actors_table	16
83	2026_07_29_100002_add_package_id_to_projects_table	16
84	2026_07_29_110000_add_use_portal_to_packages_table	16
85	2026_07_29_230000_create_social_media_comments_table	17
86	2026_07_30_200000_add_comments_checked_to_social_media_items	18
87	2026_07_30_215237_add_premium_pricing_fields_to_packages_table	19
88	2026_07_31_035441_add_limit_and_memory_to_package_actors_table	20
89	2026_07_30_000001_sync_ai_prompt_templates_to_database_driven_prompts	21
90	2026_07_31_052329_add_is_popular_to_packages_table	22
91	2026_08_01_235642_add_cost_tracking_to_apify_dispatch_states	23
92	2026_08_02_212155_add_backup_tokens_to_apify_settings_table	24
93	2026_08_02_212632_add_backup_connections_to_apify_settings_table	25
94	2026_08_03_031226_add_interval_minutes_to_packages_table	26
95	2026_08_03_220932_move_social_media_mirror_data_to_own_table	27
96	2026_08_05_150600_add_news_last_scraped_at_to_projects_table	28
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 96, true);


--
-- PostgreSQL database dump complete
--

\unrestrict YUe7ukg0sXyJ68G7PAyxwMU3taRe26fxIuGIlsdVp4n4j4SgiyoDuR2Ts6NCc2k

