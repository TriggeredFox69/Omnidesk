-- Chatly Auto Data: the shared pool of Pakistani mobile numbers for campaigns.
--
-- Light format: the ~12.6 million numbers are shuffled once and stored 1,000 per row as an
-- int[] of (phone - 923000000000), e.g. 923001234567 -> 1234567. 4 bytes per number, about
-- 50 MB in total, ~12,600 rows.
--
-- Every company has its own position (cursor) in the shuffled list, so each company gets
-- numbers it has never been given, and can go through the whole list.
-- Only the server (secret / service_role key) can use any of this; RLS blocks everyone else.
--
-- Safe to run more than once.

create table if not exists public.chatly_numbers (
  chunk integer primary key,          -- row k holds pool positions k*1000 .. k*1000+999
  nums  integer[] not null            -- phone = 923000000000 + value
);

create table if not exists public.chatly_pool_meta (
  id          smallint primary key default 1 check (id = 1),
  total       bigint  not null,       -- how many numbers are in the pool
  chunk_size  integer not null default 1000,
  imported_at timestamptz not null default now(),
  source      text
);

create table if not exists public.chatly_pool_cursors (
  company_id    text primary key,     -- Chatly company id, e.g. COMP-004
  next_pos      bigint not null default 0,
  claimed_total bigint not null default 0,
  updated_at    timestamptz not null default now()
);

alter table public.chatly_numbers      enable row level security;
alter table public.chatly_pool_meta    enable row level security;
alter table public.chatly_pool_cursors enable row level security;
revoke all on public.chatly_numbers, public.chatly_pool_meta, public.chatly_pool_cursors from anon, authenticated;

-- Hands the next p_count numbers to a company and moves its cursor past them.
-- Returns {"from": .., "to": .., "total": .., "phones": [923…, …]}.
create or replace function public.chatly_claim_numbers(p_company text, p_count integer)
returns jsonb
language plpgsql
set search_path = public
as $$
declare
  v_total bigint;
  v_size  integer;
  v_from  bigint;
  v_to    bigint;
  v_phones bigint[];
begin
  if p_company is null or p_company !~ '^[A-Za-z0-9_-]{1,64}$' then
    raise exception 'invalid company id';
  end if;
  if p_count is null or p_count < 1 or p_count > 50000 then
    raise exception 'count must be between 1 and 50000';
  end if;
  select total, chunk_size into v_total, v_size from chatly_pool_meta where id = 1;
  if v_total is null then
    raise exception 'the number pool is empty';
  end if;

  insert into chatly_pool_cursors (company_id) values (p_company) on conflict (company_id) do nothing;
  select next_pos into v_from from chatly_pool_cursors where company_id = p_company for update;
  v_to := least(v_from + p_count, v_total);
  update chatly_pool_cursors
     set next_pos = v_to, claimed_total = claimed_total + (v_to - v_from), updated_at = now()
   where company_id = p_company;

  if v_to > v_from then
    select coalesce(array_agg(923000000000::bigint + n.v order by n.pos), '{}')
      into v_phones
      from chatly_numbers c
      cross join lateral (
        select u.v, c.chunk::bigint * v_size + u.ord - 1 as pos
          from unnest(c.nums) with ordinality as u(v, ord)
      ) n
     where c.chunk between (v_from / v_size) and ((v_to - 1) / v_size)
       and n.pos >= v_from and n.pos < v_to;
  end if;

  return jsonb_build_object('from', v_from, 'to', v_to, 'total', v_total,
                            'phones', to_jsonb(coalesce(v_phones, '{}'::bigint[])));
end;
$$;

-- Gives a claim back (only when nothing was claimed after it), e.g. a campaign that did not start.
create or replace function public.chatly_release_numbers(p_company text, p_from bigint, p_to bigint)
returns boolean
language sql
set search_path = public
as $$
  update chatly_pool_cursors
     set next_pos = p_from, claimed_total = greatest(0, claimed_total - (p_to - p_from)), updated_at = now()
   where company_id = p_company and next_pos = p_to and p_to > p_from and p_from >= 0
  returning true;
$$;

-- total / used / remaining for one company.
create or replace function public.chatly_pool_status(p_company text)
returns jsonb
language sql
stable
set search_path = public
as $$
  select jsonb_build_object(
           'total', m.total,
           'used', coalesce(c.next_pos, 0),
           'remaining', greatest(0, m.total - coalesce(c.next_pos, 0)),
           'imported_at', m.imported_at)
    from chatly_pool_meta m
    left join chatly_pool_cursors c on c.company_id = p_company
   where m.id = 1;
$$;

-- Functions are executable by everyone by default: only the server's key may call these.
revoke all on function public.chatly_claim_numbers(text, integer)          from public, anon, authenticated;
revoke all on function public.chatly_release_numbers(text, bigint, bigint) from public, anon, authenticated;
revoke all on function public.chatly_pool_status(text)                     from public, anon, authenticated;
grant execute on function public.chatly_claim_numbers(text, integer)          to service_role;
grant execute on function public.chatly_release_numbers(text, bigint, bigint) to service_role;
grant execute on function public.chatly_pool_status(text)                     to service_role;
grant select, insert, update, delete on public.chatly_numbers, public.chatly_pool_meta, public.chatly_pool_cursors to service_role;
