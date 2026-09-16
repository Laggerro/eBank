-- Ejecutar en Supabase para habilitar cuentas y transferencias de alumnos.
-- Supabase Auth administra la contraseña; alumnos conserva solo el PIN operativo.

create table if not exists public.perfiles_alumnos (
    id uuid primary key references auth.users(id) on delete cascade,
    alumno_id uuid not null unique references public.alumnos(id) on delete cascade,
    activo boolean not null default true,
    creado_el timestamptz not null default now(),
    ultimo_acceso timestamptz
);

create index if not exists perfiles_alumnos_alumno_id_idx
    on public.perfiles_alumnos(alumno_id);

create or replace function public.vincular_perfil_alumno_nuevo()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
    v_alumno_id uuid;
begin
    v_alumno_id := nullif(new.raw_user_meta_data ->> 'alumno_id', '')::uuid;

    if v_alumno_id is not null then
        insert into public.perfiles_alumnos (id, alumno_id, activo)
        values (new.id, v_alumno_id, true)
        on conflict (id) do nothing;
    end if;

    return new;
end;
$$;

drop trigger if exists trigger_vincular_perfil_alumno on auth.users;
create trigger trigger_vincular_perfil_alumno
    after insert on auth.users
    for each row execute function public.vincular_perfil_alumno_nuevo();

alter table public.perfiles_alumnos enable row level security;

drop policy if exists perfiles_alumnos_select_propio on public.perfiles_alumnos;
create policy perfiles_alumnos_select_propio
    on public.perfiles_alumnos for select
    to authenticated
    using (id = auth.uid());

drop policy if exists perfiles_alumnos_insert_propio on public.perfiles_alumnos;
create policy perfiles_alumnos_insert_propio
    on public.perfiles_alumnos for insert
    to authenticated
    with check (id = auth.uid() and activo = true);

drop policy if exists perfiles_alumnos_update_propio on public.perfiles_alumnos;
create policy perfiles_alumnos_update_propio
    on public.perfiles_alumnos for update
    to authenticated
    using (id = auth.uid())
    with check (id = auth.uid());

create or replace function public.transferir_saldo_alumno(
    p_origen_id uuid,
    p_destino_dni text,
    p_monto numeric
)
returns table(exito boolean, mensaje text)
language plpgsql
security definer
set search_path = public
as $$
declare
    v_origen public.alumnos%rowtype;
    v_destino public.alumnos%rowtype;
begin
    if auth.uid() is null
       or not exists (
           select 1 from public.perfiles_alumnos
           where id = auth.uid() and alumno_id = p_origen_id and activo = true
       ) then
        return query select false, 'Sesión de alumno no autorizada.';
        return;
    end if;

    if p_monto is null or p_monto <= 0 then
        return query select false, 'Monto inválido.';
        return;
    end if;

    select * into v_origen
    from public.alumnos
    where id = p_origen_id and registrado = true
    for update;

    if not found then
        return query select false, 'Cuenta de origen inexistente.';
        return;
    end if;

    select * into v_destino
    from public.alumnos
    where dni = trim(p_destino_dni) and registrado = true
    for update;

    if not found then
        return query select false, 'El DNI destinatario no existe.';
        return;
    end if;

    if v_origen.id = v_destino.id then
        return query select false, 'No puede transferirse saldo a sí mismo.';
        return;
    end if;

    if coalesce(v_origen.saldo, 0) < p_monto then
        return query select false, 'Saldo insuficiente.';
        return;
    end if;

    update public.alumnos
    set saldo = coalesce(v_origen.saldo, 0) - p_monto
    where id = v_origen.id;

    update public.alumnos
    set saldo = coalesce(v_destino.saldo, 0) + p_monto
    where id = v_destino.id;

    insert into public.transacciones (alumno_id, monto, tipo, estado, fecha_hora)
    values
        (v_origen.id, p_monto, 'TRANSFERENCIA_SALIDA', 'OK', now()),
        (v_destino.id, p_monto, 'TRANSFERENCIA_ENTRADA', 'OK', now());

    return query select true, 'Transferencia realizada correctamente.';
end;
$$;

revoke execute on function public.transferir_saldo_alumno(uuid, text, numeric) from anon;
grant execute on function public.transferir_saldo_alumno(uuid, text, numeric) to authenticated;

create or replace function public.dar_baja_alumno_sin_saldo(p_alumno_id uuid)
returns table(exito boolean, mensaje text)
language plpgsql
security definer
set search_path = public
as $$
declare
    v_alumno public.alumnos%rowtype;
begin
    select * into v_alumno
    from public.alumnos
    where id = p_alumno_id and registrado = true
    for update;

    if not found then
        return query select false, 'El alumno no existe o ya fue dado de baja.';
        return;
    end if;

    if abs(coalesce(v_alumno.saldo, 0)) > 0.000001 then
        return query select false, 'No se puede dar de baja: la cuenta tiene saldo.';
        return;
    end if;

    update public.alumnos
    set registrado = false
    where id = p_alumno_id;

    update public.perfiles_alumnos
    set activo = false
    where alumno_id = p_alumno_id;

    return query select true, 'Alumno dado de baja correctamente.';
end;
$$;

grant execute on function public.dar_baja_alumno_sin_saldo(uuid) to anon, authenticated;

-- Refrescar el schema cache usado por PostgREST después de crear la RPC.
notify pgrst, 'reload schema';
