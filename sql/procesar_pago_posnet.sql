-- Ejecutar una sola vez en el SQL Editor de Supabase.
-- Cobro atomico: valida PIN, descuenta saldo, actualiza POSNET e inserta la venta.

create extension if not exists pgcrypto;

create or replace function public.procesar_pago_posnet(
    p_codigo_qr text,
    p_monto numeric,
    p_pin text,
    p_posnet_id bigint,
    p_tipo text
)
returns table(exito boolean, mensaje text)
language plpgsql
security definer
set search_path = public
as $$
declare
    v_alumno public.alumnos%rowtype;
    v_posnet public.usuarios_banco%rowtype;
begin
    if coalesce(trim(p_codigo_qr), '') = '' then
        return query select false, 'Código QR vacío.';
        return;
    end if;

    if p_monto is null or p_monto <= 0 then
        return query select false, 'Monto inválido.';
        return;
    end if;

    if p_tipo <> 'COBRO' then
        return query select false, 'Tipo de operación inválido.';
        return;
    end if;

    select * into v_alumno
    from public.alumnos
    where codigo_qr = p_codigo_qr
    for update;

    if not found then
        return query select false, 'Código QR inexistente.';
        return;
    end if;

    if not (v_alumno.pin::text = extensions.crypt(p_pin::text, v_alumno.pin::text)
        or v_alumno.pin::text = p_pin::text) then
        return query select false, 'PIN del alumno incorrecto.';
        return;
    end if;

    if coalesce(v_alumno.saldo, 0) < p_monto then
        return query select false, 'Fondos insuficientes.';
        return;
    end if;

    select * into v_posnet
    from public.usuarios_banco
    where id = p_posnet_id
      and rol = 'POSNET'
      and activo = true
    for update;

    if not found then
        return query select false, 'El POSNET no está habilitado.';
        return;
    end if;

    update public.alumnos
    set saldo = coalesce(v_alumno.saldo, 0) - p_monto
    where id = v_alumno.id;

    update public.usuarios_banco
    set monto_acumulado = coalesce(v_posnet.monto_acumulado, 0) + p_monto,
        cant_transacciones = coalesce(v_posnet.cant_transacciones, 0) + 1
    where id = p_posnet_id;

    insert into public.transacciones (
        alumno_id,
        monto,
        tipo,
        estado,
        fecha_hora,
        posnet_id
    ) values (
        v_alumno.id,
        p_monto,
        p_tipo,
        'OK',
        now(),
        p_posnet_id
    );

    return query select true, 'Cobro aprobado.';
end;
$$;

grant execute on function public.procesar_pago_posnet(text, numeric, text, bigint, text) to anon, authenticated;
