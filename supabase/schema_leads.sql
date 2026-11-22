-- Chatly chats in Supabase: one row per lead, one row per message.
--
-- Before, each company's leads (with every message inside) lived in one leads.json that was
-- rewritten on every new message and every tick. Here a change touches one row.
--
--  * chatly_leads.data      the lead exactly as the app knows it (without messages)
--  * chatly_messages.data   one message exactly as the app knows it
--  * rev                    grows on every change: the Sales Desk asks "what changed since
--                           rev N?" instead of downloading every lead every 5 seconds.
--  * deleted                deleted leads stay as a tiny marker so open desks drop them.
--
-- Only the server's key can use any of this (RLS on, functions revoked from everyone else).
-- Safe to run more than once.

create sequence if not exists public.chatly_rev_seq;

-- text -> bigint that never fails (bad values become 0); milliseconds become seconds.
create or replace function public.chatly_ts(v text) returns bigint
language plpgsql immutable as $$
declare n bigint;
begin
  if v is null or v = '' then return 0; end if;
  n := floor(v::numeric)::bigint;
  if n > 20000000000 then n := n / 1000; end if;
  return n;
exception when others then
  return 0;
end $$;

create or replace function public.chatly_num(v text) returns bigint
language plpgsql immutable as $$
begin
  if v is null or v = '' then return 0; end if;
  return floor(v::numeric)::bigint;
exception when others then
  return 0;
end $$;

-- ---------------------------------------------------------------- leads
create table if not exists public.chatly_leads (
  company_id      text    not null,
  id              text    not null,
  phone           text    not null default '',
  phone_key       text    not null default '',     -- last 9 digits (how Chatly matches numbers)
  owner_id        text    not null default '',
  owner_username  text    not null default '',
  lead_state      text    not null default '',
  last_message_at bigint  not null default 0,
  message_count   integer not null default 0,
  data            jsonb   not null default '{}'::jsonb,
  deleted         boolean not null default false,
  rev             bigint  not null default 0,
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now(),
  primary key (company_id, id)
);
create index if not exists chatly_leads_rev    on public.chatly_leads (company_id, rev);
create index if not exists chatly_leads_phone  on public.chatly_leads (company_id, phone_key);
create index if not exists chatly_leads_recent on public.chatly_leads (company_id, last_message_at desc) where not deleted;
create index if not exists chatly_leads_owner  on public.chatly_leads (company_id, owner_id) where not deleted;

-- Columns come from data on every write, and every write gets a new revision.
create or replace function public.chatly_leads_touch() returns trigger
language plpgsql as $$
begin
  new.data := coalesce(new.data, '{}'::jsonb) - 'messages' - 'messageCount' - 'summary' - '_fullHistory';
  new.phone := regexp_replace(coalesce(new.data->>'phone', new.phone, ''), '\D', '', 'g');
  new.phone_key := right(new.phone, 9);
  new.owner_id := coalesce(new.data->>'ownerId', new.data->>'assignedOwnerId', '');
  new.owner_username := lower(coalesce(new.data->>'ownerUsername', new.data->>'assignedOwnerUsername', ''));
  new.lead_state := coalesce(new.data->>'leadState', new.data->>'status', '');
  new.last_message_at := public.chatly_ts(new.data->>'lastMessageAt');
  -- a new lead may already have messages (a campaign send is recorded a moment before its lead)
  if tg_op = 'INSERT' then
    new.message_count := greatest(new.message_count,
      (select count(*)::int from public.chatly_messages m where m.company_id = new.company_id and m.phone_key = new.phone_key));
  end if;
  new.rev := nextval('public.chatly_rev_seq');
  new.updated_at := now();
  return new;
end $$;
drop trigger if exists chatly_leads_touch on public.chatly_leads;
create trigger chatly_leads_touch before insert or update on public.chatly_leads
  for each row execute function public.chatly_leads_touch();

-- ------------------------------------------------------------- messages
create table if not exists public.chatly_messages (
  company_id text   not null,
  msg_key    text   not null,       -- WhatsApp message id (else the app's id)
  phone_key  text   not null,       -- last 9 digits of the lead's number
  ts         bigint not null default 0,
  data       jsonb  not null,
  created_at timestamptz not null default now(),
  primary key (company_id, msg_key)
);
create index if not exists chatly_messages_chat on public.chatly_messages (company_id, phone_key, ts);
create index if not exists chatly_messages_key  on public.chatly_messages (msg_key);

alter table public.chatly_leads    enable row level security;
alter table public.chatly_messages enable row level security;
revoke all on public.chatly_leads, public.chatly_messages from anon, authenticated;

-- Ticks only move forward: sending < accepted < sent < delivered < read. "failed" replaces
-- anything that was not delivered yet.
create or replace function public.chatly_status_wins(old_s text, new_s text) returns boolean
language sql immutable as $$
  select case
    when new_s is null or new_s = '' or new_s = coalesce(old_s, '') then false
    when old_s is null or old_s = '' then true
    when new_s = 'failed' then coalesce(old_s, '') not in ('delivered', 'read', 'played')
    else (case new_s when 'sending' then 0 when 'accepted' then 1 when 'sent' then 2 when 'delivered' then 3 when 'read' then 4 when 'played' then 5 else 1 end)
       > (case old_s when 'sending' then 0 when 'accepted' then 1 when 'sent' then 2 when 'delivered' then 3 when 'read' then 4 when 'played' then 5 when 'failed' then 1 else 0 end)
  end
$$;

-- One tick update; the lead gets a new revision so open desks redraw the tick.
create or replace function public.chatly_apply_status(p_company text, p_key text, p_status text, p_error text default null)
returns void language sql set search_path = public as $$
  with upd as (
    update chatly_messages m
       set data = m.data || jsonb_build_object('deliveryStatus', p_status)
                 || case when p_error is not null then jsonb_build_object('deliveryError', p_error) else '{}'::jsonb end
     where (p_company is null or m.company_id = p_company) and m.msg_key = p_key
       and chatly_status_wins(m.data->>'deliveryStatus', p_status)
     returning m.company_id, m.phone_key
  )
  update chatly_leads l set updated_at = now()
    from upd where l.company_id = upd.company_id and l.phone_key = upd.phone_key and not l.deleted;
$$;

-- Many ticks at once: [{"key": "...", "status": "read", "error": null, "company": "COMP-004"|null}]
create or replace function public.chatly_set_statuses(p_items jsonb)
returns integer language plpgsql set search_path = public as $$
declare it jsonb; n integer := 0;
begin
  for it in select value from jsonb_array_elements(coalesce(p_items, '[]'::jsonb)) loop
    if coalesce(it->>'key', '') = '' then continue; end if;
    perform chatly_apply_status(nullif(it->>'company', ''), it->>'key', it->>'status', it->>'error');
    n := n + 1;
  end loop;
  return n;
end $$;

-- New messages: [{"phone": "923...", "msg": {...}, "touch": true}]
-- A new message is stored once (same id again = only its tick moves). With touch, the lead
-- gets what a new message means: newest time, unread +1 for a reply, fresh -> ongoing.
create or replace function public.chatly_add_messages(p_company text, p_items jsonb)
returns integer language plpgsql set search_path = public as $$
declare
  it jsonb; v_msg jsonb; v_pk text; v_key text; v_ts bigint; v_in boolean; n integer := 0;
begin
  for it in select value from jsonb_array_elements(coalesce(p_items, '[]'::jsonb)) loop
    v_msg := it->'msg';
    if v_msg is null or jsonb_typeof(v_msg) <> 'object' then continue; end if;
    v_pk := right(regexp_replace(coalesce(it->>'phone', ''), '\D', '', 'g'), 9);
    v_key := coalesce(nullif(v_msg->>'waMessageId', ''), nullif(v_msg->>'id', ''));
    if v_key is null or v_pk = '' then continue; end if;
    v_ts := chatly_ts(v_msg->>'timestamp');
    if v_ts = 0 then v_ts := extract(epoch from now())::bigint; end if;

    insert into chatly_messages (company_id, msg_key, phone_key, ts, data)
    values (p_company, v_key, v_pk, v_ts, v_msg)
    on conflict (company_id, msg_key) do nothing;

    if found then
      n := n + 1;
      if coalesce((it->>'touch')::boolean, true) then
        v_in := coalesce(v_msg->>'sender', '') = 'lead';
        update chatly_leads set
          message_count = message_count + 1,
          data = data
            || jsonb_build_object('lastMessageAt', greatest(chatly_ts(data->>'lastMessageAt'), v_ts),
                                  'lastActive', 'Just now',
                                  'status', case when v_in then 'unread' else 'replied' end)
            || case when v_in then jsonb_build_object('unreadCount', chatly_num(data->>'unreadCount') + 1) else '{}'::jsonb end
            || case when data->>'leadState' = 'fresh' then '{"leadState": "ongoing"}'::jsonb else '{}'::jsonb end
        where company_id = p_company and phone_key = v_pk and not deleted;
      else
        update chatly_leads set message_count = message_count + 1
         where company_id = p_company and phone_key = v_pk and not deleted;
      end if;
    elsif coalesce(v_msg->>'deliveryStatus', '') <> '' then
      perform chatly_apply_status(p_company, v_key, v_msg->>'deliveryStatus', v_msg->>'deliveryError');
    end if;
  end loop;
  return n;
end $$;

-- Save whole leads (as the app holds them). p_mode 'replace' overwrites, 'insert' keeps rows
-- that already exist (used by the one-time copy from the old files).
create or replace function public.chatly_upsert_leads(p_company text, p_leads jsonb, p_mode text default 'replace')
returns integer language plpgsql set search_path = public as $$
declare n integer;
begin
  if p_mode = 'insert' then
    insert into chatly_leads (company_id, id, data)
    select p_company, x.id, x.data from (
      select distinct on (e->>'id') e->>'id' as id, e as data
        from jsonb_array_elements(coalesce(p_leads, '[]'::jsonb)) with ordinality t(e, ord)
       where coalesce(e->>'id', '') <> ''
       order by e->>'id', ord desc) x
    on conflict (company_id, id) do nothing;
  else
    insert into chatly_leads (company_id, id, data)
    select p_company, x.id, x.data from (
      select distinct on (e->>'id') e->>'id' as id, e as data
        from jsonb_array_elements(coalesce(p_leads, '[]'::jsonb)) with ordinality t(e, ord)
       where coalesce(e->>'id', '') <> ''
       order by e->>'id', ord desc) x
    on conflict (company_id, id) do update set data = excluded.data, deleted = false;
  end if;
  get diagnostics n = row_count;
  return n;
end $$;

-- Change some fields of one lead (found by id, else by number). Returns the lead or null.
create or replace function public.chatly_patch_lead(p_company text, p_id text, p_phone text, p_patch jsonb)
returns jsonb language plpgsql set search_path = public as $$
declare v_id text; v_out jsonb;
begin
  select id into v_id from chatly_leads
   where company_id = p_company and not deleted
     and (id = coalesce(p_id, '') or (coalesce(p_phone, '') <> '' and phone_key = right(regexp_replace(p_phone, '\D', '', 'g'), 9)))
   order by (id = coalesce(p_id, '')) desc
   limit 1;
  if v_id is null then return null; end if;
  update chatly_leads set data = data || coalesce(p_patch, '{}'::jsonb)
   where company_id = p_company and id = v_id
  returning data || jsonb_build_object('messageCount', message_count) into v_out;
  return v_out;
end $$;

-- One lead by id or number (no messages), or null.
create or replace function public.chatly_find_lead(p_company text, p_id text, p_phone text)
returns jsonb language sql stable set search_path = public as $$
  select data || jsonb_build_object('messageCount', message_count)
    from chatly_leads
   where company_id = p_company and not deleted
     and (id = coalesce(p_id, '') or (coalesce(p_phone, '') <> '' and phone_key = right(regexp_replace(p_phone, '\D', '', 'g'), 9)))
   order by (id = coalesce(p_id, '')) desc
   limit 1
$$;

-- Leads for many numbers at once (the bridge's campaign batch). Matching is on the last 9 digits.
create or replace function public.chatly_find_leads(p_company text, p_phones text[])
returns jsonb language sql stable set search_path = public as $$
  select coalesce(jsonb_agg(data || jsonb_build_object('messageCount', message_count)), '[]'::jsonb)
    from chatly_leads
   where company_id = p_company and not deleted
     and phone_key = any(select right(regexp_replace(x, '\D', '', 'g'), 9) from unnest(coalesce(p_phones, '{}')) x)
$$;

-- Leads for the Sales Desk.
--  p_since = 0  : the p_limit most recent leads (first load)
--  p_since > 0  : only leads changed after revision p_since (deleted ones as {id, _deleted})
-- Each lead carries its last p_msgs messages and its message count. For closers the list is
-- narrowed to leads they may own; PHP applies the exact visibility rule afterwards.
create or replace function public.chatly_leads_page(p_company text, p_since bigint, p_limit integer, p_msgs integer,
                                                    p_admin boolean, p_owner text, p_username text)
returns jsonb language plpgsql stable set search_path = public as $$
declare v_rev bigint; v_rows jsonb; v_max bigint; v_count integer;
begin
  select rev into v_rev from chatly_leads where company_id = p_company order by rev desc limit 1;
  v_rev := coalesce(v_rev, 0);

  with picked as (
    select l.* from chatly_leads l
     where l.company_id = p_company
       and (case when p_since > 0 then l.rev > p_since and l.rev <= v_rev else not l.deleted end)
       and (p_admin or l.deleted
            or l.owner_id = coalesce(p_owner, '')
            or (coalesce(p_username, '') <> '' and l.owner_username = lower(p_username))
            or l.data->>'originalOwnerId' = coalesce(p_owner, '')
            or (l.owner_id = '' and coalesce(l.data->>'ownerName', '') <> ''))
     order by case when p_since > 0 then l.rev else 0 end asc, l.last_message_at desc
     limit greatest(1, least(coalesce(p_limit, 3000), 20000))
  )
  select coalesce(jsonb_agg(
           case when p.deleted then jsonb_build_object('id', p.id, 'phone', p.phone, '_deleted', true)
           else p.data || jsonb_build_object(
             'messageCount', p.message_count,
             'summary', true,
             'messages', coalesce((
               select jsonb_agg(m.data order by m.ts)
                 from (select mm.data, mm.ts from chatly_messages mm
                        where mm.company_id = p_company and mm.phone_key = p.phone_key
                        order by mm.ts desc limit greatest(0, coalesce(p_msgs, 12))) m), '[]'::jsonb))
           end), '[]'::jsonb),
         max(p.rev), count(*)
    into v_rows, v_max, v_count
    from picked p;

  return jsonb_build_object(
    'rev', case when p_since > 0 and v_count >= coalesce(p_limit, 3000) then v_max else v_rev end,
    'leads', v_rows,
    'full', coalesce(p_since, 0) = 0);
end $$;

-- Every message of one chat, oldest first.
create or replace function public.chatly_lead_messages(p_company text, p_phone text)
returns jsonb language sql stable set search_path = public as $$
  select coalesce(jsonb_agg(data order by ts), '[]'::jsonb)
    from (select data, ts from chatly_messages
           where company_id = p_company and phone_key = right(regexp_replace(coalesce(p_phone, ''), '\D', '', 'g'), 9)
           order by ts desc limit 5000) m
$$;

-- Numbers (+ owner) of a company's leads changed after p_since: lets PHP and the bridge keep
-- a local copy for "is this number a lead?" without asking the database every message.
create or replace function public.chatly_lead_keys(p_company text, p_since bigint)
returns jsonb language sql stable set search_path = public as $$
  select jsonb_build_object(
    'rev', coalesce((select rev from chatly_leads where company_id = p_company order by rev desc limit 1), 0),
    'rows', coalesce(jsonb_agg(jsonb_build_array(phone_key, owner_id, owner_username, deleted) order by rev), '[]'::jsonb))
    from chatly_leads
   where company_id = p_company
     and (case when coalesce(p_since, 0) > 0 then rev > p_since else not deleted end)
$$;

-- All leads of a company, a page at a time (stats and admin screens). Optionally with
-- every message.
create or replace function public.chatly_leads_dump(p_company text, p_offset integer, p_limit integer, p_with_messages boolean)
returns jsonb language sql stable set search_path = public as $$
  select coalesce(jsonb_agg(
           p.data || jsonb_build_object('messageCount', p.message_count)
                  || case when p_with_messages then jsonb_build_object('messages', coalesce((
                       select jsonb_agg(m.data order by m.ts) from chatly_messages m
                        where m.company_id = p_company and m.phone_key = p.phone_key), '[]'::jsonb))
                     else '{}'::jsonb end
           order by p.id), '[]'::jsonb)
    from (select * from chatly_leads where company_id = p_company and not deleted
           order by id offset greatest(0, coalesce(p_offset, 0)) limit greatest(1, least(coalesce(p_limit, 5000), 10000))) p
$$;

-- Delete leads (by id or number) and their messages. Returns how many leads were deleted.
create or replace function public.chatly_delete_leads(p_company text, p_ids text[], p_phones text[])
returns integer language plpgsql set search_path = public as $$
declare v_keys text[]; n integer;
begin
  with gone as (
    update chatly_leads
       set deleted = true, data = jsonb_build_object('id', id, 'phone', phone)
     where company_id = p_company and not deleted
       and (id = any(coalesce(p_ids, '{}'))
            or phone_key = any(select right(regexp_replace(x, '\D', '', 'g'), 9) from unnest(coalesce(p_phones, '{}')) x where length(regexp_replace(x, '\D', '', 'g')) >= 9))
    returning phone_key
  )
  select array_agg(phone_key) into v_keys from gone;
  n := coalesce(array_length(v_keys, 1), 0);
  if n > 0 then
    delete from chatly_messages where company_id = p_company and phone_key = any(v_keys);
  end if;
  return n;
end $$;

-- Recount message_count for a company (after the one-time copy).
create or replace function public.chatly_recount(p_company text)
returns void language sql set search_path = public as $$
  update chatly_leads l
     set message_count = c.n
    from (select phone_key, count(*)::int n from chatly_messages where company_id = p_company group by phone_key) c
   where l.company_id = p_company and l.phone_key = c.phone_key and l.message_count <> c.n;
$$;

-- Only the server's key may call these.
do $$
declare f text;
begin
  foreach f in array array[
    'chatly_ts(text)', 'chatly_num(text)', 'chatly_status_wins(text,text)',
    'chatly_apply_status(text,text,text,text)', 'chatly_set_statuses(jsonb)',
    'chatly_add_messages(text,jsonb)', 'chatly_upsert_leads(text,jsonb,text)',
    'chatly_patch_lead(text,text,text,jsonb)', 'chatly_find_lead(text,text,text)', 'chatly_find_leads(text,text[])',
    'chatly_leads_page(text,bigint,integer,integer,boolean,text,text)', 'chatly_lead_messages(text,text)',
    'chatly_lead_keys(text,bigint)', 'chatly_leads_dump(text,integer,integer,boolean)',
    'chatly_delete_leads(text,text[],text[])', 'chatly_recount(text)'] loop
    execute format('revoke all on function public.%s from public, anon, authenticated', f);
    execute format('grant execute on function public.%s to service_role', f);
  end loop;
end $$;
grant select, insert, update, delete on public.chatly_leads, public.chatly_messages to service_role;
grant usage on sequence public.chatly_rev_seq to service_role;
